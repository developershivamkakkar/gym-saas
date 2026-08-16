# FitCore SaaS — Partner Portal Complete Production Frontend Specification (`partner.fitcore.io`)

**Document Version:** 4.0 (Exhaustive API Specification Edition)  
**Target Domain:** `partner.fitcore.io`  
**Target Audience:** React / TypeScript AI Engineers & Senior Frontend Developers  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/partner` (or `https://api.fitcore.io/api/v1/partner`)  
**Auth Scheme:** Bearer Token via `Authorization: Bearer <token>`  
**ID Obfuscation:** Serialized String Hash IDs (`"PRT-GRNHIA"`, `"TNT-000001"`)

---

## 1. Complete Architecture & Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| **Core Framework** | **React 18 / 19 + TypeScript** | Strongly-typed SPA component architecture |
| **State Management** | **Redux Toolkit (`@reduxjs/toolkit`)** | Partner session, quota remaining counter, modal states |
| **Data Fetching** | **TanStack React Query (`@tanstack/react-query`)** | Server state caching, automatic revalidation, `useQuery` & `useMutation` |
| **HTTP Client** | **Axios** | Interceptors for Bearer Token & 403 suspension handling |
| **Routing** | **React Router v6 (`react-router-dom`)** | SPA page routing & protected route guards |

---

## 2. Axios Setup & Interceptors

```typescript
// src/api/partnerAxios.ts
import axios from 'axios';
import { store } from '../store/store';
import { partnerLogout } from '../store/slices/partnerAuthSlice';

export const partnerAxios = axios.create({
  baseURL: process.env.VITE_PARTNER_API_BASE_URL || 'http://127.0.0.1:8000/api/v1/partner',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

partnerAxios.interceptors.request.use((config) => {
  const token = localStorage.getItem('partner_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

partnerAxios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('partner_token');
      store.dispatch(partnerLogout());
      window.location.href = '/login';
    }
    return Promise.reject(error.response?.data || error);
  }
);
```

---

## 3. Standard Response Formats

### 3.1 Pagination Response Schema
```json
{
  "current_page": 1,
  "data": [ ... ],
  "first_page_url": "http://127.0.0.1:8000/api/v1/partner/gyms?page=1",
  "from": 1,
  "last_page": 3,
  "per_page": 15,
  "total": 35
}
```

### 3.2 Error Responses (400, 401, 403, 404, 422, 500)
```json
// 401 Unauthenticated
{ "message": "Unauthenticated." }

// 403 Forbidden (Suspended Partner)
{
  "success": false,
  "message": "Your Partner reseller account is currently suspended. Access to partner portal features is revoked. Please contact FitCore platform support."
}

// 422 Quota Exceeded Error
{
  "success": false,
  "message": "Gym quota limit reached (50/50). Please contact FitCore developers to request a quota increase before provisioning new gyms."
}
```

---

## 4. Exhaustive Endpoint Specifications (8 Endpoints)

---

### 4.1 Partner Login (`POST /auth/login`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/partner/auth/login`
- **React Component**: `PartnerLoginPage` (`src/features/auth/PartnerLoginPage.tsx`)
- **React Query Hook / Action**: `useDispatch(setPartnerCredentials)`
- **Path Parameters**: None
- **Query Parameters**: None
- **Request Body Schema**:
  ```json
  {
    "email": "shivam@globalfitness.com", // string, required, email format
    "password": "password123"            // string, required, min 8 chars
  }
  ```
- **Field Descriptions**:
  - `email`: Reseller Partner agency login email address.
  - `password`: Reseller account secret key.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Partner authenticated successfully",
    "portal": "partner",
    "token": "08b061ee63a229...",
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
- **Validation Error (422 Unprocessable Entity)**:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "email": ["The email field is required."],
      "password": ["The password field is required."]
    }
  }
  ```

---

### 4.2 Fetch Partner Profile (`GET /auth/me`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/partner/auth/me`
- **React Component**: `Header` / `PartnerProfilePage`
- **React Query Hook**: `usePartnerProfile()`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
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

---

### 4.3 Refresh Bearer Token (`POST /auth/refresh`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/partner/auth/refresh`
- **React Component**: `partnerAxios` interceptor
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Token refreshed successfully",
    "token": "5890f0f8d054a..."
  }
  ```

---

### 4.4 Partner Logout (`POST /auth/logout`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/partner/auth/logout`
- **React Component**: `UserDropdown`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Logged out successfully"
  }
  ```

---

### 4.5 Partner Dashboard Overview (`GET /dashboard`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/partner/dashboard`
- **React Component**: `PartnerDashboardPage` (`src/features/dashboard/PartnerDashboardPage.tsx`)
- **React Query Hook**: `usePartnerDashboard()`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "partner": {
        "company_name": "Shivam Fitness Agency",
        "email": "shivam@globalfitness.com"
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
      "recent_gyms": [
        {
          "id": "TNT-000001",
          "name": "Gold Gym Central",
          "slug": "gold-gym",
          "plan_tier": "pro",
          "instance_url": "http://gold-gym.localhost:8000"
        }
      ]
    }
  }
  ```

---

### 4.6 List Partner Gym Tenants (`GET /gyms`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/partner/gyms`
- **React Component**: `PartnerGymListPage` (`src/features/gyms/PartnerGymListPage.tsx`)
- **React Query Hook**: `useFetchPartnerGyms(search, status, page)`
- **Query Parameters**: `search` (string), `status` (string), `page` (number).
- **Success Response (200 OK - Paginated)**:
  ```json
  {
    "success": true,
    "quota_remaining": 49,
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": "TNT-000001",
          "name": "Gold Gym Central",
          "slug": "gold-gym",
          "plan_tier": "pro",
          "status": "active",
          "instance_url": "http://gold-gym.localhost:8000"
        }
      ],
      "per_page": 15,
      "total": 1
    }
  }
  ```

---

### 4.7 Provision New Gym Instance (`POST /gyms`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/partner/gyms`
- **React Component**: `ProvisionGymWizard` (`src/features/gyms/ProvisionGymWizard.tsx`)
- **React Query Hook**: `useProvisionGymMutation()`
- **Request Body Schema**:
  ```json
  {
    "gym_name": "Gold Gym Central",    // string, required, max 191
    "slug": "gold-gym",                // string, required, unique, lowercase slug format
    "owner_name": "John Gold Owner",   // string, required, max 191
    "owner_email": "owner@goldsgym.com",// string, required, email format
    "owner_password": "password123",   // string, required, min 8
    "plan_tier": "pro"                 // string, enum: ["basic", "pro", "enterprise"], default: "basic"
  }
  ```
- **Success Response (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Gym instance 'Gold Gym Central' provisioned successfully",
    "data": {
      "tenant": {
        "id": "TNT-000001",
        "name": "Gold Gym Central",
        "slug": "gold-gym",
        "plan_tier": "pro"
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
- **Quota Exceeded Error (422 Unprocessable Entity)**:
  ```json
  {
    "success": false,
    "message": "Gym quota limit reached (50/50). Please contact FitCore developers to request a quota increase before provisioning new gyms."
  }
  ```

---

### 4.8 Get Single Gym Details (`GET /gyms/{id}`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/partner/gyms/{id}`
- **React Component**: `PartnerGymDetailPage` (`src/features/gyms/PartnerGymDetailPage.tsx`)
- **React Query Hook**: `usePartnerGymDetail(idOrSlug)`
- **Path Parameter**: `id` (string, required) — Hashed Tenant ID (`TNT-000001`) or slug (`gold-gym`).
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "id": "TNT-000001",
      "name": "Gold Gym Central",
      "slug": "gold-gym",
      "plan_tier": "pro",
      "status": "active",
      "instance_url": "http://gold-gym.localhost:8000",
      "created_at": "2026-08-03T07:24:39.000000Z"
    }
  }
  ```
