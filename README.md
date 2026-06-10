# Church Ministry Platform — Kenya
## Backend API (Laravel 11)

---

## Architecture Overview

```
Ministry (Top Level)
  └── Zone (Regional)
        └── Church (Congregation)
              └── Members / Transactions
```

### Admin Roles & Access Scope
| Role           | Access Scope                             |
|----------------|------------------------------------------|
| MinistryAdmin  | Full platform — all zones & churches     |
| ZoneAdmin      | Their zone and all churches within it    |
| ChurchAdmin    | Their church only                        |

---

## Tech Stack
- **PHP** 8.2+
- **Laravel** 11
- **Laravel Sanctum** (API token auth)
- **MySQL** 8.0+
- **Custom RBAC** (Roles + Permissions)

---

## Default Credentials (Seeded)

| Role          | Email                      | Password        |
|---------------|----------------------------|-----------------|
| MinistryAdmin | admin@ministry.ke          | Admin@Kenya2024 |
| ZoneAdmin     | zone@ministry.ke           | Zone@Kenya2024  |
| ChurchAdmin   | church@ministry.ke         | Church@Kenya2024|

---

## Quick Setup

```bash
# 1. Create new Laravel project
composer create-project laravel/laravel church-ministry-api
cd church-ministry-api

# 2. Install dependencies
composer require laravel/sanctum

# 3. Copy this repository's files over the Laravel project
cp -r /path/to/this/repo/* ./

# 4. Configure your .env
cp .env.example .env
# Edit .env: DB_DATABASE, DB_USERNAME, DB_PASSWORD

# 5. Generate app key
php artisan key:generate

# 6. Run migrations + seed
php artisan migrate --seed

# 7. Serve
php artisan serve
```

---

## API Endpoints Summary

### Authentication
```
POST   /api/auth/login
POST   /api/auth/logout
GET    /api/auth/me
PUT    /api/auth/profile
PUT    /api/auth/change-password
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
POST   /api/auth/refresh
```

### User Management  *(MinistryAdmin only)*
```
GET    /api/users
POST   /api/users
GET    /api/users/{id}
PUT    /api/users/{id}
DELETE /api/users/{id}
```

### Ministry Profile  *(MinistryAdmin)*
```
GET    /api/ministry
PUT    /api/ministry
```

### Zones  *(MinistryAdmin full CRUD; ZoneAdmin view)*
```
GET    /api/zones
POST   /api/zones
GET    /api/zones/{id}
PUT    /api/zones/{id}
DELETE /api/zones/{id}
```

### Churches  *(MinistryAdmin full; ZoneAdmin zone churches; ChurchAdmin own)*
```
GET    /api/churches
POST   /api/churches
GET    /api/churches/{id}
PUT    /api/churches/{id}
DELETE /api/churches/{id}
```

### Members
```
GET    /api/members
POST   /api/members
GET    /api/members/{id}
PUT    /api/members/{id}
DELETE /api/members/{id}
```

### Transaction Types
```
GET    /api/transaction-types
POST   /api/transaction-types
PUT    /api/transaction-types/{id}
DELETE /api/transaction-types/{id}
```

### Transactions (Offerings, Donations, Tithes, etc.)
```
GET    /api/transactions
POST   /api/transactions          ← manual entry form
GET    /api/transactions/{id}
PUT    /api/transactions/{id}
DELETE /api/transactions/{id}
POST   /api/transactions/{id}/verify
GET    /api/transactions/export
```

### Analytics — Church
```
GET    /api/analytics/church/summary
GET    /api/analytics/church/by-type
GET    /api/analytics/church/trend
GET    /api/analytics/church/top-members
```

### Analytics — Zone  *(ZoneAdmin + MinistryAdmin)*
```
GET    /api/analytics/zone/summary
GET    /api/analytics/zone/churches-comparison
GET    /api/analytics/zone/trend
```

### Analytics — Ministry  *(MinistryAdmin only)*
```
GET    /api/analytics/ministry/overview
GET    /api/analytics/ministry/zones-comparison
GET    /api/analytics/ministry/trend
GET    /api/analytics/ministry/top-churches
```

### Activity Logs
```
GET    /api/activity-logs           ← scoped by role
GET    /api/activity-logs/stats
```

---

## Middleware Stack

| Middleware          | Purpose                                            |
|---------------------|----------------------------------------------------|
| `auth:sanctum`      | Validates Bearer token                             |
| `ministry.admin`    | Restricts to MinistryAdmin role only               |
| `zone.admin`        | Requires ZoneAdmin or above                        |
| `church.admin`      | Requires any admin role                            |
| `activity.logger`   | Logs every mutating request (POST/PUT/DELETE/PATCH)|
| `rate.limit`        | Custom per-route rate limiting                     |
| `cache.response`    | Caches GET responses (analytics endpoints)         |

---

## Money/Transaction Form Fields

```json
{
  "church_id": 1,
  "transaction_type_id": 2,
  "amount": 5000.00,
  "currency": "KES",
  "transaction_date": "2024-01-14",
  "service_type": "sunday_morning",
  "member_id": null,
  "reference_number": "TXN-2024-001",
  "description": "Sunday morning offering",
  "notes": "Counted by deacon team"
}
```

---

## Analytics Query Params

All analytics endpoints accept:
- `?period=30` — number of days (default 30)
- `?from=2024-01-01&to=2024-01-31` — date range
- `?group_by=week|month|day` — aggregation level
- `?church_id=X` — filter (zone/ministry admins)
- `?zone_id=X` — filter (ministry admin)
- `?type=offering|tithe|donation|...` — filter by money type
