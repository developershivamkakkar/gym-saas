# Developer Portal API Reference & Integration Guide (`admin.fitcore.io`)

**Document Version:** 3.2  
**Date:** August 3, 2026  
**Target Domain:** `admin.fitcore.io`  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/developer` (or `https://admin.fitcore.io/api/v1/developer`)  
**ID Obfuscation:** All API endpoints support Hashed Identifiers (`"id": "PRT-XXXXXX"`) to prevent sequential enumeration (IDOR protection).

---

## 1. Authentication & Common Headers

- **Content-Type**: `application/json`
- **Authorization**: `Bearer <token>` (Required for all endpoints except `POST /auth/login`)

---

## 2. Core Business Logic & Deployment Architecture

1. **Subdomain Deployment & `instance_url`**:
   - Every Gym Tenant record dynamically computes its **`instance_url`** in API responses:
     - **Local Dev**: `http://{slug}.localhost:8000` (e.g. `http://gold-gym.localhost:8000`)
     - **Production**: `https://{slug}.fitcore.io` (e.g. `https://gold-gym.fitcore.io`)
     - **Custom Domain (Enterprise)**: `https://{custom_domain}` (e.g. `https://gym.goldsgym.com`)

2. **Hashed Identifiers (`id`)**:
   - Every entity in API responses serializes its `id` key as a secure hashed string (e.g. `"id": "PRT-N686AF"`).
   - API endpoints accept **both** Hashed IDs (`PRT-N686AF`) and integer IDs for maximum compatibility.

3. **Strict Pre-Suspension Safeguard Rule**:
   - Suspending a Partner account (`PATCH /partners/{id}/status` -> `status: "suspended"`) is **STRICTLY BLOCKED** if the Partner still has active gym instances assigned (`gyms_created > 0`).
   - Returns HTTP `422 Unprocessable Entity`: `"Cannot suspend partner 'Name'. This partner still has X active gym instance(s) assigned. Please reassign all gym instances to another partner account first..."`

4. **Zero-Downtime Gym Reassignment**:
   - Developers reassign gym instances to another active Partner account using `POST /partners/{id}/reassign-tenants` before suspending the original Partner.
   - **Gym instances remain 100% active with ZERO downtime and ZERO data loss.**

---

## 3. Developer Portal Master API Index (`/api/v1/developer/*`)

| Category | HTTP Method | Endpoint Path | Description |
|---|---|---|---|
| **Auth** | `POST` | `/auth/login` | Developer Super Admin Login |
| **Auth** | `GET` | `/auth/me` | Get Developer Profile |
| **Auth** | `POST` | `/auth/refresh` | Refresh Bearer Token |
| **Auth** | `POST` | `/auth/logout` | Logout Developer |
| **Analytics** | `GET` | `/analytics` | Platform Dashboard Stats (Partners, Gyms, Shards, Logs) |
| **Partners** | `GET` | `/partners` | List All Partners (search, partner_type filter, pagination) |
| **Partners** | `POST` | `/partners` | Create Company or Individual Partner Account |
| **Partners** | `GET` | `/partners/{hash_id}` | Get Single Partner Profile + List of Created Gyms |
| **Partners** | `PATCH` | `/partners/{hash_id}/quota` | Update Partner Gym Quota Limit |
| **Partners** | `PATCH` | `/partners/{hash_id}/status` | Suspend / Activate Partner (Pre-Suspension Guarded) |
| **Partners** | `POST` | `/partners/{hash_id}/reassign-tenants` | Reassign Gym Tenants to Target Partner Account |
| **Shards** | `GET` | `/shards` | List Database Shards & Capacities |
| **Shards** | `POST` | `/shards` | Manually Provision New Database Shard |
| **Shards** | `PATCH` | `/shards/{hash_id}/capacity` | Update Shard Max Capacity Limit |
| **Tenants** | `GET` | `/tenants` | List All Gym Tenants System-Wide (with `instance_url`) |
| **Tenants** | `GET` | `/tenants/{hash_id_or_slug}` | Get Single Gym Tenant Details |
| **Tenants** | `PATCH` | `/tenants/{hash_id_or_slug}/status` | Suspend or Activate a Gym Tenant |
| **Audit Logs** | `GET` | `/audit-logs` | List System-Wide, Partner-Specific, or Gym-Specific Audit Logs |

---

## 4. Detailed API Specifications & Examples

### 4.1 Authentication APIs

#### 🔑 1. Developer Login
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

#### 👤 2. Get Developer Profile
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

#### 📊 3. Get Platform Statistics
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

---

### 4.3 Partner Reseller Management

#### ➕ 4. Create New Partner Account (Company or Individual)
- **Endpoint**: `POST /api/v1/developer/partners`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)

##### Body (Company Partner):
```json
{
  "partner_type": "company",
  "company_name": "Shivam Fitness Agency",
  "contact_person": "David Partner",
  "email": "shivam@globalfitness.com",
  "phone": "+19876543210",
  "password": "password123",
  "gym_quota": 50,
  "notes": "Premium Agency Partner"
}
```

##### Body (Individual Partner):
```json
{
  "partner_type": "individual",
  "company_name": "Mohit Fitness",
  "contact_person": "David Partner",
  "email": "shivam4@globalfitness.com",
  "phone": "+19876543210",
  "password": "password123",
  "gym_quota": 50,
  "notes": "Individual Freelance Partner"
}
```

##### Response (201 Created):
```json
{
  "success": true,
  "message": "Partner created successfully",
  "data": {
    "id": "PRT-N686AF",
    "partner_type": "individual",
    "company_name": "Mohit Fitness",
    "contact_person": "David Partner",
    "email": "shivam4@globalfitness.com",
    "phone": "+19876543210",
    "gym_quota": 50,
    "gyms_created": 0,
    "status": "active"
  }
}
```

#### 📋 5. List All Partners
- **Endpoint**: `GET /api/v1/developer/partners?search=agency&partner_type=company&page=1`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)

#### 🔍 6. Get Partner Details & Gym List
- **Endpoint**: `GET /api/v1/developer/partners/{hash_id}`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Example**: `GET /api/v1/developer/partners/PRT-N686AF`

#### 🔄 7. Reassign Gym Tenants to Another Partner
- **Endpoint**: `POST /api/v1/developer/partners/{source_partner_id}/reassign-tenants`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Request Body**:
  ```json
  {
    "target_partner_id": "PRT-GRNHIA",
    "tenant_ids": ["TNT-17WDRQP"]
  }
  ```

#### 🔒 8. Suspend or Activate Partner (With Pre-Suspension Guard)
- **Endpoint**: `PATCH /api/v1/developer/partners/{hash_id}/status`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Request Body**: `{"status": "suspended"}`

##### Response A: If Partner still owns active gyms (BLOCKED HTTP 422)
```json
{
  "success": false,
  "message": "Cannot suspend partner 'Alex Alpha'. This partner still has 1 active gym instance(s) assigned. Please reassign all gym instances to another partner account first using POST /api/v1/developer/partners/PRT-17WDRQP/reassign-tenants before suspending this partner."
}
```

##### Response B: If 0 gyms remain (SUCCESS HTTP 200)
```json
{
  "success": true,
  "message": "Partner account 'Alex Alpha' suspended successfully. All gyms were previously reassigned.",
  "data": {
    "id": "PRT-17WDRQP",
    "status": "suspended"
  }
}
```

#### 📈 9. Update Partner Gym Quota
- **Endpoint**: `PATCH /api/v1/developer/partners/{hash_id}/quota`
- **Request Body**: `{"gym_quota": 100}`

---

### 4.4 Shard Database Management

#### 🗄️ 10. List All Database Shards & Capacities
- **Endpoint**: `GET /api/v1/developer/shards`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": [
      {
        "id": "SHD-17WDRQP",
        "name": "fitcore_shard_01",
        "db_host": "127.0.0.1",
        "db_port": "3306",
        "db_name": "fitcore_shard_01",
        "max_tenants": 50,
        "current_tenants": 6,
        "is_active": true,
        "is_accepting_tenants": true,
        "tenants_count": 6
      }
    ]
  }
  ```

#### ➕ 11. Manually Provision New Shard Database
- **Endpoint**: `POST /api/v1/developer/shards`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)

#### 📏 12. Update Shard Max Capacity Limit
- **Endpoint**: `PATCH /api/v1/developer/shards/{hash_id}/capacity`
- **Request Body**: `{"max_tenants": 50}`

---

### 4.5 Gym Tenant Management

#### 🏢 13. List All Gym Tenants System-Wide
- **Endpoint**: `GET /api/v1/developer/tenants?search=gold&status=active`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "total": 1,
      "data": [
        {
          "id": "TNT-17WDRQP",
          "name": "Gold Gym",
          "slug": "gold-gym",
          "custom_domain": null,
          "partner_id": "PRT-GRNHIA",
          "shard_id": "SHD-17WDRQP",
          "plan_tier": "pro",
          "status": "active",
          "instance_url": "http://gold-gym.localhost:8000",
          "partner": {
            "id": "PRT-GRNHIA",
            "company_name": "Shivam Fitness Agency",
            "email": "shivam@globalfitness.com"
          },
          "shard": {
            "id": "SHD-17WDRQP",
            "name": "fitcore_shard_01",
            "db_name": "fitcore_shard_01"
          }
        }
      ]
    }
  }
  ```

#### 🔍 14. Get Single Tenant Details
- **Endpoint**: `GET /api/v1/developer/tenants/{hash_id_or_slug}`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Example**: `GET /api/v1/developer/tenants/TNT-17WDRQP` or `GET /api/v1/developer/tenants/gold-gym`

#### 🚫 15. Suspend or Activate Gym Tenant
- **Endpoint**: `PATCH /api/v1/developer/tenants/{hash_id_or_slug}/status`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Request Body**: `{"status": "suspended"}`

---

### 4.6 System Audit Logs

#### 📜 16. List System-Wide, Partner-Specific, or Gym-Specific Audit Logs
- **Endpoint**: `GET /api/v1/developer/audit-logs`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Filtering Options**:
  - `target_type=Partner&target_id=PRT-17WDRQP` *(Partner-specific logs)*
  - `target_type=Tenant&target_id=TNT-17WDRQP` *(Gym-specific logs)*
  - `target_type=Shard&target_id=SHD-17WDRQP` *(Shard-specific logs)*
  - `action=partner.tenants_reassigned` *(Action-specific logs)*
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "total": 12,
      "data": [
        {
          "id": 12,
          "actor_type": "Developer",
          "actor_id": 1,
          "action": "partner.tenants_reassigned",
          "target_type": "Partner",
          "target_id": 17,
          "payload": {
            "source_partner_id": "PRT-17WDRQP",
            "target_partner_id": "PRT-GRNHIA",
            "reassigned_count": 1
          },
          "ip_address": "127.0.0.1",
          "created_at": "2026-08-03T08:33:18.000000Z"
        }
      ]
    }
  }
  ```
