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

- **Navigate to:** `Backend > Producers > Add Producer` (or edit existing producers).
- **Key Attributes:**
  - **Producer Name:** Full estate name (e.g. *Château Margaux*).
  - **Country & Region:** Geographic origin (e.g. *France > Bordeaux > Margaux*).
  - **Website & Notes:** Reference links, winemaking philosophies, or visit memories.

---

## 3. Managing Wines & Naming Conventions

Wines are created under a specific Producer.

- **Navigate to:** `Backend > Wines > Add Wine`.
- **Key Attributes:**
  - **Wine Name:** Specific cuvée or appellation designation.
  - **Naming Convention:** Determines how the wine title is formatted across menus and headings (e.g. `[Producer] [Wine Name]` or `[Appellation] [Producer]`).
  - **Colour & Style:** Red, White, Rosé, Sparkling, Sweet / Dessert, Fortified.
  - **Grape Varieties & Blend Percentages:** Primary and secondary grapes (e.g. *80% Cabernet Sauvignon, 20% Merlot*).
  - **Vineyard / Lieu-dit:** Specific plot classification (e.g. *Grand Cru*, *Premier Cru*, or single-vineyard designation).

---

## 4. Managing Vintages & Drinking Windows

Each wine can have one or more vintages associated with it.

- **Navigate to:** `Backend > Vintages > Add Vintage`.
- **Key Attributes:**
  - **Vintage Year:** Harvest year (or `NV` for Non-Vintage / Multi-Vintage champagnes and fortified wines).
  - **Alcohol by Volume (ABV):** E.g. *13.5%*.
  - **Classification:** E.g. *DOCG*, *AOC*, *VDP Grosse Lage*.
  - **Drinking Window:** Estimated maturity range (e.g. `Maturity from: 2026`, `Maturity to: 2040`).

---

## 5. Adding Bottles & Storage Locations

Individual bottles belong to a Vintage and are associated with a specific storage location.

- **Bottle Formats Supported:**
  - Half Bottle (375ml / 0.375L)
  - Standard Bottle (750ml / 0.75L)
  - Magnum (1500ml / 1.5L)
  - Double Magnum / Jeroboam (3000ml / 3.0L)
  - Imperial / Methuselah (6000ml / 6.0L)
- **Location Bins:** Track precise physical coordinates in your wine cooler, cellar rack, or external storage facility (e.g. `Rack 1, Shelf B, Slot 4`).
- **Bottle Statuses:**
  - *In Cellar:* Active inventory available for consumption.
  - *Consumed:* Drunk (with link to corresponding Tasting Note).
  - *Gifted / Sold / Traded:* Disposed of without a personal tasting note.

---

## 6. Purchase Orders & Merchant Invoices

phpMyCellar lets you record purchasing transactions to calculate your cellar valuation, average bottle costs, and track merchant deliveries.

- **Navigate to:** `Backend > Orders > Add Order`.
- **Key Attributes:**
  - **Merchant / Wine Merchant:** E.g. *Berry Bros. & Rudd*, *Farr Vintners*, or direct winery purchase.
  - **Order Date & Delivery Status:** Track en-primeur / pre-orders vs. delivered inventory.
  - **Invoice Upload:** Securely upload and archive PDF invoices or merchant receipts into `uploads/invoices/`.
