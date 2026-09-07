# 04. Tasting Notes, Ratings & Blind Tastings

This guide explains how to draft tasting notes, utilize rating scales, conduct blind tastings, and publish impressions.

---

## 1. Creating a Tasting Note

Tasting notes can be recorded directly from the wine/vintage overview or via the contributor menu:

- **Navigate to:** Top navigation `Contribute > Write tasting note` (`/backend/addTastingNote.php`) or `Admin > Dashboard > Tasting notes > New tasting note`.
- **Key Attributes:**
  - **Wine or Bottle:** Select the wine vintage (Standard mode) or specific cellar bottle (Blind mode).
  - **Tasting Date:** Date when the wine was sampled.
  - **Rating / Score:** Score on your cellar's active scale (20-point integer scale or 100-point scale).
  - **WSET SAT Evaluation (Optional):** Balance, Length, Intensity, and Complexity scores when WSET mode is enabled.
  - **Flawed Status:** Flag corked, oxidized, or otherwise faulty bottles (`flawed_yn = 'yes'`).
  - **Blind Status:** Record whether the wine was tasted blind.
  - **Drinking Window:** Projected optimal years (`Drink from (yyyy)` through `Drink through (yyyy)`).
  - **Sensory Impressions:** Freeform text detailing appearance, nose, palate, structure, and finish.
  - **Bottle Photo & Alignment:** Upload a bottle or label photo to `uploads/img/` with optional alignment classes.
  - **Favourite Flag:** Mark standout bottles as personal favourites.
  - **Status:** Save as `draft` or `publish` directly (based on publication privileges).

---

## 2. Supported Rating Scales

phpMyCellar supports configurable rating methodologies with flexible dual-scale evaluation:

| Rating Scale | Description | Scoring Range |
| :--- | :--- | :--- |
| **20-Point Scale** | Traditional European / René Gabriel / Jancis Robinson scale assessing color, aroma, taste, harmony, and aging potential. | 0 – 20 points (integers, e.g. `18 / 20`) |
| **100-Point Scale** | Modern international standard (Parker / Wine Spectator standard). | 50 – 100 points (integers, e.g. `94 / 100`) |
| **WSET SAT (Optional)** | Wine & Spirit Education Trust Systematic Approach to Tasting (Poor, Acceptable, Good, Very Good, Outstanding). | Qualitative Assessment (configurable modes: Public, Logged In, Backend Only, or Disabled) |

*Tip: You can select your collection's active primary scale (20-point vs 100-point) and configure WSET SAT visibility mode (`Public`, `Logged In`, `Backend Only`, or `Disabled`) as well as display detail (Standard vs. Detailed BLIC breakdown) in `Admin > Site Settings`.*

---

## 3. Blind Tasting Mode

phpMyCellar includes dedicated blind tasting support to eliminate confirmation bias, seamlessly integrated into the tasting note editor:

1. **Step 1 (Switch Mode):** Click **Blind tasting (by bottle)** at the top of `backend/addTastingNote.php` (or access `/backend/addTastingNote.php?mode=blind`).
2. **Step 2 (Select Cellar Bottle):** Select a bottle from your cellar inventory. The dropdown shows only the bottle ID number (e.g. `#42`), keeping wine metadata hidden to maintain blind conditions.
3. **Step 3 (Evaluate):** Record your sensory impressions and rating. An optional `<details><summary>Reveal the wine?</summary>` toggle allows unmasking the wine name at any time.
4. **Step 4 (Save & Mark Consumed):** Upon saving the note, the editor prompts: *"Would you like to mark bottle #X as consumed on [date]?"* Clicking **Yes** automatically updates the bottle's inventory status to `consumed` and links it to the newly created tasting note.

---

## 4. Drinking Window Predictions

Every tasting note allows you to establish or refine forecasted drinking windows for cellar inventory planning:

- **Drink From (`drinkwindow_min`):** Starting calendar year (`yyyy`) when the wine enters its approachable drinking plateau.
- **Drink Through (`drinkwindow_max`):** Ending calendar year (`yyyy`) before the wine is anticipated to decline.
- In the *Carte des vins* and cellar management screens, bottles whose `Drink From` year has been reached are highlighted as ready to drink today.

---

## 5. SEO & Social Sharing

Public tasting notes automatically generate:
- **Canonical URLs** for search engines.
- **OpenGraph & Twitter Card metadata** with tasting photos.
- **JSON-LD Schema (`Review` / `Rating`)** structured data for Google rich snippets.
