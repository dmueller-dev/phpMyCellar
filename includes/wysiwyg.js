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

      // Initialize Quill (Option A: excludes "image" button)
      const quill = new Quill(container, {
        theme: "snow",
        placeholder: textarea.placeholder || "Write something beautiful...",
        modules: {
          toolbar: [
            [{ 'header': [1, 2, 3, 4, false] }],
            ["bold", "italic", "underline"],
            [{ 'list': 'ordered' }, { 'list': 'bullet' }],
            ["link"],
            ["clean"]
          ]
        }
      });

      // Populate Quill with original textarea content (HTML/paragraphs)
      quill.root.innerHTML = textarea.value;

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
