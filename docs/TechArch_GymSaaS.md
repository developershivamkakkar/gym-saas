# Technical Architecture & Database Design Document
## FitCore — SaaS Gym Management System

**Document Version:** 1.3  
**Date:** August 3, 2026  
**Tech Stack:** Laravel 13 (API) + React + Vite (Frontend) + VPS + Redis (Cache & Queue)  
**Scale Target:** 5,000 Gyms  
**Status:** Fully Approved ✅ — Redis Caching Integrated

---

## Table of Contents

1. [System Overview — Three Portal Architecture](#1-system-overview)
2. [Database Strategy Analysis](#2-database-strategy-analysis)
3. [Recommended: Configurable Shard-Pool Architecture](#3-recommended-shard-pool-architecture)
4. [Master Database Schema](#4-master-database-schema)
5. [Shard Database Schema](#5-shard-database-schema)
6. [Tenant Routing Flow (Laravel)](#6-tenant-routing-flow-laravel)
7. [VPS Deployment Architecture](#7-vps-deployment-architecture)
8. [Portal-Wise API Structure](#8-portal-wise-api-structure)
9. [Onboarding Flow (End to End)](#9-onboarding-flow)
10. [Open Questions for Decision](#10-open-questions)
11. [Future Phase: WhatsApp API + AI Integration](#11-future-phase-whatsapp--ai)

---

## 1. System Overview

### 1.1 Three Portal Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                     FITCORE PLATFORM                            │
│                                                                 │
│  ┌─────────────────┐    ┌─────────────────┐                    │
│  │ DEVELOPER PORTAL│    │  PARTNER PORTAL  │                    │
│  │ admin.fitcore.io│    │partner.fitcore.io│                    │
│  │                 │    │                  │                    │
│  │ • Create Partners    │ • Create Gyms    │                    │
│  │ • Assign Quotas │    │ • View Gym List   │                    │
│  │ • Manage Shards │    │ • Track Usage    │                    │
│  │ • Platform Stats│    │ • Manage Subs    │                    │
│  │ • Audit Logs    │    │   (manual P1)    │                    │
│  │ • Suspend Prtnr │    │                  │                    │
│  │ • Platform Cfg  │    │                  │                    │
│  └────────┬────────┘    └────────┬─────────┘                    │
│           │                     │                               │
│           └──────────┬──────────┘                               │
│                      ▼                                          │
│             ┌─────────────────┐                                 │
│             │  MASTER DATABASE│  (Central Registry)             │
│             │  fitcore_master  │                                 │
│             └────────┬────────┘                                 │
│                      │  tenant lookup → shard assignment        │
│                      ▼                                          │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │               GYM INSTANCES (Subdomains)                 │  │
│  │                                                          │  │
│  │  alphagym.fitcore.io    powerhouse.fitcore.io  ...5000   │  │
│  │  ┌──────────────────┐  ┌──────────────────┐             │  │
│  │  │ White-Label Logo │  │ White-Label Logo  │             │  │
│  │  │ Gym Dashboard    │  │ Gym Dashboard     │             │  │
│  │  │ Member Mgmt      │  │ Member Mgmt       │             │  │
│  │  │ Attendance       │  │ Attendance        │             │  │
│  │  │ Billing          │  │ Billing           │             │  │
│  │  └──────────────────┘  └──────────────────┘             │  │
│  └──────────────────────────────────────────────────────────┘  │
│                      │                                          │
│         ┌────────────┴────────────┐                             │
│         ▼                         ▼                             │
│   ┌───────────┐             ┌───────────┐                       │
│   │  SHARD DB │             │  SHARD DB │   ... up to N shards  │
│   │  shard_01 │             │  shard_02 │                       │
│   │ (50 gyms) │             │ (50 gyms) │                       │
│   └───────────┘             └───────────┘                       │
└─────────────────────────────────────────────────────────────────┘
```

### 1.2 Portal Responsibilities

| Portal | URL | Users | Core Purpose |
|---|---|---|---|
| **Developer Portal** | `admin.fitcore.io` | FitCore internal team | Platform-level control |
| **Partner Portal** | `partner.fitcore.io` | Gym resellers / franchises | Gym lifecycle management |
| **Gym Instance** | `{slug}.fitcore.io` | Gym owner, staff, trainers | Daily gym operations |

### 1.3 Developer Portal — Detailed Features

| # | Feature | Description |
|---|---|---|
| 1 | Create Partner | Register a new partner with gym quota assignment |
| 2 | Assign Quota | Set/update how many gyms a partner can create |
| 3 | Suspend Partner | Suspend partner access + freeze all their gyms |
| 4 | Manage Shards | Provision new shards, view usage, change pool size |
| 5 | View All Tenants | See every gym across all partners |
| 6 | Platform Analytics | MRR, total gyms, growth charts |
| 7 | Audit Logs | Every action logged with actor, timestamp, IP |
| 8 | Platform Settings | Configure global settings (trial days, defaults) |
| 9 | Feature Flags | Toggle features per tenant or plan |

### 1.4 Partner Portal — Detailed Features

| # | Feature | Description |
|---|---|---|
| 1 | Create Gym | Provision a new gym instance (uses quota) |
| 2 | View Gym List | List all their gyms with status + usage |
| 3 | View Usage | Member count, storage usage per gym |
| 4 | Manage Subscription | Manually set plan, dates, renew (Phase 1) |
| 5 | Suspend Gym | Temporarily pause a gym |
| 6 | View Quota | See gyms_created vs gym_quota |

---

## 2. Database Strategy Analysis

> Four approaches evaluated against 5,000 gym scale target.

### Option A — Single Shared DB (Shared Schema)

```
All 5000 gyms → same DB → same tables → tenant_id column
```

| Criteria | Rating | Notes |
|---|:---:|---|
| Simplicity | ⭐⭐⭐⭐⭐ | Easiest to build |
| Data Isolation | ⭐ | One bug = all tenants exposed |
| Performance at 5000 gyms | ⭐⭐ | Single DB bottleneck |
| Operational risk | ❌ HIGH | |

**Verdict: ❌ Not recommended for commercial SaaS.**

---

### Option B — Database per Tenant (5000 Databases)

```
Gym A → db_gym_001 | Gym B → db_gym_002 | ... × 5000
```

| Criteria | Rating | Notes |
|---|:---:|---|
| Data Isolation | ⭐⭐⭐⭐⭐ | Perfect |
| Migrations | ⭐ | Running migrations on 5000 DBs |
| Cost | ⭐ | 5000 DB connections = huge memory |
| Ops Complexity | ❌ VERY HIGH | |

**Verdict: ❌ Operationally infeasible on VPS at this scale.**

---

### Option C — Schema per Tenant (PostgreSQL)

```
Single PG server → schema per tenant (gym_alpha.members, gym_beta.members)
```

| Criteria | Rating | Notes |
|---|:---:|---|
| Isolation | ⭐⭐⭐⭐ | Good |
| MySQL Support | ❌ | MySQL doesn't support schemas this way |

**Verdict: ❌ Incompatible with your MySQL/Laravel stack.**

---

### ✅ Option D — Configurable Shard Pool (RECOMMENDED)

```
Master DB → tenant registry (gym → shard mapping)
Shard 01  → Gym 001 – 050  (all with tenant_id column)
Shard 02  → Gym 051 – 100
...
Shard 100 → Gym 4951 – 5000
```

| Criteria | Rating | Notes |
|---|:---:|---|
| Simplicity | ⭐⭐⭐⭐ | Manageable complexity |
| Data Isolation | ⭐⭐⭐⭐ | Limited blast radius per shard |
| Performance | ⭐⭐⭐⭐⭐ | Load distributed across shards |
| Scalability to 5000 | ⭐⭐⭐⭐⭐ | 100 shards × 50 gyms = 5000 ✅ |
| VPS Friendly | ✅ | Add VPS servers as shards grow |
| Migrations | ⭐⭐⭐⭐ | Per shard — manageable |
| Cost | ⭐⭐⭐⭐ | Add infrastructure progressively |

**Verdict: ✅ BEST FIT — This is the architecture used by Shopify, Notion, and major SaaS platforms.**

---

## 3. Recommended: Configurable Shard-Pool Architecture

---

### 🟦 What is a Shard? (Simple Explanation)

> Think of it like this:

**Imagine a filing cabinet for gym records.**

```
❌ PROBLEM — One giant cabinet for 5000 gyms:
┌───────────────────────────────────┐
│   ONE BIG DATABASE                │
│   All 5000 gyms mixed together    │
│   → Gets too heavy, too slow      │
│   → One fire = everything lost    │
└───────────────────────────────────┘

✅ SOLUTION — Many smaller cabinets (Shards):
┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│  CABINET 1   │  │  CABINET 2   │  │  CABINET 3   │
│  (Shard 01)  │  │  (Shard 02)  │  │  (Shard 03)  │
│  Gym 01–20   │  │  Gym 21–40   │  │  Gym 41–60   │
└──────────────┘  └──────────────┘  └──────────────┘
→ Each cabinet is lightweight and fast
→ If one cabinet has a problem, only 20 gyms are affected
→ You can always add more cabinets as you grow
```

**In technical terms:**

| Plain English | Technical Term |
|---|---|
| One smaller database holding a group of gyms | **Shard** |
| How many gyms fit in one shard | **Pool Size** (default = 20) |
| The central registry that knows which gym is in which shard | **Master DB** |
| The process of reading the registry and connecting to the right shard | **Tenant Routing** |
| Adding a brand new empty shard when the existing ones are full | **Provisioning a Shard** |

**In FitCore:**
- Each **shard** is a separate MySQL database (e.g., `fitcore_shard_01`)
- By default, **20 gyms** share one shard (configurable from Developer Portal)
- For **5,000 gyms**, you need approximately **250 shards**
- All shard databases have the **exact same table structure** — only the data inside differs
- The **Master DB** is the directory — it maps each gym to its shard

---

### 3.1 How It Works (Request Flow)

```
Request: alphagym.fitcore.io/api/members
         │
         ▼
[TenantResolutionMiddleware]
   Extract slug → "alphagym"
         │
         ▼
[Redis Cache Check]
   Key: "tenant:slug:alphagym" (TTL 5 min)
         │
   Cache MISS → Query Master DB
   SELECT t.id, t.shard_id, s.host, s.database_name
   FROM tenants t JOIN shards s ON t.shard_id = s.id
   WHERE t.slug = 'alphagym'
         │
         ▼
[Validate tenant active / not suspended]
         │
         ▼
[Set Dynamic Laravel DB Connection]
   config(['database.connections.tenant' => [
       'host' => $shard->host,
       'database' => $shard->database_name,
       ...
   ]])
         │
         ▼
[Execute Query with tenant_id scope]
   DB::connection('tenant')
      ->table('members')
      ->where('tenant_id', 42)
      ->get()
         │
         ▼
   ✅ Response (strictly isolated to this gym)
```

### 3.2 Shard Sizing Strategy (Growth Plan)

| Stage | Gyms | Shards | Gyms/Shard | DB Servers |
|---|---|---|---|---|
| **Launch** | 0 – 500 | 25 | **20** | 1 VPS (shared with app) |
| **Growth** | 500 – 2,000 | 100 | **20** | 2 VPS |
| **Scale** | 2,000 – 5,000 | 250 | **20** | 4 VPS |
| **Enterprise** | 5,000+ | Dynamic | Configurable via Portal | Scale out |

> **Default pool size = 20 gyms/shard.** Changeable anytime from Developer Portal (`shards.max_tenants`) without code changes.

> **Key**: The `shards.max_tenants` column is configurable from the Developer Portal — no code changes needed to resize pools.

### 3.3 New Gym Provisioning Logic

```
Partner clicks "Create Gym"
         │
         ▼
[Backend: TenantProvisioningService]
1. Check partner.gyms_created < partner.gym_quota
2. Find shard: status='active' AND current_count < max_tenants
3. If no shard available → Alert developer (send notification)
4. INSERT into master.tenants (with found shard_id)
5. Increment shards.current_tenant_count
6. Increment partners.gyms_created
7. INSERT gym_config into shard DB (fitcore_shard_XX)
8. Log to audit_logs
9. Send welcome email to gym owner
         │
         ▼
   Gym is LIVE at {slug}.fitcore.io
```

### 3.4 Safety: Blast Radius Per Shard

> **"Blast Radius"** means: if something goes wrong, how many gyms are affected?

```
❌ If we used ONE shared DB (bad approach):
   DB goes down  →  ALL 5000 gyms offline at once
   DB gets hacked →  ALL 5000 gyms' data exposed

✅ With Shard Pool (our approach — 20 gyms per shard):
   1 shard goes down →  Only 20 gyms affected (not 5000)
   1 shard gets hacked → Only 20 gyms' data exposed (not 5000)
```

**Real-world analogy:** It's like the difference between one big power grid vs. neighbourhood-level circuits. If one neighbourhood circuit trips, only that area loses power — not the whole city.

> With 20 gyms per shard: **worst case = 20 gyms impacted. Best case = 1.**

---

## 4. Master Database Schema

> **DB Name:** `fitcore_master`  
> **Purpose:** Central registry, routing, partner/developer management  
> **Location:** Dedicated primary DB server

---

### 4.1 `developers`

```sql
CREATE TABLE developers (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) UNIQUE NOT NULL,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('super_admin', 'admin', 'viewer') DEFAULT 'admin',
    is_active       BOOLEAN DEFAULT TRUE,
    last_login_at   TIMESTAMP NULL,
    two_fa_secret   VARCHAR(255) NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

### 4.2 `partners`

```sql
CREATE TABLE partners (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(150) NOT NULL,
    email               VARCHAR(150) UNIQUE NOT NULL,
    phone               VARCHAR(20) NULL,
    password            VARCHAR(255) NOT NULL,
    company_name        VARCHAR(200) NULL,
    country             VARCHAR(100) DEFAULT 'India',
    city                VARCHAR(100) NULL,
    gym_quota           INT UNSIGNED DEFAULT 10,
    gyms_created        INT UNSIGNED DEFAULT 0,
    status              ENUM('active','suspended','pending','inactive') DEFAULT 'pending',
    suspended_at        TIMESTAMP NULL,
    suspended_reason    TEXT NULL,
    suspended_by        BIGINT UNSIGNED NULL,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (suspended_by) REFERENCES developers(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES developers(id)
);
```

---

### 4.3 `shards` (The Core of Pooled Architecture)

```sql
CREATE TABLE shards (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                    VARCHAR(50) UNIQUE NOT NULL,     -- 'shard_01'
    host                    VARCHAR(255) NOT NULL,           -- DB server IP
    port                    SMALLINT UNSIGNED DEFAULT 3306,
    database_name           VARCHAR(100) NOT NULL,           -- 'fitcore_shard_01'
    username                VARCHAR(100) NOT NULL,
    password_encrypted      TEXT NOT NULL,                   -- AES-256 encrypted
    max_tenants             SMALLINT UNSIGNED DEFAULT 50,    -- CONFIGURABLE pool size
    current_tenant_count    SMALLINT UNSIGNED DEFAULT 0,
    status                  ENUM('active','full','maintenance','retired') DEFAULT 'active',
    region                  VARCHAR(50) DEFAULT 'in-south',
    vps_label               VARCHAR(100) NULL,               -- e.g., 'vps-db-01'
    notes                   TEXT NULL,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_status (status),
    INDEX idx_count (current_tenant_count)
);
```

---

### 4.4 `tenants` (Gym Registry)

```sql
CREATE TABLE tenants (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id          BIGINT UNSIGNED NOT NULL,
    shard_id            BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(200) NOT NULL,
    slug                VARCHAR(100) UNIQUE NOT NULL,       -- subdomain key
    custom_domain       VARCHAR(255) UNIQUE NULL,
    email               VARCHAR(150) NOT NULL,
    phone               VARCHAR(20) NULL,
    password            VARCHAR(255) NOT NULL,
    owner_name          VARCHAR(150) NULL,
    city                VARCHAR(100) NULL,
    state               VARCHAR(100) NULL,
    country             VARCHAR(100) DEFAULT 'India',
    timezone            VARCHAR(50) DEFAULT 'Asia/Kolkata',
    currency            VARCHAR(10) DEFAULT 'INR',
    logo_url            VARCHAR(500) NULL,
    brand_color         VARCHAR(7) DEFAULT '#3B82F6',

    -- Subscription (Manual Phase 1)
    plan                ENUM('trial','starter','pro','enterprise') DEFAULT 'trial',
    plan_start_date     DATE NULL,
    plan_end_date       DATE NULL,
    subscription_status ENUM('active','expired','suspended','cancelled') DEFAULT 'active',
    max_members         INT UNSIGNED DEFAULT 200,
    max_branches        TINYINT UNSIGNED DEFAULT 1,
    max_staff           TINYINT UNSIGNED DEFAULT 3,

    status              ENUM('active','suspended','inactive','setup') DEFAULT 'setup',
    suspended_at        TIMESTAMP NULL,
    suspended_reason    TEXT NULL,
    setup_completed     BOOLEAN DEFAULT FALSE,
    last_login_at       TIMESTAMP NULL,
    created_by          BIGINT UNSIGNED NOT NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (partner_id) REFERENCES partners(id),
    FOREIGN KEY (shard_id) REFERENCES shards(id),
    INDEX idx_slug (slug),
    INDEX idx_partner (partner_id),
    INDEX idx_shard (shard_id),
    INDEX idx_status (status)
);
```

---

### 4.5 `platform_settings`

```sql
CREATE TABLE platform_settings (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    key         VARCHAR(100) UNIQUE NOT NULL,
    value       TEXT NOT NULL,
    type        ENUM('string','integer','boolean','json') DEFAULT 'string',
    group       VARCHAR(50) DEFAULT 'general',
    description TEXT NULL,
    updated_by  BIGINT UNSIGNED NULL,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Seed data
INSERT INTO platform_settings (key, value, type, group, description) VALUES
('default_shard_max_tenants', '50',          'integer', 'sharding',      'Max gyms per shard'),
('trial_duration_days',       '14',          'integer', 'subscription',  'Trial period in days'),
('default_partner_quota',     '10',          'integer', 'partner',       'Default gym quota for partners'),
('maintenance_mode',          'false',       'boolean', 'system',        'Platform maintenance mode'),
('max_login_attempts',        '5',           'integer', 'security',      'Max failed logins before lockout');
```

---

### 4.6 `audit_logs`

```sql
CREATE TABLE audit_logs (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type      ENUM('developer','partner','tenant','system') NOT NULL,
    actor_id        BIGINT UNSIGNED NOT NULL,
    actor_email     VARCHAR(150) NULL,
    action          VARCHAR(100) NOT NULL,      -- 'partner.created', 'tenant.suspended'
    resource_type   VARCHAR(50) NULL,           -- 'partner', 'tenant', 'shard'
    resource_id     BIGINT UNSIGNED NULL,
    old_values      JSON NULL,
    new_values      JSON NULL,
    ip_address      VARCHAR(45) NULL,
    user_agent      TEXT NULL,
    metadata        JSON NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_action (action),
    INDEX idx_resource (resource_type, resource_id),
    INDEX idx_created (created_at)
);
```

---

### 4.7 `partner_subscriptions` (Manual Phase 1)

```sql
CREATE TABLE partner_subscriptions (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id      BIGINT UNSIGNED NOT NULL,
    plan_name       VARCHAR(100) NOT NULL,
    gym_quota       INT UNSIGNED NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    currency        VARCHAR(10) DEFAULT 'INR',
    billing_cycle   ENUM('monthly','quarterly','annually') DEFAULT 'monthly',
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          ENUM('active','expired','cancelled') DEFAULT 'active',
    payment_mode    ENUM('cash','bank_transfer','upi','cheque') DEFAULT 'bank_transfer',
    payment_ref     VARCHAR(100) NULL,
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    FOREIGN KEY (partner_id) REFERENCES partners(id)
);
```

---

### 4.8 `tenant_feature_flags`

```sql
CREATE TABLE tenant_feature_flags (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    feature     VARCHAR(100) NOT NULL,          -- 'whatsapp', 'biometric', 'api_access'
    is_enabled  BOOLEAN DEFAULT FALSE,
    enabled_by  BIGINT UNSIGNED NULL,
    enabled_at  TIMESTAMP NULL,
    expires_at  TIMESTAMP NULL,

    UNIQUE KEY uk_tenant_feature (tenant_id, feature),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);
```

---

## 5. Shard Database Schema

> **DB Names:** `fitcore_shard_01`, `fitcore_shard_02`, ...  
> **Same schema on every shard. All tables include `tenant_id`.**

---

### 5.1 `gym_config`

```sql
CREATE TABLE gym_config (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL UNIQUE,
    gym_name        VARCHAR(200) NOT NULL,
    logo_url        VARCHAR(500) NULL,
    brand_color     VARCHAR(7) DEFAULT '#3B82F6',
    currency        VARCHAR(10) DEFAULT 'INR',
    timezone        VARCHAR(50) DEFAULT 'Asia/Kolkata',
    tax_type        ENUM('gst','vat','none') DEFAULT 'gst',
    tax_rate        DECIMAL(5,2) DEFAULT 18.00,
    tax_number      VARCHAR(50) NULL,
    address         TEXT NULL,
    city            VARCHAR(100) NULL,
    state           VARCHAR(100) NULL,
    phone           VARCHAR(20) NULL,
    email           VARCHAR(150) NULL,
    operating_hours JSON NULL,               -- {mon: {open:'06:00', close:'22:00'}}
    settings        JSON NULL,               -- misc configurable settings
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id)
);
```

---

### 5.2 `branches`

```sql
CREATE TABLE branches (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    name        VARCHAR(200) NOT NULL,
    code        VARCHAR(20) NULL,
    address     TEXT NULL,
    city        VARCHAR(100) NULL,
    phone       VARCHAR(20) NULL,
    manager_id  BIGINT UNSIGNED NULL,
    is_active   BOOLEAN DEFAULT TRUE,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id)
);
```

---

### 5.3 `staff`

```sql
CREATE TABLE staff (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    branch_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(150) NOT NULL,
    email           VARCHAR(150) NOT NULL,
    phone           VARCHAR(20) NULL,
    password        VARCHAR(255) NOT NULL,
    role            ENUM('owner','manager','front_desk','trainer','accountant') DEFAULT 'front_desk',
    is_active       BOOLEAN DEFAULT TRUE,
    profile_photo   VARCHAR(500) NULL,
    joined_date     DATE NULL,
    last_login_at   TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_email (tenant_id, email),
    INDEX idx_tenant (tenant_id),
    INDEX idx_branch (branch_id)
);
```

---

### 5.4 `members`

```sql
CREATE TABLE members (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    branch_id           BIGINT UNSIGNED NOT NULL,
    member_code         VARCHAR(30) NOT NULL,          -- GYM-0001
    first_name          VARCHAR(100) NOT NULL,
    last_name           VARCHAR(100) NULL,
    email               VARCHAR(150) NULL,
    phone               VARCHAR(20) NOT NULL,
    gender              ENUM('male','female','other') NULL,
    date_of_birth       DATE NULL,
    profile_photo       VARCHAR(500) NULL,
    address             TEXT NULL,
    emergency_contact   VARCHAR(100) NULL,
    emergency_phone     VARCHAR(20) NULL,
    blood_group         VARCHAR(5) NULL,
    medical_notes       TEXT NULL,
    fitness_goal        VARCHAR(255) NULL,
    assigned_trainer_id BIGINT UNSIGNED NULL,
    status              ENUM('active','expired','frozen','inactive') DEFAULT 'active',
    qr_token            VARCHAR(100) UNIQUE NOT NULL,  -- For QR check-in
    referred_by         BIGINT UNSIGNED NULL,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_tenant_code (tenant_id, member_code),
    INDEX idx_tenant (tenant_id),
    INDEX idx_branch (tenant_id, branch_id),
    INDEX idx_phone (tenant_id, phone),
    INDEX idx_status (tenant_id, status),
    INDEX idx_qr (qr_token)
);
```

---

### 5.5 `membership_plans`

```sql
CREATE TABLE membership_plans (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    branch_id       BIGINT UNSIGNED NULL,               -- NULL = all branches
    name            VARCHAR(200) NOT NULL,
    description     TEXT NULL,
    duration_days   SMALLINT UNSIGNED NOT NULL,
    price           DECIMAL(10,2) NOT NULL,
    features        JSON NULL,                          -- ['gym_access','pool','classes']
    max_freeze_days TINYINT UNSIGNED DEFAULT 0,
    guest_passes    TINYINT UNSIGNED DEFAULT 0,
    is_all_branches BOOLEAN DEFAULT FALSE,
    is_active       BOOLEAN DEFAULT TRUE,
    sort_order      TINYINT UNSIGNED DEFAULT 0,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id)
);
```

---

### 5.6 `memberships`

```sql
CREATE TABLE memberships (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id           BIGINT UNSIGNED NOT NULL,
    member_id           BIGINT UNSIGNED NOT NULL,
    plan_id             BIGINT UNSIGNED NOT NULL,
    branch_id           BIGINT UNSIGNED NOT NULL,
    start_date          DATE NOT NULL,
    end_date            DATE NOT NULL,
    original_end_date   DATE NOT NULL,                  -- Before any freeze extension
    freeze_days_used    TINYINT UNSIGNED DEFAULT 0,
    status              ENUM('active','expired','frozen','cancelled') DEFAULT 'active',
    frozen_from         DATE NULL,
    amount_paid         DECIMAL(10,2) NOT NULL,
    notes               TEXT NULL,
    created_by          BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_member (tenant_id, member_id),
    INDEX idx_end_date (tenant_id, end_date),
    INDEX idx_status (tenant_id, status)
);
```

---

### 5.7 `payments`

```sql
CREATE TABLE payments (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    member_id       BIGINT UNSIGNED NOT NULL,
    membership_id   BIGINT UNSIGNED NULL,
    invoice_number  VARCHAR(50) NOT NULL,               -- INV-2026-00001
    amount          DECIMAL(10,2) NOT NULL,
    tax_amount      DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    net_amount      DECIMAL(10,2) NOT NULL,
    mode            ENUM('cash','card','upi','bank_transfer','cheque','online') NOT NULL,
    status          ENUM('paid','pending','partial','refunded') DEFAULT 'paid',
    payment_date    DATE NOT NULL,
    reference_no    VARCHAR(100) NULL,
    payment_for     VARCHAR(200) NULL,
    gateway_txn_id  VARCHAR(200) NULL,                  -- Phase 2: payment gateway
    notes           TEXT NULL,
    created_by      BIGINT UNSIGNED NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_invoice (tenant_id, invoice_number),
    INDEX idx_tenant (tenant_id),
    INDEX idx_member (tenant_id, member_id),
    INDEX idx_date (tenant_id, payment_date),
    INDEX idx_status (tenant_id, status)
);
```

---

### 5.8 `attendance`

```sql
CREATE TABLE attendance (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    branch_id   BIGINT UNSIGNED NOT NULL,
    member_id   BIGINT UNSIGNED NOT NULL,
    check_in    TIMESTAMP NOT NULL,
    check_out   TIMESTAMP NULL,
    source      ENUM('manual','qr','biometric','kiosk') DEFAULT 'manual',
    marked_by   BIGINT UNSIGNED NULL,
    notes       TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_member_date (tenant_id, member_id, check_in),
    INDEX idx_branch_date (tenant_id, branch_id, check_in)
);
```

---

### 5.9 `classes`

```sql
CREATE TABLE classes (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    branch_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(200) NOT NULL,
    trainer_id      BIGINT UNSIGNED NOT NULL,
    room            VARCHAR(100) NULL,
    max_capacity    TINYINT UNSIGNED DEFAULT 20,
    duration_mins   TINYINT UNSIGNED DEFAULT 60,
    difficulty      ENUM('beginner','intermediate','advanced') DEFAULT 'beginner',
    schedule_type   ENUM('recurring','one_time') DEFAULT 'recurring',
    recurrence_days JSON NULL,                          -- ['mon','wed','fri']
    start_time      TIME NOT NULL,
    start_date      DATE NOT NULL,
    end_date        DATE NULL,
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_branch (tenant_id, branch_id)
);
```

---

### 5.10 `class_bookings`

```sql
CREATE TABLE class_bookings (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    class_id    BIGINT UNSIGNED NOT NULL,
    member_id   BIGINT UNSIGNED NOT NULL,
    class_date  DATE NOT NULL,
    status      ENUM('booked','attended','cancelled','waitlisted','no_show') DEFAULT 'booked',
    cancelled_at TIMESTAMP NULL,
    cancel_reason VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uk_booking (tenant_id, class_id, member_id, class_date),
    INDEX idx_tenant (tenant_id),
    INDEX idx_class_date (tenant_id, class_id, class_date)
);
```

---

### 5.11 `equipment`

```sql
CREATE TABLE equipment (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    branch_id       BIGINT UNSIGNED NOT NULL,
    name            VARCHAR(200) NOT NULL,
    type            VARCHAR(100) NULL,
    brand           VARCHAR(100) NULL,
    serial_number   VARCHAR(100) NULL,
    purchase_date   DATE NULL,
    purchase_cost   DECIMAL(10,2) NULL,
    warranty_expiry DATE NULL,
    status          ENUM('active','maintenance','retired') DEFAULT 'active',
    notes           TEXT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id)
);
```

---

### 5.12 `expenses`

```sql
CREATE TABLE expenses (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    branch_id   BIGINT UNSIGNED NOT NULL,
    category    VARCHAR(100) NOT NULL,               -- 'rent', 'salary', 'utilities'
    amount      DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    vendor      VARCHAR(200) NULL,
    reference   VARCHAR(100) NULL,
    notes       TEXT NULL,
    created_by  BIGINT UNSIGNED NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_date (tenant_id, expense_date)
);
```

---

### 5.13 `notifications_log`

```sql
CREATE TABLE notifications_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id   BIGINT UNSIGNED NOT NULL,
    member_id   BIGINT UNSIGNED NULL,
    channel     ENUM('sms','whatsapp','email','push') NOT NULL,
    type        VARCHAR(100) NOT NULL,               -- 'membership_expiry_reminder'
    recipient   VARCHAR(200) NOT NULL,
    message     TEXT NOT NULL,
    status      ENUM('sent','failed','pending') DEFAULT 'pending',
    sent_at     TIMESTAMP NULL,
    error       TEXT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_tenant (tenant_id),
    INDEX idx_status (status)
);
```

---

## 6. Tenant Routing Flow (Laravel)

### 6.1 Middleware Implementation

```php
// app/Http/Middleware/TenantResolutionMiddleware.php

public function handle(Request $request, Closure $next)
{
    // 1. Extract slug from subdomain
    $host = $request->getHost();          // alphagym.fitcore.io
    $slug = explode('.', $host)[0];       // alphagym

    // 2. Check Redis cache first
    $cacheKey = "tenant:slug:{$slug}";
    $tenantData = Cache::remember($cacheKey, 300, function () use ($slug) {
        return DB::connection('master')
            ->table('tenants as t')
            ->join('shards as s', 't.shard_id', '=', 's.id')
            ->select('t.id', 't.status', 't.shard_id',
                     's.host', 's.port', 's.database_name',
                     's.username', 's.password_encrypted')
            ->where('t.slug', $slug)
            ->first();
    });

    // 3. Validate
    if (!$tenantData) abort(404, 'Gym not found');
    if ($tenantData->status === 'suspended') abort(403, 'Account suspended');

    // 4. Decrypt shard password
    $password = decrypt($tenantData->password_encrypted);

    // 5. Set dynamic DB connection
    config(['database.connections.tenant' => [
        'driver'    => 'mysql',
        'host'      => $tenantData->host,
        'port'      => $tenantData->port,
        'database'  => $tenantData->database_name,
        'username'  => $tenantData->username,
        'password'  => $password,
        'charset'   => 'utf8mb4',
    ]]);

    DB::purge('tenant');  // Clear any previous connection

    // 6. Bind tenant to app container
    app()->instance('tenant', (object)[
        'id'     => $tenantData->id,
        'slug'   => $slug,
        'shard'  => $tenantData->shard_id,
    ]);

    return $next($request);
}
```

### 6.2 Base Tenant Model (Auto-scopes tenant_id)

```php
// app/Models/TenantModel.php
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';

    protected static function booted()
    {
        // Auto-filter by tenant on all queries
        static::addGlobalScope('tenant', function ($query) {
            $query->where('tenant_id', app('tenant')->id);
        });

        // Auto-set tenant_id on create
        static::creating(function ($model) {
            $model->tenant_id = app('tenant')->id;
        });
    }
}

// All shard models extend this:
class Member extends TenantModel { protected $table = 'members'; }
class Payment extends TenantModel { protected $table = 'payments'; }
// etc.
```

### 6.3 Caching Strategy

> **Phase 1 (No Redis):** Tenant resolution uses Laravel's `file` cache driver.  
> **Phase 2:** Switch to Redis for high-performance distributed caching.

**Phase 1 — File Cache (`config/cache.php` → driver: `file`)**
```php
// Tenant lookup cached to storage/framework/cache
$tenantData = Cache::remember("tenant:slug:{$slug}", 300, fn() => ...);
```

**Phase 2 — Redis Cache Keys & TTL**
```
tenant:slug:{slug}      → tenant + shard info          TTL: 5 min
shard:id:{id}           → shard connection details     TTL: 30 min
tenant:config:{id}      → gym_config JSON              TTL: 10 min
tenant:stats:{id}       → dashboard counters           TTL: 60 min

Invalidation:
→ Tenant status change  → flush tenant:slug:{slug}
→ Shard update          → flush shard:id:{shard_id}
→ Gym config update     → flush tenant:config:{id}
```

---

## 7. VPS Deployment Architecture

### 7.1 Phase 1 — Launch (0 – 500 Gyms)

> **Master DB is on the SAME VPS** as the application and shard databases. Separate only when load demands it.

```
┌──────────────────────────────────────────────────────────────┐
│  VPS-1: Combined App + DB Server (32GB RAM, 8 vCPU, SSD)   │
│                                                              │
│  APPLICATION LAYER                                           │
│  ├── Nginx (reverse proxy + wildcard *.fitcore.io)           │
│  ├── PHP 8.3-FPM + Laravel 13 API                           │
│  ├── Supervisor (queue workers — database driver)           │
│  └── React builds (Developer + Partner + Gym portals)       │
│                                                              │
│  DATABASE LAYER (same VPS)                                   │
│  ├── MySQL 8.0 — fitcore_master  (central registry)         │
│  ├── MySQL 8.0 — fitcore_shard_01  (gyms 01–20)            │
│  ├── MySQL 8.0 — fitcore_shard_02  (gyms 21–40)            │
│  ├── MySQL 8.0 — fitcore_shard_03  (gyms 41–60)            │
│  └── ... up to shard_25 = 500 gyms                         │
└──────────────────────────────────────────────────────────────┘
```

### 7.2 Phase 2 — Growth (500 – 2,000 Gyms)

> At this point, split master DB and shards onto a dedicated DB VPS. A Load Balancer is added to distribute API traffic.

```
                    ┌──────────────────┐
                    │   Load Balancer  │ (Nginx / HAProxy)
                    └────────┬─────────┘
              ┌──────────────┴──────────────┐
              ▼                              ▼
      ┌───────────────┐              ┌───────────────┐
      │  App VPS-1    │              │  App VPS-2    │
      │  Laravel 13   │              │  Laravel 13   │
      │  PHP-FPM 8.3  │              │  PHP-FPM 8.3  │
      └───────────────┘              └───────────────┘
              │                              │
    ┌─────────┴──────────────────┬───────────┘
    ▼                            ▼
┌──────────────────┐      ┌──────────────────┐
│  DB VPS-1        │      │  DB VPS-2        │
│  fitcore_master  │      │  shard_26–100    │
│  shard_01–25     │      │  (500–2000 gyms) │
└──────────────────┘      └──────────────────┘
```

> **Redis** will be introduced as a separate upgrade decision (not tied to Phase 2). When added, it will replace the database queue driver and file cache driver for improved performance.

### 7.3 Nginx Wildcard Config

```nginx
server {
    listen 443 ssl;
    server_name *.fitcore.io fitcore.io;

    ssl_certificate     /etc/letsencrypt/live/fitcore.io/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/fitcore.io/privkey.pem;

    root /var/www/fitcore/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

### 7.4 SSL Wildcard Certificate

```bash
# Wildcard cert covering all gym subdomains
certbot certonly --dns-cloudflare \
  -d "*.fitcore.io" -d "fitcore.io" \
  --dns-cloudflare-credentials /etc/cloudflare/creds.ini
```

### 7.4 Queue Workers (Supervisor)

> **Phase 1:** Uses `database` queue driver (no Redis dependency).  
> **Phase 2:** Migrate to Redis queue driver for better performance.

```ini
[program:fitcore-notifications]
command=php /var/www/fitcore/artisan queue:work database --queue=notifications
numprocs=2
autostart=true
autorestart=true

[program:fitcore-reports]
command=php /var/www/fitcore/artisan queue:work database --queue=reports
numprocs=1
```

**Phase 1 `queue` table (jobs)** — auto-created by Laravel migration:
```bash
php artisan queue:table
php artisan migrate
```

---

## 8. Portal-Wise API Structure

### 8.1 Developer Portal APIs (`admin.fitcore.io/api/v1/`)

```
Auth
├── POST   /auth/login
└── POST   /auth/logout

Partners
├── GET    /partners                     List + filter partners
├── POST   /partners                     Create partner
├── GET    /partners/{id}                View partner
├── PATCH  /partners/{id}                Update partner
├── PATCH  /partners/{id}/quota          Change gym quota
├── POST   /partners/{id}/suspend        Suspend partner
└── POST   /partners/{id}/activate       Reactivate partner

Tenants
├── GET    /tenants                      All gyms (cross-partner)
├── GET    /tenants/{id}                 View gym detail
├── POST   /tenants/{id}/suspend         Suspend gym
└── GET    /tenants/{id}/stats           Usage stats

Shards
├── GET    /shards                       List shards + usage
├── POST   /shards                       Provision new shard
├── PATCH  /shards/{id}                  Update shard config
└── GET    /shards/{id}/tenants          Gyms in shard

Analytics
└── GET    /analytics/platform           MRR, growth, churn

Settings
├── GET    /settings
└── PATCH  /settings/{key}

Audit Logs
└── GET    /audit-logs                   Filterable, paginated
```

---

### 8.2 Partner Portal APIs (`partner.fitcore.io/api/v1/`)

```
Auth
├── POST   /auth/login
└── POST   /auth/refresh

Dashboard
└── GET    /dashboard                    Quota, gym list, summary

Gyms
├── GET    /gyms                         Their gym list
├── POST   /gyms                         Create new gym instance
├── GET    /gyms/{id}                    View gym
├── PATCH  /gyms/{id}                    Update gym info
├── POST   /gyms/{id}/suspend            Suspend gym
├── POST   /gyms/{id}/activate           Reactivate gym
└── GET    /gyms/{id}/usage              Members, storage

Subscriptions (Manual Phase 1)
├── GET    /gyms/{id}/subscription
├── POST   /gyms/{id}/subscription       Assign plan
└── PATCH  /gyms/{id}/subscription/{id} Renew / modify

Profile
├── GET    /profile
└── PATCH  /profile
```

---

### 8.3 Gym Instance APIs (`{slug}.fitcore.io/api/v1/`)

```
Auth
├── POST   /auth/login
└── POST   /auth/refresh

Members
├── GET    /members
├── POST   /members
├── GET    /members/{id}
├── PATCH  /members/{id}
├── POST   /members/{id}/freeze
├── POST   /members/{id}/unfreeze
└── POST   /members/import

Memberships & Plans
├── GET    /plans
├── POST   /plans
├── GET    /memberships
└── POST   /memberships

Payments
├── GET    /payments
├── POST   /payments
└── GET    /payments/{id}/invoice

Attendance
├── POST   /attendance/checkin
├── POST   /attendance/checkout
└── GET    /attendance/today

Classes & Bookings
├── GET    /classes
├── POST   /classes
├── POST   /classes/{id}/book
└── DELETE /classes/{id}/cancel

Staff
├── GET    /staff
├── POST   /staff
└── PATCH  /staff/{id}

Analytics
├── GET    /analytics/dashboard
├── GET    /analytics/revenue
└── GET    /analytics/members
```

---

## 9. Onboarding Flow (End to End)

```
1. DEVELOPER creates a PARTNER
   ─────────────────────────────
   Developer Portal → POST /partners
   { name, email, gym_quota: 10 }
   → Creates partner record
   → Sends login credentials via email

2. PARTNER creates a GYM
   ───────────────────────
   Partner Portal → POST /gyms
   { name: "Alpha Fitness", slug: "alphafitness",
     owner_email: "...", plan: "starter", plan_end_date: "2027-01-31" }

   Backend:
   ① Validate gyms_created < gym_quota
   ② Find shard: status='active' AND current_count < max_tenants
   ③ INSERT into master.tenants (shard_id assigned)
   ④ Increment shard.current_tenant_count
   ⑤ Increment partner.gyms_created
   ⑥ INSERT gym_config into fitcore_shard_XX
   ⑦ Log action to audit_logs
   ⑧ Send welcome email to gym owner

3. GYM OWNER logs in
   ──────────────────
   alphafitness.fitcore.io → POST /auth/login
   → Middleware resolves shard → JWT issued
   → Redirected to Setup Wizard

4. SETUP WIZARD (First-time)
   ─────────────────────────
   Step 1: Configure gym (hours, address, logo)
   Step 2: Create membership plans
   Step 3: Add staff accounts
   Step 4: Add first members
   → setup_completed = true

5. GYM IS OPERATIONAL ✅
```

---

## 10. Open Questions for Decision

> Please confirm these before development begins.

| # | Question | Decision |
|---|---|---|
| 1 | **MySQL vs PostgreSQL?** | ✅ **MySQL 8.0** |
| 2 | **Shard pool size default?** | ✅ **20 gyms/shard** — changeable via Developer Portal |
| 3 | **Master DB location?** | ✅ **Same VPS** as app (Phase 1) → dedicated VPS in Phase 2 |
| 4 | **Partner portal URL?** | ✅ Shared `partner.fitcore.io` |
| 5 | **File storage (logos/photos)?** | ✅ **Local VPS** (`storage/app`) → S3 in Phase 2 |
| 6 | **Queue driver?** | ✅ **Redis** (from Phase 1) |
| 7 | **Caching?** | ✅ **Redis** (from Phase 1) |
| 8 | **PDF invoices?** | ✅ **DomPDF** |
| 9 | **Shard password security?** | ✅ Laravel `encrypt()` in master DB |
| 10 | **Email provider?** | ✅ **Brevo** (transactional email) |
| 11 | **Mobile app?** | ✅ **Not in Phase 1** — future phase |

---

## Appendix — Tech Stack Summary

| Layer | Technology | Phase 1 | Phase 2 |
|---|---|---|---|
| API Framework | Laravel | **13.x** | 13.x |
| Frontend | React + Vite | 18.x + 5.x | 18.x + 5.x |
| Auth | JWT (tymon/jwt-auth) | latest | latest |
| Primary DB | MySQL | 8.0 | 8.0 |
| Queue Driver | Redis | ✅ Active | ✅ Active |
| Caching | Redis | ✅ Active | ✅ Active |
| File Storage | Local VPS (`storage/app`) | ✅ Active | → Migrate to AWS S3 |
| Web Server | Nginx | latest stable | latest stable |
| PHP Runtime | PHP-FPM | 8.3 | 8.3 |
| Process Manager | Supervisor | latest | latest |
| SSL | Let's Encrypt (Certbot) | wildcard | wildcard |
| Dev Tooling | Laravel Telescope | ✅ Active | remove in prod |

---

*© 2026 FitCore Technologies. Confidential — Technical Architecture Document v1.2*

---

## 11. Future Phase: WhatsApp API + AI Integration

> **Short Answer: YES — the system is fully designed to support both.**  
> The architecture, database, and notification pipeline are already built to plug these in without changing any core code.

---

### 11.1 WhatsApp Business API Integration

#### What is WhatsApp Business API?

```
Normal WhatsApp         →  You send messages manually on your phone
WhatsApp Business App   →  Small business, manual, limited features
WhatsApp Business API   →  Programmatic, automated, high volume
                           → This is what we integrate
```

**Provider:** Meta (Facebook) officially, accessed via approved BSPs  
**Recommended BSP (Business Solution Provider):** Interakt / AiSensy / Wati  
*(These are Indian WhatsApp API providers — easy to integrate, affordable)*

#### How It Works in FitCore

```
FitCore Backend (Laravel)
        │
        │  HTTP API call with template + member phone number
        ▼
WhatsApp BSP (e.g. Interakt)
        │
        │  Delivers message via Meta's infrastructure
        ▼
Member's WhatsApp ✅
```

#### What Messages Will Be Sent Automatically

| Trigger | Message Example | When |
|---|---|---|
| New member joins | "Welcome to Alpha Gym, Rahul! 🏋️ Your membership starts today." | On registration |
| Membership expiry (7 days) | "Hi Rahul, your membership expires in 7 days. Renew now!" | Auto cron |
| Membership expiry (1 day) | "Last day reminder — your membership expires tomorrow!" | Auto cron |
| Payment received | "Payment of ₹2,500 received ✅ Invoice: INV-2026-00042" | On payment |
| Payment due | "Hi Rahul, you have a due payment of ₹1,200 at Alpha Gym." | Auto cron |
| Class booking confirmed | "Your Zumba class is booked for Monday 7 AM ✅" | On booking |
| Birthday wish | "🎂 Happy Birthday Rahul! Special gift from Alpha Gym inside." | Daily cron |
| Gym promotion | "🔥 Monsoon Offer: 3 months @ ₹1,999 only! Valid till Aug 10." | Manual bulk send |

#### Architecture — Already Provisioned

The `notifications_log` table in **every shard DB** already has `whatsapp` as a channel:

```sql
-- This column already exists in our schema
channel  ENUM('sms', 'whatsapp', 'email', 'push')  NOT NULL
```

The notification pipeline in Laravel just needs a **new driver** added:

```
Phase 1 (Current):   NotificationService → Brevo SMTP → Email only
Phase 2 (WhatsApp):  NotificationService → Brevo       → Email
                                         → Interakt API → WhatsApp ✅
                                         → MSG91        → SMS
```

#### Per-Gym WhatsApp Config (Shard DB addition needed)

```sql
-- Add to gym_config table (or separate table)
ALTER TABLE gym_config ADD COLUMN whatsapp_enabled      BOOLEAN DEFAULT FALSE;
ALTER TABLE gym_config ADD COLUMN whatsapp_api_key      VARCHAR(255) NULL;  -- BSP API key
ALTER TABLE gym_config ADD COLUMN whatsapp_phone_number VARCHAR(20)  NULL;  -- Registered WA number
ALTER TABLE gym_config ADD COLUMN whatsapp_bsp          VARCHAR(50)  NULL;  -- 'interakt', 'wati'
```

> **Each gym connects their OWN WhatsApp Business number** — FitCore facilitates, not owns.

#### Laravel Service Addition (Phase 2)

```php
// app/Services/WhatsAppService.php
class WhatsAppService
{
    public function send(string $phone, string $templateName, array $variables): bool
    {
        $config = GymConfig::first();  // Gets current gym's WA config

        if (!$config->whatsapp_enabled || !$config->whatsapp_api_key) {
            return false;  // WhatsApp not configured for this gym
        }

        // Call BSP API (e.g. Interakt)
        $response = Http::withToken($config->whatsapp_api_key)
            ->post('https://api.interakt.ai/v1/public/message/', [
                'countryCode' => '+91',
                'phoneNumber' => $phone,
                'type'        => 'Template',
                'template'    => [
                    'name'       => $templateName,   // Pre-approved WA template
                    'languageCode' => 'en',
                    'bodyValues' => $variables,
                ]
            ]);

        // Log in notifications_log
        NotificationLog::create([
            'channel'   => 'whatsapp',
            'type'      => $templateName,
            'recipient' => $phone,
            'status'    => $response->successful() ? 'sent' : 'failed',
        ]);

        return $response->successful();
    }
}
```

---

### 11.2 AI Integration

> AI features are entirely **additive** — they don't change any existing code.  
> They plug in as new API endpoints and background services.

#### AI Features Planned by Phase

| Phase | AI Feature | What It Does |
|---|---|---|
| **Phase 3** | 🤖 **Member Churn Prediction** | Predicts which members are likely NOT to renew — alerts trainer/admin |
| **Phase 3** | 💪 **Workout Plan Generator** | AI generates a personalised workout plan based on member's goal, age, level |
| **Phase 3** | 📊 **Revenue Forecasting** | Predicts next month's revenue based on expiry trends |
| **Phase 4** | 🗣️ **AI WhatsApp Chatbot** | Members ask questions on WhatsApp — AI answers ("When does my membership end?") |
| **Phase 4** | 🍎 **Diet & Nutrition Advisor** | AI suggests diet plans based on fitness goal and body measurements |
| **Phase 4** | 📈 **Attendance Pattern Analysis** | Finds peak hours, suggests optimal class scheduling |

---

#### How AI Integrates — Architecture

```
FitCore Laravel API
        │
        │  Sends: member data, attendance history,
        │         membership history, body measurements
        ▼
AI Service (can be any of these):
  Option A: OpenAI API (GPT-4)      → for chatbot, text generation
  Option B: Google Gemini API       → for recommendations, predictions
  Option C: Custom Python ML model  → for churn prediction (trained on your data)
        │
        │  Returns: predictions, recommendations, plans
        ▼
FitCore stores result → shows in gym dashboard / member app
```

#### AI is Per-Gym (Tenant-Isolated)

```
Gym A's AI requests use Gym A's data only  ✅
Gym B's AI requests use Gym B's data only  ✅
No cross-tenant data mixing               ✅
```

Because all data queries already go through `WHERE tenant_id = ?` — AI features
just query the same scoped data.

#### DB Additions for AI (Shard DB — Phase 3)

```sql
-- Store AI-generated workout plans
CREATE TABLE ai_workout_plans (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    member_id       BIGINT UNSIGNED NOT NULL,
    generated_by    VARCHAR(50) DEFAULT 'openai',   -- 'openai', 'gemini'
    goal            VARCHAR(100) NULL,              -- 'weight_loss', 'muscle_gain'
    plan_data       JSON NOT NULL,                  -- Full workout plan as JSON
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_member (tenant_id, member_id)
);

-- Store churn risk scores (updated daily by background job)
CREATE TABLE ai_churn_scores (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tenant_id       BIGINT UNSIGNED NOT NULL,
    member_id       BIGINT UNSIGNED NOT NULL,
    risk_score      TINYINT UNSIGNED NOT NULL,      -- 0-100 (100 = highest risk)
    risk_level      ENUM('low', 'medium', 'high') NOT NULL,
    factors         JSON NULL,                      -- Why is this score high?
    computed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tenant_risk (tenant_id, risk_level)
);
```

#### WhatsApp AI Chatbot Flow (Phase 4)

```
Member sends WhatsApp message:
"When does my membership expire?"
        │
        ▼
WhatsApp BSP (Interakt/Wati) receives it
        │  Webhook to FitCore
        ▼
Laravel: AI Chatbot Webhook Handler
  1. Identify member by phone number
  2. Fetch their membership data
  3. Send to OpenAI/Gemini with context:
     "Member Rahul, membership expires 2026-09-15. Answer: {question}"
  4. Get AI response
        │
        ▼
Reply sent back via WhatsApp:
"Hi Rahul! Your membership expires on 15 September 2026.
Would you like to renew now? Reply YES to get a payment link. 😊"
```

---

### 11.3 Integration Readiness Summary

| Integration | Phase | Architecture Ready? | DB Ready? | Code Needed |
|---|---|:---:|:---:|---|
| **WhatsApp Notifications** | Phase 2 | ✅ Yes | ✅ Yes (channel enum) | New `WhatsAppService` class |
| **WhatsApp Bulk Messaging** | Phase 2 | ✅ Yes | ✅ Yes | BSP API wrapper + admin UI |
| **AI Workout Plans** | Phase 3 | ✅ Yes | ➕ New table needed | `AIWorkoutService` + OpenAI API |
| **AI Churn Prediction** | Phase 3 | ✅ Yes | ➕ New table needed | `ChurnPredictionService` + ML model |
| **AI WhatsApp Chatbot** | Phase 4 | ✅ Yes | ✅ Uses existing data | Webhook handler + OpenAI/Gemini |
| **AI Revenue Forecasting** | Phase 3 | ✅ Yes | ✅ Uses payment data | Analytics service extension |

> **Bottom line:** No architectural changes needed. Both WhatsApp and AI are plug-in additions
> to the existing Laravel service layer. The shard-per-tenant design means every gym's AI/WhatsApp
> data stays completely isolated.

---

*© 2026 FitCore Technologies. Confidential — Technical Architecture Document v1.2*
