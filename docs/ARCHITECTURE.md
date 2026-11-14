# System Architecture & Technical Specifications
## P and P Pet Shop E-Commerce Platform

### 1. High-Level Architecture Overview

The P and P Pet Shop platform combines a pre-rendered, high-performance **Astro.js** frontend with a secure, modular **PHP 8.3 REST API** backend and **MySQL 8.0+ / MariaDB** database, hosted on a single shared cPanel hosting account.

```
+-------------------------------------------------------------------------+
|                              CLIENT BROWSER                             |
|      (Desktop, Tablet, Mobile - Astro.js Pre-rendered + Reactive UI)    |
+-------------------------------------------------------------------------+
                                     |
              HTTPS Requests         |  JSON REST API
       (Pages: /shop, /cart, etc.)   |  (/api/v1/*)
                                     v
+-------------------------------------------------------------------------+
|                           APACHE / CPANEL                               |
|  - Webroot: public_html/                                                |
|  - .htaccess Rewrite Rules: Clean URLs & API routing                     |
|  - Security Headers & Static Asset Caching                              |
+-------------------------------------------------------------------------+
                 |                                      |
       Static HTML & Assets                 Route /api/v1/*
                 v                                      v
+----------------------------------+  +-----------------------------------+
|     PUBLIC WEBROOT (public_html) |  |   API ENTRYPOINT (api/index.php)  |
| - index.html (Home)              |  |   Bridges to private backend      |
| - shop/index.html (Catalogue)    |  +-----------------------------------+
| - shop/[slug]/index.html         |                    |
| - cart/index.html (Cart)         |                    v
| - checkout/index.html            |  +-----------------------------------+
| - tracking/index.html            |  |      PHP 8.3 BACKEND (Private)    |
| - admin/index.html (Admin Portal)|  |  Location: /home/user/backend/    |
+----------------------------------+  |                                   |
                                      | - App\Core (Router, PDO, Config)  |
                                      | - Controllers & Services          |
                                      | - Repositories & Models           |
                                      | - Validation & Security           |
                                      | - Uber Eats Integration Layer     |
                                      | - CLI Cron Task Schedulers        |
                                      +-----------------------------------+
                                                        |
                                       +----------------+----------------+
                                       |                                 |
                                       v                                 v
                     +----------------------------------+  +---------------------------+
                     |         MYSQL DATABASE           |  |   UBER EATS OFFICIAL API  |
                     |  - 24 Relational Tables          |  |  - OAuth 2.0 Auth Flow    |
                     |  - InnoDB, Transactions          |  |  - Stores / Menus API     |
                     |  - DECIMAL(12,2) Monetary        |  |  - Items Availability API |
                     |  - Snapshots & Event Timelines   |  |  - Webhook Listener       |
                     +----------------------------------+  +---------------------------+
```

---

### 2. Physical & Logical Directory Structure

```
/home/username/
├── backend/                               <-- PRIVATE (Outside public_html)
│   ├── config/
│   │   ├── app.php
│   │   ├── database.php
│   │   └── uber.php
│   ├── database/
│   │   ├── migrations/001_initial_schema.sql
│   │   ├── seeders/seed.php
│   │   └── database.sql                   <-- Combined 1-click SQL import
│   ├── src/
│   │   ├── Autoloader.php                 <-- Zero-dependency PSR-4 autoloader
│   │   ├── Core/ (App, Router, Database, Request, Response, Logger, Config, Session)
│   │   ├── Middleware/ (Auth, Admin, RateLimit, Cors, Csrf)
│   │   ├── Models/
│   │   ├── Repositories/ (Product, Order, Customer, Uber)
│   │   ├── Services/ (Cart, Checkout, Order, Auth, Payment, Delivery)
│   │   ├── Controllers/ (Product, Category, Cart, Checkout, Order, Auth, Admin, Webhook)
│   │   ├── Validators/
│   │   └── Helpers/ (CurrencyHelper, StringHelper, SecurityHelper)
│   ├── integrations/
│   │   └── Uber/
│   │       ├── UberClient.php             <-- cURL client with exponential backoff
│   │       ├── OAuthManager.php           <-- Official OAuth2 token lifecycle
│   │       ├── StoreService.php
│   │       ├── MenuService.php
│   │       ├── ProductSyncService.php
│   │       ├── InventorySyncService.php
│   │       ├── OrderService.php
│   │       ├── DeliveryService.php
│   │       ├── WebhookService.php
│   │       └── Adapters/
│   │           ├── UberOrderAdapterInterface.php
│   │           ├── UberExternalOrderAdapter.php
│   │           ├── UberMarketplaceOrderAdapter.php
│   │           └── UberDirectDeliveryAdapter.php
│   ├── cron/
│   │   ├── CronLock.php                   <-- Atomic locking via database
│   │   ├── scheduler.php                  <-- Master scheduler for cPanel cron
│   │   ├── sync_products.php
│   │   ├── sync_prices.php
│   │   ├── sync_inventory.php
│   │   ├── process_orders.php
│   │   └── cleanup.php
│   ├── storage/
│   │   ├── logs/ (app.log, uber.log, audit.log)
│   │   ├── cache/rate_limits/
│   │   └── temp/
│   ├── .env                               <-- Environment configuration
│   └── composer.json
│
└── public_html/                           <-- PUBLIC WEBROOT
    ├── index.html                         <-- Homepage
    ├── shop/index.html                    <-- Product catalogue
    ├── shop/[slug]/index.html             <-- Product details
    ├── cart/index.html                    <-- Shopping cart
    ├── checkout/index.html                <-- Checkout form
    ├── order-confirmation/index.html      <-- Order receipt
    ├── tracking/index.html                <-- Live tracking
    ├── admin/index.html                   <-- Admin portal
    ├── _astro/                            <-- Compiled JS & CSS bundles
    ├── assets/                            <-- Optimized pet imagery
    ├── api/index.php                      <-- Secure API proxy to backend
    └── .htaccess                          <-- Apache routing, HTTPS, security headers
```

---

### 3. Core Architectural Principles

1. **Independent Operations**: The shop, catalogue, cart, checkout, and order management operate 100% reliably even if Uber API is unavailable or unconfigured.
2. **Strict Server-Side Price Calculation**: Browser clients are never trusted for pricing or totals. Every item price, variant surcharge, delivery fee, and discount is calculated and verified in PHP.
3. **Transactional Safety & Idempotency**: Order submissions use database transactions (`BEGIN...COMMIT`) and client-generated idempotency keys (`idempotency_key`) to prevent double-charging or duplicate order creation.
4. **Adapter Architecture for Uber Integration**: Order and delivery integrations are implemented behind modular interfaces (`UberExternalOrderAdapter`, `UberDirectDeliveryAdapter`). Features are only enabled when the merchant account has official API permissions.
5. **Zero-Dependency PSR-4 Autoloading**: Backend works immediately on shared cPanel hosting without requiring Composer or Node.js on the production server.

