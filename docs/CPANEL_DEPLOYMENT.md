# Production cPanel Deployment Guide
## P and P Pet Shop E-Commerce Platform

This step-by-step guide explains how to deploy the P and P Pet Shop platform on standard shared cPanel hosting without requiring Node.js or Composer on the production server.

---

### Step 1 — Build the Deployment Package Locally

On your local workstation or CI/CD environment, run:

```bash
./scripts/build-cpanel.sh
```

This generates:
- `deploy_package/public_html/`: Static Astro site and API gateway proxy.
- `deploy_package/backend/`: Secure PHP 8.3 application code.
- `deploy_package/database.sql`: Combined 1-click database schema and seed data.
- `pandp_petshop_cpanel_package.zip`: Compressed archive ready for upload.

---

### Step 2 — Create the MySQL Database in cPanel

1. Log into your cPanel account.
2. Under **Databases**, open **MySQL Database Wizard**:
   - Create Database: `pandp_petshop` (Note the full prefix, e.g. `user_pandp`).
   - Create Database User: `pandp_user` with a strong password.
   - Assign Privileges: Check **ALL PRIVILEGES**.
3. Under **Databases**, open **phpMyAdmin**:
   - Select your newly created database.
   - Click the **Import** tab.
   - Choose `database.sql` from your deployment package and click **Import**.
   - Verify that all 24 tables are created successfully.

---

### Step 3 — Upload Application Files to cPanel

Open **cPanel File Manager**:

#### 1. Upload the Private Backend (Outside public_html):
- Navigate to your home directory (`/home/username/`).
- Create a directory named `backend` (if not already present).
- Upload the contents of `deploy_package/backend/` into `/home/username/backend/`.
- Ensure directory permissions for storage:
  ```
  chmod 775 /home/username/backend/storage
  chmod 775 /home/username/backend/storage/logs
  chmod 775 /home/username/backend/storage/cache
  ```

#### 2. Configure Environment (`.env`):
- Inside `/home/username/backend/`, edit `.env`:
  ```env
  APP_ENV=production
  APP_DEBUG=false
  APP_URL=https://pandppetshop.lk

  DB_HOST=localhost
  DB_NAME=yourcpanel_pandp_petshop
  DB_USER=yourcpanel_pandp_user
  DB_PASS=YourActualDatabasePassword

  JWT_SECRET=generate-a-random-64-character-string
  ```

#### 3. Upload Public Frontend (Inside public_html):
- Navigate into `/home/username/public_html/`.
- Upload all files from `deploy_package/public_html/` into `public_html/`.
- Verify the following structure inside `public_html/`:
  - `index.html`
  - `shop/`
  - `cart/`
  - `checkout/`
  - `order-confirmation/`
  - `tracking/`
  - `admin/`
  - `api/index.php`
  - `.htaccess`
  - `_astro/`

---

### Step 4 — Verify PHP 8.3 & Extensions

1. In cPanel, navigate to **MultiPHP Manager**:
   - Select your domain (`pandppetshop.lk`).
   - Set PHP Version to **PHP 8.3**.
2. In cPanel, open **Select PHP Version / PHP Extensions**:
   - Confirm the following extensions are enabled:
     - `pdo_mysql`
     - `curl`
     - `json`
     - `openssl`
     - `mbstring`
     - `fileinfo`

---

### Step 5 — Configure Background Synchronization (Cron Jobs)

In cPanel, open **Cron Jobs**:
- Choose interval: **Once Per Minute** (`* * * * *`).
- Enter the command:
  ```bash
  /usr/local/bin/php /home/username/backend/cron/scheduler.php >/dev/null 2>&1
  ```
  *(Replace `/home/username/` with your actual cPanel home path displayed on your cPanel sidebar).*

---

### Step 6 — Post-Deployment Verification Checklist

1. [ ] Visit `https://pandppetshop.lk/` — confirms homepage loads with brand styling.
2. [ ] Visit `https://pandppetshop.lk/shop` — confirms products load from the database.
3. [ ] Add a product to cart and visit `/cart` — confirms dynamic cart calculations.
4. [ ] Place a test order on `/checkout` — confirms database order creation.
5. [ ] Visit `/tracking` — verifies order progress and status timeline.
6. [ ] Visit `/admin` — log in with `admin` / `Admin@123` (change password immediately after first login).
7. [ ] Visit `https://pandppetshop.lk/api/v1/health` — should return `{"success":true,"status":"operational"}`.

