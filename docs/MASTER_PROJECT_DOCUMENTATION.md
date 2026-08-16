# 🏋️ FitCore SaaS — Master Project Documentation

Welcome to the comprehensive system documentation for **FitCore**, an enterprise-grade, multi-tenant SaaS Gym Management System.

---

## 📌 Executive Overview

FitCore is designed as a high-performance, multi-tenant, multi-database SaaS platform capable of scaling to thousands of gym locations across partitioned database shards.

### Core Portals
1. **Developer Portal (`admin.fitcore.io`)**
   - System administrator hub for managing database shards, partner accounts, system-wide tenants, global analytics, and immutable audit logs.
2. **Partner Portal (`partner.fitcore.io`)**
   - White-label reseller & franchisee portal for managing purchased gym licenses, tracking branch quota allocations, onboarding new gyms via automated provisioning wizards, and viewing regional revenue performance.
3. **Gym Instance Portal (`{slug}.fitcore.io`)**
   - Tenant-isolated operational portal for gym owners, branch managers, staff, and members to handle branch management, member check-ins, staff scheduling, revenue analytics, and branch quota compliance.

---

## 🏗️ Technical Architecture & Database Design

### 1. Hybrid Multi-Tenant Shard Pool Model
- **Central Master Database (`fitcore_master`)**:
  - Stores system-wide configuration, partner accounts, global tenant mapping (`tenants`), database shard definitions (`shards`), global audit logs, and global user credentials.
- **Dynamic Database Shards (`fitcore_shard_01`, `fitcore_shard_02`, ...)**:
  - Each shard houses isolated operational data for assigned tenants: branches, members, memberships, staff, equipment, check-ins, payments, and branch settings.
  - Zero cross-tenant data leakage via automated connection switching driven by the `GymTenantResolver` middleware.

### 2. Multi-Branch & Quota Enforcer Architecture
- **Tenant Hierarchy**: `Partner` ➡️ `Tenant (Gym)` ➡️ `Branches`.
- **Branch Quota Enforcement**:
  - `Partner` accounts have a total maximum branch quota (`max_branch_quota`).
  - `Tenant` accounts inherit branch quota limits assigned during onboarding.
  - `BranchQuotaGuard` middleware verifies branch creation requests in real-time against active subscription limits before database insertion.

### 3. Security & Hashid Encoding
- **Obfuscated Resource IDs**: All integer IDs exposed in public endpoints are converted to hashed strings using `Hashids` salt encoding.
- **`HashedIdResolver` Middleware**: Automatically converts URL route parameter hashids back into integers before executing controller logic.

---

## 🛠️ Backend Stack & Modules (`GYM_SAAS`)

- **Framework**: Laravel 11 / PHP 8.2+
- **Auth**: Laravel Sanctum with multi-guard separation (`developer_api`, `partner_api`, `gym_api`).
- **Database Support**: MySQL 8.0+ / MariaDB with dynamic PDO connection management.

### Key API Modules & Controllers
| Module | Endpoint Namespace | Primary Controllers | Key Functions |
|---|---|---|---|
| **Developer API** | `/api/v1/developer` | `AuthController`, `TenantController`, `PartnerController`, `ShardController`, `AuditLogController`, `AnalyticsController` | Developer Auth, Partner lifecycle, Tenant suspension/activation, Shard registration & health metrics, Global Audit Logging. |
| **Partner API** | `/api/v1/partner` | `AuthController`, `PartnerDashboardController`, `PartnerGymController` | Partner Login, Quota consumption tracking, Automated Gym Tenant & Branch provisioning wizard. |
| **Gym API** | `/api/v1/gym` | `AuthController`, `BranchController`, `GymDashboardController` | Tenant Auth, Multi-branch management, Quota compliance checks, Tenant operational analytics. |

---

## 🎨 Frontend Architecture & Specifications (`GYM_SAAS_PORTAL`)

- **Tech Stack**: Vite + React 18 + TypeScript + Redux Toolkit + Tailwind CSS / Custom Glassmorphism UI.
- **State Management**: Modular Redux Slices (`authSlice`, `gymAuthSlice`, `toastSlice`, etc.).
- **HTTP Layer**: Axios client instances (`developerAxios`, `partnerAxios`, `gymAxios`) with automatic JWT bearer header injection, tenant context headers (`X-Tenant-Slug`), and dynamic error interceptors.

### Portal Frontend Modules
1. **Developer Portal (`/developer`)**:
   - Dashboard with active shard counts, live tenant totals, partner activity.
   - Partner Management: Modal for partner onboarding, license adjustment.
   - Tenant Directory: Pre-suspension modal guards, shard migration triggers.
   - Audit Log Viewer: Filterable log event inspector.
2. **Partner Portal (`/partner`)**:
   - Partner Dashboard: Interactive branch quota progress rings, total active gyms summary.
   - Provisioning Wizard: 4-step wizard (Partner Details ➡️ Tenant Info ➡️ Branch Setup ➡️ License/Quota Allocation).
   - Gym List: Managed gym inventory with deep-link navigation.
3. **Gym Instance Portal (`/gym`)**:
   - Gym Dashboard: Tenant-wide KPIs (Active Members, Daily Check-ins, Revenue, Branch distribution).
   - Branch Management: Branch listing, Branch creation modal with live quota limit bar.

---

## 📂 Documentation File Directory Index

All technical documents are stored in the [`docs/`](file:///d:/Startup/GYM_SAAS/docs) directory:

1. **[MASTER_PROJECT_DOCUMENTATION.md](file:///d:/Startup/GYM_SAAS/docs/MASTER_PROJECT_DOCUMENTATION.md)** — Comprehensive Master System Documentation (This file).
2. **[TechArch_GymSaaS.md](file:///d:/Startup/GYM_SAAS/docs/TechArch_GymSaaS.md)** — Detailed Technical Architecture & Database Sharding strategy.
3. **[SRS_GymSaaS.md](file:///d:/Startup/GYM_SAAS/docs/SRS_GymSaaS.md)** — Software Requirements Specification & Functional Requirements.
4. **[Branch_Tenant_Architecture.md](file:///d:/Startup/GYM_SAAS/docs/Branch_Tenant_Architecture.md)** — Multi-branch hierarchy and quota guard rules.
5. **[Backend_Auth_Tenant_Setup.md](file:///d:/Startup/GYM_SAAS/docs/Backend_Auth_Tenant_Setup.md)** — Authentication guards and middleware configuration.
6. **[API_Documentation.md](file:///d:/Startup/GYM_SAAS/docs/API_Documentation.md)** — Complete API endpoint reference across all 3 portals.
7. **[Developer_Portal_APIs.md](file:///d:/Startup/GYM_SAAS/docs/Developer_Portal_APIs.md)** — Developer Portal API specification.
8. **[Partner_Portal_APIs.md](file:///d:/Startup/GYM_SAAS/docs/Partner_Portal_APIs.md)** — Partner Portal API specification.
9. **[Gym_Instance_APIs.md](file:///d:/Startup/GYM_SAAS/docs/Gym_Instance_APIs.md)** — Gym Instance Portal API specification.
10. **[Developer_Portal_Frontend_Spec.md](file:///d:/Startup/GYM_SAAS/docs/Developer_Portal_Frontend_Spec.md)** — React UI specification for Developer Portal.
11. **[Partner_Portal_Frontend_Spec.md](file:///d:/Startup/GYM_SAAS/docs/Partner_Portal_Frontend_Spec.md)** — React UI specification for Partner Portal.
12. **[Gym_Instance_Frontend_Spec.md](file:///d:/Startup/GYM_SAAS/docs/Gym_Instance_Frontend_Spec.md)** — React UI specification for Gym Portal.
13. **[Scale_Testing_Report.md](file:///d:/Startup/GYM_SAAS/docs/Scale_Testing_Report.md)** — Benchmark performance and 60-tenant multi-shard scale report.

---

## 🚀 Quick Start & Development Setup

### Backend Setup (`GYM_SAAS`)
```bash
# Install PHP dependencies
composer install

# Environment setup
cp .env.example .env

# Run database migrations for master DB
php artisan migrate --database=fitcore_master

# Start development server
php artisan serve
```

### Frontend Setup (`GYM_SAAS_PORTAL`)
```bash
# Install Node dependencies
npm install

# Start Vite React dev server
npm run dev
```

---
*FitCore SaaS Engineering Team*
