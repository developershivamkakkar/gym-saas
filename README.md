# FitCore — SaaS Gym Management System

Welcome to the **FitCore** SaaS Gym Management System project repository.

---

## 📚 Project Documentation

All core architectural, technical, API, and testing documents are stored in the [`docs/`](file:///d:/Startup/GYM_SAAS/docs) directory:

1. **[Developer Portal API Reference](file:///d:/Startup/GYM_SAAS/docs/Developer_Portal_APIs.md)** ⭐
   - Comprehensive dedicated API documentation for the **Developer Portal (`admin.fitcore.io`)**
   - Includes full 17 API endpoint specifications, pre-suspension guards, zero-downtime gym reassignment, and audit log APIs

2. **[Partner Portal API Reference](file:///d:/Startup/GYM_SAAS/docs/Partner_Portal_APIs.md)** ⭐
   - Comprehensive dedicated API documentation for the **Partner Portal (`partner.fitcore.io`)**
   - Includes Partner authentication, Gym Provisioning using Quota, Quota enforcement, and Partner Dashboard APIs

3. **[Complete API Reference & Integration Guide](file:///d:/Startup/GYM_SAAS/docs/API_Documentation.md)**
   - Master OpenAPI / Postman style documentation for **all 3 portals** (*Developer*, *Partner*, and *Gym Instance*)

4. **[Scale & Multi-Shard Testing Report](file:///d:/Startup/GYM_SAAS/docs/Scale_Testing_Report.md)**
   - Test report for 60-gym scale testing across 4 database shards (`fitcore_shard_01` through `04`)

5. **[Backend Auth & Tenant Resolution Architecture](file:///d:/Startup/GYM_SAAS/docs/Backend_Auth_Tenant_Setup.md)**
   - Technical setup for 3-portal authentication (*Developer*, *Partner*, *Gym Instance*)
   - `TenantResolutionMiddleware` details, dynamic PDO shard connections, and `TenantModel` isolation scope

6. **[Software Requirements Specification (SRS)](file:///d:/Startup/GYM_SAAS/docs/SRS_GymSaaS.md)**
   - Complete functional and non-functional requirements

7. **[Technical Architecture & DB Design Document](file:///d:/Startup/GYM_SAAS/docs/TechArch_GymSaaS.md)**
   - Three-portal system architecture (*Developer Portal*, *Partner Portal*, *Gym Instance*)
   - Configurable Shard-Pool database strategy (20 gyms per shard default)
