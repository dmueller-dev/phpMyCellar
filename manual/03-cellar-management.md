# 03. Wine Cellar & Order Management

This guide covers how to manage producers, wines, vintages, bottles, storage bins, and merchant purchase orders.

---

## 1. The Wine Hierarchy

To accurately represent wine collections, phpMyCellar structures records into a clear 4-level hierarchy:

```text
1. Producer (e.g. Domaine Coche-Dury)
   │
   └── 2. Wine Master (e.g. Meursault 1er Cru Les Perrières)
       │
       └── 3. Vintage (e.g. 2018)
           │
           └── 4. Bottles (e.g. 3x 750ml, Bin A-04, Cost: €450, Purchased: 2021-03)
```

---

## 2. Managing Producers

Producers represent wineries, domaines, estates, châteaux, or bodegas.

- **Navigate to:** `Admin > Dashboard` (`/backend/index.php`) > **Producers > Add new producer** (`addProducer.php`) or edit existing producers (`editProducer.php`). Alternatively, click the *Edit producer* shortcut directly when viewing wines grouped by producer in `browseWines.php` or `browseBottles.php`.
- **Key Attributes:**
  - **Producer Name:** Full estate name (e.g. *Domaine Leflaive*).
  - **Country & Region:** Geographic origin (e.g. *France > Burgundy > Côte de Beaune*).
  - **Website & Notes:** Reference links, winemaking philosophies, or visit memories.

---

## 3. Managing Wine Masters & Naming Conventions

A **Wine Master** establishes the overarching identity and classification of a wine before assigning individual harvest vintages.

- **Navigate to:** `Admin > Dashboard` (`/backend/index.php`) > **Wines > Add new master** (`addWineMaster.php`) or edit via `editWineMaster.php`.
- **Key Attributes:**
  - **Producer:** Winery or estate crafting the cuvée.
  - **Wine Name:** Specific cuvée designation (e.g. *Les Pucelles*).
  - **Naming Convention:** Determines how the wine title is formatted across menus and headings (e.g. `[Vintage] [Producer] [Vineyard] [Name]` or `[Vintage] [Producer] [Name]`).
  - **Colour:** `red`, `white`, `rosé`, or `orange`.
  - **Style:** `still (dry)`, `still (off-dry)`, `still (sweet)`, `sparkling`, or `fortified`.
  - **Grape Variety:** Primary grape variety.
  - **Appellation & Vineyard:** Specific AOC/DOCG designation and single-vineyard (*lieu-dit*) plot.

---

## 4. Managing Wine Vintages

Once a Wine Master is created, individual harvest years are added as **Wine Vintages**.

- **Navigate to:** Top navigation `Admin > Add wine` (`/backend/addWine.php`) or `Admin > Dashboard > Wines > Add new wine`.
- **Key Attributes:**
  - **Wine Master:** Select the base wine profile.
  - **Vintage Year:** Harvest year (or `NV` for Non-Vintage champagnes and fortified wines).
  - **CellarTracker ID (Optional):** Numerical ID for cross-referencing with CellarTracker.
  - **Vintage Notes / Description:** Winemaking details, weather conditions, or blend variations.

---

## 5. Adding Bottles, Storage Locations & Drinking Windows

Individual physical bottles belong to a Wine Vintage and represent tangible cellar stock.

- **Navigate to:** Top navigation `Admin > Add bottle` (`/backend/addBottle.php`) or via purchase order delivery.
- **Bottle Formats Supported:**
  - `375ml` (Half bottle)
  - `500ml` (Half-litre bottle)
  - `750ml` (Standard bottle)
  - `1000ml` (One-litre bottle)
  - `1500ml` (Magnum)
  - `3000ml` (Double magnum / Jéroboam)
  - `6000ml` (Impériale / Methuselah)
  - Large formats up to `18000ml` (Melchior)
- **Storage Locations:** Assign bottles to specific cellars and bin locations (e.g. `Rack 1, Shelf B, Slot 4`).
- **Drinking Window:** Set `Drink from (yyyy)` and `Drink through (yyyy)` to guide readiness calculations in the cellar and the *Carte des vins*.
- **Bottle Statuses:**
  - `in cellar`: Active physical inventory ready or aging in the cellar.
  - `consumed`: Drunk bottle (linked to tasting date, tasting note ID, and consumption notes).
  - `empty / missing`: Lost, broken, or gifted bottles.

---

## 6. Purchase Orders & Merchant Invoices

phpMyCellar lets you record purchasing transactions to calculate your cellar valuation, average bottle costs, and track merchant deliveries.

- **Navigate to:** `Admin > Dashboard > Cellar management > Create new order` (`addOrder.php`) and `Manage open orders` (`manageOrders.php`).
- **Key Attributes:**
  - **Merchant / Store:** Retailer, auctioneer, or direct domaine purchase.
  - **Order Reference & Date:** Purchase order reference and acquisition date.
  - **Order Items:** Format, quantity, unit price, tax status (duty paid vs in-bond), and delivery status.
  - **Receiving Deliveries:** Accept deliveries via `manageOrders.php` to assign bins and automatically move bottles into active `in cellar` status.
  - **Invoice Archive:** Securely upload and store PDF receipts and merchant invoices in `uploads/invoices/`.
