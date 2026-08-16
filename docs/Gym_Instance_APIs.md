# Gym Instance Portal API Reference & Integration Guide (`{slug}.fitcore.io`)

**Document Version:** 3.1 (Streamlined Edition - Attendance Removed)  
**Target Domain:** `{slug}.fitcore.io`  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/gym` (or `https://{slug}.fitcore.io/api/v1/gym`)  
**ID Obfuscation:** Supports Hashed Identifiers (`"id": "TNT-XXXXXX"`) for tenant resolution.

---

## 1. Authentication & Common Headers

Every API request to a Gym Instance requires identifying the target gym tenant:

- **Content-Type**: `application/json`
- **X-Tenant-Slug**: `<gym-slug>` (e.g. `gold-gym` or `matrix-gym-4580`) — *Required on all requests*
- **Authorization**: `Bearer <token>` (Required for protected endpoints)
- **X-Branch-ID**: `<branch-id>` (Optional: Used by multi-branch Owners to scope request context to a specific physical location)

---

## 2. Core Business Logic Rules

1. **Strict Plan Tier Branch Limit Enforcement**:
   - Creating a new physical branch location (`POST /branches`) enforces `tenant.plan_tier`:
     - **Basic Plan**: Max 1 Branch (Single Location). Rejects 2nd branch with HTTP `422 Unprocessable Entity`.
     - **Pro Plan**: Max 3 Branches.
     - **Enterprise Plan**: Unlimited Branches (999).
2. **Tenant Shard Isolation & Resolution**:
   - `TenantResolutionMiddleware` extracts `{slug}`, verifies account status (`active` vs `suspended`), and dynamically reconnects PDO to the assigned database shard (`fitcore_shard_01` to `04`) in < 0.5ms.
3. **15-Day Grace Period & Past-Due Renewal Rules**:
   - Subscriptions reaching `end_date` transition to `past_due` for 15 days (allowing grace period for payment collection).
   - On Day 16, daily cron transitions status to `expired`.
   - Renewal default `start_date` = `previous_end_date + 1` for `past_due` subscriptions to prevent unbilled gap days. Staff can manually override `start_date` in the UI.
4. **Payment Idempotency Safeguards**:
   - Payments (`POST /billing/invoices/{id}/payments`) enforce `idempotency_key` (scoped per tenant: `(tenant_id, idempotency_key)`).

---

## 3. Complete API Endpoint Master Table (26 Endpoints)

| Category | HTTP Method | Endpoint Path | Description |
|---|---|---|---|
| **Authentication** | `POST` | `/api/v1/gym/auth/login` | Gym Owner / Manager / Staff Login against Tenant Shard |
| **Authentication** | `GET` | `/api/v1/gym/auth/me` | Get Authenticated User Profile, Role & Branch Context |
| **Authentication** | `POST` | `/api/v1/gym/auth/refresh` | Refresh Bearer Token |
| **Authentication** | `POST` | `/api/v1/gym/auth/logout` | User Logout |
| **Dashboard** | `GET` | `/api/v1/gym/dashboard` | Gym Instance Overview (Branches count, Staff, Members, Usage text) |
| **Branches** | `GET` | `/api/v1/gym/branches` | List Physical Branch Locations for this Gym Instance |
| **Branches** | `POST` | `/api/v1/gym/branches` | Create New Physical Branch Location (Enforces Plan Tier Quota) |
| **Branches** | `POST` | `/api/v1/gym/branches/switch` | Switch Active Branch Context |
| **Branches** | `GET` | `/api/v1/gym/branches/{id}` | Get Single Physical Branch Details with Live Stats |
| **Branches** | `PUT/PATCH` | `/api/v1/gym/branches/{id}` | Update Physical Branch Details |
| **Branches** | `DELETE` | `/api/v1/gym/branches/{id}` | Delete Physical Branch (Guarded against Main Branch) |
| **Branches** | `GET` | `/api/v1/gym/branches/{id}/financials` | Dedicated Branch Financial P&L |
| **Members** | `GET` | `/api/v1/gym/members` | List Members with Search (`?search=john`) & Status filters |
| **Members** | `POST` | `/api/v1/gym/members` | Register New Member Profile |
| **Members** | `GET` | `/api/v1/gym/members/{id}` | Get Single Member Details |
| **Members** | `PUT/PATCH` | `/api/v1/gym/members/{id}` | Update Member Profile Details & Status |
| **Members** | `POST` | `/api/v1/gym/members/{id}/subscriptions` | Subscribe Member to a Plan |
| **Memberships** | `GET` | `/api/v1/gym/memberships/plans` | List Available Membership Plans |
| **Memberships** | `POST` | `/api/v1/gym/memberships/plans` | Create New Membership Plan Tier |
| **Memberships** | `POST` | `/api/v1/gym/memberships/subscriptions/{id}/renew` | Renew Subscription (Supports Staff Start Date Overrides) |
| **Memberships** | `POST` | `/api/v1/gym/memberships/subscriptions/{id}/freeze` | Freeze Active Subscription |
| **Memberships** | `POST` | `/api/v1/gym/memberships/subscriptions/{id}/unfreeze` | Unfreeze Subscription & Extend End Date |
| **Memberships** | `POST` | `/api/v1/gym/memberships/subscriptions/{id}/upgrade` | Upgrade Plan Tier (Term Replacement + Prorated Credit) |
| **Billing** | `GET` | `/api/v1/gym/billing/invoices` | List Invoices for Gym Instance |
| **Billing** | `GET` | `/api/v1/gym/billing/invoices/{id}` | Get Detailed Invoice + Payments & Subscription Info |
| **Billing** | `POST` | `/api/v1/gym/billing/invoices/{id}/payments` | Record Payment against Invoice (Idempotency Protected) |
| **Diet Plans** | `GET` | `/api/v1/gym/diet-plans` | List Diet Plans & Reusable Master Templates |
| **Diet Plans** | `POST` | `/api/v1/gym/diet-plans` | Create Diet Plan for Member or Save Master Template |
| **Diet Plans** | `GET` | `/api/v1/gym/diet-plans/{id}` | Get Single Diet Plan Details with Meal Schedule |
| **Diet Plans** | `PUT/PATCH` | `/api/v1/gym/diet-plans/{id}` | Update Diet Plan or Master Template |
| **Diet Plans** | `DELETE` | `/api/v1/gym/diet-plans/{id}` | Delete Diet Plan |
| **Diet Plans** | `POST` | `/api/v1/gym/diet-plans/{id}/assign` | Assign Master Diet Template to a Specific Member |

---

## 4. Detailed API Specifications & Examples

### 4.1 Authentication APIs

#### 🔑 1. Gym User Login
- **Endpoint**: `POST /api/v1/gym/auth/login`
- **Request Body**:
  ```json
  {
    "email": "owner@goldsgym.com",
    "password": "password123"
  }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "token": "8ae7815506b259693...",
    "tenant": { "id": "TNT-000001", "name": "Gold Gym Central", "slug": "gold-gym", "plan_tier": "basic" },
    "data": { "id": 1, "name": "John Owner", "role": "owner" }
  }
  ```

---

### 4.2 Member Management APIs

#### 👤 2. List Members
- **Endpoint**: `GET /api/v1/gym/members`
- **Query Params**: `?search=John&status=active&per_page=15`
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "member_code": "MEM-8A19F",
        "first_name": "John",
        "last_name": "Doe",
        "phone": "+1999888777",
        "email": "john@example.com",
        "status": "active"
      }
    ],
    "pagination": { "total": 1, "current_page": 1, "last_page": 1 }
  }
  ```

#### ➕ 3. Register Member (Supports Prefix & Suffix Customization)
- **Endpoint**: `POST /api/v1/gym/members`
- **Dynamic Member Code Rules**:
  - **Option A (Default SVS Prefix)**: Uses configured prefix (e.g. `SVS-1001`).
  - **Option B (Custom Prefix & Suffix)**: Pass `"member_prefix": "SVS"` and/or `"member_suffix": "VIP"` in body ➡️ Generates `SVS-1001-VIP` or `SVS-1001-GOLD`.
  - **Option C (Exact Custom Code)**: Pass `"member_code": "MVP-2026"` in body ➡️ Uses `MVP-2026`.
- **Request Body Example**:
  ```json
  {
    "first_name": "Jane",
    "last_name": "Smith",
    "phone": "+1999777666",
    "email": "jane@example.com",
    "gender": "female",
    "date_of_birth": "1995-05-12",
    "member_prefix": "SVS",
    "member_suffix": "VIP"
  }
  ```
- **Response (201 Created)**:
  ```json
  {
    "success": true,
    "message": "Member registered successfully with Code [SVS-1001-VIP]",
    "data": {
      "id": 15,
      "tenant_id": 1,
      "member_code": "SVS-1001-VIP",
      "first_name": "Jane",
      "last_name": "Smith",
      "phone": "+1999777666",
      "status": "active"
    }
  }
  ```

---

### 4.3 Memberships & Subscription Lifecycle APIs

#### 📋 4. List Membership Plans
- **Endpoint**: `GET /api/v1/gym/memberships/plans`
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "data": [
      {
        "id": 1,
        "name": "Annual Gold Membership",
        "duration_days": 365,
        "price": 12000.00,
        "max_freeze_days": 30
      }
    ]
  }
  ```

#### 💳 5. Subscribe Member to Plan
- **Endpoint**: `POST /api/v1/gym/members/{id}/subscriptions`
- **Request Body**:
  ```json
  {
    "plan_id": 1,
    "start_date": "2026-08-10",
    "auto_renew": true
  }
  ```

#### 🔄 6. Renew Subscription
- **Endpoint**: `POST /api/v1/gym/memberships/subscriptions/{id}/renew`
- **Request Body**:
  ```json
  {
    "plan_id": 1,
    "override_start_date": "2026-08-10",
    "auto_renew": true
  }
  ```

#### ❄️ 7. Freeze Subscription
- **Endpoint**: `POST /api/v1/gym/memberships/subscriptions/{id}/freeze`
- **Request Body**:
  ```json
  {
    "freeze_start_date": "2026-09-01",
    "requested_days": 14,
    "reason": "Traveling abroad"
  }
  ```

#### 🔥 8. Unfreeze Subscription
- **Endpoint**: `POST /api/v1/gym/memberships/subscriptions/{id}/unfreeze`
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "message": "Subscription unfrozen successfully",
    "data": { "id": 10, "status": "active", "end_date": "2027-08-24" }
  }
  ```

#### ⬆️ 9. Upgrade Subscription Plan Tier
- **Endpoint**: `POST /api/v1/gym/memberships/subscriptions/{id}/upgrade`
- **Request Body**: `{ "new_plan_id": 2 }`

---

### 4.4 Billing & Payments APIs

#### 🧾 10. List Invoices
- **Endpoint**: `GET /api/v1/gym/billing/invoices`

#### 💸 11. Record Idempotent Payment
- **Endpoint**: `POST /api/v1/gym/billing/invoices/{id}/payments`
- **Request Body**:
  ```json
  {
    "amount": 12000.00,
    "payment_method": "upi",
    "transaction_reference": "UPI-8899112233",
    "idempotency_key": "PAY-KEY-99881122"
  }
  ```

---

### 4.5 Diet Plan Management APIs

#### 🥗 12. Create / Save Diet Plan (or Master Template)
- **Endpoint**: `POST /api/v1/gym/diet-plans`
- **Request Body**:
  ```json
  {
    "member_id": 1,
    "title": "Fat Loss & Lean Muscle 2000 kcal",
    "description": "High-protein deficit diet for 8 weeks",
    "target_calories": 2000,
    "protein_grams": 160,
    "carbs_grams": 180,
    "fat_grams": 60,
    "is_template": false,
    "start_date": "2026-08-10",
    "end_date": "2026-10-05",
    "meals": [
      { "meal": "Breakfast", "time": "08:00 AM", "items": "4 Egg whites, 50g Oats bowl with almonds", "calories": 450 },
      { "meal": "Lunch", "time": "01:30 PM", "items": "200g Grilled Chicken Breast, 150g Brown Rice, Salad", "calories": 650 },
      { "meal": "Evening Snack", "time": "05:30 PM", "items": "1 Scoop Whey Protein, 1 Banana", "calories": 250 },
      { "meal": "Dinner", "time": "08:30 PM", "items": "150g Paneer / Fish, Steamed Vegetables", "calories": 650 }
    ]
  }
  ```

#### 📋 13. List Diet Plans & Master Templates
- **Endpoint**: `GET /api/v1/gym/diet-plans`
- **Query Params**: `?is_template=true` or `?member_id=1`

#### 🔗 14. Assign Master Template to Member
- **Endpoint**: `POST /api/v1/gym/diet-plans/{id}/assign`
- **Request Body**: `{ "member_id": 1, "start_date": "2026-08-10" }`
