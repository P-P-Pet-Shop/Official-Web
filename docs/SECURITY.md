# Security Architecture & Safeguards
## P and P Pet Shop E-Commerce Platform

This document describes the security controls implemented across the frontend and backend architectures.

---

### 1. Webroot Isolation (Defense in Depth)

- **Backend Source Code Isolation**: The entire PHP backend (`/home/username/backend/`) is deployed outside `public_html/`. No visitor can directly invoke backend model files, view configuration, or download `.env` files.
- **Single Public API Entry Point**: Only `api/index.php` is accessible via the web server.
- **Apache Directory Protection**: `.htaccess` explicitly disables directory browsing (`Options -Indexes`) and blocks requests targeting sensitive file extensions (`.env`, `.sql`, `.log`, `.json`, `.lock`).

---

### 2. Database Protection & SQL Injection Prevention

- **100% Prepared Statements**: Every SQL query is executed via PDO with parameter binding. No raw string interpolation is used.
- **Strict Data Types**: Numeric IDs are cast with `(int)`, financial values are validated and stored as `DECIMAL(12,2)`, and status columns use MySQL `ENUM` types.
- **Transactional Atomicity**: All order placements, inventory updates, and multi-step writes run inside database transactions (`beginTransaction()`, `commit()`, `rollBack()`).

---

### 3. Authentication & Password Security

- **Password Hashing**: Stored using PHP's native `password_hash()` with `PASSWORD_BCRYPT` and a work cost factor of 12.
- **Constant-Time Verification**: Passwords and signatures are verified using `password_verify()` and `hash_equals()` to prevent timing attacks.
- **JWT Authorization**: Admin and customer API sessions issue cryptographically signed JWT tokens (HMAC SHA256) with strict expiration timeouts.
- **Cookie Flags**: All session cookies are configured with `HttpOnly`, `SameSite=Lax`, and `Secure` (over HTTPS).

---

### 4. Input Validation & XSS Prevention

- **Server-Side Validation**: All checkout fields (phone numbers, addresses, names) are validated using strict regex patterns and length boundaries.
- **Output Encoding**: User data rendered into HTML is sanitized using `htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.

---

### 5. Webhook Security

- **HMAC SHA256 Signature Verification**: Incoming Uber Eats webhooks must include an `X-Uber-Signature` header matching the HMAC hash of the raw payload using the secret `UBER_WEBHOOK_SIGNING_KEY`.
- **Replay Protection**: Webhook event IDs are deduplicated in `uber_webhook_events`.

---

### 6. Rate Limiting & Denial of Service Protection

- **IP-Based Throttling**: The `RateLimitMiddleware` restricts API requests to a configurable threshold (default: 60 requests per minute per IP address).
- **Graceful Error Responses**: Rate-limited clients receive HTTP `429 Too Many Requests` with a `Retry-After` header.

---

### 7. Audit & Privacy Logging

- **Sensitive Data Masking**: The `api_logs` table automatically strips passwords and authorization tokens before storing request payloads.
- **Production Error Handling**: Stack traces and internal database errors are logged privately in `storage/logs/` and are never exposed to customers.

