# Database Setup

## Quick Start

```bash
# 1. Start MySQL (first time will auto-init schema + seed)
docker compose up -d db

# 2. Wait for MySQL to be healthy (~15-30 seconds)
docker compose ps  # check db is "healthy"

# 3. Start the full app
docker compose up -d

# 4. Open
open http://localhost:8080
```

## First Run

On first `docker compose up -d db`, MySQL will automatically:
1. Create the `iq_creative` database
2. Run `database/schema.sql` (all tables)
3. Run `database/seed.sql` (demo data)

This only happens once. Subsequent restarts skip init (data persists in `mysql_data` volume).

## Reset Database

To start fresh:

```bash
docker compose down -v   # -v removes the volume
docker compose up -d db
```

## Demo Credentials

### User Account
- **Email:** `demo@example.local`
- **Password:** `Demo1234`
- **Class:** 1-2

### Admin Account
- **URL:** `http://localhost:8080/admin/`
- **Username:** `admin`
- **Password:** `admin123`

## Demo Users

| Email | Class | Points |
|-------|-------|--------|
| demo@example.local | 1-2 | 120 |
| demo34@example.local | 3-4 | 85 |
| demo56@example.local | 5-6 | 210 |
| demo78@example.local | 7-8 | 55 |
| demo911@example.local | 9-11 | 340 |
| demo14course@example.local | 1-4 | 0 |

All passwords: `Demo1234`

## Manual SQL Access

```bash
# Connect to MySQL
docker compose exec db mysql -u iq_user -p'change_this_local_password' iq_creative

# Run a query
docker compose exec db mysql -u iq_user -p'change_this_local_password' iq_creative -e "SELECT * FROM users;"
```
