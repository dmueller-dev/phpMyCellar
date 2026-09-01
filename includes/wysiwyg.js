/**
 * dmueller-com WYSIWYG Editor Integration
 * Centralized progressive enhancement using Quill v2.0
 */
document.addEventListener("DOMContentLoaded", function () {
  // Select all textareas in the backend except plain text fields (like addresses)
  const textareas = document.querySelectorAll(
    "textarea:not(#address):not([name='address'])"
  );

  // If there are no eligible textareas on the page, exit immediately
  if (textareas.length === 0) {
    return;
  }

  // 1. Dynamically load Quill Stylesheet
  const link = document.createElement("link");
  link.rel = "stylesheet";
  link.href = "https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.snow.css";
  document.head.appendChild(link);

  // 2. Dynamically load Quill JS Library
  const script = document.createElement("script");
  script.src = "https://cdn.jsdelivr.net/npm/quill@2.0.2/dist/quill.js";
  script.onload = function () {
    // --- 1. Register Custom Blots & Formats ---
    const ImageBlot = Quill.import('formats/image');
    const ATTRIBUTES = ['alt', 'height', 'width', 'style', 'class'];

    // Extend default Image blot to allow classes & styles
    class CustomImageBlot extends ImageBlot {
      static formats(domNode) {
        return ATTRIBUTES.reduce((formats, attribute) => {
          if (domNode.hasAttribute(attribute)) {
            formats[attribute] = domNode.getAttribute(attribute);
          }
          return formats;
        }, {});
      }

      format(name, value) {
        if (ATTRIBUTES.indexOf(name) > -1) {
          if (value) {
            this.domNode.setAttribute(name, value);
          } else {
            this.domNode.removeAttribute(name);
          }
        } else {
          super.format(name, value);
        }
      }
    }
    Quill.register('formats/image', CustomImageBlot, true);

    // Create custom BlockEmbed blot for captioned images
    const BlockEmbed = Quill.import('blots/block/embed');
    class CaptionedImageBlot extends BlockEmbed {
      static create(value) {
        const node = super.create();
        node.setAttribute('class', 'captioned-image-container');
        node.setAttribute('contenteditable', 'false');
        
        const img = document.createElement('img');
        img.setAttribute('src', value.src);
        img.setAttribute('class', 'block center');
        if (value.alt) img.setAttribute('alt', value.alt);
        node.appendChild(img);
        
        if (value.caption) {
          const p = document.createElement('p');
          p.setAttribute('style', 'color:SlateGray;text-align:center;margin-top:0;');
          
          const small = document.createElement('small');
          small.textContent = value.caption;
          
          p.appendChild(small);
          node.appendChild(p);
        }
        return node;
      }

      static value(domNode) {
        const img = domNode.querySelector('img');
        const p = domNode.querySelector('p');
        const small = p ? p.querySelector('small') : null;
        return {
          src: img ? img.getAttribute('src') : '',
          alt: img ? img.getAttribute('alt') : '',
          caption: small ? small.textContent : (p ? p.textContent : '')
        };
      }
    }
    CaptionedImageBlot.blotName = 'captioned-image';
    CaptionedImageBlot.tagName = 'div';
    CaptionedImageBlot.className = 'captioned-image-container';
    Quill.register(CaptionedImageBlot);

    // Create custom Embed blot for soft line breaks (<br>)
    const Parchment = Quill.import('parchment') || window.Parchment;
    const EmbedBase = (Parchment && Parchment.EmbedBlot) ? Parchment.EmbedBlot : (Quill.import('blots/block/embed') || Object);
    class SoftBreakBlot extends EmbedBase {
      static create() {
        const node = super.create();
        return node;
      }
      static value() {
        return true;
      }
    }
    SoftBreakBlot.blotName = 'softbreak';
    SoftBreakBlot.tagName = 'br';
    SoftBreakBlot.className = 'ql-soft-break';
    if (Parchment && Parchment.Scope) {
      SoftBreakBlot.scope = Parchment.Scope.INLINE_BLOT;
    }
    Quill.register(SoftBreakBlot, true);
    Quill.register('formats/softbreak', SoftBreakBlot, true);

    // --- 2. Inject Dynamic CSS Styles for Overlay & Editor Previews ---
    const styleEl = document.createElement("style");
    styleEl.textContent = `
      /* Custom icon alignment & styling in toolbar */
      .ql-toolbar .ql-customImage {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 24px;
        padding: 3px 5px;
        cursor: pointer;
      }
      .ql-toolbar .ql-customImage svg {
        stroke: #444;
        transition: stroke 0.2s;
      }
      .ql-toolbar .ql-customImage:hover svg {
        stroke: firebrick !important;
      }

      /* Editor content preview styles to match frontend */
      .ql-editor .captioned-image-container {
        text-align: center;
        margin-top: 15px;
        margin-bottom: 15px;
        display: block;
      }
      .ql-editor .inline.left {
        float: left;
        margin: 5px 15px 5px 0px !important;
        max-width: 50% !important;
        height: auto !important;
      }
      .ql-editor .block.center {
        display: block !important;
        margin-left: auto !important;
        margin-right: auto !important;
        margin-top: 15px !important;
        margin-bottom: 15px !important;
        max-width: 70% !important;
        height: auto !important;
      }

      /* Overlay Backdrop with Glassmorphism */
      .image-overlay-backdrop {
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;
        background-color: rgba(30, 20, 20, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 100000;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.25s ease;
      }
      .image-overlay-backdrop.active {
        opacity: 1;
        pointer-events: auto;
      }

      /* Modal Window styled in dmueller-com theme */
      .image-overlay-modal {
        background-color: #FFF3F3;
        border-radius: 12px;
        box-shadow: 0 15px 35px rgba(139, 0, 0, 0.15), 0 5px 15px rgba(0, 0, 0, 0.1);
        padding: 30px;
        width: 90%;
        max-width: 480px;
        font-family: Georgia, serif;
        transform: scale(0.92);
        transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
        border: 1px solid rgba(205, 92, 92, 0.2);
        box-sizing: border-box;
      }
      .image-overlay-backdrop.active .image-overlay-modal {
        transform: scale(1);
      }
      .image-overlay-title {
        margin-top: 0;
        margin-bottom: 24px;
        color: firebrick;
        font-size: 22px;
        border-bottom: 2px solid rgba(205, 92, 92, 0.2);
        padding-bottom: 12px;
        text-align: center;
        font-weight: normal;
      }
      .image-overlay-form-group {
        margin-bottom: 20px;
        text-align: left;
      }
      .image-overlay-label {
        display: block;
        font-size: 14px;
        color: #555;
        margin-bottom: 8px;
        font-weight: bold;
      }

      /* Input styling */
      .image-file-input-wrapper {
        display: flex;
        align-items: center;
        border: 1px solid rgba(205, 92, 92, 0.3);
        border-radius: 6px;
        background-color: white;
        overflow: hidden;
        transition: border-color 0.2s, box-shadow 0.2s;
      }
      .image-file-input-wrapper:focus-within {
        border-color: firebrick;
        box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.15);
      }
      .image-file-prefix {
        background-color: rgba(205, 92, 92, 0.1);
        color: firebrick;
        padding: 10px 12px;
        font-size: 15px;
        border-right: 1px solid rgba(205, 92, 92, 0.2);
        user-select: none;
      }
      .image-overlay-input {
        flex: 1;
        border: none;
        padding: 10px 12px;
        font-size: 15px;
        font-family: Georgia, serif;
        color: #333;
        outline: none;
        background: transparent;
      }
      input.image-overlay-input:not(div input),
      textarea.image-overlay-input {
        width: 100%;
        border: 1px solid rgba(205, 92, 92, 0.3);
        border-radius: 6px;
        background-color: white;
        padding: 10px 12px;
        font-size: 15px;
        font-family: Georgia, serif;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        box-sizing: border-box;
      }
      input.image-overlay-input:not(div input):focus,
      textarea.image-overlay-input:focus {
        border-color: firebrick;
        box-shadow: 0 0 0 3px rgba(178, 34, 34, 0.15);
      }
      textarea.image-overlay-input {
        resize: vertical;
        min-height: 80px;
        line-height: 1.4;
      }

      /* Layout option selectors (styled custom radios) */
      .image-style-options {
        display: flex;
        gap: 15px;
      }
      .image-style-option {
        display: flex;
        align-items: center;
        cursor: pointer;
        position: relative;
        user-select: none;
        padding: 10px 12px;
        border: 1px solid rgba(205, 92, 92, 0.2);
        border-radius: 6px;
        background-color: white;
        transition: background-color 0.2s, border-color 0.2s;
        flex: 1;
        justify-content: center;
      }
      .image-style-option:hover {
        background-color: rgba(205, 92, 92, 0.05);
        border-color: rgba(205, 92, 92, 0.4);
      }
      .image-style-option input {
        position: absolute;
        opacity: 0;
        cursor: pointer;
        height: 0;
        width: 0;
      }
      .custom-radio {
        height: 16px;
        width: 16px;
        background-color: #eee;
        border-radius: 50%;
        margin-right: 8px;
        display: inline-block;
        position: relative;
        border: 1px solid #ccc;
        transition: background-color 0.2s, border-color 0.2s;
      }
      .image-style-option input:checked ~ .custom-radio {
        background-color: indianred;
        border-color: firebrick;
      }
      .custom-radio:after {
        content: "";
        position: absolute;
        display: none;
        top: 4px;
        left: 4px;
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: white;
      }
      .image-style-option input:checked ~ .custom-radio:after {
        display: block;
      }
      .image-style-option input:checked ~ .option-label {
        color: firebrick;
        font-weight: bold;
      }
      .option-label {
        font-size: 14px;
        color: #555;
        transition: color 0.2s;
      }

      /* Actions button block */
      .image-overlay-actions {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 30px;
        border-top: 1px solid rgba(205, 92, 92, 0.15);
        padding-top: 20px;
      }
      .image-overlay-button {
        padding: 10px 20px;
        font-family: Georgia, serif;
        font-size: 15px;
        border-radius: 6px;
        cursor: pointer;
        border: none;
        transition: background-color 0.2s, transform 0.1s;
      }
      .image-overlay-button:active {
        transform: scale(0.98);
      }
      .button-primary {
        background-color: indianred;
        color: white;
      }
      .button-primary:hover {
        background-color: firebrick;
      }
      .button-secondary {
        background-color: transparent;
        color: #777;
        border: 1px solid rgba(0, 0, 0, 0.15);
      }
      .button-secondary:hover {
        background-color: rgba(0, 0, 0, 0.05);
        color: #333;
      }
    `;
    document.head.appendChild(styleEl);

    // --- 3. Overlay Modal Manager (Global Instance) ---
    let activeQuillInstance = null;

    function closeImageOverlay() {
      const overlay = document.getElementById("image-insert-overlay");
      if (overlay) {
        overlay.classList.remove("active");
      }
      activeQuillInstance = null;
    }

    // Helper functions to escape HTML special characters
    function escapeHtmlAttr(str) {
      if (!str) return '';
      return str
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function escapeHtmlText(str) {
      if (!str) return '';
      return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    }

    function getOrCreateImageOverlay() {
      let overlay = document.getElementById("image-insert-overlay");
      if (!overlay) {
        overlay = document.createElement("div");
        overlay.id = "image-insert-overlay";
        overlay.className = "image-overlay-backdrop";
        overlay.innerHTML = `
          <div class="image-overlay-modal">
            <h3 class="image-overlay-title">Insert Site Image</h3>
            
            <div class="image-overlay-form-group">
              <label class="image-overlay-label" for="image-file-name">Image File Name</label>
              <div class="image-file-input-wrapper">
                <span class="image-file-prefix">/uploads/img/</span>
                <input type="text" id="image-file-name" class="image-overlay-input" placeholder="example.jpg" required />
              </div>
            </div>

            <div class="image-overlay-form-group">
              <label class="image-overlay-label" for="image-alt-text">Image Alt Text (Optional)</label>
              <textarea id="image-alt-text" class="image-overlay-input" placeholder="Describe the image for screen readers..."></textarea>
            </div>
            
            <div class="image-overlay-form-group">
              <label class="image-overlay-label">Image Alignment Style</label>
              <div class="image-style-options">
                <label class="image-style-option">
                  <input type="radio" name="image-style" value="block center" checked />
                  <span class="custom-radio"></span>
                  <span class="option-label">Block Center</span>
                </label>
                <label class="image-style-option">
                  <input type="radio" name="image-style" value="inline left" />
                  <span class="custom-radio"></span>
                  <span class="option-label">Inline Left</span>
                </label>
              </div>
            </div>
            
            <div class="image-overlay-form-group" id="image-caption-group">
              <label class="image-overlay-label" for="image-caption">Image Caption (Optional)</label>
              <input type="text" id="image-caption" class="image-overlay-input" placeholder="Type a beautiful caption..." />
            </div>
            
            <div class="image-overlay-actions">
              <button type="button" id="image-overlay-cancel" class="image-overlay-button button-secondary">Cancel</button>
              <button type="button" id="image-overlay-submit" class="image-overlay-button button-primary">Insert Image</button>
            </div>
          </div>
        `;
        document.body.appendChild(overlay);

        // Overlay event listeners
        const styleRadios = overlay.querySelectorAll('input[name="image-style"]');
        const captionGroup = overlay.querySelector("#image-caption-group");
        styleRadios.forEach(radio => {
          radio.addEventListener("change", function() {
            if (this.value === "block center") {
              captionGroup.style.display = "block";
            } else {
              captionGroup.style.display = "none";
            }
          });
        });

        overlay.querySelector("#image-overlay-cancel").addEventListener("click", closeImageOverlay);
        
        overlay.addEventListener("click", function(e) {
          if (e.target === overlay) {
            closeImageOverlay();
          }
        });

        document.addEventListener("keydown", function(e) {
          if (e.key === "Escape") {
            closeImageOverlay();
          }
        });

        overlay.querySelector("#image-overlay-submit").addEventListener("click", function() {
          if (!activeQuillInstance) return;
          
          const fileInput = overlay.querySelector("#image-file-name");
          const altInput = overlay.querySelector("#image-alt-text");
          const captionInput = overlay.querySelector("#image-caption");
          const selectedStyle = overlay.querySelector('input[name="image-style"]:checked').value;
          
          const fileName = fileInput.value.trim();
          if (!fileName) {
            fileInput.reportValidity();
            return;
          }

          // Format image path correctly in the background
          let filePath = fileName;
          if (filePath.startsWith("uploads/img/")) {
            filePath = "/" + filePath;
          } else if (filePath.startsWith("img/")) {
            filePath = "/uploads/" + filePath;
          } else if (filePath.startsWith("/img/")) {
            filePath = "/uploads" + filePath;
          } else if (!filePath.startsWith("/uploads/img/")) {
            filePath = "/uploads/img/" + filePath.replace(/^\/+/, "");
          }

          const altText = altInput.value.trim();
          const captionText = captionInput.value.trim();
          
          const escapedAlt = escapeHtmlAttr(altText);
          const escapedCaptionAttr = escapeHtmlAttr(captionText);
          const escapedCaptionText = escapeHtmlText(captionText);
          
          const range = activeQuillInstance.getSelection(true);
          
          let html = "";
          if (selectedStyle === "block center" && captionText) {
            html = `<div class="captioned-image-container"><img src="${filePath}" class="block center" alt="${escapedAlt || escapedCaptionAttr}" /><p style="color:SlateGray;text-align:center;margin-top:0;"><small>${escapedCaptionText}</small></p></div>`;
          } else {
            html = `<img src="${filePath}" class="${selectedStyle}" alt="${escapedAlt}" />`;
          }

          activeQuillInstance.clipboard.dangerouslyPasteHTML(range.index, html, 'user');
          activeQuillInstance.setSelection(range.index + 1, 'user');
          closeImageOverlay();
        });
      }
      return overlay;
    }

    // Once Quill has loaded, initialize it on each textarea
    textareas.forEach(function (textarea) {
      // Create Quill wrapper container
      const container = document.createElement("div");
      container.className = "wysiwyg-editor-container";

      // Style wrapper container to blend in with dmueller-com design system
      container.style.minHeight = "250px";
      container.style.backgroundColor = "#ffffff";
      container.style.color = "#333333";
      container.style.fontFamily = "Georgia, serif";
      container.style.fontSize = "16px";
      container.style.lineHeight = "1.6";
      container.style.marginBottom = "15px";
      container.style.borderRadius = "4px";

      // Insert container right before the original textarea
      textarea.parentNode.insertBefore(container, textarea);

      // Hide the original textarea
      textarea.style.display = "none";

      // Initialize Quill with custom Image Button
      const quill = new Quill(container, {
        theme: "snow",
        placeholder: textarea.placeholder || "Write something beautiful...",
        modules: {
          toolbar: {
            container: [
              [{ 'header': [1, 2, 3, 4, false] }],
              ["bold", "italic", "underline"],
              [{ 'list': 'ordered' }, { 'list': 'bullet' }],
              ["link", "customImage"],
              ["clean"]
            ],
            handlers: {
              customImage: function () {
                activeQuillInstance = quill;
                const overlay = getOrCreateImageOverlay();
                overlay.querySelector("#image-file-name").value = "";
                overlay.querySelector("#image-alt-text").value = "";
                overlay.querySelector("#image-caption").value = "";
                overlay.querySelector('input[name="image-style"][value="block center"]').checked = true;
                overlay.querySelector("#image-caption-group").style.display = "block";
                overlay.classList.add("active");
                setTimeout(() => {
                  overlay.querySelector("#image-file-name").focus();
                }, 100);
              }
            }
          }
        }
      });

      // Intercept Shift+Enter in DOM capture phase to reliably insert <br> soft break before Quill paragraph split
      quill.root.addEventListener("keydown", function (e) {
        if (e.key === "Enter" && e.shiftKey) {
          e.preventDefault();
          e.stopPropagation();
          e.stopImmediatePropagation();

          const range = quill.getSelection(true);
          if (range) {
            if (range.length > 0) {
              quill.deleteText(range.index, range.length, 'user');
            }
            quill.insertEmbed(range.index, 'softbreak', true, 'user');
            quill.setSelection(range.index + 1, 0, 'user');
          }
        }
      }, true);

      // Inject beautiful SVG icon for our customImage button in the toolbar
      const toolbarEl = container.previousSibling;
      if (toolbarEl && toolbarEl.classList.contains("ql-toolbar")) {
        const customButton = toolbarEl.querySelector(".ql-customImage");
        if (customButton) {
          customButton.innerHTML = `<svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><polyline points="21 15 16 10 5 21"></polyline></svg>`;
          customButton.title = "Insert Site Image";
        }
      }

      // Register custom clipboard matcher for soft line breaks
      const Delta = Quill.import('delta');
      if (quill.clipboard && typeof quill.clipboard.addMatcher === 'function') {
        quill.clipboard.addMatcher('BR', function (node, delta) {
          // If this is Quill's internal filler BR inside an empty block (<p><br></p>), leave it
          if (node.parentNode && node.parentNode.childNodes.length === 1 && node.parentNode.firstChild === node) {
            return delta;
          }
          return new Delta().insert({ softbreak: true });
        });
      }

      // Populate Quill safely via clipboard parser to support existing content and container tags
      if (textarea.value && textarea.value.trim().length > 0) {
        if (quill.clipboard && typeof quill.clipboard.dangerouslyPasteHTML === 'function') {
          quill.clipboard.dangerouslyPasteHTML(0, textarea.value, 'silent');
        } else {
          quill.root.innerHTML = textarea.value;
        }
      }

      // Sync Quill content back to the hidden textarea on text changes
      quill.on("text-change", function () {
        textarea.value = quill.root.innerHTML;
      });

      // Handle validation: If textarea is required, prevent form focus crash
      if (textarea.hasAttribute("required")) {
        textarea.removeAttribute("required");
        const form = textarea.closest("form");
        if (form) {
          form.addEventListener("submit", function (event) {
            // Check if Quill content is empty (excluding trailing spaces/newlines)
            const textContent = quill.getText().trim();
            if (textContent.length === 0) {
              alert("The description / note content is required.");
              event.preventDefault();
              quill.focus();
            }
          });
        }
      }
    });
  };
  document.head.appendChild(script);
});
