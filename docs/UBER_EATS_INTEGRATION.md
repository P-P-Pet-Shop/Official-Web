# Official Uber Eats Integration Guide
## P and P Pet Shop (Mount Lavinia, Sri Lanka)

This document provides complete technical specifications for the integration between **P and P Pet Shop** and the **Uber Eats Developer Platform**, based on official Uber documentation: https://developer.uber.com/docs/eats

---

### 1. Architectural Independence & Safety

The P and P Pet Shop platform is architected to operate **autonomously**:
- If Uber Eats API credentials are not yet entered, or if the Uber API experiences downtime or token revocation, the website's complete shopping catalogue, customer carts, checkout, local order database, and admin fulfillment workflows continue to function without interruption.
- No third-party API outage can block local customers from purchasing or viewing products.

---

### 2. Supported Official Uber Eats APIs

| Capability | Official Uber Endpoint | Merchant Permission Scope | Status in P&P Pet Shop |
|---|---|---|---|
| **OAuth 2.0 Auth** | `https://login.uber.com/oauth/v2/token` | Standard client credentials | **Implemented** |
| **Store Details** | `GET /v1/eats/stores/{store_id}` | `eats.store` | **Implemented** |
| **Menu / Catalogue** | `GET /v2/eats/stores/{store_id}/menus` | `eats.store` | **Implemented** |
| **Item Availability** | `POST /v1/eats/stores/{store_id}/items/{item_id}` | `eats.store` | **Implemented** |
| **Marketplace Orders** | `GET /v1/eats/stores/{store_id}/created-orders` | `eats.order` | **Implemented (Adapter)** |
| **POS Order Injection**| `POST /v1/eats/stores/{store_id}/orders` | Partner approval required | **Implemented (Adapter Guard)** |
| **Webhooks** | `POST /api/v1/webhooks/uber` | Webhook subscription | **Implemented** |

---

### 3. Website Orders vs. Uber Eats Orders (Crucial Clarification)

#### The Business Requirement:
P and P Pet Shop desires website customer orders to appear directly inside the merchant's Uber Eats Orders tablet / POS dashboard.

#### The Technical Reality & Uber API Policy:
1. **Inbound Marketplace Workflow**: The standard Uber Eats Marketplace API is built for consumers ordering on the *Uber Eats mobile app* and receiving those orders in the merchant's store.
2. **Outbound / POS Injection Workflow**: Injecting third-party website orders into Uber Eats Orders requires an approved **Uber POS Integration Partner Agreement** or an enterprise merchant API grant.
3. **P and P Pet Shop Solution**:
   - The integration implements the `UberExternalOrderAdapter`.
   - When an order is placed on the website, it is immediately saved in the local MySQL database with status `PENDING` and assigned a unique order number (`PNP-YYYYMMDD-XXXX`).
   - If the merchant has been granted the required POS Injection capability (flagged in `.env` as `UBER_EXTERNAL_ORDER_INJECTION_APPROVED=true`), the order is dispatched via the adapter to `POST /v1/eats/stores/{store_id}/orders`.
   - If that permission has not yet been approved by Uber for this Sri Lankan merchant account, the adapter safely logs the event and keeps the order in `NOT_INTEGRATED_LOCAL_ONLY` status for direct local fulfillment by the store staff.
   - **We never use browser automation, scraping, or private undocumented endpoints.**

---

### 4. Configuration & Environment Variables

Add the following to your private `backend/.env` file:

```env
# Uber API Environment: 'sandbox' for testing, 'production' for live
UBER_ENVIRONMENT=production

# Credentials from developer.uber.com
UBER_CLIENT_ID=your_client_id_here
UBER_CLIENT_SECRET=your_client_secret_here
UBER_REDIRECT_URI=https://pandppetshop.lk/api/v1/auth/uber/callback

# Merchant Store UUID
UBER_STORE_ID=your_uber_store_uuid_here

# Webhook Security
UBER_WEBHOOK_SIGNING_KEY=your_webhook_signing_key_here

# Permissions Flags (enable once approved by Uber Partner Program)
UBER_EXTERNAL_ORDER_INJECTION_APPROVED=false
UBER_DIRECT_ENABLED=false
```

---

### 5. Product & Price Synchronization

#### Synchronizing Catalogue:
Products are mapped using the `uber_product_mappings` table:
- Matches items by `uber_item_id` and internal `product_id` / `sku`.
- Preserves manual website photos, rich SEO descriptions, and category tags.
- Price updates can be synced automatically via cPanel cron jobs every 15 minutes.

#### Availability Sync:
When a product runs out of stock in the local physical inventory (`inventory.quantity_on_hand <= 0`), the `sync_inventory.php` cron script automatically dispatches an item suspension payload:

```json
{
  "suspension": {
    "reason": "OUT_OF_STOCK"
  }
}
```
This guarantees that items out of stock in Mount Lavinia are simultaneously marked unavailable on Uber Eats.

---

### 6. Webhook Setup

Configure the webhook URL in your Uber Developer Dashboard:

```
https://pandppetshop.lk/api/v1/webhooks/uber
```

Ensure the Webhook Signing Key is copied to `UBER_WEBHOOK_SIGNING_KEY` in `backend/.env`. All incoming requests are authenticated using HMAC SHA256 signature verification on the `X-Uber-Signature` header.

