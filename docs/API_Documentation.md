# FitCore SaaS Platform — Complete API Reference & Integration Guide

**Document Version:** 2.4  
**Date:** August 3, 2026  
**Base Server URL:** `http://127.0.0.1:8000` (or `https://api.fitcore.io`)  
**Format:** REST API / JSON Payload  
**ID Obfuscation:** All API endpoints support Hashed Identifiers (`"id": "PRT-XXXXXX"`) to prevent sequential enumeration (IDOR protection).

---

## 1. Authentication & Common Headers

### Header Requirements per Portal

| Portal | Required Headers | Description |
|---|---|---|
| **Developer Portal** | `Content-Type: application/json`<br>`Authorization: Bearer <token>` | `admin.fitcore.io` — FitCore Super Admins |
| **Partner Portal** | `Content-Type: application/json`<br>`Authorization: Bearer <token>` | `partner.fitcore.io` — Resellers / Franchise Agencies / Individual Partners |
| **Gym Instance** | `Content-Type: application/json`<br>`Authorization: Bearer <token>`<br>`X-Tenant-Slug: <gym-slug>` | `{slug}.fitcore.io` — Gym Owners, Managers, Staff, Trainers |

---

## 2. Core Business Logic & Infrastructure Architecture

1. **Subdomain Deployment & `instance_url`**:
   - Every Gym Tenant record dynamically computes its **`instance_url`** in API responses:
     - **Local Dev**: `http://{slug}.localhost:8000` (e.g. `http://gold-gym.localhost:8000`)
     - **Production**: `https://{slug}.fitcore.io` (e.g. `https://gold-gym.fitcore.io`)
     - **Custom Domain (Enterprise)**: `https://{custom_domain}` (e.g. `https://gym.goldsgym.com`)
   - **Wildcard DNS**: `*.fitcore.io` points to VPS IP, Nginx routes to Laravel, and `TenantResolutionMiddleware` switches database shard in Redis (< 0.5ms).

2. **Hashed Identifiers (`id`)**:
   - Every entity in API responses serializes its `id` key as a secure hashed string (e.g. `"id": "PRT-N686AF"`).
   - API endpoints accept **both** Hashed IDs (`PRT-N686AF`) and integer IDs for maximum compatibility.

3. **Strict Pre-Suspension Safeguard Rule**:
   - Suspending a Partner account (`PATCH /partners/{id}/status` -> `status: "suspended"`) is **STRICTLY BLOCKED** if the Partner still has active gym instances assigned (`gyms_created > 0`).
   - The API returns HTTP `422 Unprocessable Entity`: `"Cannot suspend partner 'Name'. This partner still has X active gym instance(s) assigned. Please reassign all gym instances to another partner account first..."`

4. **Zero-Downtime Gym Reassignment**:
   - Developers reassign gym instances to another active Partner account using `POST /partners/{id}/reassign-tenants` before suspending the original Partner.
   - **Gym instances remain 100% active with ZERO downtime and ZERO data loss.**

---

## 3. Developer Portal Master API Index (`/api/v1/developer/*`)

| Category | HTTP Method | Endpoint Path | Description |
|---|---|---|---|
| **Authentication** | `POST` | `/api/v1/developer/auth/login` | Developer Super Admin Login |
| **Authentication** | `GET` | `/api/v1/developer/auth/me` | Get Developer Profile |
| **Authentication** | `POST` | `/api/v1/developer/auth/refresh` | Refresh Developer Token |
| **Authentication** | `POST` | `/api/v1/developer/auth/logout` | Logout Developer |
| **Analytics** | `GET` | `/api/v1/developer/analytics` | Platform Dashboard Stats (Partners, Gyms, Shards) |
| **Partner Management** | `GET` | `/api/v1/developer/partners` | List All Partners (search, partner_type filter, pagination) |
| **Partner Management** | `POST` | `/api/v1/developer/partners` | Create Company or Individual Partner Account |
| **Partner Management** | `GET` | `/api/v1/developer/partners/{hash_id}` | Get Single Partner Profile + List of Created Gyms |
| **Partner Management** | `PATCH` | `/api/v1/developer/partners/{hash_id}/quota` | Update Partner Gym Quota Limit |
| **Partner Management** | `PATCH` | `/api/v1/developer/partners/{hash_id}/status` | Suspend / Activate Partner (with Pre-Suspension Guard) |
| **Partner Management** | `POST` | `/api/v1/developer/partners/{hash_id}/reassign-tenants` | Reassign Gym Tenants to Another Partner Account |
| **Shard Management** | `GET` | `/api/v1/developer/shards` | List Database Shards & Capacities |
| **Shard Management** | `POST` | `/api/v1/developer/shards` | Manually Provision New Database Shard |
| **Shard Management** | `PATCH` | `/api/v1/developer/shards/{hash_id}/capacity` | Update Shard Max Capacity |
| **Tenant Management** | `GET` | `/api/v1/developer/tenants` | List All Gym Tenants System-Wide (with `instance_url`) |
| **Tenant Management** | `GET` | `/api/v1/developer/tenants/{hash_id_or_slug}` | Get Single Gym Tenant Details |
| **Tenant Management** | `PATCH` | `/api/v1/developer/tenants/{hash_id_or_slug}/status` | Suspend or Activate a Gym Tenant |
| **System Audit Logs** | `GET` | `/api/v1/developer/audit-logs` | List System-Wide Audit Logs |

---

## 4. Developer Portal Detailed API Reference

### 4.1 Developer Authentication

#### 🔑 Developer Login
- **Endpoint**: `POST /api/v1/developer/auth/login`
- **Auth Required**: No
- **Request Body**:
  ```json
  {
    "email": "admin@fitcore.io",
    "password": "password123"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Developer authenticated successfully",
    "portal": "developer",
    "token": "77924156c205ad9ff855e04656f9f353157ab82f47ff43d4e241d41265439639",
    "data": {
      "id": 1,
      "name": "FitCore Super Admin",
      "email": "admin@fitcore.io",
      "role": "super_admin"
    }
  }
  ```

#### 👤 Get Developer Profile
- **Endpoint**: `GET /api/v1/developer/auth/me`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "name": "FitCore Super Admin",
      "email": "admin@fitcore.io",
      "role": "super_admin",
      "is_active": true,
      "last_login_at": "2026-08-03T09:38:38.000000Z",
      "created_at": "2026-08-03T07:24:39.000000Z",
      "updated_at": "2026-08-03T09:38:38.000000Z"
    }
  }
  ```

---

### 4.2 Platform Analytics & Dashboard

#### 📊 Get Platform Statistics
- **Endpoint**: `GET /api/v1/developer/analytics`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "partners": {
        "total": 7,
        "active": 6
      },
      "gym_tenants": {
        "total": 61,
        "active": 61,
        "trial": 0,
        "suspended": 0
      },
      "shards": {
        "total": 4,
        "active": 4
      },
      "recent_logs": [ ... ]
    }
  }
  ```
