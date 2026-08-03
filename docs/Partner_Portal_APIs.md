# Partner Portal API Reference & Integration Guide (`partner.fitcore.io`)

**Document Version:** 1.0  
**Date:** August 3, 2026  
**Target Domain:** `partner.fitcore.io`  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/partner` (or `https://partner.fitcore.io/api/v1/partner`)  
**ID Obfuscation:** All API endpoints support Hashed Identifiers (`"id": "TNT-XXXXXX"`, `"id": "PRT-XXXXXX"`) to prevent sequential enumeration (IDOR protection).

---

## 1. Common Headers & Authentication

- **Content-Type**: `application/json`
- **Authorization**: `Bearer <token>` (Required for all protected endpoints)

---

## 2. Core Business Logic Rules Enforced

1. **Strict Quota Limit Enforcement**:
   - Partner attempts to provision a gym via `POST /gyms` are **STRICTLY BLOCKED** if `gyms_created >= gym_quota`.
   - Returns HTTP `422 Unprocessable Entity`: `"Gym quota limit reached (X/X). Please contact FitCore developers to request a quota increase before provisioning new gyms."`

2. **Suspended Partner Lockout**:
   - Suspended Partners cannot log into `partner.fitcore.io` or call any Partner Portal APIs (`HTTP 403 Forbidden`).

3. **Automatic Shard Routing & Tenant Database Provisioning**:
   - Provisioning a gym via `POST /gyms` automatically assigns the next available database shard pool (`fitcore_shard_01` to `04`), executes migrations, seeds default roles, and registers the initial Gym Owner account.

4. **Dynamic `instance_url`**:
   - API responses return the clickable hosted access URL (e.g. `http://gold-gym.localhost:8000` or `https://gold-gym.fitcore.io`).

---

## 3. Partner Portal Master API Table

| Category | HTTP Method | Endpoint Path | Description |
|---|---|---|---|
| **Authentication** | `POST` | `/api/v1/partner/auth/login` | Partner Account Login (Company or Individual) |
| **Authentication** | `GET` | `/api/v1/partner/auth/me` | Get Partner Profile & Quota Stats |
| **Authentication** | `POST` | `/api/v1/partner/auth/refresh` | Refresh Bearer Token |
| **Authentication** | `POST` | `/api/v1/partner/auth/logout` | Partner Logout |
| **Dashboard** | `GET` | `/api/v1/partner/dashboard` | Partner Dashboard Overview (Quota, Usage %, Gym Status breakdown) |
| **Gym Provisioning** | `GET` | `/api/v1/partner/gyms` | List All Gym Tenants Created by this Partner |
| **Gym Provisioning** | `POST` | `/api/v1/partner/gyms` | Provision a New Gym Instance for a Gym Owner (using Quota) |
| **Gym Provisioning** | `GET` | `/api/v1/partner/gyms/{id}` | Get Details of a Single Gym Created by this Partner |

---

## 4. Detailed API Specifications & Examples

### 4.1 Authentication APIs

#### 🔑 1. Partner Login
- **Endpoint**: `POST /api/v1/partner/auth/login`
- **Auth Required**: No
- **Request Body**:
  ```json
  {
    "email": "shivam@globalfitness.com",
    "password": "password123"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Partner authenticated successfully",
    "portal": "partner",
    "token": "08b061ee63a229c092cb14610e3a32fbe3c0d72d90506d672d6b672b8ea98566",
    "data": {
      "id": "PRT-GRNHIA",
      "partner_type": "company",
      "company_name": "Shivam Fitness Agency",
      "contact_person": "David Partner",
      "email": "shivam@globalfitness.com",
      "phone": "19876543210",
      "gym_quota": 50,
      "gyms_created": 1,
      "quota_remaining": 49,
      "status": "active"
    }
  }
  ```

#### 👤 2. Get Profile & Quota Stats
- **Endpoint**: `GET /api/v1/partner/auth/me`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "id": "PRT-GRNHIA",
      "partner_type": "company",
      "company_name": "Shivam Fitness Agency",
      "contact_person": "David Partner",
      "email": "shivam@globalfitness.com",
      "gym_quota": 50,
      "gyms_created": 1,
      "quota_remaining": 49,
      "status": "active"
    }
  }
  ```

---

### 4.2 Partner Dashboard Overview

#### 📊 3. Partner Dashboard Stats
- **Endpoint**: `GET /api/v1/partner/dashboard`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "partner": {
        "id": "PRT-GRNHIA",
        "company_name": "Shivam Fitness Agency",
        "email": "shivam@globalfitness.com",
        "status": "active"
      },
      "quota": {
        "gym_quota": 50,
        "gyms_created": 1,
        "quota_remaining": 49,
        "usage_percentage": 2.0
      },
      "gyms": {
        "total": 1,
        "active": 1,
        "trial": 0,
        "suspended": 0
      },
      "recent_gyms": [ ... ]
    }
  }
  ```

---

### 4.3 Gym Provisioning APIs

#### ➕ 4. Provision New Gym Instance (Using Partner Quota)
- **Endpoint**: `POST /api/v1/partner/gyms`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Request Body**:
  ```json
  {
    "gym_name": "Gold Gym Central",
    "slug": "gold-gym",
    "owner_name": "John Gold Owner",
    "owner_email": "owner@goldsgym.com",
    "owner_password": "password123",
    "plan_tier": "pro"
  }
  ```

##### Response A: Success (HTTP 201 Created)
```json
{
  "success": true,
  "message": "Gym instance 'Gold Gym Central' provisioned successfully",
  "data": {
    "tenant": {
      "id": "TNT-000001",
      "name": "Gold Gym Central",
      "slug": "gold-gym",
      "plan_tier": "pro",
      "status": "active",
      "instance_url": "http://gold-gym.localhost:8000"
    },
    "instance_url": "http://gold-gym.localhost:8000",
    "owner_login": {
      "email": "owner@goldsgym.com",
      "url": "http://gold-gym.localhost:8000/login"
    },
    "quota_remaining": 49
  }
}
```

##### Response B: If Partner Quota Exceeded (BLOCKED HTTP 422)
```json
{
  "success": false,
  "message": "Gym quota limit reached (50/50). Please contact FitCore developers to request a quota increase before provisioning new gyms."
}
```

#### 📋 5. List Created Gym Instances
- **Endpoint**: `GET /api/v1/partner/gyms?search=gold&status=active`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "quota_info": {
      "gym_quota": 50,
      "gyms_created": 1,
      "quota_remaining": 49
    },
    "data": {
      "current_page": 1,
      "total": 1,
      "data": [
        {
          "id": "TNT-000001",
          "name": "Gold Gym Central",
          "slug": "gold-gym",
          "plan_tier": "pro",
          "status": "active",
          "instance_url": "http://gold-gym.localhost:8000"
        }
      ]
    }
  }
  ```

#### 🔍 6. Get Single Gym Instance Details
- **Endpoint**: `GET /api/v1/partner/gyms/{hash_id_or_slug}`
- **Auth Required**: Yes (`Authorization: Bearer <token>`)
- **Example**: `GET /api/v1/partner/gyms/TNT-000001` or `GET /api/v1/partner/gyms/gold-gym`
