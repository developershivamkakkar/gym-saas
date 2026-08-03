# FitCore Multi-Tenant Backend & Portal Authentication Architecture

**Document Version:** 1.4  
**Date:** August 3, 2026  
**Tech Stack:** Laravel 13 · MySQL 8.0 · Redis 7.x (predis) · Brevo Email  
**Status:** Implemented & Verified ✅  

---

## 1. Overview

This document details the implemented backend architecture for **FitCore**, including the 3-Portal system (*Developer Portal*, *Partner Portal*, *Gym/Clinic Instance*), database multi-connection config, dynamic shard tenant resolution middleware with **Redis caching**, and **automated Shard Provisioning**.

---

## 2. Developer Portal API Specifications (`admin.fitcore.io`)

Location: `app/Http/Controllers/Developer/` | Routes: `routes/developer.php`

### 2.1 Analytics & Dashboard
- **GET `/api/v1/developer/analytics`**: Returns platform-wide statistics (total gyms, active/suspended tenants, total database shards, active partners, and recent audit logs).

### 2.2 Partner Management
- **GET `/api/v1/developer/partners`**: List all partner reseller accounts with search, pagination, and gym counts.
- **POST `/api/v1/developer/partners`**: Register a new Partner account with assigned gym creation quota (`gym_quota`).
- **GET `/api/v1/developer/partners/{id}`**: Get detailed partner info + list of gyms created by this partner.
- **PATCH `/api/v1/developer/partners/{id}/quota`**: Update a partner's gym creation quota limit.
- **PATCH `/api/v1/developer/partners/{id}/status`**: Suspend / Activate a partner account.

### 2.3 Shard Database Management
- **GET `/api/v1/developer/shards`**: List all database shards with capacity tracking (`current_tenants` vs `max_tenants`, accepting status).
- **POST `/api/v1/developer/shards`**: Manually provision a new database shard DB.
- **PATCH `/api/v1/developer/shards/{id}/capacity`**: Update max tenant capacity limit for a shard.

### 2.4 Gym Tenant Management
- **GET `/api/v1/developer/tenants`**: List all gym tenants across all partners and shards with filtering.
- **PATCH `/api/v1/developer/tenants/{id}/status`**: Suspend or Activate a gym tenant (automatically invalidates Redis cache).

---

## 3. Automated Shard Provisioning (`ShardRouter`)

When gym count crosses the configured threshold (default: **20 gyms per shard**):

1. **Capacity Tracking**:
   - `fitcore_master.shards` tracks `max_tenants` (20), `current_tenants`, and `is_accepting_tenants`.
2. **Automated Shard Creation (`ShardRouter::getAvailableShard()`)**:
   - When Shard 1 hits 20 gyms, `is_accepting_tenants` is automatically set to `0`.
   - `ShardRouter` automatically executes `CREATE DATABASE fitcore_shard_02;`, runs all shard migrations, registers `fitcore_shard_02` in `fitcore_master`, and assigns Gym #21 to `fitcore_shard_02`.

---

## 4. Dynamic Tenant Resolution (`TenantResolutionMiddleware`) with Redis

1. **Slug Extraction**: Subdomain or `X-Tenant-Slug` header.
2. **Redis Lookup**: Cached using `Cache::remember("tenant:slug:{slug}", 300, ...)` (< 0.5ms latency).
3. **Dynamic PDO Connection**: `DB::purge('tenant')` and reconnects to target shard DB.
4. **Context Injection**: Binds active tenant object to Container (`app('tenant')`).

---

## 5. Portal Authentication APIs

### 1. Developer Portal (`admin.fitcore.io`)
- **Login**: `POST /api/v1/developer/auth/login`
- **Refresh Token**: `POST /api/v1/developer/auth/refresh`
- **Logout**: `POST /api/v1/developer/auth/logout`
- **Profile**: `GET /api/v1/developer/auth/me`

### 2. Partner Portal (`partner.fitcore.io`)
- **Login**: `POST /api/v1/partner/auth/login`
- **Refresh Token**: `POST /api/v1/partner/auth/refresh`
- **Logout**: `POST /api/v1/partner/auth/logout`
- **Profile**: `GET /api/v1/partner/auth/me`

### 3. Gym / Clinic Instance (`{slug}.fitcore.io`)
- **Login**: `POST /api/v1/gym/auth/login` (`X-Tenant-Slug: <slug>`)
- **Refresh Token**: `POST /api/v1/gym/auth/refresh`
- **Logout**: `POST /api/v1/gym/auth/logout`
- **Profile**: `GET /api/v1/gym/auth/me`
