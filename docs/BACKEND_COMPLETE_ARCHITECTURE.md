# 🏗️ FitCore SaaS — Master Backend Architecture Specification

**Document Version:** 4.0  
**Target Repository:** `GYM_SAAS` (Laravel 11 / PHP 8.2+)  
**Scope:** Complete Backend Architecture, Multi-Tenant Database Sharding, Security & Data Isolation, Business Rules Engine, and Master API Index across all 3 Portals.

---

## 📌 1. Executive System Architecture

FitCore is built on a **3-Tier Multi-Tenant Architecture** separating System Administration, Partner Licensing, and Tenant Gym Operations.

```
┌─────────────────────────────────────────────────────────────────────────────────────────┐
│                                   FITCORE SAAS PLATFORM                                 │
└─────────────────────────────────────────────────────────────────────────────────────────┘
        │                                    │                                    │
        ▼                                    ▼                                    ▼
┌───────────────────────┐            ┌───────────────────────┐            ┌───────────────────────┐
│   DEVELOPER PORTAL    │            │    PARTNER PORTAL     │            │  GYM INSTANCE PORTAL  │
│  admin.fitcore.io     │            │  partner.fitcore.io   │            │  {slug}.fitcore.io    │
├───────────────────────┤            ├───────────────────────┤            ├───────────────────────┤
│ • Platform Super Admin│            │ • Reseller/Franchise  │            │ • Gym Owners & Staff  │
│ • Shard Provisioning  │            │ • License Allocation  │            │ • Multi-Branch Mgmt   │
│ • Partner Management  │            │ • Gym Onboarding      │            │ • Member Operations   │
│ • System Audit Logs   │            │ • Quota Analytics     │            │ • Branch Financial P&L│
└───────────────────────┘            └───────────────────────┘            └───────────────────────┘
        │                                    │                                    │
        └────────────────────────────────────┼────────────────────────────────────┘
                                             ▼
                             ┌──────────────────────────────┐
                             │    LARAVEL 11 BACKEND API    │
                             │     (/api/v1/{portal}/*)     │
                             └──────────────────────────────┘
```

---

## 🗄️ 2. Database Architecture & Multi-Tenant Shard Pool Model

FitCore uses a **Hybrid Centralized Master + Dynamic Shard Database Pool** model to ensure horizontal scalability up to thousands of gym locations.

```
                              ┌───────────────────────────────────┐
                              │     fitcore_master DATABASE       │
                              │  (Central System & Metadata)      │
                              ├───────────────────────────────────┤
                              │ • developers                      │
                              │ • partners                        │
                              │ • tenants                         │
                              │ • shards                          │
                              │ • audit_logs                      │
                              └───────────────────────────────────┘
                                                │
                 ┌──────────────────────────────┼──────────────────────────────┐
                 ▼                              ▼                              ▼
  ┌──────────────────────────────┐ ┌──────────────────────────────┐ ┌──────────────────────────────┐
  │      fitcore_shard_01        │ │      fitcore_shard_02        │ │      fitcore_shard_03        │
  │  (Tenant Operational Data)   │ │  (Tenant Operational Data)   │ │  (Tenant Operational Data)   │
  ├──────────────────────────────┤ ├──────────────────────────────┤ ├──────────────────────────────┤
  │ • branches                   │ │ • branches                   │ │ • branches                   │
  │ • staff                      │ │ • staff                      │ │ • staff                      │
  │ • members                    │ │ • members                    │ │ • members                    │
  │ • memberships                │ │ • memberships                │ │ • memberships                │
  │ • attendance                 │ │ • attendance                 │ │ • attendance                 │
  │ • payments                   │ │ • payments                   │ │ • payments                   │
  │ • gym_configs                │ │ • gym_configs                │ │ • gym_configs                │
  └──────────────────────────────┘ └──────────────────────────────┘ └──────────────────────────────┘
```

### 2.1 Central Master Database (`fitcore_master`)
- **`developers`**: Stores system super-admin credentials (`role`, `email`, `password`, `is_active`).
- **`partners`**: Stores reseller / franchisee accounts (`partner_type`, `gym_quota`, `gyms_created`, `status`).
- **`tenants`**: Global tenant registry mapping every gym tenant to its assigned database shard (`slug`, `partner_id`, `shard_id`, `plan_tier`, `status`).
- **`shards`**: Shard pool directory tracking shard database host, credentials, and capacity (`db_name`, `max_tenants`, `current_tenants`, `is_active`).
- **`audit_logs`**: Immutable system-wide audit event ledger (`actor_type`, `action`, `target_type`, `payload`, `ip_address`).

### 2.2 Dynamic Shard Pool Databases (`fitcore_shard_XX`)
- Each shard database hosts up to 20 gym tenants (configurable via `max_tenants`).
- Tables (`branches`, `staff`, `members`, `payments`, etc.) contain a mandatory `tenant_id` column.
- **Automated Shard Provisioning (`ShardRouter::getAvailableShard`)**:
  1. Looks for an active shard where `current_tenants < max_tenants`.
  2. If all existing shards are full, automatically executes `CREATE DATABASE fitcore_shard_XX` and runs shard migrations (`database/migrations/shard`).

---

## 🛡️ 3. Security & Data Isolation Architecture (3 Layers)

To guarantee 100% complete data isolation with zero possibility of cross-tenant data leakage, FitCore enforces 3 independent layers of defense:

### Layer 1: Middleware & Dynamic Connection Purging (`TenantResolutionMiddleware`)
1. Resolves target tenant slug from `X-Tenant-Slug` header or HTTP host subdomain (`{slug}.fitcore.io`).
2. Queries `fitcore_master.tenants` to resolve target tenant and its assigned `shard_id`.
3. Reconfigures `'database.connections.tenant'` dynamically and executes **`DB::purge('tenant')`** to purge previous PDO connections and isolate database execution.

### Layer 2: Eloquent Global Query Scopes (`TenantModel` & `Staff`)
All shard models extend `App\Models\Shard\TenantModel` or register global query scopes:
```php
static::addGlobalScope('tenant_isolation', function (Builder $builder) {
    if (app()->bound('tenant')) {
        $tenant = app('tenant');
        $tenantId = method_exists($tenant, 'getNumericId') ? $tenant->getNumericId() : ($tenant->id ?? $tenant->getKey());
        if ($tenantId) {
            $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
        }
    }
});
```
Every query automatically appends `WHERE table.tenant_id = $currentTenantId`.

### Layer 3: Controller-Level Scoping & Hashed Identifiers
- Direct database queries explicitly enforce `WHERE tenant_id = $tenantId`.
- Integer IDs exposed in API responses are obfuscated with `Hashids` (e.g. `TNT-N686AF`, `PRT-8821AF`), preventing IDOR enumeration attacks.

---

## ⚙️ 4. Business Logic & Rules Engine

### Rule 1: Strict Pre-Suspension Safeguard
- **Enforcement**: [`PartnerController::updateStatus`](file:///d:/Startup/GYM_SAAS/app/Http/Controllers/Developer/PartnerController.php#L220)
- Suspending a Partner account (`PATCH /partners/{id}/status` -> `status: "suspended"`) is **STRICTLY BLOCKED** if `gyms_created > 0`.
- Returns `422 Unprocessable Entity`: Requiring gym tenants to be reassigned first.

### Rule 2: Zero-Downtime Gym Reassignment
- **Enforcement**: [`PartnerController::reassignTenants`](file:///d:/Startup/GYM_SAAS/app/Http/Controllers/Developer/PartnerController.php#L261)
- Reassigns gym ownership from one Partner to another by updating `partner_id` in `fitcore_master.tenants`.
- Operational data on `fitcore_shard_XX` remains completely untouched with **zero downtime**.

### Rule 3: Plan Tier Physical Branch Limits
- **Enforcement**: [`BranchController::store`](file:///d:/Startup/GYM_SAAS/app/Http/Controllers/Gym/BranchController.php#L101) & `BranchQuotaGuard`
- `Basic` Plan ➡️ Max **1 Branch**
- `Pro` Plan ➡️ Max **3 Branches**
- `Enterprise` Plan ➡️ Max **999 (Unlimited) Branches**
- Rejects additional branch creation with `422 Unprocessable Entity`.

### Rule 4: Partner Licensing Quota Guard
- **Enforcement**: [`PartnerGymController::store`](file:///d:/Startup/GYM_SAAS/app/Http/Controllers/Partner/PartnerGymController.php#L75)
- Rejects new gym tenant provisioning if `gyms_created >= gym_quota`.

---

## 📡 5. Master API Endpoint Index

### 5.1 Developer Portal APIs (`/api/v1/developer/*`)
| HTTP Method | Endpoint Path | Controller & Action | Description |
|---|---|---|---|
| `POST` | `/auth/login` | `AuthController@login` | Developer Super Admin Login |
| `GET` | `/auth/me` | `AuthController@me` | Get Developer Profile |
| `POST` | `/auth/refresh` | `AuthController@refresh` | Refresh Bearer Token |
| `POST` | `/auth/logout` | `AuthController@logout` | Logout Developer |
| `GET` | `/analytics` | `AnalyticsController@index` | Dashboard System Metrics |
| `GET` | `/partners` | `PartnerController@index` | List All Partners (search, filter) |
| `POST` | `/partners` | `PartnerController@store` | Create Partner Account |
| `GET` | `/partners/{id}` | `PartnerController@show` | Get Single Partner Details |
| `PATCH` | `/partners/{id}/quota` | `PartnerController@updateQuota` | Update Gym Quota Limit |
| `PATCH` | `/partners/{id}/status` | `PartnerController@updateStatus` | Suspend/Activate Partner (Pre-Suspension Guard) |
| `POST` | `/partners/{id}/reassign-tenants` | `PartnerController@reassignTenants` | Reassign Gym Tenants |
| `GET` | `/shards` | `ShardController@index` | List Shards & Capacities |
| `POST` | `/shards` | `ShardController@store` | Provision New Database Shard |
| `PATCH` | `/shards/{id}/capacity` | `ShardController@updateCapacity` | Update Shard Max Capacity |
| `GET` | `/tenants` | `TenantController@index` | List System Gym Tenants |
| `GET` | `/tenants/{id}` | `TenantController@show` | Get Single Gym Tenant Details |
| `PATCH` | `/tenants/{id}/status` | `TenantController@updateStatus` | Suspend or Activate Gym Tenant |
| `GET` | `/audit-logs` | `AuditLogController@index` | System-Wide Audit Log Inspector |

### 5.2 Partner Portal APIs (`/api/v1/partner/*`)
| HTTP Method | Endpoint Path | Controller & Action | Description |
|---|---|---|---|
| `POST` | `/auth/login` | `AuthController@login` | Partner Login |
| `GET` | `/auth/me` | `AuthController@me` | Get Partner Profile |
| `POST` | `/auth/refresh` | `AuthController@refresh` | Refresh Partner Token |
| `POST` | `/auth/logout` | `AuthController@logout` | Logout Partner |
| `GET` | `/dashboard` | `PartnerDashboardController@index` | Partner Quota & Gym Analytics |
| `GET` | `/gyms` | `PartnerGymController@index` | List Managed Gym Tenants |
| `POST` | `/gyms` | `PartnerGymController@store` | Automated Gym Provisioning Wizard |
| `GET` | `/gyms/{id}` | `PartnerGymController@show` | Get Single Gym Tenant Details |

### 5.3 Gym Instance Portal APIs (`/api/v1/gym/*`)
| HTTP Method | Endpoint Path | Controller & Action | Description |
|---|---|---|---|
| `POST` | `/auth/login` | `AuthController@login` | Gym Owner / Staff Login |
| `GET` | `/auth/me` | `AuthController@me` | Get Staff Profile & Tenant Context |
| `POST` | `/auth/refresh` | `AuthController@refresh` | Refresh Gym Token |
| `POST` | `/auth/logout` | `AuthController@logout` | Logout Staff |
| `GET` | `/dashboard` | `GymDashboardController@index` | Gym Tenant KPI Summary |
| `GET` | `/branches` | `BranchController@index` | List Branches & Quota Info |
| `POST` | `/branches` | `BranchController@store` | Create Physical Branch (Plan Guarded) |
| `POST` | `/branches/switch` | `BranchController@switchContext` | Active Branch Switcher |
| `GET` | `/branches/{id}` | `BranchController@show` | Get Single Branch Details |
| `PUT/PATCH` | `/branches/{id}` | `BranchController@update` | Update Branch Details |
| `DELETE` | `/branches/{id}` | `BranchController@destroy` | Delete Secondary Branch |
| `GET` | `/branches/{id}/financials` | `BranchController@financials` | Dedicated Branch P&L Financials |

---

### 📚 Detailed Module Specifications & Roadmaps
- 🏋️ [Gym Operations Module Architecture & Roadmap](file:///d:/Startup/GYM_SAAS/docs/Gym_Operations_Module_Architecture.md) (Members, Memberships, Attendance & RBAC)

---
*FitCore SaaS Engineering Team*

