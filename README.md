# P and P Pet Shop — E-Commerce & Uber Eats Platform
> Production-ready online pet shop, order management system, and official Uber Eats integration for **P and P Pet Shop (Mount Lavinia, Sri Lanka)**.

Hosted on **Shared cPanel Hosting** with **Astro.js (Frontend)**, **PHP 8.3 (Backend REST API)**, and **MySQL 8.0+ / MariaDB (Database)**.

---

## 🌟 Highlights

- **Astro.js Frontend**: High-performance, pre-rendered static site preserving P&P Pet Shop's visual identity, brand colors, pet gallery, and responsive layouts.
- **PHP 8.3 Backend**: Clean, zero-dependency PSR-4 REST API with PDO prepared statements, database transactions, and secure session management.
- **Authoritative Price Validation**: All item prices, variant surcharges, delivery fees, and order totals are calculated and verified server-side.
- **Official Uber Eats Integration**:
  - Implements official Uber Eats Developer APIs (Store, Menu, Items Availability, Orders, Webhooks).
  - Standalone operation: the website shop and local order fulfillment operate 100% reliably if Uber API is unavailable or unconfigured.
  - Adapter architecture (`UberExternalOrderAdapter`, `UberMarketplaceOrderAdapter`, `UberDirectDeliveryAdapter`).
  - Strict guard against unapproved external order injection.
- **Multi-Tier Delivery in Sri Lanka**: Built-in calculation for Colombo (Rs. 350 / Free over Rs. 15,000), Gampaha/Kalutara (Rs. 450), and Outstations (Rs. 650).
- **Comprehensive Admin Portal**: Metrics dashboard, order lifecycle status updater, product catalogue manager, stock controls, and Uber sync monitors.
- **cPanel Production Package**: 1-step build script generates ready-to-upload `public_html/` and private `backend/` files.

---

## 📁 Repository Structure

```
.
├── backend/                       # PHP 8.3 Backend (Uploaded outside public_html)
│   ├── config/                    # Application, Database, and Uber configuration
│   ├── database/                  # Migrations (24 tables) and seeders
│   ├── src/                       # Controllers, Services, Repositories, Core
│   ├── integrations/Uber/         # Official Uber Eats integration layer & adapters
│   ├── cron/                      # Background task scheduler and sync scripts
│   ├── storage/                   # Logs, cache, and temporary files
│   └── tests/                     # Automated unit and integration test runner
│
├── src/                           # Astro.js Frontend Source
│   ├── pages/
│   │   ├── index.astro            # Homepage (P&P Companions & Services)
│   │   ├── shop/index.astro       # Product Catalogue with category & search filters
│   │   ├── shop/[slug].astro      # Product Details with variant selection & stock
│   │   ├── cart.astro             # Interactive Shopping Cart with delivery calculator
│   │   ├── checkout.astro         # Order Checkout with COD & Bank Transfer
│   │   ├── order-confirmation.astro # Order Receipt & Confirmation
│   │   ├── tracking.astro         # Real-time Order Tracking timeline
│   │   ├── admin/index.astro      # Complete Store Management Admin Portal
│   │   ├── gallery.astro          # Curated pet gallery
│   │   ├── services.astro         # Pet services & vet care
│   │   ├── about.astro            # About P&P Pet Shop
│   │   └── contact.astro          # Contact form & store location
│   ├── components/                # Navbar, Footer, UI components
│   └── scripts/                   # Reactive cart state manager & API client
│
├── public/                        # Static assets, .htaccess, and API gateway proxy
├── docs/                          # In-depth architectural & deployment guides
├── scripts/build-cpanel.sh        # Automated cPanel package builder
└── package.json                   # Astro & Tailwind dependencies
```

---

## 🚀 Quickstart & Development

### 1. Install Frontend Dependencies
```bash
npm install
```

### 2. Start Astro Development Server
```bash
npm run dev
```
Open [http://localhost:4321](http://localhost:4321) in your browser.

### 3. Build Static Frontend
```bash
npm run build
```

### 4. Build Complete cPanel Package
```bash
./scripts/build-cpanel.sh
```
This builds all 22 static pages and produces `pandp_petshop_cpanel_package.zip` ready for File Manager upload.

---

## 📦 cPanel Deployment Overview

1. **MySQL Database**: Create database in cPanel and import `backend/database/database.sql` via phpMyAdmin.
2. **Backend**: Upload `backend/` to `/home/username/backend/` (outside `public_html`).
3. **Environment**: Configure database credentials in `/home/username/backend/.env`.
4. **Frontend**: Upload `public_html/` contents to cPanel `/home/username/public_html/`.
5. **PHP Version**: Ensure PHP 8.3 is enabled in MultiPHP Manager.
6. **Cron Jobs**: Add single master cron job:
   ```bash
   * * * * * /usr/local/bin/php /home/username/backend/cron/scheduler.php >/dev/null 2>&1
   ```

For detailed step-by-step instructions, see [docs/CPANEL_DEPLOYMENT.md](docs/CPANEL_DEPLOYMENT.md).

---

## 📚 Technical Documentation

- **[System Architecture](docs/ARCHITECTURE.md)**: High-level diagrams, execution flow, and design patterns.
- **[Database Schema](docs/DATABASE_SCHEMA.md)**: Specification of all 24 relational tables, data types, and indexes.
- **[REST API Documentation](docs/API_DOCUMENTATION.md)**: Public, customer, admin, and webhook endpoint references.
- **[Uber Eats Integration Guide](docs/UBER_EATS_INTEGRATION.md)**: OAuth2, store connection, catalogue sync, and order adapter rules.
- **[cPanel Deployment Guide](docs/CPANEL_DEPLOYMENT.md)**: Complete guide for production cPanel shared hosting.
- **[Cron Jobs Setup](docs/CRON_SETUP.md)**: Concurrency locking, sync intervals, and scheduler syntax.
- **[Security Architecture](docs/SECURITY.md)**: Defense in depth, input sanitization, and password policies.
- **[Testing & Verification](docs/TESTING.md)**: Automated backend test suite and verification checklist.

---

## 🛡️ License

Proprietary © P and P Pet Shop & S JAY Web Solutions (Pvt) Ltd. All Rights Reserved.
