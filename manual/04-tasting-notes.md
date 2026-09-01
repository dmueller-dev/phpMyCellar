# 04. Tasting Notes, Ratings & Blind Tastings

This guide explains how to draft tasting notes, utilize rating scales, conduct blind tastings, and publish impressions.

---

## 1. Creating a Tasting Note

Tasting notes can be recorded directly from the wine/vintage overview or via the backend:

- **Navigate to:** `Backend > Tasting Notes > Add Note`.
- **Key Attributes:**
  - **Vintage:** Select the specific wine and vintage tasted.
  - **Tasting Date:** Date of tasting.
  - **Taster / Author:** Automatically set to your user account (or selected co-taster).
  - **Decanting Duration:** E.g. *2 hours in Zalto Bordeaux decanter*.
  - **Serving Temperature & Glassware:** Notes on glass selection and temperature.
  - **Sensory Impressions:** Structured or freeform narrative (Appearance, Nose, Palate, Finish).
  - **Bottle Photo:** Upload a high-resolution bottle or label photo to `uploads/img/`.

---

## 2. Supported Rating Scales

phpMyCellar supports configurable rating methodologies with flexible dual-scale evaluation:

| Rating Scale | Description | Scoring Range |
| :--- | :--- | :--- |
| **20-Point Scale** | Traditional European / René Gabriel / Jancis Robinson scale assessing color, aroma, taste, harmony, and aging potential. | 0 – 20 points (e.g. `18 / 20` or `18.5`) |
| **100-Point Scale** | Modern international standard (Parker / Wine Spectator standard). | 50 – 100 points (e.g. `94 / 100`) |
| **WSET SAT (Optional)** | Wine & Spirit Education Trust Systematic Approach to Tasting (Poor, Acceptable, Good, Very Good, Outstanding). | Qualitative Assessment (can be enabled alongside 20-point or 100-point scale) |

*Tip: You can select your collection's active primary scale (20-point vs 100-point) and enable/disable the optional WSET SAT assessment in `Backend > Site Settings`.*

---

## 3. Blind Tasting Mode

phpMyCellar includes dedicated blind tasting support to eliminate confirmation bias:

1. **Step 1:** Select *Blind Tasting Mode* when creating a note.
2. **Step 2:** Record your raw sensory observations, preliminary score, and guessed grape/region.
3. **Step 3:** Reveal and link the note to the actual bottle in your cellar.

---

## 4. Drinking Window Predictions

Every tasting note allows you to update or refine the vintage's forecasted drinking window based on current maturity:

- **Too Young / Developing:** Needs further cellaring.
- **Peak / Optimal:** Drinking at its zenith.
- **Past Peak / Declining:** Drink immediately.

---

## 5. SEO & Social Sharing

Public tasting notes automatically generate:
- **Canonical URLs** for search engines.
- **OpenGraph & Twitter Card metadata** with tasting photos.
- **JSON-LD Schema (`Review` / `Rating`)** structured data for Google rich snippets.
