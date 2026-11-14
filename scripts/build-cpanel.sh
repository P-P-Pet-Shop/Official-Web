#!/usr/bin/env bash
# ==============================================================================
# P and P Pet Shop - cPanel Deployment Package Builder
# Builds Astro frontend and packages PHP 8.3 backend + MySQL schema
# ==============================================================================

set -e

echo "=== P&P Pet Shop: Building Production cPanel Package ==="

# 1. Run Astro Build Locally
echo "[1/4] Building Astro.js frontend static assets..."
npm run build

# 2. Prepare Deployment Directories
echo "[2/4] Assembling deployment filesystem..."
DEPLOY_DIR="deploy_package"
rm -rf "$DEPLOY_DIR"
mkdir -p "$DEPLOY_DIR/public_html/api"
mkdir -p "$DEPLOY_DIR/backend/storage/logs"
mkdir -p "$DEPLOY_DIR/backend/storage/cache/rate_limits"
mkdir -p "$DEPLOY_DIR/backend/storage/temp"

# 3. Copy Static Frontend into public_html
cp -r dist/* "$DEPLOY_DIR/public_html/"
cp public/.htaccess "$DEPLOY_DIR/public_html/.htaccess"
cp public/api/index.php "$DEPLOY_DIR/public_html/api/index.php"

# 4. Copy Backend Code
cp -r backend/config "$DEPLOY_DIR/backend/"
cp -r backend/src "$DEPLOY_DIR/backend/"
cp -r backend/integrations "$DEPLOY_DIR/backend/"
cp -r backend/cron "$DEPLOY_DIR/backend/"
cp -r backend/database "$DEPLOY_DIR/backend/"
cp backend/composer.json "$DEPLOY_DIR/backend/"
cp backend/.env.example "$DEPLOY_DIR/backend/.env.example"
cp backend/.env.example "$DEPLOY_DIR/backend/.env"

# Copy 1-Click Database Setup
cp backend/database/database.sql "$DEPLOY_DIR/database.sql"

# 5. Create Zip Archive if zip is installed
echo "[3/4] Creating ZIP package for cPanel File Manager upload..."
if command -v zip >/dev/null 2>&1; then
    (cd "$DEPLOY_DIR" && zip -r -q "../pandp_petshop_cpanel_package.zip" .)
    echo "✓ Created archive: pandp_petshop_cpanel_package.zip"
else
    echo "(!) 'zip' command not found. The files are organized ready in '$DEPLOY_DIR/' directory."
fi

echo "[4/4] Package assembly completed!"
echo "--------------------------------------------------------"
echo "Deployment Structure:"
echo "  $DEPLOY_DIR/public_html/  -> Upload to cPanel public_html/ (Astro static site + API proxy)"
echo "  $DEPLOY_DIR/backend/      -> Upload to /home/username/backend/ (Private PHP 8.3 app)"
echo "  $DEPLOY_DIR/database.sql  -> Import into MySQL database via cPanel phpMyAdmin"
echo "--------------------------------------------------------"

