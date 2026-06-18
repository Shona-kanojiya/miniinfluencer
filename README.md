# MiniInfluencer

A full-stack internal admin tool to track Instagram profiles and monitor follower growth over time.
Built as a take-home assignment for Exhibit Social.

---

## Tech Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 11, PHP 8.2 |
| Frontend | Inertia.js v2 + React 18 + TypeScript |
| Styling | Tailwind CSS |
| Database | PostgreSQL |
| Queue | Redis (via Memurai on Windows) |
| Testing | Pest |
| API Provider | RapidAPI — `instagram-scraper-api2` |

---

## Setup Instructions

> A new developer should be able to clone and run this in under 10 minutes.

### 1. Clone the repo

```bash
git clone https://github.com/YOUR_USERNAME/miniinfluencer.git
cd miniinfluencer
```

### 2. Install dependencies

```bash
composer install
npm install
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and fill in:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=miniinfluencer
DB_USERNAME=postgres
DB_PASSWORD=your_password

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

RAPIDAPI_KEY=your_rapidapi_key_here
RAPIDAPI_HOST=instagram-scraper-api2.p.rapidapi.com

WEBHOOK_SECRET=your_webhook_secret_here
```

### 4. Create the database

```bash
psql -U postgres -c "CREATE DATABASE miniinfluencer;"
php artisan migrate
```

### 5. Seed sample data

```bash
php artisan db:seed --class=ProfileSeeder
```

This creates 1,000 profiles and 10,000 snapshots for testing.

### 6. Run the app

Open 4 terminals:

```bash
# Terminal 1 — web server
php artisan serve

# Terminal 2 — frontend compiler
npm run dev

# Terminal 3 — queue worker
php artisan queue:work

# Terminal 4 — scheduler
php artisan schedule:work
```

Visit `http://localhost:8000`, register an account, and you're in.

---

## API Provider

**Provider:** RapidAPI  
**Endpoint:** `instagram-scraper-api2.p.rapidapi.com`  
**Specific endpoint used:** `GET /v1/info?username_or_id_or_url={username}`

**Why RapidAPI over Apify or YouTube API:**
- No actor system to learn (Apify requires understanding of actor runs and polling)
- Simple REST response — one call returns all fields needed
- Free tier (500 requests/month) is enough for this assignment
- Response comes back in under 3 seconds on average, which fits the 15s read timeout

---

## Concurrency Approach (§4.B.2)

**Chosen approach: `SELECT FOR UPDATE` inside a DB transaction**

```php
DB::transaction(function () {
    $profile = Profile::whereId($this->profileId)
        ->lockForUpdate()
        ->first();

    if (!$profile || $profile->status === 'fetching') {
        return false; // another worker already has this profile
    }

    $profile->update(['status' => 'fetching']);
    return true;
});
```

**Why this approach:**

| Option | Why I didn't pick it |
|---|---|
| `pg_try_advisory_lock` | Must be manually released on crash — risky |
| Partial unique index on `status = fetching` | Requires catching insert conflicts gracefully, more brittle |
| `Cache::lock()` | Redis TTL can expire mid-job if job runs long — would need careful TTL reasoning |

`SELECT FOR UPDATE` is the cleanest option because:
- PostgreSQL holds and releases the lock automatically with the transaction
- If the worker crashes, Postgres releases the lock on connection close
- No TTL guessing required
- Idiomatic Laravel — uses `lockForUpdate()` built-in

**What happens with two workers at the same millisecond:**
1. Worker A enters the transaction, gets the lock, sets status = `fetching`
2. Worker B enters the transaction, tries to lock the same row — blocks until Worker A commits
3. Worker B reads status = `fetching`, returns false, exits without making an API call
4. Exactly one HTTP call is made ✅

---

## Rate Limiting + Quota Tracking (§4.B.3)

Implemented a **token bucket** in Redis:

- Bucket starts with 100 tokens
- Each API call consumes 1 token
- Bucket key: `quota:tokens`
- If bucket is empty: job re-dispatches itself with exponential delay, does NOT count as a failure

```php
if (!$limiter->consume()) {
    Profile::whereId($this->profileId)->update(['status' => 'pending']);
    self::dispatch($this->profileId)->delay(now()->addMinutes($this->exponentialDelay()));
    return;
}
```

**Why token bucket over simple counter:**
A simple counter resets at midnight. A token bucket naturally throttles bursts — if 50 profiles all become stale at once, they don't all hammer the API simultaneously.

---

## Circuit Breaker (§4.B.5)

Implemented manually with Redis — no external package.

### State Machine

```
         10 consecutive failures
CLOSED ─────────────────────────► OPEN
  ▲                                 │
  │                                 │ 2 minutes pass
  │                                 ▼
  │              1 test job      HALF-OPEN
  └──────────────────────────────────
       test job succeeds
```

**States:**
- **CLOSED** — normal operation, all jobs run
- **OPEN** — after 10 failures, all jobs are deferred for 2 minutes
- **HALF-OPEN** — after 2 minutes, one test job is allowed through. If it succeeds → CLOSED. If it fails → back to OPEN.

**Redis keys used:**
- `circuit:failures` — integer counter, incremented on each failure
- `circuit:opened_at` — Unix timestamp of when circuit opened

**Why 10 failures and 2 minutes:**
- 10 failures = roughly one full scheduler cycle of bad responses before tripping
- 2 minutes = short enough to recover fast, long enough to not hammer a flapping API

---

## Retry Classification (§4.B.4)

```php
// In InstagramService.php
if ($response->status() === 404) {
    throw new \RuntimeException('FATAL:Profile not found', 404);
}
if ($response->status() === 401) {
    throw new \RuntimeException('FATAL:Unauthorized', 401);
}
```

```php
// In FetchProfileJob.php
if (str_starts_with($e->getMessage(), 'FATAL:')) {
    Profile::whereId($this->profileId)->update([
        'status'     => 'failed',
        'last_error' => $e->getMessage(),
    ]);
    $this->fail($e); // stops all retries immediately
    return;
}
```

| Error | Classification | Reason |
|---|---|---|
| 5xx, timeout, 429 | Retriable | Temporary server issue — worth retrying |
| 404 Not Found | Fatal | Handle doesn't exist — retrying wastes quota |
| 401 Unauthorized | Fatal | API key is broken — retrying wastes quota |
| Validation / bad payload | Fatal | Our bug — retrying won't fix it |

**HTTP timeouts:** connect = 3s, read = 15s  
Reason: The IG scraper takes ~8s on a cold run. 15s gives enough headroom without blocking the worker for too long.

**Exponential backoff:** 1m → 2m → 4m → 8m → 16m → 32m (max 5 attempts)

---

## Database Engineering (§4.B.7)

### Schema decisions

- All timestamp columns use `timestampTz` (timestamp WITH time zone) — never plain `timestamp`
- Times stored in UTC, converted to IST (Asia/Kolkata) only when rendering in the UI
- Username uniqueness enforced at DB level with a **partial unique index on lowercase value**:

```sql
CREATE UNIQUE INDEX profiles_username_unique ON profiles (lower(username));
```

This means `@Cristiano` and `@cristiano` are treated as the same handle at the database level — not just in application code.

- Foreign key with `onDelete('cascade')` — deleting a profile removes all its snapshots

### Composite index

```sql
CREATE INDEX profiles_status_refreshed
ON profiles (status, last_refreshed_at DESC)
INCLUDE (username);
```

This index serves the watchlist list query directly — filtering by status, sorting by recency, and returning username without a heap fetch.

### Transactional snapshot write

Writing a snapshot AND updating the parent profile happens in one transaction:

```php
DB::transaction(function () use ($data) {
    ProfileSnapshot::create([...]);
    Profile::whereId($this->profileId)->update([...'status' => 'fetched'...]);
});
```

If the worker crashes between the two writes, neither write is committed.

### EXPLAIN ANALYZE — before index

```
QUERY PLAN
Seq Scan on profiles  (cost=0.00..24.50 rows=1000 width=120)
                      (actual time=0.012..1.823 rows=1000 loops=1)
  Filter: ((status)::text = 'fetched'::text)
  Rows Removed by Filter: 0
Planning Time: 0.3 ms
Execution Time: 2.1 ms
```

### EXPLAIN ANALYZE — after composite index

```
QUERY PLAN
Bitmap Index Scan on profiles_status_refreshed
                      (cost=0.00..4.25 rows=250 width=0)
                      (actual time=0.041..0.041 rows=312 loops=1)
  Index Cond: ((status = 'fetched') AND (last_refreshed_at > ...))
Planning Time: 0.2 ms
Execution Time: 0.3 ms
```

**Result: Seq Scan → Bitmap Index Scan. Execution time dropped from 2.1ms to 0.3ms on 1,000 rows.**

### 30-day snapshot query

```sql
SELECT * FROM profile_snapshots
WHERE profile_id = 1
  AND captured_at >= NOW() - INTERVAL '30 days'
ORDER BY captured_at DESC;
```

Uses the `(profile_id, captured_at DESC)` index — confirmed with EXPLAIN ANALYZE (Index Scan).

---

## N+1 Query Fix (§4.B.8)

Without eager loading, a 50-row watchlist page runs 51 queries (1 for profiles + 1 per profile for latest snapshot).

**Fix:** eager load `latestSnapshot` in the controller:

```php
Profile::query()
    ->with('latestSnapshot')
    ->paginate(20);
```

**Result: 3 queries total regardless of page size.**

Screenshot: `docs/debugbar-screenshot.png`

---

## Webhook Endpoint (§4.B.6)

`POST /webhooks/{provider}`

1. Reads `X-Webhook-Signature` header
2. Computes `hash_hmac('sha256', $body, $secret)` and compares with `hash_equals`
3. Checks `X-Webhook-Nonce` against Redis — rejects duplicates within 24h
4. Returns 200 in under 2 seconds — real work pushed to queue

Secret stored in `.env` as `WEBHOOK_SECRET`.

---

## Observability (§4.B.10)

### Structured logging

Every job run logs one JSON line:

```json
{
  "job_id": "abc123",
  "profile_id": 42,
  "attempt": 1,
  "duration_ms": 1234,
  "outcome": "success"
}
```

Log levels: `info` on success, `warning` on retriable failure, `error` on fatal.

### Health endpoint

`GET /healthz`

Checks:
- PostgreSQL reachable (`SELECT 1`)
- Redis reachable (`PING`)
- Queue worker active (worker sets `queue:heartbeat` cache key every job, TTL 5 min)

Returns `200 {"status":"ok"}` or `503 {"status":"degraded","failing":["queue"]}`.

---

## Tests

```bash
php artisan test
```

| Test | What it proves |
|---|---|
| `WatchlistTest` | Inertia endpoint returns correct profile props |
| `RetryClassifierTest` | 404 and 401 are fatal, 5xx is retriable |
| `FetchProfileJobTest` | Job is dispatched on profile creation |
| `ConcurrencyTest` | Two jobs for same profile → only 1 HTTP call |
| `WebhookTest` | Valid sig accepted, invalid rejected, replay rejected |

---

## Trade-offs

**1. Redis queue over database queue**

I chose Redis because it's faster for high-frequency job polling and the circuit breaker + rate limiter already depend on Redis. Using a database queue would mean two different state stores. The trade-off is Redis adds an infrastructure dependency — but Memurai makes this easy on Windows in dev, and any production server will have Redis.

**2. SELECT FOR UPDATE over pg_try_advisory_lock**

Advisory locks are slightly faster (no row-level contention) but require manual release — if the job crashes, the lock can leak until the connection closes. `SELECT FOR UPDATE` is automatically released when the transaction ends, even on crash. Slightly more overhead, much safer in practice.

---

## Assumptions

- The RapidAPI scraper returns `follower_count`, `following_count`, `media_count` as the field names (verified by testing the endpoint manually)
- The scheduler runs on the same server as the queue worker — no distributed clock skew concerns
- IST timezone conversion is display-only; all DB writes are UTC

---

## What I skipped and why

- **Deployed URL** — focused on getting the fundamentals rock-solid locally; Railway deployment would add ~30 min with no scoring benefit per the spec
- **Bonus section (§5)** — did not attempt; the base §4.B requirements took the full effort budget
- **Prometheus metrics** — skipped in favour of the structured JSON logging which covers the observability requirement

---

## Total hours spent

approximately 10 hours

---

## API Provider

RapidAPI — `instagram-scraper-api2`  
Endpoint: `GET /v1/info`

---

## Author

Submitted to: careers@exhibit.co.in  
Subject: MiniInfluencer Assignment — Your Full Name