# cPanel Cron Jobs & Task Scheduling
## P and P Pet Shop

To ensure product prices, stock availability, and orders stay synchronized with Uber Eats and the physical store, background tasks are managed via cPanel Cron Jobs.

---

### 1. Recommended Setup: Master Scheduler (1 Single Cron Job)

Instead of scheduling 5 separate cron jobs, configure a **single master scheduler**:

```bash
* * * * * /usr/local/bin/php /home/username/backend/cron/scheduler.php >/dev/null 2>&1
```

The master scheduler runs every minute and internally evaluates task schedules:

| Task Script | Purpose | Interval |
|---|---|---|
| `process_orders.php` | Reconcile pending orders and sync statuses | Every 2 minutes |
| `sync_inventory.php` | Synchronize out-of-stock items with Uber Eats | Every 10 minutes |
| `sync_prices.php` | Synchronize price updates with Uber Eats | Every 15 minutes |
| `sync_products.php` | Full menu and catalog synchronization | Every 30 minutes |
| `cleanup.php` | Prune abandoned carts and old API logs | Daily at 03:00 AM |

---

### 2. Alternative Setup: Individual Cron Jobs

If you prefer discrete cron job entries in cPanel:

#### Order Processing (Every 2 minutes)
```bash
*/2 * * * * /usr/local/bin/php /home/username/backend/cron/process_orders.php >/dev/null 2>&1
```

#### Inventory & Availability Sync (Every 10 minutes)
```bash
*/10 * * * * /usr/local/bin/php /home/username/backend/cron/sync_inventory.php >/dev/null 2>&1
```

#### Price Sync (Every 15 minutes)
```bash
*/15 * * * * /usr/local/bin/php /home/username/backend/cron/sync_prices.php >/dev/null 2>&1
```

#### Product Catalogue Sync (Every 30 minutes)
```bash
*/30 * * * * /usr/local/bin/php /home/username/backend/cron/sync_products.php >/dev/null 2>&1
```

#### Daily Cleanup (Daily at 3:00 AM)
```bash
0 3 * * * /usr/local/bin/php /home/username/backend/cron/cleanup.php >/dev/null 2>&1
```

---

### 3. Concurrency Protection & Overlap Prevention

All cron scripts utilize the atomic `CronLock` class:
1. When a task starts, it creates a record in `cron_locks` with a strict Time-To-Live (TTL).
2. If the previous run is still processing (e.g. during a slow external network response), subsequent runs detect the active lock and exit immediately.
3. Upon completion, the lock is released. Stale locks are automatically pruned.

---

### 4. Monitoring & Logs

Execution logs are recorded in:
- `/home/username/backend/storage/logs/uber-YYYY-MM-DD.log`
- `/home/username/backend/storage/logs/app-YYYY-MM-DD.log`
- Database table: `uber_sync_logs` (visible in Admin Dashboard under the Uber Eats tab).

