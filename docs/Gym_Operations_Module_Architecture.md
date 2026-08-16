# 🏋️ FitCore SaaS — Gym Operations & Business Logic Specification

**Document Version:** 2.2 (Production Architectural Contract — Final Approved)  
**Target File Path:** `docs/Gym_Operations_Module_Architecture.md`  
**Scope:** Final specification for Gym Operations, Database Schemas (VARCHAR + App Enums), Billing/Payment Idempotency, Subscription Lifecycles (Renewal, Freeze Accounting, Entitlement Upgrades, Check-In, Expiry & Cancellation), Concurrency, Audit Events, and Service-Driven Domain Architecture.

---

## 📌 1. Module Overview & Architecture

The **Gym Operations Module** powers day-to-day gym branch operations within multi-tenant database shards (`fitcore_shard_XX`). 
All lifecycle mutations are strictly managed by the **Service Layer** (`SubscriptionService`, `PaymentService`, `AttendanceService`) using pessimistic row-locking (`lockForUpdate()`), audit event generation (`SubscriptionEvent`), and idempotency protection on payments and attendance check-ins.

### Final Architecture Module Structure
```
Gym Operations
│
├── Members
│   ├── Member
│   └── MemberController
│
├── Memberships
│   ├── MembershipPlan
│   ├── MemberSubscription
│   ├── SubscriptionFreeze
│   ├── SubscriptionEvent (Audit Trail)
│   └── SubscriptionService
│       ├── subscribe()
│       ├── renew()
│       ├── freeze()
│       ├── unfreeze()
│       ├── upgrade()
│       ├── cancel()
│       └── expire()
│
├── Billing
│   ├── Invoice
│   ├── Payment
│   └── PaymentService
│
├── Attendance
│   ├── AttendanceLog
│   └── AttendanceService (Real-time Entitlement + Idempotency)
│
└── Automation
    ├── ProcessSubscriptionRenewals
    └── ExpireSubscriptions
```

---

## 🗄️ 2. Database Schema & Data Models

All operational tables reside in `fitcore_shard_XX` dynamic databases and extend `TenantModel` to enforce multi-tenancy (`WHERE tenant_id = $tenantId`).  
**Note**: All status fields use `VARCHAR` (instead of database `ENUM`) combined with PHP Backed Enums (`App\Enums\*`) for zero-migration schema evolution.

### PHP Backed Enums (`App\Enums\*`)
- **`MemberStatus`**: `active`, `inactive`, `disabled`, `terminated`
- **`SubscriptionStatus`**: `scheduled`, `active`, `frozen`, `past_due`, `expired`, `cancelled`, `upgraded`, `renewed`
- **`InvoiceStatus`**: `draft`, `unpaid`, `paid`, `partially_paid`, `void`, `refunded`
- **`PaymentStatus`**: `pending`, `completed`, `failed`, `refunded`

```
┌─────────────────────────┐        ┌─────────────────────────┐        ┌─────────────────────────┐
│        members          │        │    membership_plans     │        │      staff (RBAC)       │
├─────────────────────────┤        ├─────────────────────────┤        ├─────────────────────────┤
│ id (BIGINT)             │        │ id (BIGINT)             │        │ id (BIGINT)             │
│ tenant_id (BIGINT)      │        │ tenant_id (BIGINT)      │        │ tenant_id (BIGINT)      │
│ home_branch_id (BIGINT) │        │ name (VARCHAR)          │        │ primary_branch_id(BIGINT│
│ member_code (VARCHAR)   │        │ duration_days (INT)     │        │ role (VARCHAR)          │
│ first_name, last_name   │        │ price (DECIMAL 10,2)    │        │ permissions (JSON)      │
│ email, phone, status    │        │ max_freeze_days (INT)   │        └─────────────────────────┘
└────────────┬────────────┘        └────────────┬────────────┘
             │                                  │
             │      ┌───────────────────────────┘
             ▼      ▼
┌──────────────────────────────────┐        ┌──────────────────────────────────┐
│      member_subscriptions        │        │       subscription_freezes       │
├──────────────────────────────────┤        ├──────────────────────────────────┤
│ id (BIGINT)                      │        │ id (BIGINT)                      │
│ tenant_id (BIGINT)               │◄───────┤ subscription_id (BIGINT)         │
│ member_id (BIGINT)               │        │ freeze_start_date (DATE)         │
│ plan_id (BIGINT)                 │        │ requested_end_date (DATE)        │
│ start_date, end_date (DATE)      │        │ actual_unfreeze_date (DATE, null)│
│ auto_renew (BOOLEAN)             │        │ requested_days (INT)             │
│ status (VARCHAR)                 │        │ actual_days_frozen (INT, null)   │
└────────┬───────────┬─────────────┘        │ reason (VARCHAR)                 │
         │           │                      └──────────────────────────────────┘
         │           ▼
         │  ┌──────────────────────────────┐
         │  │     subscription_events      │
         │  ├──────────────────────────────┤
         │  │ id (BIGINT)                  │
         │  │ tenant_id (BIGINT)           │
         │  │ subscription_id (BIGINT)     │
         │  │ event_type (VARCHAR)         │
         │  │ old_status (VARCHAR, null)   │
         │  │ new_status (VARCHAR, null)   │
         │  │ actor_type, actor_id (BIGINT)│
         │  │ metadata (JSON)              │
         │  │ created_at (TIMESTAMP)       │
         │  └──────────────────────────────┘
         ▼
┌──────────────────────────────────┐        ┌──────────────────────────────────┐
│             invoices             │        │             payments             │
├──────────────────────────────────┤        ├──────────────────────────────────┤
│ id (BIGINT)                      │        │ id (BIGINT)                      │
│ tenant_id (BIGINT)               │◄───────┤ tenant_id (BIGINT)               │
│ member_id (BIGINT)               │        │ invoice_id (BIGINT)              │
│ subscription_id (BIGINT)         │        │ amount (DECIMAL 10,2)            │
│ invoice_number (VARCHAR)         │        │ payment_method (VARCHAR)         │
│ subtotal, tax, total (DECIMAL)   │        │ transaction_reference (VARCHAR)  │
│ status (VARCHAR)                 │        │ status (VARCHAR)                 │
└──────────────────────────────────┘        │ idempotency_key (VARCHAR, UNIQUE)│
                 │                          └──────────────────────────────────┘
                 ▼
┌──────────────────────────────────┐
│         attendance_logs          │
├──────────────────────────────────┤
│ id (BIGINT)                      │
│ tenant_id (BIGINT)               │
│ branch_id (BIGINT)               │
│ member_id (BIGINT)               │
│ subscription_id (BIGINT)         │
│ check_in_at (TIMESTAMP)          │
│ access_granted (BOOLEAN)         │
│ denial_reason (VARCHAR)          │
│ idempotency_key (VARCHAR, NULL)  │
└──────────────────────────────────┘
```

---

## ⚙️ 3. Core Business Logics & Life Cycle Workflows

### 🛡️ 3.0 Service Layer & Concurrency Locks

All subscription state mutations (renewal, freeze, unfreeze, upgrade, cancellation) **MUST** execute inside an atomic database transaction with pessimistic row locking (`lockForUpdate()`) and emit a `SubscriptionEvent` audit record:

```php
DB::transaction(function () use ($subscriptionId, $actor) {
    $subscription = MemberSubscription::query()
        ->lockForUpdate()
        ->findOrFail($subscriptionId);

    // Validate & mutate state...
    
    // Record Audit Event
    SubscriptionEvent::create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'event_type' => 'subscription.renewed',
        'old_status' => $oldStatus,
        'new_status' => $subscription->status,
        'actor_type' => get_class($actor),
        'actor_id' => $actor->id,
        'metadata' => ['renewed_at' => now()],
    ]);
});
```

---

### 🔄 3.1 Membership Renewal Logic & 15-Day Grace Period Workflow

#### 1. Status Transition Pipeline & 15-Day Grace Period
Rather than immediately marking a subscription as `expired` on day 1 post-expiry, the system maintains member visibility through a 15-day Grace Period:
```
[ Active ] ──(end_date reached)──► [ Past Due / Grace (Days 1–15) ] ──(Day 16 Cron)──► [ Expired / Left Gym ]
```
- **`active`**: Current subscription valid (`end_date >= TODAY`).
- **`past_due` (Grace Period)**: Expired within the last 15 days (`TODAY` is between `end_date + 1` and `end_date + 15`). Allows gym owners time to collect dues while keeping the member profile active.
- **`expired` (Left Gym)**: Lapsed > 15 days without renewal. Automatically transitioned by daily scheduled job (`ExpireSubscriptions`).

#### 2. Dynamic Start Date Calculation & Staff UI Override
When staff opens the **Renewal Modal**, the backend provides a pre-filled default start date, but allows full staff override:

- **Pre-filled Default `start_date` Rules**:
  - **Grace Period Renewal (`status == 'past_due'`)**: Default `start_date` = `previous_end_date + 1 day` (the date grace period started, ensuring continuous billing with no unbilled free days).
  - **Active Renewal (`status == 'active'`)**: Default `start_date` = `current_end_date + 1 day` (scheduled consecutive extension).
  - **Re-joining Post-Lapsed (`status == 'expired'`)**: Default `start_date` = `TODAY`.
- **Dynamic Override Ability**:
  - Staff can override the pre-filled `start_date` via the UI date picker (e.g., setting it to `TODAY` or an agreed-upon date).
- **Dynamic `end_date` Calculation**:
  - `new_end_date` is recalculated automatically based on the chosen `start_date` and plan duration:
    $$\text{new\_end\_date} = \text{start\_date} + \text{plan.duration\_days}$$

#### 3. Financial & Audit Obligation
- Generates an `invoice` record linked to the new `subscription_id`.
- Marks the previous subscription as `renewed`.
- Emits a `SubscriptionEvent` with metadata (`renewal_mode`, `grace_days_elapsed`, `overridden_start_date`).

---

### ❄️ 3.2 Freeze & Unfreeze Logic (Disambiguated Accounting)

Freeze quota enforcement is calculated strictly using **`actual_days_frozen`**:
$$\text{Used Quota} = \sum \text{actual\_days\_frozen} + \text{requested\_days} \le \text{plan.max\_freeze\_days}$$

#### 1. Freezing a Subscription (`POST /subscriptions/{id}/freeze`)
- Records `subscription_freezes` entry (`freeze_start_date`, `requested_end_date`, `requested_days`).
- Sets `member_subscriptions.status = 'frozen'`.

#### 2. Unfreezing & Extension Calculation (`POST /subscriptions/{id}/unfreeze`)
- Records `actual_unfreeze_date = TODAY`.
- Calculates actual elapsed freeze days:
  $$\text{actual\_days\_frozen} = \text{actual\_unfreeze\_date} - \text{freeze\_start\_date}$$
- Extends the subscription's `end_date`:
  $$\text{New End Date} = \text{Current End Date} + \text{actual\_days\_frozen}$$
- Sets `member_subscriptions.status = 'active'`.

---

### ⬆️ 3.3 Plan Upgrade (Term Replacement with Credit)

Upgrade follows the **Term Replacement Model**:  
$$\text{Upgrade} = \text{Terminate Old Subscription} + \text{Create New Full-Term Subscription} + \text{Apply Unused Credit}$$

1. **Unused Credit Formula**:
   $$\text{Daily Rate} = \frac{\text{Current Plan Price}}{\text{Current Plan Duration Days}}$$
   $$\text{Remaining Unused Days} = \text{Current End Date} - \text{TODAY}$$
   $$\text{Unused Credit} = \text{Daily Rate} \times \text{Remaining Unused Days}$$

2. **Net Payable Charge**:
   $$\text{Net Payable} = \max(0, \text{New Plan Price} - \text{Unused Credit})$$

3. **New Entitlement Creation**:
   - Old subscription `status = 'upgraded'`.
   - New subscription created: `start_date = TODAY`, `end_date = TODAY + new_plan.duration_days`, `status = 'active'`.
   - Invoice generated for `Net Payable` with `payments.idempotency_key`.

---

### 💳 3.4 Financial & Payment Idempotency Guard

All payment attempts enforce `payments.idempotency_key` (unique per tenant `(tenant_id, idempotency_key)`):
- If client retries a payment with an existing `idempotency_key`:
  - Returns the existing `Payment` record without reprocessing gateway charges.

---

### 🚪 3.5 Real-Time Check-In & Idempotency Engine

On check-in scan:
1. `members.status` MUST be `'active'`.
2. Real-time query verifies an `'active'` `member_subscriptions` record for `TODAY` (`start_date <= TODAY <= end_date` and `status != 'frozen'`).
3. Turnstile check-ins validate `idempotency_key` or a 60-second scan window per member/branch.

---

## 📡 4. Master API Endpoint Index

### 4.1 Members (`/api/v1/gym/members`)
| HTTP Method | Endpoint Path | Description |
|---|---|---|
| `GET` | `/members` | List members with filters (status, branch, search) |
| `POST` | `/members` | Register member profile |
| `GET` | `/members/{id}` | Get member profile + active sub details |
| `PUT/PATCH` | `/members/{id}` | Update member profile |
| `PATCH` | `/members/{id}/status` | Activate/disable/terminate member profile |

### 4.2 Subscriptions & Lifecycles (`/api/v1/gym/memberships`)
| HTTP Method | Endpoint Path | Description |
|---|---|---|
| `GET` | `/membership-plans` | List available membership plans |
| `POST` | `/membership-plans` | Create a new membership plan tier |
| `POST` | `/members/{id}/subscriptions` | Create new member subscription |
| `POST` | `/subscriptions/{id}/renew` | Renew subscription |
| `POST` | `/subscriptions/{id}/freeze` | Freeze subscription |
| `POST` | `/subscriptions/{id}/unfreeze` | Unfreeze subscription & recalculate `end_date` |
| `POST` | `/subscriptions/{id}/upgrade` | Upgrade plan (Term Replacement + Credit) |
| `POST` | `/subscriptions/{id}/cancel` | Cancel subscription |
| `GET` | `/subscriptions/{id}/events` | View subscription audit trail log |

### 4.3 Billing & Payments (`/api/v1/gym/billing`)
| HTTP Method | Endpoint Path | Description |
|---|---|---|
| `GET` | `/invoices` | List invoices for tenant |
| `GET` | `/invoices/{id}` | Get invoice details |
| `POST` | `/invoices/{id}/payments` | Record payment against invoice (Idempotent) |

### 4.4 Attendance (`/api/v1/gym/attendance`)
| HTTP Method | Endpoint Path | Description |
|---|---|---|
| `POST` | `/attendance/checkin` | Real-time check-in validation + idempotency guard |
| `GET` | `/attendance/today` | Today's check-in log for active branch |
| `GET` | `/attendance/logs` | Historical attendance log |

---

## 📅 5. Final Step-by-Step Implementation Roadmap

### Phase 1: Database Schema & Migrations
- [ ] `2026_08_07_000005_create_membership_plans_table.php`
- [ ] `2026_08_07_000006_create_member_subscriptions_table.php`
- [ ] `2026_08_07_000007_create_subscription_freezes_table.php`
- [ ] `2026_08_07_000008_create_subscription_events_table.php`
- [ ] `2026_08_07_000009_create_invoices_table.php`
- [ ] `2026_08_07_000010_create_payments_table.php`
- [ ] `2026_08_07_000011_create_attendance_logs_table.php`

### Phase 2: PHP Enums, Eloquent Models & Service Layer
- [ ] Create Enums: `MemberStatus`, `SubscriptionStatus`, `InvoiceStatus`, `PaymentStatus`.
- [ ] Models: `Member`, `MembershipPlan`, `MemberSubscription`, `SubscriptionFreeze`, `SubscriptionEvent`, `Invoice`, `Payment`, `AttendanceLog`.
- [ ] `App\Services\SubscriptionService.php` (All lifecycle mutations, locking & audit trail).
- [ ] `App\Services\PaymentService.php` (Idempotent billing & invoice processing).
- [ ] `App\Services\AttendanceService.php` (Real-time entitlement evaluation & check-in idempotency).

### Phase 3: Form Requests, Validation & Controllers
- [ ] Form Request classes & API Resources.
- [ ] Controllers: `MemberController`, `MembershipController`, `BillingController`, `AttendanceController`.

### Phase 4: Artisan Automation Commands
- [ ] `App\Console\Commands\ProcessSubscriptionRenewals.php`
- [ ] `App\Console\Commands\ExpireSubscriptions.php`

### Phase 5: Verification & Automated Tests
- [ ] Concurrency tests (pessimistic lock verification).
- [ ] Idempotency tests for Payments & Attendance Check-ins.
- [ ] Freeze accounting & Unfreeze end-date extension tests.
- [ ] Upgrade term replacement & credit calculation tests.
- [ ] Multi-tenant isolation & branch scoping tests.

---
*FitCore SaaS Engineering Team*
