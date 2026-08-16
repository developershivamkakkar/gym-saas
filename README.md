# FitCore — SaaS Gym Management System

Welcome to the **FitCore** SaaS Gym Management System project repository.

---

## 📚 Project Documentation

All core architectural, technical, API, testing, and frontend AI specification documents are stored in the [`docs/`](file:///d:/Startup/GYM_SAAS/docs) directory:

0. **[Master Backend Architecture Specification](file:///d:/Startup/GYM_SAAS/docs/BACKEND_COMPLETE_ARCHITECTURE.md)** 🌟 *(Complete Architecture of all 3 Portals, Database Sharding, 3-Layer Isolation, Rules Engine & All Endpoints)*
1. **[Master Project Documentation](file:///d:/Startup/GYM_SAAS/docs/MASTER_PROJECT_DOCUMENTATION.md)** 🌟 *(Complete Overview of System Architecture, Portals, API Modules, & Specs)*
1. **[Developer Portal Frontend Spec](file:///d:/Startup/GYM_SAAS/docs/Developer_Portal_Frontend_Spec.md)** ⭐
   - Complete React UI specification for `admin.fitcore.io` (Routes, TypeScript Interfaces, Pre-suspension guards, API mappings).
2. **[Partner Portal Frontend Spec](file:///d:/Startup/GYM_SAAS/docs/Partner_Portal_Frontend_Spec.md)** ⭐
   - Complete React UI specification for `partner.fitcore.io` (Routes, Quota Progress Widgets, Provisioning Wizard, API mappings).
3. **[Gym Instance Frontend Spec](file:///d:/Startup/GYM_SAAS/docs/Gym_Instance_Frontend_Spec.md)** ⭐
   - Complete React UI specification for `{slug}.fitcore.io` (Tenant Headers, Branch Quota Guards, Dashboard, API mappings).

---

### ⚙️ Backend API & Infrastructure References
4. **[Developer Portal API Reference](file:///d:/Startup/GYM_SAAS/docs/Developer_Portal_APIs.md)**
   - API reference for `admin.fitcore.io` (17 Endpoints, Hashed IDs, Audit logs).
5. **[Partner Portal API Reference](file:///d:/Startup/GYM_SAAS/docs/Partner_Portal_APIs.md)**
   - API reference for `partner.fitcore.io` (Partner Auth, Quota enforcement, Gym provisioning).
6. **[Gym Instance Portal API Reference](file:///d:/Startup/GYM_SAAS/docs/Gym_Instance_APIs.md)**
   - API reference for `{slug}.fitcore.io` (Shard Auth, Branch Quota Guards, Dashboard).
7. **[Complete API Reference & Integration Guide](file:///d:/Startup/GYM_SAAS/docs/API_Documentation.md)**
   - Master Postman/OpenAPI style documentation for all 3 portals.
8. **[Scale & Multi-Shard Testing Report](file:///d:/Startup/GYM_SAAS/docs/Scale_Testing_Report.md)**
   - 60-gym scale testing report across 4 database shards (`fitcore_shard_01` to `04`).
9. **[Technical Architecture & DB Design Document](file:///d:/Startup/GYM_SAAS/docs/TechArch_GymSaaS.md)**
   - Three-portal system architecture & Shard-Pool database strategy.
