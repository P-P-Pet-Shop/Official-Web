# REST API Documentation (v1)
## P and P Pet Shop Backend

Base URL: `https://pandppetshop.lk/api/v1`

All responses follow a consistent JSON envelope format:

```json
{
  "success": true,
  "message": "Human readable status message",
  "data": { ... },
  "meta": { ... }
}
```

---

### 1. Public Endpoints

#### `GET /products`
Retrieve a paginated list of active products with optional filters.
- **Query Parameters**:
  - `category` (string, optional): Category slug or ID (e.g. `foods`)
  - `search` (string, optional): Keyword query
  - `sort` (string, optional): `featured`, `price_asc`, `price_desc`, `name_asc`
  - `page` (int, default: 1)
  - `per_page` (int, default: 12)
- **Response `200 OK`**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Drools Focus Adult Super Premium Dog Food",
      "slug": "drools-focus-adult-super-premium-dog-food",
      "sku": "PNP-FOOD-1001",
      "base_price": "7200.00",
      "sale_price": "6850.00",
      "category_name": "Pet Foods",
      "primary_image": "/src/assets/foods/10003.jpg",
      "total_stock": 48
    }
  ],
  "meta": {
    "total": 9,
    "page": 1,
    "per_page": 12,
    "total_pages": 1
  }
}
```

#### `GET /products/{slug}`
Retrieve full product details including image gallery and size/weight variants.
- **Response `200 OK`**:
```json
{
  "success": true,
  "data": {
    "id": 1,
    "name": "Drools Focus Adult Super Premium Dog Food",
    "slug": "drools-focus-adult-super-premium-dog-food",
    "sku": "PNP-FOOD-1001",
    "description": "...",
    "base_price": "7200.00",
    "sale_price": "6850.00",
    "images": [
      { "id": 1, "image_url": "/src/assets/foods/10003.jpg", "is_primary": 1 }
    ],
    "variants": [
      { "id": 101, "variant_name": "1.2 kg Pack", "additional_price": "0.00", "available_stock": 25 },
      { "id": 102, "variant_name": "4 kg Bag", "additional_price": "6500.00", "available_stock": 15 }
    ]
  }
}
```

#### `POST /cart/validate`
Validates client cart contents and returns authoritative server-calculated pricing.
- **Request Body**:
```json
{
  "items": [
    { "product_id": 1, "variant_id": 101, "quantity": 2 },
    { "product_id": 6, "variant_id": null, "quantity": 1 }
  ],
  "district": "Colombo"
}
```
- **Response `200 OK`**:
```json
{
  "success": true,
  "data": {
    "is_valid": true,
    "subtotal": 17100.00,
    "subtotal_formatted": "Rs. 17,100.00",
    "delivery_fee": 0.00,
    "delivery_fee_formatted": "Rs. 0.00",
    "total": 17100.00,
    "total_formatted": "Rs. 17,100.00",
    "total_items_count": 3
  }
}
```

#### `POST /checkout`
Submits a customer order and initiates local fulfillment or external integration.
- **Request Body**:
```json
{
  "customer_name": "Kasun Perera",
  "customer_phone": "0771234567",
  "customer_email": "kasun@example.com",
  "delivery_district": "Colombo",
  "delivery_city": "Mount Lavinia",
  "delivery_address": "No. 45/2, Templers Road",
  "delivery_instructions": "Leave with security",
  "payment_method": "CASH_ON_DELIVERY",
  "items": [
    { "product_id": 1, "variant_id": 101, "quantity": 2 }
  ]
}
```
- **Response `201 Created`**:
```json
{
  "success": true,
  "message": "Your order has been placed successfully!",
  "data": {
    "id": 14,
    "order_number": "PNP-20261113-A78K",
    "tracking_token": "a1b2c3d4e5f6...",
    "total_amount": 13700.00,
    "total_amount_formatted": "Rs. 13,700.00",
    "payment_method": "CASH_ON_DELIVERY",
    "payment_status": "PENDING",
    "website_order_status": "PENDING",
    "tracking_url": "/tracking?order=PNP-20261113-A78K&token=a1b2c3d4e5f6..."
  }
}
```

#### `GET /orders/{orderNumber}/tracking`
Retrieve real-time preparation and delivery timeline for an order.
- **Query Parameters**:
  - `token` (string): 64-character tracking token received at checkout, OR
  - `phone` (string): Last digits of customer phone number for security verification.
- **Response `200 OK`**:
```json
{
  "success": true,
  "data": {
    "order_number": "PNP-20261113-A78K",
    "website_order_status": "PREPARING",
    "delivery_status": "PENDING",
    "delivery_city": "Mount Lavinia",
    "delivery_district": "Colombo",
    "total_amount_formatted": "Rs. 13,700.00",
    "timeline": [
      { "status": "PENDING", "description": "Order placed successfully by customer via website checkout.", "created_at": "2026-11-13 14:15:00" },
      { "status": "CONFIRMED", "description": "Order reviewed and stock confirmed at Mount Lavinia warehouse.", "created_at": "2026-11-13 14:20:00" },
      { "status": "PREPARING", "description": "Items packaged in eco-friendly protective packaging.", "created_at": "2026-11-13 14:35:00" }
    ]
  }
}
```

---

### 2. Admin Endpoints (Require `Authorization: Bearer <token>`)

- `POST /admin/auth/login`: Authenticate admin with username & password.
- `GET /admin/dashboard`: Metrics for total revenue, active orders, and low-stock alerts.
- `GET /admin/orders`: List customer orders with search and status filters.
- `PATCH /admin/orders/{id}`: Update order lifecycle status (`CONFIRMED`, `PREPARING`, `READY_FOR_PICKUP`, `DELIVERED`, `CANCELLED`).
- `GET /admin/inventory`: Live stock levels and reorder alerts.
- `PATCH /admin/inventory/{id}`: Update on-hand inventory levels.
- `GET /admin/uber/status`: Uber Eats merchant connection and store status.
- `POST /admin/uber/sync`: Trigger manual sync (`CATALOG`, `PRICE`, `INVENTORY`, or `ALL`).
- `GET /admin/uber/mappings`: View product mapping between website products and Uber items.

---

### 3. Webhook Endpoints

#### `POST /webhooks/uber`
Receives official Uber Eats status updates. Requires valid `X-Uber-Signature` header matching the HMAC SHA256 of the payload.
- Supported events: `eats.order.status_update`, `eats.store.status_update`, `eats.item.out_of_stock`.

