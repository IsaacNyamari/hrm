# Deployment

Production deployment guide for the multi-tenant HRM SaaS.

## 1. Server requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3+ | extensions: `pdo_mysql`, `mbstring`, `openssl`, `bcmath`, `intl`, `gd` (letter/QR), `redis`, `zip`, `curl` |
| MySQL | 8.0+ | `utf8mb4`, `innodb_file_per_table` on; `sql_mode` including `STRICT_TRANS_TABLES` |
| Redis | 6+ | cache, session, queue, locks |
| Node | 20+ | build assets only (`npm ci && npm run build`), not needed at runtime |
| Web server | Nginx or Caddy | wildcard vhost + TLS (below) |
| Supervisor | any | keeps the queue worker alive |
| Cron | any | one line, runs the scheduler |

Recommended: 2 vCPU / 4 GB to start; the app is stateless so scale the web tier
horizontally behind a load balancer, with Redis + MySQL as shared services.

## 2. DNS & TLS — the wildcard subdomain

Tenants are addressed as `‹slug›.app.yourdomain.com`. You need:

* An `A`/`AAAA` record for `app.yourdomain.com` **and** a wildcard
  `*.app.yourdomain.com` pointing at the web tier.
* A TLS certificate that covers `app.yourdomain.com` **and** `*.app.yourdomain.com`
  (Let's Encrypt wildcard via DNS-01, or your platform's managed cert).
* `TENANCY_CENTRAL_DOMAINS=yourdomain.com,app.yourdomain.com` — hosts in this list
  are treated as the marketing / platform site, everything else as a tenant slug.
  `TENANCY_SUBDOMAINS_ENABLED=true`.
* The public per-tenant careers board lives at `‹slug›.app.yourdomain.com/careers`.
* Custom domains: a tenant may set `tenants.custom_domain`; point that host at the
  web tier too and the request resolves by exact host match before the subdomain
  rule.

### Nginx (sketch)

```nginx
server {
  listen 443 ssl http2;
  server_name app.yourdomain.com *.app.yourdomain.com;
  ssl_certificate     /etc/letsencrypt/live/app.yourdomain.com/fullchain.pem;
  ssl_certificate_key /etc/letsencrypt/live/app.yourdomain.com/privkey.pem;
  root /var/www/hrm/current/public;
  index index.php;
  client_max_body_size 12M;              # résumé / document uploads

  location / { try_files $uri $uri/ /index.php?$query_string; }
  location ~ \.php$ {
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
  }
  location ~ /\.(?!well-known).* { deny all; }
}
```

The app already sends `X-Frame-Options`, `X-Content-Type-Options`,
`Referrer-Policy`, `Permissions-Policy` and (on HTTPS in production)
`Strict-Transport-Security` via the `SecurityHeaders` middleware — no nginx
duplication needed. A Content-Security-Policy is deliberately left to the
deployment; add one at the edge once you have pinned your asset hosts.

## 3. First deploy

```bash
# as the deploy user, in the release directory
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

cp .env.example .env          # then edit — see section 4
php artisan key:generate
php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\RolePermissionSeeder --force
php artisan db:seed --class=Database\\Seeders\\PlanSeeder --force
php artisan db:seed --class=Database\\Seeders\\PlatformSeeder --force   # creates the platform Super Admin

php artisan storage:link
php artisan config:cache route:cache view:cache event:cache
```

Do **not** run `DemoTenantSeeder` in production — it creates two fake orgs.

### Subsequent deploys (zero-downtime)

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache route:cache view:cache event:cache
php artisan queue:restart          # workers pick up new code
php artisan up
```

`php artisan down --render="errors::503"` around the migrate step if a migration
is not backwards compatible. All migrations to date are additive.

## 4. Environment (`.env`)

Start from `.env.example`; the block at the bottom lists every app-specific key.
Production values that matter:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://app.yourdomain.com
APP_KEY=base64:...                       # php artisan key:generate

TENANCY_CENTRAL_DOMAINS=yourdomain.com,app.yourdomain.com
TENANCY_SUBDOMAINS_ENABLED=true

DB_CONNECTION=mysql
DB_HOST=... DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=...

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=... REDIS_PASSWORD=... REDIS_PORT=6379

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SESSION_DOMAIN=.app.yourdomain.com       # share the session across tenant subdomains

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning

MAIL_MAILER=postmark                     # or smtp / ses / resend
POSTMARK_API_KEY=...
MAIL_FROM_ADDRESS="no-reply@yourdomain.com"
MAIL_FROM_NAME="Your HRM"

PASSKEYS_USER_HANDLE_SECRET=...          # random 32+ chars, never rotate casually

# Billing (only if you charge tenants through M-Pesa)
BILLING_DEFAULT_PROVIDER=mpesa
MPESA_ENVIRONMENT=production
MPESA_BASE_URL=https://api.safaricom.co.ke
MPESA_CONSUMER_KEY=... MPESA_CONSUMER_SECRET=...
MPESA_SHORTCODE=... MPESA_PASSKEY=...
MPESA_CALLBACK_URL=https://app.yourdomain.com/api/webhooks/mpesa
MPESA_CALLBACK_TOKEN=...                 # shared secret checked on the callback
```

**Secrets** (`APP_KEY`, `PASSKEYS_USER_HANDLE_SECRET`, DB / Redis / mail / M-Pesa
credentials) belong in your platform's secret store, not in the repo. `APP_KEY`
rotation invalidates every encrypted column (bank account numbers, statutory IDs,
webhook secrets) — use `APP_PREVIOUS_KEYS` for a staged rotation.

## 5. Queue worker (Supervisor)

Notifications, webhook delivery, imports and exports run on the queue.

```ini
[program:hrm-worker]
command=php /var/www/hrm/current/artisan queue:work redis --sleep=1 --tries=3 --max-time=3600 --backoff=10
directory=/var/www/hrm/current
autostart=true
autorestart=true
stopwaitsecs=3600
numprocs=2
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/hrm/worker.log
```

`php artisan queue:restart` on every deploy. Scale `numprocs` with volume;
webhook delivery back-offs at `[10, 60, 300, 900]s` and disables an endpoint
after 10 straight failures.

## 6. Scheduler (cron)

```cron
* * * * * cd /var/www/hrm/current && php artisan schedule:run >> /dev/null 2>&1
```

Registered jobs (`routes/console.php`, all `->onOneServer()`):

| Command | Cadence | Purpose |
|---|---|---|
| `subscriptions:expire` | daily 00:10 | lapse ended trials / unpaid subscriptions |
| `subscriptions:send-reminders` | daily 08:00 | trial-ending e-mails |
| `leave:accrue` | monthly, 1st 01:00 | grant / carry-forward leave balances (chunked) |
| `documents:check-expiry` | daily 07:00 | notify on expiring employee documents (chunked) |

## 7. Storage & file security

* `FILESYSTEM_DISK=local`. Employee documents, generated letters, résumés and
  expense receipts are written to the **private** `local` disk under
  `storage/app/tenants/‹tenant-uuid›/…` and only ever served through an
  authorising controller (`Storage::download`). They are never public.
* Tenant branding logos go to the **public** disk (`storage/app/public`, exposed
  via `php artisan storage:link`) because e-mail clients need absolute image URLs.
* If you move private files to S3, the bucket must be private (no public ACL);
  keep the `public` disk on S3+CloudFront only for logos.
* `storage/` and `bootstrap/cache/` must be writable by the web/worker user.

## 8. Health checks & monitoring

* Load-balancer health probe: `GET /up` (Laravel's built-in, returns 200 when the
  framework boots).
* Add application metrics / error tracking (Sentry, Flare, or the `LOG_SLACK_*`
  channel) — set `LOG_STACK=daily,slack` and `LOG_SLACK_WEBHOOK_URL`.
* Watch: queue depth (`queue:monitor redis:default,1000`), failed jobs
  (`queue:failed`), Redis memory, MySQL slow query log, disk on `storage/`.

## 9. Backups

* **MySQL**: nightly `mysqldump --single-transaction --routines` (or managed
  snapshots) with 30-day retention; test a restore quarterly.
* **`storage/app`**: rsync / object-storage sync — this is the only file state
  (documents, receipts, letters, logos).
* Redis is a cache/queue only; no backup needed, but a flush loses queued (not
  yet processed) jobs.

## 10. Scaling notes

* Web tier is stateless — add instances behind the LB; sessions and cache are in
  Redis.
* The tenant is resolved per request from the host and never trusted from the
  client, so a shared web tier is safe for all tenants.
* Long jobs (payroll runs, imports) are already queued; give them a dedicated
  worker queue (`queue:work redis --queue=payroll,default`) if one tenant's run
  should not delay another's notifications.
* MySQL read replicas: point `DB_URL` read/write split via `config/database.php`
  `read`/`write` when the reporting load justifies it.

## 11. Go-live checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` set
- [ ] Wildcard DNS + TLS for `*.app.yourdomain.com`
- [ ] `TENANCY_CENTRAL_DOMAINS` correct, `SESSION_DOMAIN=.app.yourdomain.com`
- [ ] `SESSION_SECURE_COOKIE=true`, HTTPS enforced at the edge
- [ ] MySQL + Redis reachable; `php artisan migrate --force` clean
- [ ] `RolePermissionSeeder`, `PlanSeeder`, `PlatformSeeder` run; demo seeder **not** run
- [ ] Supervisor worker running; `schedule:run` cron installed
- [ ] `storage:link` done; `storage/` writable
- [ ] `config:cache route:cache view:cache event:cache` in the deploy script
- [ ] Mail transport verified (send a test), From/branding sane
- [ ] Backups scheduled and a restore tested
- [ ] Error tracking + queue/DB monitoring wired
- [ ] Platform Super Admin credentials rotated from the seeder default
