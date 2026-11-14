# Testing & Verification Guide
## P and P Pet Shop E-Commerce Platform

This document details the automated and manual testing procedures for verifying the platform's functionality.

---

### 1. Running Automated Backend Tests

The backend includes a standalone, zero-dependency test runner in `backend/tests/TestRunner.php`.

To run the test suite:

```bash
php backend/tests/TestRunner.php
```

#### Test Coverage:
1. **`CurrencyHelperTest`**:
   - Validates correct formatting in Sri Lankan Rupee (`Rs. 2,500.00`).
   - Validates comma separators for large amounts (`Rs. 1,450,000.50`).
   - Validates exact 2-decimal string conversions for database storage.
2. **`StringHelperTest`**:
   - Validates URL slug generation for pet products.
   - Validates order number generation pattern (`PNP-YYYYMMDD-XXXX`).
   - Validates 64-character tracking tokens.
   - Validates HTML sanitization against XSS payloads.
3. **`UberIntegrationTest`**:
   - Validates that `UberExternalOrderAdapter` is disabled by default until merchant POS approval is verified.
   - Validates genuine HMAC SHA256 signature verification for Uber webhooks.
   - Validates rejection of forged or expired webhook deliveries.
   - Validates Sri Lankan delivery fee tiers (Colombo Rs. 350, Free over Rs. 15,000, Outstations Rs. 650).

---

### 2. Frontend Build Verification

To verify that all 22 static Astro pages, components, and Tailwind styles compile without errors:

```bash
npm run build
```

Expected output:
```
[build] Complete!
22 page(s) built in ~2.5s
```

---

### 3. End-to-End Verification Checklist

#### Customer Shopping Workflow:
1. Navigate to `/shop` — confirm products render with image, category, and LKR prices.
2. Click any product to open `/shop/[slug]` — verify variant selector updates price in real time.
3. Click "Add to Shopping Cart" — verify the cart badge updates in the top navigation bar.
4. Navigate to `/cart` — update quantity or delete an item; change district and verify delivery charge recalculation.
5. Click "Proceed to Checkout" (`/checkout`) — fill in customer name, Sri Lankan mobile number, and delivery address.
6. Submit order — verify receipt screen on `/order-confirmation` with order number.
7. Click "Track Your Order" (`/tracking`) — verify order timeline displays "PENDING" and customer details.

#### Admin Store Management:
1. Navigate to `/admin`.
2. Sign in with `admin` / `Admin@123`.
3. Check the **Overview** dashboard — verify order count and revenue in LKR.
4. Open the **Orders** tab — change an order status from `PENDING` to `CONFIRMED` or `PREPARING`.
5. Re-check the customer tracking page (`/tracking`) — confirm the new status reflects immediately.
6. Open the **Uber Eats** tab — trigger manual sync buttons to verify log generation.

