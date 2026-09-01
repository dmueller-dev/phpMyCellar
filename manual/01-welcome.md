# 01. Welcome to phpMyCellar

## Introduction

Welcome to **phpMyCellar**, an open-source, self-hosted wine cellar management notebook and tasting notes journal.

Whether you are a seasoned collector curating a temperature-controlled cellar, an enthusiast documenting tasting journeys, or a sommelier organising a private collection, phpMyCellar offers a digital platform tailored to the nuances of wine appreciation.

---

## Core Philosophy

### 1. Data Sovereignty & Privacy
Your cellar records, financial purchase histories, tasting impressions, and storage layouts belong entirely to you. phpMyCellar operates without external cloud dependencies, vendor lock-in, tracking pixels, or third-party telemetry.

### 2. Tailored to Fine Wine Collector Workflows
Generic inventory systems often struggle with the multi-tiered hierarchies intrinsic to wine:
- A single **Producer** (e.g. *Domaine Leflaive*) crafts wines across multiple **Appellations** (e.g. *Puligny-Montrachet*, *Bâtard-Montrachet*).
- Each wine has individual **Vintages** with distinct characteristics and optimal drinking windows.
- Each vintage may be acquired in varying **Bottle Formats** (e.g. 750ml Standard, 1500ml Magnum, 375ml Half-bottle) across distinct **Purchase Orders** and stored in specific **Cellar Bins**.

phpMyCellar models these relationships with clarity.

### 3. Sharing Impressions
Wine is inherently communal. phpMyCellar lets you maintain a private digital cellar notebook while giving you the option to share an elegant **Wine Menu (*Carte des vins*)**, long-form **Tasting Notes**, and **Vintage Articles** with dinner guests, tasting groups, or the broader wine community.

---

## High-Level Architecture Overview

```text
[Public Visitors & Guests]        [Tasting Group Members]          [Cellar Administrator]
          │                                  │                               │
          ▼                                  ▼                               ▼
┌──────────────────┐               ┌───────────────────┐           ┌──────────────────┐
│ Public Wine Menu │               │  Tasting Notes &  │           │ Complete Backend │
│  & Public Blog   │               │   Subscriptions   │           │ Administration   │
└─────────┬────────┘               └─────────┬─────────┘           └────────┬─────────┘
          │                                  │                              │
          └──────────────────────────────────┼──────────────────────────────┘
                                             │
                                   ┌─────────▼─────────┐
                                   │    phpMyCellar    │
                                   │    PHP Backend    │
                                   └─────────┬─────────┘
                                             │
                                   ┌─────────▼─────────┐
                                   │  MariaDB / MySQL  │
                                   │  Relational Data  │
                                   └───────────────────┘
```

---

## Next Steps

To begin deploying and configuring your cellar, proceed to [02. Getting Started & Installation](02-getting-started.md).
