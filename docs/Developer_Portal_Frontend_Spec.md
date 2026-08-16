# FitCore SaaS — Developer Portal Complete Production Frontend Specification (`admin.fitcore.io`)

**Document Version:** 4.0 (Exhaustive API Specification Edition)  
**Target Domain:** `admin.fitcore.io`  
**Target Audience:** React / TypeScript AI Engineers & Senior Frontend Developers  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/developer` (or `https://api.fitcore.io/api/v1/developer`)  
**Auth Scheme:** Bearer Token via `Authorization: Bearer <token>`  
**ID Obfuscation:** Serialized String Hash IDs (`"PRT-GRNHIA"`, `"TNT-000001"`, `"SHD-000001"`)

---

## 1. Complete Architecture & Tech Stack

| Layer | Technology | Purpose |
|---|---|---|
| **Core Framework** | **React 18 / 19 + TypeScript** | Strongly-typed SPA component architecture |
| **State Management** | **Redux Toolkit (`@reduxjs/toolkit`)** | Global app state, developer auth session, modal states, UI theme |
| **Data Fetching** | **TanStack React Query (`@tanstack/react-query`)** | Server state caching, automatic revalidation, `useQuery` & `useMutation` |
| **HTTP Client** | **Axios** | Interceptors for Bearer Token injection & global error handling |
| **Routing** | **React Router v6 (`react-router-dom`)** | Protected route guards & page navigation |

---

## 2. Axios Configuration & Error Handling Interceptor

```typescript
// src/api/developerAxios.ts
import axios from 'axios';
import { store } from '../store/store';
import { developerLogout } from '../store/slices/authSlice';

export const developerAxios = axios.create({
  baseURL: process.env.VITE_DEVELOPER_API_BASE_URL || 'http://127.0.0.1:8000/api/v1/developer',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

developerAxios.interceptors.request.use((config) => {
  const token = localStorage.getItem('developer_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

developerAxios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('developer_token');
      store.dispatch(developerLogout());
      window.location.href = '/login';
    }
    return Promise.reject(error.response?.data || error);
  }
);
```

---

## 3. Redux Store Architecture (`store.ts`)

```typescript
// src/store/store.ts
import { configureStore } from '@reduxjs/toolkit';
import authReducer from './slices/authSlice';
import uiReducer from './slices/uiSlice';
import toastReducer from './slices/toastSlice';

export const store = configureStore({
  reducer: {
    auth: authReducer,
    ui: uiReducer,
    toast: toastReducer,
  },
});

export type RootState = ReturnType<typeof store.getState>;
export type AppDispatch = typeof store.dispatch;
```

---

## 4. Standard Response Formats

### 4.1 Pagination Response Schema
```json
{
  "current_page": 1,
  "data": [ ... ],
  "first_page_url": "http://127.0.0.1:8000/api/v1/developer/partners?page=1",
  "from": 1,
  "last_page": 5,
  "last_page_url": "http://127.0.0.1:8000/api/v1/developer/partners?page=5",
  "next_page_url": "http://127.0.0.1:8000/api/v1/developer/partners?page=2",
  "path": "http://127.0.0.1:8000/api/v1/developer/partners",
  "per_page": 15,
  "prev_page_url": null,
  "to": 15,
  "total": 68
}
```

### 4.2 Error Responses (400, 401, 403, 404, 422, 500)
```json
// 401 Unauthenticated
{ "message": "Unauthenticated." }

// 403 Forbidden
{ "success": false, "message": "Access denied. Developer credentials required." }

// 404 Not Found
{ "success": false, "message": "Resource not found" }

// 422 Validation Error
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "gym_quota": ["The gym quota must be at least 1."]
  }
}

// 500 Internal Server Error
{ "success": false, "message": "Server Error: Could not connect to database shard." }
```

---

## 5. Exhaustive Endpoint Specifications (18 Endpoints)

---

### 5.1 Developer Login (`POST /auth/login`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/auth/login`
- **React Component**: `DeveloperLoginPage` (`src/features/auth/DeveloperLoginPage.tsx`)
- **React Query Hook / Redux Action**: `useDispatch(setDeveloperCredentials)`
- **Path Parameters**: None
- **Query Parameters**: None
- **Request Body Schema**:
  ```json
  {
    "email": "admin@fitcore.io", // string, required, email format
    "password": "password123"    // string, required, min 8 chars
  }
  ```
- **Field Descriptions**:
  - `email`: Super admin developer account email address.
  - `password`: Super admin account secret key.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Developer authenticated successfully",
    "portal": "developer",
    "token": "3a0bfa7c5890f4e...",
    "data": {
      "id": 1,
      "name": "Super Admin Developer",
      "email": "admin@fitcore.io"
    }
  }
  ```
- **Validation Error Response (422 Unprocessable Entity)**:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "email": ["The email field is required."],
      "password": ["The password field is required."]
    }
  }
  ```
- **Error Responses**:
  - `401 Unauthorized`: `{ "success": false, "message": "Invalid developer credentials" }`

---

### 5.2 Fetch Developer Profile (`GET /auth/me`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/auth/me`
- **React Component**: `Header` / `ProfilePage`
- **React Query Hook**: `useDeveloperProfile()`
- **Path Parameters**: None
- **Query Parameters**: None
- **Request Body Schema**: None
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "id": 1,
      "name": "Super Admin Developer",
      "email": "admin@fitcore.io"
    }
  }
  ```
- **Error Responses**: `401 Unauthenticated`

---

### 5.3 Refresh Bearer Token (`POST /auth/refresh`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/auth/refresh`
- **React Component**: `developerAxios` interceptor
- **React Query Hook / Action**: Automatic token refresh dispatcher
- **Request Body**: None
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Token refreshed successfully",
    "token": "9bf801f92e01a..."
  }
  ```

---

### 5.4 Developer Logout (`POST /auth/logout`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/auth/logout`
- **React Component**: `UserDropdown`
- **React Query Hook / Action**: `useDispatch(developerLogout)`
- **Request Body**: None
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Logged out successfully"
  }
  ```

---

### 5.5 Platform Analytics Overview (`GET /analytics`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/analytics`
- **React Component**: `DeveloperDashboardPage` (`src/features/dashboard/DeveloperDashboardPage.tsx`)
- **React Query Hook**: `useDeveloperAnalytics()`
- **Path Parameters**: None
- **Query Parameters**: None
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "partners": { "total": 12, "active": 10, "suspended": 2 },
      "tenants": { "total": 45, "active": 40, "trial": 5, "suspended": 0 },
      "shards": { "total_shards": 3, "avg_utilization_pct": 75 },
      "recent_logs": [
        {
          "id": 102,
          "actor_type": "Developer",
          "action": "partner.reassign_tenants",
          "target_type": "Partner",
          "target_id": "PRT-GRNHIA",
          "created_at": "2026-08-03T10:15:30Z"
        }
      ]
    }
  }
  ```

---

### 5.6 List Partners (`GET /partners`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/partners`
- **React Component**: `PartnerListPage` (`src/features/partners/PartnerListPage.tsx`)
- **React Query Hook**: `useFetchPartners(search, partner_type, page)`
- **Path Parameters**: None
- **Query Parameters**:
  - `search` (string, optional): Search query matching company name, contact person, or email.
  - `partner_type` (string, optional): Filter by type (`company` | `individual`).
  - `page` (number, optional, default: `1`): Pagination page index.
- **Success Response (200 OK - Paginated)**:
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": "PRT-GRNHIA",
          "partner_type": "company",
          "company_name": "Shivam Fitness Agency",
          "contact_person": "David Partner",
          "email": "shivam@globalfitness.com",
          "phone": "19876543210",
          "gym_quota": 50,
          "gyms_created": 1,
          "quota_remaining": 49,
          "status": "active",
          "notes": null,
          "created_at": "2026-08-03T07:24:39.000000Z"
        }
      ],
      "per_page": 15,
      "total": 12
    }
  }
  ```

---

### 5.7 Onboard New Reseller Partner (`POST /partners`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/partners`
- **React Component**: `CreatePartnerPage` (`src/features/partners/CreatePartnerPage.tsx`)
- **React Query Hook**: `useCreatePartnerMutation()`
- **Request Body Schema**:
  ```json
  {
    "partner_type": "company",        // string, enum: ["company", "individual"], required
    "company_name": "Global Agency", // string, required, max 191
    "contact_person": "Jane Partner", // string, required, max 191
    "email": "jane@global.com",       // string, required, email, unique
    "phone": "+1999888777",          // string, required, max 50
    "password": "password123",        // string, required, min 8
    "gym_quota": 25,                  // integer, required, min 1
    "notes": "Premium Agency Account" // string, optional
  }
  ```
- **Success Response (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Partner account created successfully",
    "data": {
      "id": "PRT-98F12A",
      "company_name": "Global Agency",
      "contact_person": "Jane Partner",
      "email": "jane@global.com",
      "gym_quota": 25,
      "gyms_created": 0,
      "status": "active"
    }
  }
  ```
- **Validation Error (422 Unprocessable Entity)**:
  ```json
  {
    "message": "The given data was invalid.",
    "errors": {
      "email": ["The email has already been taken."],
      "gym_quota": ["The gym quota must be at least 1."]
    }
  }
  ```

---

### 5.8 Get Single Partner Details (`GET /partners/{id}`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/partners/{id}`
- **React Component**: `PartnerDetailPage` (`src/features/partners/PartnerDetailPage.tsx`)
- **React Query Hook**: `usePartnerDetail(id)`
- **Path Parameter**: `id` (string, required) — Hashed Partner ID (e.g. `PRT-GRNHIA`) or integer ID.
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": {
      "id": "PRT-GRNHIA",
      "company_name": "Shivam Fitness Agency",
      "contact_person": "David Partner",
      "email": "shivam@globalfitness.com",
      "gym_quota": 50,
      "gyms_created": 1,
      "quota_remaining": 49,
      "status": "active",
      "tenants": [
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
- **Error Response**: `404 Not Found` if partner does not exist.

---

### 5.9 Update Partner Gym Quota (`PATCH /partners/{id}/quota`)
- **HTTP Method**: `PATCH`
- **URL**: `/api/v1/developer/partners/{id}/quota`
- **React Component**: `UpdateQuotaModal` (`src/components/modals/UpdateQuotaModal.tsx`)
- **React Query Hook**: `useUpdatePartnerQuotaMutation()`
- **Path Parameter**: `id` (string, required) — Hashed Partner ID (`PRT-GRNHIA`).
- **Request Body Schema**:
  ```json
  {
    "gym_quota": 100 // integer, required, min 1
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Partner quota updated successfully to 100",
    "data": {
      "id": "PRT-GRNHIA",
      "gym_quota": 100,
      "gyms_created": 1,
      "quota_remaining": 99
    }
  }
  ```

---

### 5.10 Reassign Gym Tenants (`POST /partners/{id}/reassign-tenants`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/partners/{id}/reassign-tenants`
- **React Component**: `ReassignGymsModal` (`src/components/modals/ReassignGymsModal.tsx`)
- **React Query Hook**: `useReassignTenantsMutation()`
- **Path Parameter**: `id` (string, required) — Source Hashed Partner ID (`PRT-GRNHIA`).
- **Request Body Schema**:
  ```json
  {
    "target_partner_id": "PRT-17WDRQP" // string, required, target partner Hash ID or numeric ID
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Successfully reassigned 1 gym instance(s) from 'Shivam Fitness Agency' to 'Fitness Resellers Ltd'. Zero downtime guaranteed.",
    "reassigned_gyms": [
      {
        "id": "TNT-000001",
        "name": "Gold Gym Central",
        "slug": "gold-gym"
      }
    ]
  }
  ```

---

### 5.11 Update Partner Status with Guard (`PATCH /partners/{id}/status`)
- **HTTP Method**: `PATCH`
- **URL**: `/api/v1/developer/partners/{id}/status`
- **React Component**: `PreSuspensionGuardModal` (`src/components/modals/PreSuspensionGuardModal.tsx`)
- **React Query Hook**: `useUpdatePartnerStatusMutation()`
- **Path Parameter**: `id` (string, required) — Hashed Partner ID (`PRT-GRNHIA`).
- **Request Body Schema**:
  ```json
  {
    "status": "suspended" // string, enum: ["active", "suspended"], required
  }
  ```
- **Success Response (200 OK - When gyms_created == 0)**:
  ```json
  {
    "success": true,
    "message": "Partner status updated to suspended",
    "data": { "id": "PRT-GRNHIA", "status": "suspended" }
  }
  ```
- **Pre-Suspension Protection Error Response (422 Unprocessable Entity - When gyms_created > 0)**:
  ```json
  {
    "success": false,
    "message": "Cannot suspend partner 'David Partner'. This partner still has 1 active gym instance(s) assigned. Please reassign all gym instances to another partner account first before suspending this partner."
  }
  ```
- **Frontend Action**: Catch error 422 and present prompt to open `ReassignGymsModal`.

---

### 5.12 List Shard Database Pools (`GET /shards`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/shards`
- **React Component**: `ShardListPage` (`src/features/shards/ShardListPage.tsx`)
- **React Query Hook**: `useFetchShards()`
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": [
      {
        "id": "SHD-000001",
        "name": "fitcore_shard_01",
        "db_name": "fitcore_shard_01",
        "max_tenants": 20,
        "current_tenants": 15,
        "is_active": true,
        "is_accepting_tenants": true
      }
    ]
  }
  ```

---

### 5.13 Provision New Shard Database Pool (`POST /shards`)
- **HTTP Method**: `POST`
- **URL**: `/api/v1/developer/shards`
- **React Component**: `ShardListPage`
- **React Query Hook**: `useCreateShardMutation()`
- **Request Body**: None
- **Success Response (201 Created)**:
  ```json
  {
    "success": true,
    "message": "New Database Shard 'fitcore_shard_02' provisioned successfully",
    "data": {
      "id": "SHD-000002",
      "name": "fitcore_shard_02",
      "max_tenants": 20,
      "current_tenants": 0,
      "is_accepting_tenants": true
    }
  }
  ```

---

### 5.14 Expand Shard Capacity (`PATCH /shards/{id}/capacity`)
- **HTTP Method**: `PATCH`
- **URL**: `/api/v1/developer/shards/{id}/capacity`
- **React Component**: `ExpandShardModal` (`src/components/modals/ExpandShardModal.tsx`)
- **React Query Hook**: `useUpdateShardCapacityMutation()`
- **Path Parameter**: `id` (string, required) — Hashed Shard ID (`SHD-000001`).
- **Request Body Schema**:
  ```json
  {
    "max_tenants": 50 // integer, required, min 1
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Shard capacity updated successfully to 50 max tenants",
    "data": {
      "id": "SHD-000001",
      "max_tenants": 50,
      "current_tenants": 15
    }
  }
  ```

---

### 5.15 List All Gym Tenants System-Wide (`GET /tenants`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/tenants`
- **React Component**: `TenantListPage` (`src/features/tenants/TenantListPage.tsx`)
- **React Query Hook**: `useFetchTenants(search, status, page)`
- **Query Parameters**: `search` (string), `status` (string), `page` (number).
- **Success Response (200 OK - Paginated)**:
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": "TNT-000001",
          "name": "Gold Gym Central",
          "slug": "gold-gym",
          "partner_id": "PRT-GRNHIA",
          "shard_id": "SHD-000001",
          "plan_tier": "pro",
          "status": "active",
          "instance_url": "http://gold-gym.localhost:8000"
        }
      ],
      "per_page": 15,
      "total": 45
    }
  }
  ```

---

### 5.16 Get Single Gym Tenant Details (`GET /tenants/{id}`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/tenants/{id}`
- **React Component**: `TenantDetailPage` (`src/features/tenants/TenantDetailPage.tsx`)
- **React Query Hook**: `useTenantDetail(id)`
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
      "partner": { "id": "PRT-GRNHIA", "company_name": "Shivam Fitness Agency" },
      "shard": { "id": "SHD-000001", "name": "fitcore_shard_01" }
    }
  }
  ```

---

### 5.17 Toggle Gym Tenant Status (`PATCH /tenants/{id}/status`)
- **HTTP Method**: `PATCH`
- **URL**: `/api/v1/developer/tenants/{id}/status`
- **React Component**: `TenantListPage`
- **React Query Hook**: `useUpdateTenantStatusMutation()`
- **Path Parameter**: `id` (string, required) — Hashed Tenant ID (`TNT-000001`).
- **Request Body Schema**:
  ```json
  {
    "status": "suspended" // string, enum: ["active", "suspended"], required
  }
  ```
- **Success Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Gym tenant account status updated to suspended",
    "data": { "id": "TNT-000001", "status": "suspended" }
  }
  ```

---

### 5.18 System Audit Logs Trail (`GET /audit-logs`)
- **HTTP Method**: `GET`
- **URL**: `/api/v1/developer/audit-logs`
- **React Component**: `AuditLogsPage` (`src/features/audit/AuditLogsPage.tsx`)
- **React Query Hook**: `useFetchAuditLogs(target_type, target_id)`
- **Query Parameters**:
  - `target_type` (string, optional): Filter by `Partner`, `Tenant`, or `Shard`.
  - `target_id` (string, optional): Target Hash ID (`PRT-GRNHIA` or `TNT-000001`).
- **Success Response (200 OK - Paginated)**:
  ```json
  {
    "success": true,
    "data": {
      "current_page": 1,
      "data": [
        {
          "id": 105,
          "actor_type": "Developer",
          "actor_id": 1,
          "action": "partner.reassign_tenants",
          "target_type": "Partner",
          "target_id": "PRT-GRNHIA",
          "payload": { "source_partner_id": 1, "target_partner_id": 2, "tenants_moved": 1 },
          "ip_address": "127.0.0.1",
          "created_at": "2026-08-03T10:15:30.000000Z"
        }
      ],
      "per_page": 15,
      "total": 105
    }
  }
  ```
