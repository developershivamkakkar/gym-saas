# FitCore SaaS — Gym Instance Complete Production Frontend Specification (`{slug}.fitcore.io`)

**Document Version:** 6.0 (Complete with Plan Assignment Architecture & Data Models)  
**Target Domain:** `{slug}.fitcore.io` (or `http://{slug}.localhost:8000`)  
**Target Audience:** React / TypeScript AI Engineers & Senior Frontend Developers  
**Base Server URL:** `http://127.0.0.1:8000/api/v1/gym` (or `https://{slug}.fitcore.io/api/v1/gym`)  
**Required HTTP Headers:**
- `X-Tenant-Slug: <gym-slug>` (e.g. `gold-gym` or `matrix-gym-4580`) — *Mandatory on all API calls*
- `Authorization: Bearer <token>` (Required for protected endpoints)

---

## 1. Architecture & Tech Stack Guidelines

| Layer | Technology | Purpose |
|---|---|---|
| **Core Framework** | **React 18 / 19 + TypeScript** | Strongly-typed SPA component architecture |
| **State Management** | **Redux Toolkit (`@reduxjs/toolkit`)** | Session state (`gymAuthSlice`), active branch switcher context |
| **Data Fetching** | **TanStack React Query (`@tanstack/react-query`)** | Server state caching, automatic revalidation, `useQuery` & `useMutation` |
| **HTTP Client** | **Axios** | Interceptors for auto-injecting `X-Tenant-Slug` & `Bearer Token` |
| **Routing** | **React Router v6 (`react-router-dom`)** | Subdomain-aware SPA page routing |

---

## 2. Subdomain-Aware Axios Setup (`src/api/gymAxios.ts`)

```typescript
import axios from 'axios';
import { store } from '../store/store';
import { gymLogout } from '../store/slices/gymAuthSlice';

export const gymAxios = axios.create({
  baseURL: import.meta.env.VITE_GYM_API_BASE_URL || 'http://127.0.0.1:8000/api/v1/gym',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

gymAxios.interceptors.request.use((config) => {
  const host = window.location.hostname;
  let slug = localStorage.getItem('gym_slug');

  if (!slug && host.includes('.')) {
    slug = host.split('.')[0];
  }

  if (slug) {
    config.headers['X-Tenant-Slug'] = slug;
  }

  const token = localStorage.getItem('gym_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

gymAxios.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem('gym_token');
      store.dispatch(gymLogout());
      window.location.href = '/login';
    }
    return Promise.reject(error.response?.data || error);
  }
);
```

---

## 3. Frontend Pages & Feature Modules Overview

```
src/
├── features/
│   ├── auth/              ← Login & Auth Context
│   ├── dashboard/         ← Gym Dashboard Widgets & Quota Progress
│   ├── branches/          ← Multi-Branch Management & Financial P&L
│   ├── members/           ← Member Profiles, Registration & Status Toggle
│   ├── memberships/       ← Dynamic Plans, Subscriptions, Renewals, Freezes & Upgrades
│   ├── billing/           ← Invoices & Idempotent Payments Modal
│   └── dietPlans/         ← Diet Plans, Meal Schedule Builder & Template Assigner
```

---

## 4. Module Specifications & API Integration Guide

---

### 4.1 Module 1: Authentication & User Session

#### Components: `GymLoginPage` (`src/features/auth/GymLoginPage.tsx`)
- **API Endpoint**: `POST /auth/login`
- **Request Payload**:
  ```json
  { "email": "owner@goldsgym.com", "password": "password123" }
  ```
- **Response (200 OK)**:
  ```json
  {
    "success": true,
    "token": "8ae7815506b25...",
    "tenant": { "id": "TNT-000001", "name": "Gold Gym", "slug": "gold-gym", "plan_tier": "basic" },
    "data": { "id": 1, "name": "John Owner", "email": "owner@goldsgym.com", "role": "owner" }
  }
  ```
- **Frontend Action**: Save `token` to `localStorage` and update Redux auth state.

---

### 4.2 Module 2: Dashboard & Physical Branches

#### Components: `GymDashboardPage`, `BranchListPage`, `BranchFinancialsWidget`
- **Endpoints**:
  - `GET /dashboard` (Overview counters)
  - `GET /branches` (List branches & check plan limits)
  - `POST /branches` (Create physical location)
  - `GET /branches/{id}/financials` (Branch financial P&L)
- **Branch Limit Handling**:
  - If `POST /branches` fails with `422 Unprocessable Entity` (`"Branch limit reached..."`), display the **Plan Upgrade Banner/Modal**.

---

### 4.3 Module 3: Member Management & Dynamic Codes with Suffix Customization

#### Components: `MemberListPage`, `RegisterMemberModal`, `MemberProfilePage`
- **Endpoints**:
  - `GET /members?search=john&status=active&per_page=15`
  - `POST /members` (Register member with optional custom prefix/suffix override)
  - `GET /members/{id}`
  - `PUT/PATCH /members/{id}`

#### Member Code Generation Logic (Frontend Display):
**Auto-generated member codes follow this priority:**
1. **Custom Code Override** — If staff provides explicit `member_code`, use as-is
2. **Request-Level Suffix** — If `member_suffix` passed in request, override global setting
3. **Global Gym Suffix** — Falls back to gym config `member_suffix` (one-time setup)
4. **Format**: `{PREFIX}-{SEQUENTIAL_NUMBER}{-SUFFIX}` (e.g., `SVS-1001` or `SVS-1001-VIP`)

#### Register Member Request Payload:
```json
{
  "first_name": "Jane",
  "last_name": "Smith",
  "phone": "+1999777666",
  "email": "jane@example.com",
  "gender": "female",
  "date_of_birth": "1995-05-12",
  "branch_id": 1,
  "member_prefix": "SVS",        // Optional — override gym's default prefix
  "member_suffix": "GOLD"        // Optional — override gym's global suffix (e.g., VIP, GOLD, PRO)
  // If both omitted, code = {gym_prefix}-{next_number}{-gym_suffix} = SVS-1001-VIP
}
```

#### Response (201 Created):
```json
{
  "success": true,
  "message": "Member registered successfully with Code [SVS-1001-GOLD]",
  "data": {
    "id": 15,
    "tenant_id": 1025,
    "member_code": "SVS-1001-GOLD",
    "first_name": "Jane",
    "last_name": "Smith",
    "phone": "+1999777666",
    "email": "jane@example.com",
    "gender": "female",
    "status": "active",
    "created_at": "2026-08-08T10:30:00Z"
  }
}
```

#### Member Registration Form (UI Component):
```typescript
// 1. Auto-calculate preview of member code
const [formData, setFormData] = useState({
  first_name: '',
  member_suffix: '', // Optional override
});

const [autoGeneratedCode, setAutoGeneratedCode] = useState('SVS-1001-VIP'); // Preview

// Show auto-generated code to staff (read-only or copyable)
<TextField
  label="Auto-Generated Member Code"
  value={autoGeneratedCode}
  disabled
  helperText="System generates: {Prefix}-{Number}{-Suffix}"
/>

// Optional: Allow override of suffix for this specific member
<TextField
  label="Override Member Suffix (Optional)"
  placeholder="Leave blank to use gym default"
  value={formData.member_suffix}
  onChange={(e) => setFormData({...formData, member_suffix: e.target.value})}
  helperText="E.g., GOLD creates: SVS-1001-GOLD"
/>
```

#### Complete Member Onboarding Flow (Registration → Plan Assignment)

**Step 1: Register Member**
```bash
POST /members
{
  "first_name": "Jane",
  "phone": "9876543210",
  "member_suffix": "GOLD"
}
→ Returns: member_id = 15, member_code = "SVS-1001-GOLD"
```

**Step 2: Immediately Redirect to Plan Assignment**
```bash
POST /members/15/subscriptions
{
  "plan_id": 3,
  "start_date": "2026-08-10",
  "auto_renew": true
}
→ Subscription created, Invoice generated
```

**Result:**
- Member: SVS-1001-GOLD (Active)
- Plan: Annual Gold (₹20,000, 365 days)
- Subscription: Active from 2026-08-10 to 2027-08-10
- Invoice: Awaiting payment

**Frontend UX Pattern (Two-Step Wizard):**
```typescript
const MemberOnboardingWizard = () => {
  const [step, setStep] = useState(1); // 1: Registration, 2: Plan Assignment
  const [memberId, setMemberId] = useState(null);

  const handleMemberCreated = (member) => {
    setMemberId(member.id);
    setStep(2); // Move to plan assignment
  };

  return (
    <>
      {step === 1 && <MemberRegistrationForm onSuccess={handleMemberCreated} />}
      {step === 2 && (
        <SubscriptionModal memberId={memberId} onSuccess={() => alert('Onboarding Complete!')} />
      )}
    </>
  );
};
```

---


### 4.4 Module 4: Membership Plans & Subscription Lifecycles ⭐

#### Components: `PlanManagementPage`, `PlanListPage`, `SubscriptionModal`, `RenewModal`, `FreezeModal`, `UpgradeModal`, `PlanAssignmentFlow`

#### **Architecture Overview**

```
MembershipPlan (Template)
     ↓
MemberSubscription (Assignment to Member)
     ├─→ start_date: "2026-08-10"
     ├─→ end_date: "2027-08-10"
     ├─→ status: "active" | "expired" | "renewed" | "paused"
     ├─→ auto_renew: true/false
     ├─→ Triggers → Invoice (auto-billed)
     └─→ Creates → SubscriptionEvent (audit trail)
```

---

#### **Database Schema**

##### **membership_plans** (Plan Templates - Reusable across members)
```json
{
  "id": 1,
  "tenant_id": 1025,
  "name": "Annual Gold",
  "description": "12-month premium membership",
  "duration_days": 365,
  "price": 20000.00,
  "max_freeze_days": 30,
  "is_active": true,
  "created_at": "2026-08-01T10:00:00Z",
  "updated_at": "2026-08-01T10:00:00Z"
}
```

##### **member_subscriptions** (Plan Assignments - One per member term)
```json
{
  "id": 50,
  "tenant_id": 1025,
  "member_id": 15,
  "plan_id": 1,
  "start_date": "2026-08-10",
  "end_date": "2027-08-10",
  "auto_renew": true,
  "status": "active",
  "created_at": "2026-08-08T10:00:00Z",
  "updated_at": "2026-08-08T10:00:00Z"
}
```

---

#### **Subscription Lifecycle States**

```
┌─────────────┐
│   ACTIVE    │ ← Member is actively subscribed
└──────┬──────┘
       │ (end_date reached + auto_renew = true)
       ↓
┌──────────────────────────────┐
│ SubscriptionService::renew() │ ← Automated or manual
└──────┬───────────────────────┘
       ↓
┌─────────────────┐
│ RENEWED         │ ← New subscription created
└────────┬────────┘
         │ (New subscription status = ACTIVE)
         ↓
┌──────────────────────────────┐
│   ACTIVE (New Term)          │
│   start: 2027-08-11          │
│   end: 2028-08-11            │
└──────────────────────────────┘

OR (No Auto-Renew)

┌─────────────┐
│   ACTIVE    │
└──────┬──────┘
       │ (end_date reached)
       ↓
┌──────────────┐
│   EXPIRED    │ ← Manual staff intervention needed
└──────┬───────┘
       │ (Staff renews manually)
       ↓
┌──────────────────────────┐
│ SubscriptionService::    │
│ renew(overrideStartDate) │
└──────┬───────────────────┘
       ↓
┌──────────────────────────────┐
│ RENEWED → New ACTIVE         │
└──────────────────────────────┘

OR (Freeze/Pause)

┌─────────────┐
│   ACTIVE    │
└──────┬──────┘
       │ (Staff requests freeze)
       ↓
┌──────────────────────────────────┐
│ SubscriptionService::freeze()    │
│ • Creates SubscriptionFreeze     │
│ • Extends end_date by N days     │
│ • Maintains ACTIVE status        │
└──────┬───────────────────────────┘
       ↓
┌──────────────────────────┐
│ ACTIVE (Extended)        │
│ Original end: 2027-08-10 │
│ New end: 2027-08-24      │ (+14 days freeze)
└──────────────────────────┘
```

---

#### **1. List All Membership Plans**

**Endpoint:** `GET /memberships/plans`

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "tenant_id": 1025,
      "name": "Basic Monthly",
      "description": "1-month membership",
      "duration_days": 30,
      "price": 2000.00,
      "max_freeze_days": 15,
      "is_active": true
    },
    {
      "id": 2,
      "tenant_id": 1025,
      "name": "Quarterly",
      "description": "3-month membership",
      "duration_days": 90,
      "price": 5500.00,
      "max_freeze_days": 20,
      "is_active": true
    },
    {
      "id": 3,
      "tenant_id": 1025,
      "name": "Annual Gold",
      "description": "12-month premium membership",
      "duration_days": 365,
      "price": 20000.00,
      "max_freeze_days": 30,
      "is_active": true
    }
  ]
}
```

**Frontend Component (PlanListPage):**
```typescript
const PlanListPage = () => {
  const { data: plans, isLoading } = useQuery('plans', () =>
    gymAxios.get('/memberships/plans').then(res => res.data.data)
  );

  return (
    <Container>
      <Typography variant="h4">Membership Plans</Typography>
      <Grid container spacing={2}>
        {plans?.map(plan => (
          <Grid item xs={12} sm={6} md={4} key={plan.id}>
            <Card>
              <CardContent>
                <Typography variant="h6">{plan.name}</Typography>
                <Typography variant="body2">{plan.description}</Typography>
                <Typography variant="h5" sx={{ mt: 2 }}>
                  ₹{plan.price}
                </Typography>
                <Typography variant="caption">
                  {plan.duration_days} days | Max Freeze: {plan.max_freeze_days} days
                </Typography>
              </CardContent>
            </Card>
          </Grid>
        ))}
      </Grid>
    </Container>
  );
};
```

---

#### **2. Create New Membership Plan (Admin)**

**Endpoint:** `POST /memberships/plans`

**Request:**
```json
{
  "name": "Annual Gold",
  "description": "12-month premium membership",
  "duration_days": 365,
  "price": 20000.00,
  "max_freeze_days": 30
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Membership plan created successfully",
  "data": {
    "id": 3,
    "tenant_id": 1025,
    "name": "Annual Gold",
    "description": "12-month premium membership",
    "duration_days": 365,
    "price": 20000.00,
    "max_freeze_days": 30,
    "is_active": true,
    "created_at": "2026-08-08T10:30:00Z"
  }
}
```

---

#### **3. Assign Plan to Member (CORE FLOW)**

**Endpoint:** `POST /members/{memberId}/subscriptions`

**Request:**
```json
{
  "plan_id": 3,
  "start_date": "2026-08-10",
  "auto_renew": true
}
```

**Response (201 Created):**
```json
{
  "success": true,
  "message": "Subscription created successfully",
  "data": {
    "id": 50,
    "tenant_id": 1025,
    "member_id": 15,
    "plan_id": 3,
    "start_date": "2026-08-10",
    "end_date": "2027-08-10",
    "auto_renew": true,
    "status": "active"
  }
}
```

**What Happens Automatically:**
1. ✅ Subscription record created
2. ✅ Member status updated to "active"
3. ✅ Invoice generated (UNPAID status)
4. ✅ SubscriptionEvent logged (audit trail)

**Frontend Component (SubscriptionModal):**
```typescript
const SubscriptionModal = ({ memberId, onSuccess }) => {
  const [formData, setFormData] = useState({
    plan_id: '',
    start_date: new Date().toISOString().split('T')[0],
    auto_renew: true,
  });

  const { data: plans } = useQuery('plans', () =>
    gymAxios.get('/memberships/plans').then(res => res.data.data)
  );

  const subscribeMutation = useMutation(
    (payload) => gymAxios.post(`/members/${memberId}/subscriptions`, payload),
    { onSuccess }
  );

  const handleSubmit = () => {
    subscribeMutation.mutate(formData);
  };

  return (
    <Dialog open={true}>
      <DialogTitle>Assign Plan to Member</DialogTitle>
      <DialogContent>
        <Select
          label="Select Plan"
          value={formData.plan_id}
          onChange={(e) => setFormData({...formData, plan_id: e.target.value})}
        >
          {plans?.map(plan => (
            <MenuItem key={plan.id} value={plan.id}>
              {plan.name} - ₹{plan.price} ({plan.duration_days} days)
            </MenuItem>
          ))}
        </Select>

        <TextField
          label="Start Date"
          type="date"
          value={formData.start_date}
          onChange={(e) => setFormData({...formData, start_date: e.target.value})}
        />

        <FormControlLabel
          control={<Checkbox checked={formData.auto_renew} onChange={(e) => setFormData({...formData, auto_renew: e.target.checked})} />}
          label="Auto-Renew on Expiry"
        />

        <Typography variant="body2" sx={{ mt: 2 }}>
          Subscription Period: {formData.start_date} → {addDays(formData.start_date, 365)}
        </Typography>
      </DialogContent>
      <DialogActions>
        <Button onClick={handleSubmit} variant="contained">Assign Plan</Button>
      </DialogActions>
    </Dialog>
  );
};
```

---

#### **4. Renew Subscription (Extend/Upgrade)**

**Endpoint:** `POST /memberships/subscriptions/{id}/renew`

**Request:**
```json
{
  "plan_id": 2,
  "override_start_date": "2027-08-11",
  "auto_renew": true
}
```

**Logic:**
- If no `override_start_date`: Auto-calculated as day after current `end_date`
- Can upgrade to different plan: `plan_id` optional
- Creates new subscription (old marked as RENEWED)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription renewed successfully",
  "data": {
    "id": 51,
    "tenant_id": 1025,
    "member_id": 15,
    "plan_id": 2,
    "start_date": "2027-08-11",
    "end_date": "2027-11-09",
    "auto_renew": true,
    "status": "active"
  }
}
```

**Frontend Component (RenewModal):**
```typescript
const RenewModal = ({ subscriptionId, memberId, currentPlan, currentEndDate, onSuccess }) => {
  const [formData, setFormData] = useState({
    plan_id: currentPlan?.id || '',
    override_start_date: addDays(currentEndDate, 1).toISOString().split('T')[0],
    auto_renew: true,
  });

  const { data: plans } = useQuery('plans', () =>
    gymAxios.get('/memberships/plans').then(res => res.data.data)
  );

  const renewMutation = useMutation(
    (payload) => gymAxios.post(`/memberships/subscriptions/${subscriptionId}/renew`, payload),
    { onSuccess }
  );

  const selectedPlan = plans?.find(p => p.id === parseInt(formData.plan_id));

  const handleRenew = () => {
    renewMutation.mutate(formData);
  };

  return (
    <Dialog open={true}>
      <DialogTitle>Renew / Upgrade Subscription</DialogTitle>
      <DialogContent>
        <Typography variant="body2" sx={{ mb: 2 }}>
          Current Plan: <strong>{currentPlan?.name}</strong> (Expires: {currentEndDate})
        </Typography>

        <Select
          label="Select Plan (or keep current)"
          value={formData.plan_id}
          onChange={(e) => setFormData({...formData, plan_id: e.target.value})}
        >
          {plans?.map(plan => (
            <MenuItem key={plan.id} value={plan.id}>
              {plan.name} - ₹{plan.price}
            </MenuItem>
          ))}
        </Select>

        <TextField
          label="Renewal Start Date"
          type="date"
          value={formData.override_start_date}
          onChange={(e) => setFormData({...formData, override_start_date: e.target.value})}
        />

        {selectedPlan && (
          <Typography variant="body2" sx={{ mt: 2 }}>
            New Period: {formData.override_start_date} → {addDays(formData.override_start_date, selectedPlan.duration_days)}
          </Typography>
        )}
      </DialogContent>
      <DialogActions>
        <Button onClick={handleRenew} variant="contained">Renew</Button>
      </DialogActions>
    </Dialog>
  );
};
```

---

#### **5. Freeze Subscription (Pause without Canceling)**

**Endpoint:** `POST /memberships/subscriptions/{id}/freeze`

**Request:**
```json
{
  "freeze_start_date": "2026-09-01",
  "requested_days": 14,
  "reason": "Vacation / Medical Leave"
}
```

**What Happens:**
1. Creates `SubscriptionFreeze` record
2. Extends subscription `end_date` by requested days
3. Maintains "active" status (not paused)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription frozen successfully",
  "data": {
    "id": 1,
    "subscription_id": 50,
    "freeze_start_date": "2026-09-01",
    "requested_days": 14,
    "reason": "Vacation",
    "status": "pending"
  }
}
```

**Frontend Component (FreezeModal):**
```typescript
const FreezeModal = ({ subscriptionId, maxFreezeDays, currentEndDate, onSuccess }) => {
  const [freezeData, setFreezeData] = useState({
    freeze_start_date: new Date().toISOString().split('T')[0],
    requested_days: 7,
    reason: '',
  });

  const freezeMutation = useMutation(
    (payload) => gymAxios.post(`/memberships/subscriptions/${subscriptionId}/freeze`, payload),
    { onSuccess }
  );

  const newEndDate = addDays(currentEndDate, freezeData.requested_days);

  return (
    <Dialog open={true}>
      <DialogTitle>Freeze Subscription</DialogTitle>
      <DialogContent>
        <TextField
          label="Freeze Start Date"
          type="date"
          value={freezeData.freeze_start_date}
          onChange={(e) => setFreezeData({...freezeData, freeze_start_date: e.target.value})}
        />

        <TextField
          label="Requested Freeze Days"
          type="number"
          value={freezeData.requested_days}
          onChange={(e) => setFreezeData({...freezeData, requested_days: parseInt(e.target.value)})}
          inputProps={{ min: 1, max: maxFreezeDays }}
        />

        <TextField
          label="Reason (Optional)"
          multiline
          rows={3}
          value={freezeData.reason}
          onChange={(e) => setFreezeData({...freezeData, reason: e.target.value})}
          placeholder="Vacation, Medical leave, etc."
        />

        <Alert severity="info" sx={{ mt: 2 }}>
          Current End Date: <strong>{currentEndDate}</strong>
          <br />
          New End Date: <strong>{newEndDate}</strong> (+{freezeData.requested_days} days)
        </Alert>
      </DialogContent>
      <DialogActions>
        <Button onClick={() => freezeMutation.mutate(freezeData)} variant="contained">
          Freeze
        </Button>
      </DialogActions>
    </Dialog>
  );
};
```

---

#### **6. Unfreeze Subscription**

**Endpoint:** `POST /memberships/subscriptions/{id}/unfreeze`

**Request:** (No body required)

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription unfrozen successfully",
  "data": {
    "id": 50,
    "status": "active",
    "end_date": "2027-08-24"
  }
}
```

---

#### **7. Upgrade Subscription (Same Member, Better Plan)**

**Endpoint:** `POST /memberships/subscriptions/{id}/upgrade`

**Request:**
```json
{
  "new_plan_id": 3
}
```

**What Happens:**
1. Calculates prorated credit from old plan
2. Applies credit to new plan
3. Creates new subscription with same end_date
4. Marks old as RENEWED

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Subscription upgraded successfully",
  "data": {
    "id": 51,
    "member_id": 15,
    "plan_id": 3,
    "start_date": "2026-08-10",
    "end_date": "2027-08-10",
    "status": "active",
    "upgrade_credit": 5000.00
  }
}
```

---

#### **8. Member Subscription Profile (Get Member's Active Subscription)**

**Endpoint:** `GET /members/{id}` (or dedicated endpoint)

**Response includes:**
```json
{
  "success": true,
  "data": {
    "id": 15,
    "member_code": "SVS-1001-VIP",
    "first_name": "Jane",
    "status": "active",
    "current_subscription": {
      "id": 50,
      "plan_id": 3,
      "plan_name": "Annual Gold",
      "start_date": "2026-08-10",
      "end_date": "2027-08-10",
      "days_remaining": 337,
      "status": "active",
      "auto_renew": true,
      "freezes": []
    }
  }
}
```

---

### 4.5 Module 5: Billing & Payments

#### Components: `InvoiceListPage`, `InvoiceDetailModal`, `RecordPaymentModal`
- **Endpoints**:
  - `GET /billing/invoices`
  - `GET /billing/invoices/{id}`
  - `POST /billing/invoices/{id}/payments`
- **Record Payment Payload (With Idempotency Key Guard)**:
  ```json
  {
    "amount": 12000.00,
    "payment_method": "upi",
    "transaction_reference": "UPI-9988776655",
    "idempotency_key": "PAY-UUID-889900" // Prevents double click / double charge
  }
  ```

---

### 4.6 Module 6: Diet Plan Management & Meal Builder ⭐

#### Components: `DietPlanListPage`, `MealScheduleBuilder`, `AssignDietTemplateModal`

1. **Meals Form Structure**:
   Frontend maintains `meals` array in state and submits JSON payload:

   ```typescript
   export interface MealItem {
     meal: string;     // e.g. "Breakfast"
     time: string;     // e.g. "08:00 AM"
     items: string;    // e.g. "4 Egg Whites, 50g Oats Bowl"
     calories: number; // e.g. 450
   }
   ```

2. **Create Diet Plan Payload (`POST /diet-plans`)**:
   ```json
   {
     "member_id": 1,
     "title": "Fat Loss 2000 kcal Plan",
     "description": "High protein deficit plan",
     "target_calories": 2000,
     "protein_grams": 160,
     "carbs_grams": 180,
     "fat_grams": 60,
     "is_template": false,
     "start_date": "2026-08-10",
     "end_date": "2026-10-05",
     "meals": [
       { "meal": "Breakfast", "time": "08:00 AM", "items": "4 Egg Whites, Oats bowl", "calories": 450 },
       { "meal": "Lunch", "time": "01:30 PM", "items": "200g Grilled Chicken, Rice", "calories": 650 },
       { "meal": "Evening Snack", "time": "05:30 PM", "items": "1 Scoop Whey Protein, Banana", "calories": 250 },
       { "meal": "Dinner", "time": "08:30 PM", "items": "150g Fish / Paneer, Vegetables", "calories": 650 }
     ]
   }
   ```

3. **Save Master Template (`POST /diet-plans`)**:
   - Set `"is_template": true` and omit `member_id`.

4. **Assign Master Template to Member (`POST /diet-plans/{id}/assign`)**:
   - Payload: `{ "member_id": 1, "start_date": "2026-08-10" }`

---

### 4.7 Module 7: Gym Settings & Configuration ⭐ **NEW**

#### Components: `GymSettingsPage`, `GymConfigForm`
- **Endpoints**:
  - `GET /gym/config` (Fetch current gym config)
  - `PATCH /gym/config` (Update gym settings)

#### Get Current Gym Configuration (`GET /gym/config`):
```json
{
  "success": true,
  "data": {
    "id": 1,
    "tenant_id": 1025,
    "gym_name": "Gold Gym",
    "logo_url": "https://cdn.fitcore.io/logos/gold-gym.png",
    "primary_color": "#3B82F6",
    "currency": "INR",
    "tax_rate": 18.00,
    "member_prefix": "SVS",
    "member_suffix": "VIP",                    // ← New: Global default suffix for all new members
    "next_member_number": 1052,                // ← Internal sequential counter
    "support_email": "support@goldgym.com",
    "support_phone": "+91-9999999999"
  }
}
```

#### Gym Settings Form (Frontend):
```typescript
const [gymConfig, setGymConfig] = useState({
  gym_name: 'Gold Gym',
  primary_color: '#3B82F6',
  currency: 'INR',
  tax_rate: 18.00,
  member_prefix: 'SVS',
  member_suffix: 'VIP',              // ← New: Editable global suffix
  support_email: 'support@goldgym.com',
  support_phone: '+91-9999999999'
});

// Form Fields:
<TextField
  label="Gym Name"
  value={gymConfig.gym_name}
  onChange={(e) => setGymConfig({...gymConfig, gym_name: e.target.value})}
/>

<TextField
  label="Member Code Prefix"
  value={gymConfig.member_prefix}
  onChange={(e) => setGymConfig({...gymConfig, member_prefix: e.target.value})}
  helperText="Applied to all new members (e.g., SVS, MVP)"
  placeholder="SVS"
  maxLength={20}
/>

<TextField
  label="Member Code Suffix (Optional)"
  value={gymConfig.member_suffix}
  onChange={(e) => setGymConfig({...gymConfig, member_suffix: e.target.value})}
  helperText="One-time setup: Applied to all new members (e.g., VIP, GOLD, PRO). Override per-member if needed."
  placeholder="VIP"
  maxLength={20}
/>

<TextField
  label="Currency"
  select
  value={gymConfig.currency}
  onChange={(e) => setGymConfig({...gymConfig, currency: e.target.value})}
>
  <MenuItem value="INR">INR (Indian Rupee)</MenuItem>
  <MenuItem value="USD">USD (US Dollar)</MenuItem>
  <MenuItem value="EUR">EUR (Euro)</MenuItem>
</TextField>

<TextField
  label="Tax Rate (%)"
  type="number"
  value={gymConfig.tax_rate}
  onChange={(e) => setGymConfig({...gymConfig, tax_rate: parseFloat(e.target.value)})}
/>

<TextField
  label="Support Email"
  type="email"
  value={gymConfig.support_email}
  onChange={(e) => setGymConfig({...gymConfig, support_email: e.target.value})}
/>

<TextField
  label="Support Phone"
  value={gymConfig.support_phone}
  onChange={(e) => setGymConfig({...gymConfig, support_phone: e.target.value})}
/>
```

#### Update Gym Configuration (`PATCH /gym/config`):
```json
{
  "gym_name": "Gold Gym",
  "primary_color": "#3B82F6",
  "currency": "INR",
  "tax_rate": 18.00,
  "member_prefix": "SVS",
  "member_suffix": "VIP",           // ← New: Can be null for no suffix
  "support_email": "support@goldgym.com",
  "support_phone": "+91-9999999999"
}
```

#### Response (200 OK):
```json
{
  "success": true,
  "message": "Gym configuration updated successfully",
  "data": {
    "id": 1,
    "gym_name": "Gold Gym",
    "member_suffix": "VIP",           // ← Updated global suffix
    "next_member_number": 1052,
    "currency": "INR",
    "tax_rate": 18.00
  }
}
```

#### UI/UX Notes:
- **One-time Setup**: Gym owner configures suffix once during initial setup
- **Re-configurable**: Can update suffix at any time from Gym Settings
- **Per-Member Override**: Staff can override global suffix when registering individual members
- **Member Code Preview**: Show live preview on member registration form: `{PREFIX}-{NEXT_NUMBER}{-SUFFIX}`

---

## 5. UI Routing Structure (`src/routes/AppRoutes.tsx`)

| Path | Protected | Component | Description |
|---|---|---|---|
| `/login` | Public | `GymLoginPage` | Gym Staff Login |
| `/dashboard` | Protected | `GymDashboardPage` | Overview Counters & Quotas |
| `/settings` | Protected | `GymSettingsPage` | **NEW: Gym Config, Member Code Prefix/Suffix Setup** |
| `/branches` | Protected | `BranchListPage` | Multi-Branch Physical Locations |
| `/members` | Protected | `MemberListPage` | Member Registry & Status Filters |
| `/members/:id` | Protected | `MemberProfilePage` | Profile & Subscriptions |
| `/memberships/plans` | Protected | `PlanManagementPage` | Dynamic Plan Tiers |
| `/billing/invoices` | Protected | `InvoiceListPage` | Invoices & Payment Recording |
| `/diet-plans` | Protected | `DietPlanListPage` | Member Diets & Master Templates |
| `/diet-plans/builder` | Protected | `MealScheduleBuilder` | Interactive Meal Builder Form |

---

## 6. Data Models & Relationships Reference

### Core Entity Relationships

```
┌─────────────────────────────────────────────────────────────────┐
│                        GymConfig                                │
├─────────────────────────────────────────────────────────────────┤
│ • gym_name                                                      │
│ • member_prefix (default: "SVS")                               │
│ • member_suffix (global default, e.g., "VIP")                 │
│ • next_member_number (sequential counter)                      │
│ • currency, tax_rate, support_email, support_phone            │
│ • logo_url, primary_color                                      │
└────────────┬────────────────────────────────────────────────────┘
             │
             │ (Configures)
             ↓
┌─────────────────────────────────────────────────────────────────┐
│                        Member                                   │
├─────────────────────────────────────────────────────────────────┤
│ • member_code (auto-generated: SVS-1001-VIP)                   │
│ • first_name, last_name                                        │
│ • phone (unique per gym)                                       │
│ • email, gender, date_of_birth                                │
│ • status: "active" | "expired" | "paused"                     │
│ • branch_id (multi-location support)                          │
└────────────┬────────────────────────────────────────────────────┘
             │
             │ (1-to-Many)
             ↓
┌─────────────────────────────────────────────────────────────────┐
│                   MemberSubscription                            │
├─────────────────────────────────────────────────────────────────┤
│ • plan_id (FK → MembershipPlan)                               │
│ • start_date, end_date                                        │
│ • status: "active" | "expired" | "renewed" | "paused"        │
│ • auto_renew: boolean                                         │
│ • Created when plan assigned to member                        │
└────────────┬─────────────────────┬────────────────────────────┘
             │                     │
             │ (Many)              │ (Many)
             ↓                     ↓
    ┌──────────────────┐  ┌──────────────────┐
    │ SubscriptionFreeze│  │ SubscriptionEvent│
    ├──────────────────┤  ├──────────────────┤
    │ • freeze_start   │  │ • event_type     │
    │ • requested_days │  │ • old_status     │
    │ • reason         │  │ • new_status     │
    │ • status         │  │ • actor_type     │
    │ (Extends end_date)│  │ (Audit trail)    │
    └──────────────────┘  └──────────────────┘

             │ (Many)
             ↓
┌─────────────────────────────────────────────────────────────────┐
│                        Invoice                                  │
├─────────────────────────────────────────────────────────────────┤
│ • invoice_number (auto-generated: INV-XXXXX)                   │
│ • subtotal, tax, total                                        │
│ • status: "unpaid" | "paid" | "overdue"                       │
│ • created_at (auto-generated on subscription)                 │
└────────────┬────────────────────────────────────────────────────┘
             │ (1-to-Many)
             ↓
┌─────────────────────────────────────────────────────────────────┐
│                        Payment                                  │
├─────────────────────────────────────────────────────────────────┤
│ • amount                                                       │
│ • payment_method: "upi" | "card" | "bank_transfer"           │
│ • transaction_reference (unique per payment)                  │
│ • idempotency_key (prevents double charging)                  │
│ • status: "pending" | "completed" | "failed"                 │
└─────────────────────────────────────────────────────────────────┘


┌─────────────────────────────────────────────────────────────────┐
│                   MembershipPlan                                │
├─────────────────────────────────────────────────────────────────┤
│ • name (template: "Annual Gold", "Basic Monthly")             │
│ • description                                                 │
│ • duration_days (how long the membership lasts)              │
│ • price (cost of plan)                                       │
│ • max_freeze_days (max pause duration allowed)               │
│ • is_active: boolean                                         │
│ • Reusable across multiple members                           │
└────────────┬────────────────────────────────────────────────────┘
             │ (1-to-Many: One plan, many subscriptions)
             ↓
     (See MemberSubscription above)
```

---

### TypeScript Interfaces for Frontend

```typescript
// Gym Configuration
interface GymConfig {
  id: number;
  tenant_id: number;
  gym_name: string;
  logo_url?: string;
  primary_color: string;
  currency: string;
  tax_rate: number;
  member_prefix: string;
  member_suffix?: string;
  next_member_number: number;
  support_email?: string;
  support_phone?: string;
  created_at: string;
  updated_at: string;
}

// Membership Plan Template
interface MembershipPlan {
  id: number;
  tenant_id: number;
  name: string;
  description?: string;
  duration_days: number;
  price: number;
  max_freeze_days: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
}

// Member Record
interface Member {
  id: number;
  tenant_id: number;
  branch_id?: number;
  member_code: string;
  first_name: string;
  last_name?: string;
  email?: string;
  phone: string;
  gender?: 'male' | 'female' | 'other';
  date_of_birth?: string;
  status: 'active' | 'expired' | 'paused';
  current_subscription?: MemberSubscription;
  created_at: string;
  updated_at: string;
}

// Subscription (Plan Assignment)
interface MemberSubscription {
  id: number;
  tenant_id: number;
  member_id: number;
  plan_id: number;
  plan?: MembershipPlan;
  start_date: string; // YYYY-MM-DD
  end_date: string;   // YYYY-MM-DD
  auto_renew: boolean;
  status: 'active' | 'expired' | 'renewed' | 'paused';
  days_remaining?: number; // Calculated frontend
  freezes?: SubscriptionFreeze[];
  events?: SubscriptionEvent[];
  invoices?: Invoice[];
  created_at: string;
  updated_at: string;
}

// Subscription Freeze (Pause)
interface SubscriptionFreeze {
  id: number;
  subscription_id: number;
  freeze_start_date: string;
  requested_days: number;
  reason?: string;
  status: 'pending' | 'approved' | 'rejected';
  created_at: string;
}

// Invoice (Auto-generated on subscription)
interface Invoice {
  id: number;
  tenant_id: number;
  member_id: number;
  subscription_id: number;
  invoice_number: string;
  subtotal: number;
  tax: number;
  total: number;
  status: 'unpaid' | 'paid' | 'overdue';
  created_at: string;
  updated_at: string;
}

// Payment
interface Payment {
  id: number;
  invoice_id: number;
  amount: number;
  payment_method: 'upi' | 'card' | 'bank_transfer' | 'cash';
  transaction_reference: string;
  idempotency_key: string;
  status: 'pending' | 'completed' | 'failed';
  created_at: string;
}

// Subscription Event (Audit Trail)
interface SubscriptionEvent {
  id: number;
  subscription_id: number;
  event_type: 'subscription.created' | 'subscription.renewed' | 'subscription.frozen' | 'subscription.unfrozen';
  old_status?: string;
  new_status: string;
  actor_type?: string;
  actor_id?: number;
  metadata?: Record<string, any>;
  created_at: string;
}
```

---

## 7. Common Workflows & Patterns

### Workflow 1: Member Registration → Plan Assignment (Onboarding)

```typescript
// Step 1: Register Member
const createMember = async (memberData) => {
  const { data } = await gymAxios.post('/members', memberData);
  return data.data; // Returns { id, member_code, ... }
};

// Step 2: Assign Plan Immediately
const assignPlan = async (memberId, planData) => {
  const { data } = await gymAxios.post(`/members/${memberId}/subscriptions`, planData);
  return data.data; // Returns { id, start_date, end_date, status: 'active' }
};

// Usage:
const member = await createMember({ first_name: 'Jane', phone: '9876543210' });
const subscription = await assignPlan(member.id, { plan_id: 3, auto_renew: true });
console.log(`${member.member_code} subscribed to ${subscription.plan_id}`);
```

### Workflow 2: Auto-Renewal Handling

```typescript
// Check if subscription needs renewal (on dashboard load)
const checkRenewalDue = (subscription: MemberSubscription) => {
  const daysUntilExpiry = differenceInDays(new Date(subscription.end_date), new Date());
  
  if (daysUntilExpiry <= 0 && subscription.status === 'active') {
    // Show "Renew Now" prompt
    return { needsRenewal: true, daysOverdue: Math.abs(daysUntilExpiry) };
  }
  
  if (daysUntilExpiry <= 7 && subscription.auto_renew === false) {
    // Show "Expiring Soon" warning
    return { expiringsoon: true, daysRemaining: daysUntilExpiry };
  }
  
  return { ok: true };
};

// Auto-renew logic (typically server-side, but UI can trigger manually)
const renewSubscription = async (subscriptionId, newPlanId?: number) => {
  const { data } = await gymAxios.post(
    `/memberships/subscriptions/${subscriptionId}/renew`,
    {
      plan_id: newPlanId,
      override_start_date: format(addDays(new Date(), 1), 'yyyy-MM-dd'),
      auto_renew: true
    }
  );
  return data.data;
};
```

### Workflow 3: Membership Upgrade (Same Term)

```typescript
const upgradeSubscription = async (subscriptionId, newPlanId) => {
  const { data } = await gymAxios.post(
    `/memberships/subscriptions/${subscriptionId}/upgrade`,
    { new_plan_id: newPlanId }
  );
  
  return {
    newSubscription: data.data,
    creditApplied: data.data.upgrade_credit,
    message: `Upgraded to plan ${newPlanId}. Credit: ₹${data.data.upgrade_credit}`
  };
};
```

### Workflow 4: Freeze & Unfreeze (Pause Membership)

```typescript
// Freeze (pause for vacation, etc.)
const freezeSubscription = async (subscriptionId, freezeDays, reason) => {
  const { data } = await gymAxios.post(
    `/memberships/subscriptions/${subscriptionId}/freeze`,
    {
      freeze_start_date: format(new Date(), 'yyyy-MM-dd'),
      requested_days: freezeDays,
      reason
    }
  );
  return data.data;
};

// Unfreeze (resume)
const unfreezeSubscription = async (subscriptionId) => {
  const { data } = await gymAxios.post(
    `/memberships/subscriptions/${subscriptionId}/unfreeze`
  );
  return data.data;
};
```

---

## 8. Best Practices for Frontend Implementation

| Practice | Details |
|----------|---------|
| **Idempotency Keys** | Always include `idempotency_key` on payment requests to prevent double charging |
| **Date Handling** | Use `date-fns` for date formatting (YYYY-MM-DD). Store dates in UTC. |
| **Error Handling** | Wrap subscription operations in try-catch; show meaningful error messages to staff |
| **Loading States** | Show spinners during plan assignment, renewal, freeze operations |
| **Confirmation Dialogs** | Always confirm before renewing, freezing, or upgrading subscriptions |
| **Audit Trail** | Display `SubscriptionEvent` timeline in subscription details for transparency |
| **Real-time Updates** | Use React Query's `refetchOnWindowFocus` for automatic sync of subscription status |
| **Timezone Awareness** | Convert dates to gym's timezone for display (store as UTC) |
| **Validation** | Validate start_date < end_date, requested_freeze_days <= max_freeze_days |

---

*FitCore SaaS Frontend Specification Document*
