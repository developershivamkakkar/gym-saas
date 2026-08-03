# Laravel 13 — Detailed Implementation Guide
## FitCore Multi-Tenant Shard System

**Document Version:** 1.1  
**Date:** August 2, 2026  
**Audience:** Developers building FitCore  
**Stack:** Laravel 13 · MySQL 8.0 · PHP 8.3 · Nginx · Redis · Brevo Email

---

## Table of Contents

1. [Project Setup](#1-project-setup)
2. [Folder Structure](#2-folder-structure)
3. [Environment Configuration (.env)](#3-environment-configuration)
4. [Database Config — Multi-Connection](#4-database-configuration)
5. [Master DB Migrations](#5-master-db-migrations)
6. [Shard DB Migrations](#6-shard-db-migrations)
7. [Tenant Resolution Middleware ⭐](#7-tenant-resolution-middleware)
8. [Base Models](#8-base-models)
9. [Service Classes](#9-service-classes)
10. [Routes (Per Portal)](#10-routes)
11. [Controller Pattern](#11-controller-pattern)
12. [End-to-End Flow Walkthrough](#12-end-to-end-flow-walkthrough)
13. [Artisan Commands](#13-artisan-commands)
14. [Common Mistakes to Avoid](#14-common-mistakes)
15. [Authentication System — All Three Portals](#15-authentication-system)

---

## 1. Project Setup

### Step 1.1 — Create Laravel 13 Project

```bash
# Create the project
composer create-project laravel/laravel fitcore
cd fitcore
```

### Step 1.2 — Install Required Packages

```bash
# JWT Authentication (for API auth)
composer require tymon/jwt-auth

# PDF generation (for invoices)
composer require barryvdh/laravel-dompdf

# Telescope (dev debugging only — remove in production)
composer require laravel/telescope --dev
```

### Step 1.3 — Publish Configs & Setup

```bash
# JWT setup
php artisan vendor:publish --provider="Tymon\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret

# DomPDF
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"

# Telescope (dev only)
php artisan telescope:install

# Create the jobs table (for database queue driver — Phase 1)
php artisan queue:table
php artisan migrate
```

---

## 2. Folder Structure

> This is the complete project layout. Read the comments to understand each folder's role.

```
fitcore/
│
├── app/
│   │
│   ├── Console/Commands/
│   │   ├── ProvisionShard.php          ← Creates new shard DB + runs migrations
│   │   ├── RunShardMigrations.php      ← Runs migrations on ALL shards
│   │   └── CheckMembershipExpiry.php   ← Daily cron: alerts for expiring memberships
│   │
│   ├── Http/
│   │   ├── Controllers/
│   │   │   │
│   │   │   ├── Developer/              ← Developer Portal controllers
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── PartnerController.php
│   │   │   │   ├── TenantController.php
│   │   │   │   ├── ShardController.php
│   │   │   │   ├── AnalyticsController.php
│   │   │   │   ├── AuditLogController.php
│   │   │   │   └── PlatformSettingController.php
│   │   │   │
│   │   │   ├── Partner/                ← Partner Portal controllers
│   │   │   │   ├── AuthController.php
│   │   │   │   ├── GymController.php
│   │   │   │   ├── DashboardController.php
│   │   │   │   └── SubscriptionController.php
│   │   │   │
│   │   │   └── Gym/                    ← Gym Instance controllers
│   │   │       ├── AuthController.php
│   │   │       ├── MemberController.php
│   │   │       ├── MembershipController.php
│   │   │       ├── PlanController.php
│   │   │       ├── PaymentController.php
│   │   │       ├── AttendanceController.php
│   │   │       ├── ClassController.php
│   │   │       ├── StaffController.php
│   │   │       ├── BranchController.php
│   │   │       ├── EquipmentController.php
│   │   │       ├── ExpenseController.php
│   │   │       └── AnalyticsController.php
│   │   │
│   │   ├── Middleware/
│   │   │   ├── TenantResolutionMiddleware.php  ← CORE: subdomain → shard routing
│   │   │   ├── DeveloperAuthMiddleware.php     ← Protects developer routes
│   │   │   ├── PartnerAuthMiddleware.php       ← Protects partner routes
│   │   │   └── GymAuthMiddleware.php           ← Protects gym routes + checks JWT
│   │   │
│   │   └── Requests/                   ← Input validation classes
│   │       ├── Developer/
│   │       │   ├── CreatePartnerRequest.php
│   │       │   └── CreateShardRequest.php
│   │       ├── Partner/
│   │       │   └── CreateGymRequest.php
│   │       └── Gym/
│   │           ├── CreateMemberRequest.php
│   │           └── CreatePaymentRequest.php
│   │
│   ├── Models/
│   │   ├── Master/                     ← Always use 'master' DB connection
│   │   │   ├── Developer.php
│   │   │   ├── Partner.php
│   │   │   ├── Shard.php
│   │   │   ├── Tenant.php
│   │   │   ├── AuditLog.php
│   │   │   ├── PlatformSetting.php
│   │   │   ├── PartnerSubscription.php
│   │   │   └── TenantFeatureFlag.php
│   │   │
│   │   └── Shard/                      ← Use dynamic 'tenant' DB connection
│   │       ├── TenantModel.php         ← BASE CLASS — all shard models extend this
│   │       ├── GymConfig.php
│   │       ├── Branch.php
│   │       ├── Staff.php
│   │       ├── Member.php
│   │       ├── MembershipPlan.php
│   │       ├── Membership.php
│   │       ├── Payment.php
│   │       ├── Attendance.php
│   │       ├── GymClass.php
│   │       ├── ClassBooking.php
│   │       ├── Equipment.php
│   │       ├── Expense.php
│   │       └── NotificationLog.php
│   │
│   └── Services/
│       ├── ShardRouter.php             ← Finds available shard for a new gym
│       ├── TenantProvisioningService.php ← End-to-end gym creation logic
│       ├── AuditLogger.php             ← Writes all actions to audit_logs
│       ├── InvoiceService.php          ← PDF invoice generation (DomPDF)
│       └── NotificationService.php    ← Email via Brevo
│
├── config/
│   ├── database.php                    ← Multi-connection config (master + tenant)
│   └── fitcore.php                     ← Custom app config
│
├── database/
│   ├── migrations/
│   │   ├── master/                     ← Run on fitcore_master DB
│   │   │   ├── create_developers_table.php
│   │   │   ├── create_partners_table.php
│   │   │   ├── create_shards_table.php
│   │   │   ├── create_tenants_table.php
│   │   │   ├── create_audit_logs_table.php
│   │   │   ├── create_platform_settings_table.php
│   │   │   ├── create_partner_subscriptions_table.php
│   │   │   └── create_tenant_feature_flags_table.php
│   │   │
│   │   └── shard/                      ← Run on EACH shard DB
│   │       ├── create_gym_config_table.php
│   │       ├── create_branches_table.php
│   │       ├── create_staff_table.php
│   │       ├── create_members_table.php
│   │       ├── create_membership_plans_table.php
│   │       ├── create_memberships_table.php
│   │       ├── create_payments_table.php
│   │       ├── create_attendance_table.php
│   │       ├── create_classes_table.php
│   │       ├── create_class_bookings_table.php
│   │       ├── create_equipment_table.php
│   │       ├── create_expenses_table.php
│   │       └── create_notifications_log_table.php
│   │
│   └── seeders/
│       └── MasterDatabaseSeeder.php    ← Seeds platform_settings + first developer
│
└── routes/
    ├── api.php                         ← Portal detection + route loader
    ├── developer.php                   ← Developer portal routes
    ├── partner.php                     ← Partner portal routes
    └── gym.php                         ← Gym instance routes
```

---

## 3. Environment Configuration

### `.env`

```env
APP_NAME=FitCore
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://fitcore.io

# ── MASTER DATABASE ──────────────────────────────────────────────
DB_CONNECTION=master
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=fitcore_master
DB_USERNAME=fitcore_user
DB_PASSWORD=your_very_secure_password

# ── REDIS (Queue + Cache) ───────────────────────────────────────
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Use Redis for both queue AND cache
QUEUE_CONNECTION=redis
CACHE_STORE=redis

# ── EMAIL: Brevo SMTP ───────────────────────────────────────────
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=your_brevo_login@email.com
MAIL_PASSWORD=your_brevo_smtp_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@fitcore.io
MAIL_FROM_NAME="FitCore"

# ── JWT ──────────────────────────────────────────────────────
JWT_SECRET=your_jwt_secret_generated_by_artisan
JWT_TTL=1440

# ── FITCORE CUSTOM ─────────────────────────────────────────────
FITCORE_DEFAULT_SHARD_MAX=20
```

---

## 4. Database Configuration

### `config/database.php`

> We define **two connections**: `master` (always fixed) and `tenant` (dynamically
> overridden per request by middleware). The placeholder values in `tenant` are
> never used directly — they get replaced at runtime.

```php
<?php

return [

    'default' => env('DB_CONNECTION', 'master'),

    'connections' => [

        // ── MASTER DB ───────────────────────────────────────────────
        // Used by: Developer Portal, Partner Portal, middleware lookup
        // Models: app/Models/Master/*
        'master' => [
            'driver'    => 'mysql',
            'host'      => env('DB_HOST', '127.0.0.1'),
            'port'      => env('DB_PORT', '3306'),
            'database'  => env('DB_DATABASE', 'fitcore_master'),
            'username'  => env('DB_USERNAME', 'root'),
            'password'  => env('DB_PASSWORD', ''),
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict'    => true,
            'engine'    => null,
        ],

        // ── TENANT / SHARD DB ────────────────────────────────────────
        // Used by: Gym Instance routes ONLY
        // Config is REPLACED at runtime by TenantResolutionMiddleware
        // These default values here are placeholders — never used
        'tenant' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'port'      => '3306',
            'database'  => 'placeholder_replaced_at_runtime',
            'username'  => 'placeholder',
            'password'  => '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict'    => true,
            'engine'    => null,
        ],

    ],

];
```

---

## 5. Master DB Migrations

### How to run

```bash
# Run only master migrations (on fitcore_master DB)
php artisan migrate \
  --path=database/migrations/master \
  --database=master
```

### Example: `database/migrations/master/create_shards_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master'; // ALWAYS specify connection for master migrations

    public function up(): void
    {
        Schema::connection('master')->create('shards', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();            // 'shard_01'
            $table->string('host', 255);                     // DB server IP
            $table->unsignedSmallInteger('port')->default(3306);
            $table->string('database_name', 100);            // 'fitcore_shard_01'
            $table->string('username', 100);
            $table->text('password_encrypted');              // AES-256 via Laravel encrypt()
            $table->unsignedSmallInteger('max_tenants')->default(20); // Configurable pool
            $table->unsignedSmallInteger('current_tenant_count')->default(0);
            $table->enum('status', ['active','full','maintenance','retired'])->default('active');
            $table->string('region', 50)->default('in-south');
            $table->string('vps_label', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('current_tenant_count');
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('shards');
    }
};
```

### Example: `create_tenants_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'master';

    public function up(): void
    {
        Schema::connection('master')->create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_id')->constrained('partners');
            $table->foreignId('shard_id')->constrained('shards');
            $table->string('name', 200);
            $table->string('slug', 100)->unique();           // Used as subdomain
            $table->string('email', 150);
            $table->string('password', 255);
            $table->string('owner_name', 150)->nullable();
            $table->string('brand_color', 7)->default('#3B82F6');
            $table->string('logo_url', 500)->nullable();
            $table->enum('plan', ['trial','starter','pro','enterprise'])->default('trial');
            $table->date('plan_start_date')->nullable();
            $table->date('plan_end_date')->nullable();
            $table->unsignedInteger('max_members')->default(200);
            $table->unsignedTinyInteger('max_branches')->default(1);
            $table->unsignedTinyInteger('max_staff')->default(3);
            $table->enum('status', ['active','suspended','inactive','setup'])->default('setup');
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();
            $table->boolean('setup_completed')->default(false);
            $table->foreignId('created_by')->constrained('partners');
            $table->timestamps();

            $table->index('slug');
            $table->index(['partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::connection('master')->dropIfExists('tenants');
    }
};
```

---

## 6. Shard DB Migrations

### How to run (on a new shard)

```bash
# Use the custom artisan command (explained in Section 13)
# This creates the DB and runs all shard migrations automatically
php artisan fitcore:provision-shard \
  --name=shard_01 \
  --host=127.0.0.1 \
  --database=fitcore_shard_01 \
  --username=fitcore_user \
  --password=your_password
```

### Example: `database/migrations/shard/create_members_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // No fixed connection — set dynamically by ProvisionShard command

    public function up(): void
    {
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');         // THE isolation key
            $table->unsignedBigInteger('branch_id');
            $table->string('member_code', 30);               // e.g. GYM-0001
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('phone', 20);
            $table->enum('gender', ['male','female','other'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('profile_photo', 500)->nullable();
            $table->text('address')->nullable();
            $table->string('emergency_contact', 100)->nullable();
            $table->string('emergency_phone', 20)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->text('medical_notes')->nullable();
            $table->string('fitness_goal', 255)->nullable();
            $table->unsignedBigInteger('assigned_trainer_id')->nullable();
            $table->enum('status', ['active','expired','frozen','inactive'])->default('active');
            $table->string('qr_token', 100)->unique();       // For QR check-in
            $table->unsignedBigInteger('referred_by')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'member_code']);
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'phone']);
            $table->index('qr_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
```

---

## 7. Tenant Resolution Middleware ⭐

> **This is the most important file in the entire project.**
> Every single API request to `{slug}.fitcore.io` passes through here.
> It does 7 things: detect gym → lookup master DB → validate → decrypt → switch DB → bind tenant → continue.

### `app/Http/Middleware/TenantResolutionMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class TenantResolutionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // ════════════════════════════════════════════════════════
        // STEP 1: Extract the subdomain slug from the URL
        // ════════════════════════════════════════════════════════
        // If host = "alphagym.fitcore.io"
        // explode('.') gives: ["alphagym", "fitcore", "io"]
        // We take index [0] = "alphagym"
        $host  = $request->getHost();
        $parts = explode('.', $host);
        $slug  = $parts[0];

        // ════════════════════════════════════════════════════════
        // STEP 2: Look up the tenant (from file cache or master DB)
        // ════════════════════════════════════════════════════════
        // Cache key is unique per slug. TTL = 300 seconds (5 mins).
        // On cache miss, we query the master DB.
        $cacheKey   = "tenant:slug:{$slug}";
        $tenantData = Cache::remember($cacheKey, 300, function () use ($slug) {
            return DB::connection('master')
                ->table('tenants as t')
                ->join('shards as s', 't.shard_id', '=', 's.id')
                ->select([
                    't.id          as tenant_id',
                    't.status      as tenant_status',
                    't.shard_id',
                    't.name        as gym_name',
                    't.plan',
                    't.max_members',
                    't.max_branches',
                    't.max_staff',
                    's.host',
                    's.port',
                    's.database_name',
                    's.username',
                    's.password_encrypted',
                ])
                ->where('t.slug', $slug)
                ->first();
        });

        // ════════════════════════════════════════════════════════
        // STEP 3: Validate — does this gym exist?
        // ════════════════════════════════════════════════════════
        if (!$tenantData) {
            return response()->json([
                'error'   => 'Not Found',
                'message' => 'No gym registered at this URL.',
            ], 404);
        }

        // ════════════════════════════════════════════════════════
        // STEP 4: Validate — is this gym active?
        // ════════════════════════════════════════════════════════
        if (in_array($tenantData->tenant_status, ['suspended', 'inactive'])) {
            Cache::forget($cacheKey);   // Remove stale cache
            return response()->json([
                'error'   => 'Access Denied',
                'message' => 'This gym account is ' . $tenantData->tenant_status . '.',
            ], 403);
        }

        // ════════════════════════════════════════════════════════
        // STEP 5: Decrypt the shard DB password
        // ════════════════════════════════════════════════════════
        // We stored it encrypted with Laravel's encrypt() function
        try {
            $shardPassword = decrypt($tenantData->password_encrypted);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Server configuration error'], 500);
        }

        // ════════════════════════════════════════════════════════
        // STEP 6: Override the 'tenant' DB connection dynamically
        // ════════════════════════════════════════════════════════
        // This changes which shard DB the 'tenant' connection points to
        // It only affects THIS request — not other parallel requests
        Config::set('database.connections.tenant', [
            'driver'    => 'mysql',
            'host'      => $tenantData->host,
            'port'      => $tenantData->port,
            'database'  => $tenantData->database_name,
            'username'  => $tenantData->username,
            'password'  => $shardPassword,
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'strict'    => true,
        ]);

        // IMPORTANT: purge any cached connection, force reconnect with new config
        DB::purge('tenant');
        DB::reconnect('tenant');

        // ════════════════════════════════════════════════════════
        // STEP 7: Make tenant context available globally this request
        // ════════════════════════════════════════════════════════
        // Any controller can now call app('tenant') to get current gym info
        app()->instance('tenant', (object) [
            'id'          => $tenantData->tenant_id,
            'slug'        => $slug,
            'gym_name'    => $tenantData->gym_name,
            'shard_id'    => $tenantData->shard_id,
            'plan'        => $tenantData->plan,
            'max_members' => $tenantData->max_members,
        ]);

        // ════════════════════════════════════════════════════════
        // STEP 8: Pass to the next middleware / controller
        // ════════════════════════════════════════════════════════
        return $next($request);
    }
}
```

---

## 8. Base Models

### 8.1 Master Models — `app/Models/Master/`

> Simple Eloquent models — always use `'master'` connection.

```php
<?php
// app/Models/Master/Tenant.php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $connection = 'master';  // Always master DB — never shard
    protected $table      = 'tenants';
    protected $hidden     = ['password'];

    protected $fillable = [
        'partner_id', 'shard_id', 'name', 'slug', 'email',
        'password', 'owner_name', 'plan', 'plan_start_date',
        'plan_end_date', 'status', 'logo_url', 'brand_color',
        'max_members', 'max_branches', 'max_staff', 'created_by',
    ];

    public function partner() { return $this->belongsTo(Partner::class); }
    public function shard()   { return $this->belongsTo(Shard::class); }
}
```

```php
<?php
// app/Models/Master/Shard.php

namespace App\Models\Master;

use Illuminate\Database\Eloquent\Model;

class Shard extends Model
{
    protected $connection = 'master';
    protected $table      = 'shards';
    protected $hidden     = ['password_encrypted'];

    protected $fillable = [
        'name', 'host', 'port', 'database_name', 'username',
        'password_encrypted', 'max_tenants', 'status', 'region', 'vps_label',
    ];

    /**
     * Find the shard with the most room (fewest tenants, still under max).
     */
    public static function findAvailable(): ?self
    {
        return static::where('status', 'active')
            ->whereColumn('current_tenant_count', '<', 'max_tenants')
            ->orderBy('current_tenant_count', 'asc')
            ->first();
    }
}
```

---

### 8.2 Base Tenant Model — `app/Models/Shard/TenantModel.php`

> **The foundation of data isolation.**  
> All gym (shard) models extend this. It automatically:
> - Routes all queries to the correct shard via `tenant` connection
> - Adds `WHERE tenant_id = ?` to EVERY query (global scope)
> - Sets `tenant_id` automatically on every new record

```php
<?php
// app/Models/Shard/TenantModel.php

namespace App\Models\Shard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class TenantModel extends Model
{
    // This connection is dynamically set per-request by TenantResolutionMiddleware
    protected $connection = 'tenant';

    protected static function booted(): void
    {
        // ── GLOBAL SCOPE: auto-filter by tenant_id ──────────────────
        // Member::all() → SELECT * FROM members WHERE tenant_id = 42
        // Member::find(5) → SELECT * FROM members WHERE tenant_id = 42 AND id = 5
        // Member::where('status','active')->get() → ...WHERE tenant_id = 42 AND status = 'active'
        static::addGlobalScope('tenant_scope', function (Builder $query) {
            $tenant = app('tenant');
            $query->where('tenant_id', $tenant->id);
        });

        // ── AUTO-SET tenant_id on new records ───────────────────────
        // Member::create(['name' => '...']) → automatically sets tenant_id = 42
        // You NEVER need to pass tenant_id manually in controllers
        static::creating(function (Model $model) {
            if (empty($model->tenant_id)) {
                $model->tenant_id = app('tenant')->id;
            }
        });
    }
}
```

---

### 8.3 Shard Models

```php
<?php
// app/Models/Shard/Member.php

namespace App\Models\Shard;

use Illuminate\Database\Eloquent\Builder;

class Member extends TenantModel
{
    protected $table = 'members';

    protected $fillable = [
        'branch_id', 'first_name', 'last_name', 'email', 'phone',
        'gender', 'date_of_birth', 'profile_photo', 'address',
        'emergency_contact', 'emergency_phone', 'blood_group',
        'medical_notes', 'fitness_goal', 'assigned_trainer_id',
        'status', 'referred_by', 'notes', 'created_by',
    ];

    protected static function booted(): void
    {
        parent::booted();   // ← ALWAYS call parent first to keep tenant scoping

        static::creating(function (Member $member) {
            // Auto-generate member code: GYM-0001, GYM-0002, ...
            $count = static::withoutGlobalScope('tenant_scope')
                ->where('tenant_id', app('tenant')->id)
                ->count();
            $member->member_code = 'GYM-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);

            // Auto-generate unique QR token for check-in
            $member->qr_token = \Illuminate\Support\Str::uuid()->toString();
        });
    }

    // Scopes
    public function scopeActive(Builder $q)  { return $q->where('status', 'active'); }
    public function scopeExpired(Builder $q) { return $q->where('status', 'expired'); }

    // Relationships
    public function memberships() { return $this->hasMany(Membership::class, 'member_id'); }
    public function payments()    { return $this->hasMany(Payment::class,    'member_id'); }
    public function attendance()  { return $this->hasMany(Attendance::class, 'member_id'); }
}
```

```php
<?php
// app/Models/Shard/Payment.php

namespace App\Models\Shard;

class Payment extends TenantModel
{
    protected $table = 'payments';

    protected $fillable = [
        'member_id', 'membership_id', 'amount', 'tax_amount',
        'discount_amount', 'net_amount', 'mode', 'status',
        'payment_date', 'reference_no', 'payment_for', 'notes', 'created_by',
    ];

    protected static function booted(): void
    {
        parent::booted();

        // Auto-generate invoice number: INV-2026-00001
        static::creating(function (Payment $payment) {
            $year  = date('Y');
            $count = static::withoutGlobalScope('tenant_scope')
                ->where('tenant_id', app('tenant')->id)
                ->whereYear('created_at', $year)
                ->count();
            $payment->invoice_number = "INV-{$year}-" . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
        });
    }
}
```

---

## 9. Service Classes

### `app/Services/TenantProvisioningService.php`

> Called when a partner creates a new gym. Handles everything end-to-end.

```php
<?php

namespace App\Services;

use App\Models\Master\{Partner, Shard, Tenant};
use Illuminate\Support\Facades\{Config, DB, Hash};

class TenantProvisioningService
{
    public function __construct(private ShardRouter $shardRouter) {}

    public function provision(Partner $partner, array $data): Tenant
    {
        // ── 1. Check partner gym quota ───────────────────────────────
        if ($partner->gyms_created >= $partner->gym_quota) {
            throw new \RuntimeException(
                "Gym quota reached ({$partner->gym_quota}). Contact support to increase."
            );
        }

        // ── 2. Find available shard ──────────────────────────────────
        $shard = $this->shardRouter->findAvailableShard();

        // ── 3. Create tenant + update counts (in a transaction) ──────
        $tenant = DB::connection('master')->transaction(function () use ($partner, $shard, $data) {
            $tenant = Tenant::create([
                'partner_id'      => $partner->id,
                'shard_id'        => $shard->id,
                'name'            => $data['name'],
                'slug'            => $data['slug'],
                'email'           => $data['email'],
                'password'        => Hash::make($data['password']),
                'owner_name'      => $data['owner_name'] ?? null,
                'plan'            => $data['plan'] ?? 'trial',
                'plan_start_date' => now()->toDateString(),
                'plan_end_date'   => now()->addDays(14)->toDateString(),
                'status'          => 'setup',
                'created_by'      => $partner->id,
            ]);

            $shard->increment('current_tenant_count');
            $partner->increment('gyms_created');

            return $tenant;
        });

        // ── 4. Seed gym_config in the shard DB ───────────────────────
        $this->seedGymInShard($shard, $tenant);

        return $tenant;
    }

    private function seedGymInShard(Shard $shard, Tenant $tenant): void
    {
        // Temporarily connect to the shard to insert initial config
        $password = decrypt($shard->password_encrypted);
        Config::set('database.connections.temp_shard', [
            'driver' => 'mysql', 'host' => $shard->host,
            'port' => $shard->port, 'database' => $shard->database_name,
            'username' => $shard->username, 'password' => $password,
            'charset' => 'utf8mb4',
        ]);
        DB::purge('temp_shard');

        DB::connection('temp_shard')->table('gym_config')->insert([
            'tenant_id'  => $tenant->id,
            'gym_name'   => $tenant->name,
            'currency'   => 'INR',
            'timezone'   => 'Asia/Kolkata',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::purge('temp_shard');
    }
}
```

---

## 10. Routes

### `routes/api.php` — Portal detection

```php
<?php
// routes/api.php

use Illuminate\Support\Facades\Route;

// Detect which portal this request belongs to, based on subdomain
$host  = request()->getHost();          // e.g. "alphagym.fitcore.io"
$slug  = explode('.', $host)[0];        // "alphagym"

if ($slug === 'admin') {
    require base_path('routes/developer.php');
} elseif ($slug === 'partner') {
    require base_path('routes/partner.php');
} else {
    // Any other subdomain = a gym instance
    require base_path('routes/gym.php');
}
```

### `routes/gym.php`

```php
<?php

use App\Http\Controllers\Gym;
use Illuminate\Support\Facades\Route;

// TenantResolutionMiddleware runs on ALL gym routes
// It resolves the shard DB and binds app('tenant')
Route::prefix('api/v1')->middleware(['tenant.resolve'])->group(function () {

    // Login is public (but still needs tenant resolved for the correct shard)
    Route::post('/auth/login', [Gym\AuthController::class, 'login']);

    // All other routes need JWT auth too
    Route::middleware(['auth.gym'])->group(function () {

        Route::post('/auth/logout', [Gym\AuthController::class, 'logout']);
        Route::get('/auth/me',      [Gym\AuthController::class, 'me']);

        // Members
        Route::apiResource('members', Gym\MemberController::class);
        Route::post('members/{id}/freeze',   [Gym\MemberController::class, 'freeze']);
        Route::post('members/{id}/unfreeze', [Gym\MemberController::class, 'unfreeze']);
        Route::post('members/import',        [Gym\MemberController::class, 'import']);

        // Plans & Memberships
        Route::apiResource('plans',       Gym\PlanController::class);
        Route::apiResource('memberships', Gym\MembershipController::class);

        // Payments + Invoice
        Route::apiResource('payments', Gym\PaymentController::class);
        Route::get('payments/{id}/invoice', [Gym\PaymentController::class, 'invoice']);

        // Attendance
        Route::post('attendance/checkin',  [Gym\AttendanceController::class, 'checkIn']);
        Route::post('attendance/checkout', [Gym\AttendanceController::class, 'checkOut']);
        Route::get('attendance/today',     [Gym\AttendanceController::class, 'today']);
        Route::get('attendance',           [Gym\AttendanceController::class, 'index']);

        // Classes
        Route::apiResource('classes', Gym\ClassController::class);
        Route::post('classes/{id}/book',   [Gym\ClassController::class, 'book']);
        Route::post('classes/{id}/cancel', [Gym\ClassController::class, 'cancel']);

        // Staff & Branches
        Route::apiResource('staff',    Gym\StaffController::class);
        Route::apiResource('branches', Gym\BranchController::class);

        // Equipment & Expenses
        Route::apiResource('equipment', Gym\EquipmentController::class);
        Route::apiResource('expenses',  Gym\ExpenseController::class);

        // Analytics
        Route::get('analytics/dashboard', [Gym\AnalyticsController::class, 'dashboard']);
        Route::get('analytics/revenue',   [Gym\AnalyticsController::class, 'revenue']);
        Route::get('analytics/members',   [Gym\AnalyticsController::class, 'members']);
    });
});
```

### Register middleware in `bootstrap/app.php` (Laravel 13 style)

```php
<?php
// bootstrap/app.php

use App\Http\Middleware\{
    TenantResolutionMiddleware,
    DeveloperAuthMiddleware,
    PartnerAuthMiddleware,
    GymAuthMiddleware
};
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\{Exceptions, Middleware};

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: '',   // No /api prefix — routes define their own prefix
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'tenant.resolve' => TenantResolutionMiddleware::class,
            'auth.developer' => DeveloperAuthMiddleware::class,
            'auth.partner'   => PartnerAuthMiddleware::class,
            'auth.gym'       => GymAuthMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
```

---

## 11. Controller Pattern

### `app/Http/Controllers/Gym/MemberController.php`

```php
<?php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gym\CreateMemberRequest;
use App\Models\Shard\Member;
use Illuminate\Http\{JsonResponse, Request};

class MemberController extends Controller
{
    /**
     * GET /api/v1/members
     * tenant_id is automatically filtered — you NEVER write it manually
     */
    public function index(Request $request): JsonResponse
    {
        $members = Member::query()
            ->when($request->status, fn($q, $s) => $q->where('status', $s))
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($inner) =>
                    $inner->where('first_name', 'like', "%{$s}%")
                          ->orWhere('phone',      'like', "%{$s}%")
                          ->orWhere('member_code','like', "%{$s}%")
                )
            )
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($members);
    }

    /**
     * POST /api/v1/members
     * tenant_id is set automatically by TenantModel::creating()
     */
    public function store(CreateMemberRequest $request): JsonResponse
    {
        $member = Member::create($request->validated());

        return response()->json([
            'message' => 'Member created successfully',
            'data'    => $member,
        ], 201);
    }

    /**
     * GET /api/v1/members/{id}
     * Even if someone guesses another gym's member ID, it returns 404
     * because global scope adds WHERE tenant_id = current_gym
     */
    public function show(int $id): JsonResponse
    {
        $member = Member::with(['memberships', 'payments'])->findOrFail($id);
        return response()->json($member);
    }

    /**
     * POST /api/v1/members/{id}/freeze
     */
    public function freeze(int $id, Request $request): JsonResponse
    {
        $request->validate(['days' => 'required|integer|min:1|max:90']);

        $member = Member::findOrFail($id);
        $member->update(['status' => 'frozen']);

        // Extend the active membership end_date by freeze days
        $membership = $member->memberships()->where('status', 'active')->first();
        if ($membership) {
            $membership->update([
                'status'           => 'frozen',
                'frozen_from'      => now()->toDateString(),
                'end_date'         => \Carbon\Carbon::parse($membership->end_date)
                                         ->addDays($request->days)->toDateString(),
                'freeze_days_used' => $membership->freeze_days_used + $request->days,
            ]);
        }

        return response()->json(['message' => "Member frozen for {$request->days} days"]);
    }
}
```

---

## 12. End-to-End Flow Walkthrough

### Full lifecycle: `GET alphagym.fitcore.io/api/v1/members`

```
1.  Browser/App sends:
    GET https://alphagym.fitcore.io/api/v1/members
    Authorization: Bearer eyJhbGci...

2.  Nginx receives request
    → Wildcard *.fitcore.io matches
    → Passes to PHP-FPM → Laravel

3.  routes/api.php
    → host = "alphagym.fitcore.io"
    → slug = "alphagym" (not 'admin' or 'partner')
    → Loads routes/gym.php

4.  Middleware stack runs in order:
    [A] TenantResolutionMiddleware
        → slug = "alphagym"
        → Cache miss → queries master DB:
             SELECT t.id, s.host, s.database_name, s.password_encrypted
             FROM tenants t JOIN shards s ON t.shard_id = s.id
             WHERE t.slug = 'alphagym'
             → tenant_id=42, host=127.0.0.1, db=fitcore_shard_01
        → decrypt(password_encrypted) = "abc123"
        → Config::set('database.connections.tenant', {host, db, ...})
        → DB::purge('tenant') + DB::reconnect('tenant')
        → app()->instance('tenant', {id: 42, slug: 'alphagym'})
        → ✅ Passes to next middleware

    [B] GymAuthMiddleware
        → Validates JWT token
        → Confirms auth user belongs to tenant_id = 42
        → ✅ Passes to controller

5.  MemberController@index runs
    → Member::query()
       (uses 'tenant' connection = fitcore_shard_01)
       (global scope adds WHERE tenant_id = 42)

    Final SQL executed:
    SELECT * FROM fitcore_shard_01.members
    WHERE tenant_id = 42
    ORDER BY created_at DESC
    LIMIT 20

6.  JSON response returned:
    { data: [...members of alphagym only...] }

    ✅ Zero risk of data leak to other gyms
```

---

## 13. Artisan Commands

### `php artisan fitcore:provision-shard`

> Run this from Developer Portal when you need to add a new shard.

```php
<?php
// app/Console/Commands/ProvisionShard.php

namespace App\Console\Commands;

use App\Models\Master\Shard;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Config, DB};

class ProvisionShard extends Command
{
    protected $signature = 'fitcore:provision-shard
                            {--name=     : Shard name, e.g. shard_01}
                            {--host=     : DB server IP}
                            {--database= : DB name, e.g. fitcore_shard_01}
                            {--username= : MySQL username}
                            {--password= : MySQL password}
                            {--max=20    : Max gyms in this shard}';

    protected $description = 'Provision a new MySQL shard database and register it';

    public function handle(): void
    {
        $name     = $this->option('name');
        $host     = $this->option('host');
        $database = $this->option('database');
        $username = $this->option('username');
        $password = $this->option('password');
        $max      = $this->option('max');

        // ── Step 1: Create the MySQL database ───────────────────────
        $this->info("Creating database `{$database}` on {$host}...");
        Config::set('database.connections.temp_provision', [
            'driver'   => 'mysql',
            'host'     => $host,
            'database' => 'information_schema',   // Connect without specific DB
            'username' => $username,
            'password' => $password,
        ]);
        DB::connection('temp_provision')
          ->statement("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // ── Step 2: Switch to the new database ───────────────────────
        Config::set("database.connections.temp_provision.database", $database);
        DB::purge('temp_provision');

        // ── Step 3: Run all shard migrations ─────────────────────────
        $this->info("Running shard migrations...");
        Artisan::call('migrate', [
            '--path'     => 'database/migrations/shard',
            '--database' => 'temp_provision',
            '--force'    => true,
        ]);
        $this->line(Artisan::output());

        // ── Step 4: Register shard in master DB ──────────────────────
        Shard::create([
            'name'               => $name,
            'host'               => $host,
            'port'               => 3306,
            'database_name'      => $database,
            'username'           => $username,
            'password_encrypted' => encrypt($password),   // Stored encrypted!
            'max_tenants'        => $max,
            'status'             => 'active',
        ]);

        // ── Cleanup ──────────────────────────────────────────────────
        DB::purge('temp_provision');

        $this->info("✅ Shard [{$name}] provisioned successfully! Max: {$max} gyms.");
    }
}
```

**Usage:**
```bash
php artisan fitcore:provision-shard \
  --name=shard_01 \
  --host=127.0.0.1 \
  --database=fitcore_shard_01 \
  --username=fitcore_user \
  --password=YourSecurePassword \
  --max=20
```

---

## 14. Common Mistakes to Avoid

| # | ❌ Mistake | 💥 What Goes Wrong | ✅ Fix |
|---|---|---|---|
| 1 | Forgetting `DB::purge('tenant')` after `Config::set` | Laravel reuses the old connection | Always call `DB::purge()` then `DB::reconnect()` |
| 2 | Not calling `parent::booted()` in a child model | Tenant global scope stops working silently | Always call `parent::booted()` as the FIRST line |
| 3 | Using `DB::table('members')` in gym controllers | Bypasses global scope → potential data leak | Always use Eloquent models (`Member::all()`) |
| 4 | Running shard migrations with `--database=master` | Wrong tables in wrong database | Use correct database flag per migration folder |
| 5 | Manually setting `tenant_id` in controllers | Redundant code, error-prone | `TenantModel::creating()` sets it automatically |
| 6 | Using Master models in gym controllers | Queries wrong DB | Master models have `$connection = 'master'`; only use in Developer/Partner portals |
| 7 | Not caching the tenant lookup | 1 master DB query per API request = slow | `Cache::remember()` in middleware |
| 8 | Storing shard password in plain text | Security breach exposes all shard DBs | Always `encrypt()` when storing, `decrypt()` when using |
| 9 | Forgetting wildcard SSL | Gym subdomains get SSL errors | `certbot` wildcard cert for `*.fitcore.io` |
| 10 | Not testing cross-tenant isolation | Data leaks go unnoticed | Always test: login as gym A and try to access gym B's member IDs |

---

## 15. Authentication System — All Three Portals

---

### 🔐 Overview — How Auth Works Across 3 Portals

```
┌────────────────────────────────────────────────────────────────┐
│  PORTAL            USER TABLE      DB           JWT GUARD   │
├────────────────────────────────────────────────────────────────┤
│  Developer Portal  developers      Master DB    'developer' │
│  Partner Portal    partners        Master DB    'partner'   │
│  Gym Instance      staff           Shard DB     'gym'       │
└────────────────────────────────────────────────────────────────┘

All three use JWT tokens. Each has its own SEPARATE guard in Laravel.
Tokens from one portal CANNOT be used on another portal.
```

---

### 15.1 JWT Guards Configuration

#### `config/auth.php`

```php
<?php

return [

    'defaults' => [
        'guard'     => 'developer',
        'passwords' => 'developers',
    ],

    'guards' => [

        // ── DEVELOPER PORTAL ─────────────────────────────────────
        // Users: developers table in master DB
        'developer' => [
            'driver'   => 'jwt',
            'provider' => 'developers',
        ],

        // ── PARTNER PORTAL ──────────────────────────────────────
        // Users: partners table in master DB
        'partner' => [
            'driver'   => 'jwt',
            'provider' => 'partners',
        ],

        // ── GYM INSTANCE ────────────────────────────────────────
        // Users: staff table in SHARD DB (dynamic connection)
        'gym' => [
            'driver'   => 'jwt',
            'provider' => 'staff',
        ],
    ],

    'providers' => [
        'developers' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Master\Developer::class,
        ],
        'partners' => [
            'driver' => 'eloquent',
            'model'  => App\Models\Master\Partner::class,
        ],
        'staff' => [
            'driver' => 'eloquent',
            // This model uses 'tenant' connection (set dynamically by middleware)
            'model'  => App\Models\Shard\Staff::class,
        ],
    ],

];
```

---

### 15.2 What's Inside a JWT Token?

> A JWT token is a **signed string** that contains user information.  
> It has 3 parts: Header . Payload . Signature  
> The payload carries identity information — no DB lookup needed on every request.

#### Developer Token Payload
```json
{
  "sub": 1,
  "portal": "developer",
  "email": "admin@fitcore.io",
  "role": "super_admin",
  "iat": 1722614400,
  "exp": 1722700800
}
```

#### Partner Token Payload
```json
{
  "sub": 5,
  "portal": "partner",
  "email": "partner@gymchain.com",
  "gym_quota": 10,
  "gyms_created": 3,
  "iat": 1722614400,
  "exp": 1722700800
}
```

#### Gym Staff Token Payload
```json
{
  "sub": 42,
  "portal": "gym",
  "tenant_id": 7,
  "shard_id": 1,
  "role": "manager",
  "branch_id": 2,
  "email": "manager@alphagym.com",
  "iat": 1722614400,
  "exp": 1722700800
}
```

> **Key:** `tenant_id` and `shard_id` are embedded in the gym token.  
> The middleware uses these to quickly validate the right shard without extra DB lookup.

---

### 15.3 Login Flow — Developer Portal

```
POST admin.fitcore.io/api/v1/auth/login
Body: { email, password }
        │
        ▼
DeveloperAuthController@login
  1. Query master DB: SELECT * FROM developers WHERE email = ?
  2. Hash::check($password, $developer->password)
  3. If valid → auth()->guard('developer')->login($developer)
  4. JWT token generated with developer payload
  5. Return: { token, developer info }
```

#### `app/Http/Controllers/Developer/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Models\Master\Developer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Find developer in master DB
        $developer = Developer::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$developer || !Hash::check($request->password, $developer->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Generate JWT for 'developer' guard
        $token = auth()->guard('developer')->login($developer);

        // Log last login
        $developer->update(['last_login_at' => now()]);

        return response()->json([
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id'    => $developer->id,
                'name'  => $developer->name,
                'email' => $developer->email,
                'role'  => $developer->role,
            ],
        ]);
    }

    public function logout()
    {
        auth()->guard('developer')->logout();
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me()
    {
        return response()->json(auth()->guard('developer')->user());
    }
}
```

---

### 15.4 Login Flow — Partner Portal

```
POST partner.fitcore.io/api/v1/auth/login
Body: { email, password }
        │
        ▼
PartnerAuthController@login
  1. Query master DB: SELECT * FROM partners WHERE email = ?
  2. Check partner status is 'active' (not suspended)
  3. Hash::check($password, $partner->password)
  4. If valid → auth()->guard('partner')->login($partner)
  5. Return: { token, partner info, quota info }
```

```php
<?php
// app/Http/Controllers/Partner/AuthController.php

public function login(Request $request)
{
    $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string',
    ]);

    $partner = \App\Models\Master\Partner::where('email', $request->email)->first();

    if (!$partner || !Hash::check($request->password, $partner->password)) {
        return response()->json(['error' => 'Invalid credentials'], 401);
    }

    if ($partner->status !== 'active') {
        return response()->json([
            'error'   => 'Account ' . $partner->status,
            'message' => 'Your partner account is not active. Contact support.'
        ], 403);
    }

    $token = auth()->guard('partner')->login($partner);
    $partner->update(['last_login_at' => now()]);

    return response()->json([
        'token'        => $token,
        'token_type'   => 'bearer',
        'expires_in'   => config('jwt.ttl') * 60,
        'user' => [
            'id'           => $partner->id,
            'name'         => $partner->name,
            'email'        => $partner->email,
            'gym_quota'    => $partner->gym_quota,
            'gyms_created' => $partner->gyms_created,
            'slots_left'   => $partner->gym_quota - $partner->gyms_created,
        ],
    ]);
}
```

---

### 15.5 Login Flow — Gym Instance (Most Complex)

```
POST alphagym.fitcore.io/api/v1/auth/login
Body: { email, password }
        │
        ▼
[TenantResolutionMiddleware runs FIRST]
  → Resolves "alphagym" → shard DB connection set
  → app('tenant') = { id: 7, shard_id: 1 }
        │
        ▼
GymAuthController@login
  1. Query SHARD DB: SELECT * FROM staff WHERE email = ? AND tenant_id = 7
  2. Check staff is_active = true
  3. Hash::check($password, $staff->password)
  4. If valid → generate JWT with tenant_id + role embedded
  5. Return: { token, staff info, gym info, permissions }
```

```php
<?php
// app/Http/Controllers/Gym/AuthController.php

namespace App\Http\Controllers\Gym;

use App\Http\Controllers\Controller;
use App\Models\Shard\Staff;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        // Staff is in SHARD DB — already connected by TenantResolutionMiddleware
        // Global scope auto-adds: WHERE tenant_id = 7
        $staff = Staff::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$staff || !Hash::check($request->password, $staff->password)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        $tenant = app('tenant');

        // Embed tenant_id and role into the JWT payload
        // This avoids DB lookup on every request
        $customClaims = [
            'portal'    => 'gym',
            'tenant_id' => $tenant->id,
            'shard_id'  => $tenant->shard_id,
            'role'      => $staff->role,
            'branch_id' => $staff->branch_id,
        ];

        $token = JWTAuth::claims($customClaims)->fromUser($staff);
        $staff->update(['last_login_at' => now()]);

        return response()->json([
            'token'      => $token,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'id'        => $staff->id,
                'name'      => $staff->name,
                'email'     => $staff->email,
                'role'      => $staff->role,
                'branch_id' => $staff->branch_id,
            ],
            'gym' => [
                'id'       => $tenant->id,
                'name'     => $tenant->gym_name,
                'plan'     => $tenant->plan,
            ],
            'permissions' => $this->getPermissions($staff->role),
        ]);
    }

    // Returns what each role can do — used by React frontend
    private function getPermissions(string $role): array
    {
        return match($role) {
            'owner'      => ['members', 'payments', 'staff', 'branches', 'plans',
                             'classes', 'equipment', 'analytics', 'settings'],
            'manager'    => ['members', 'payments', 'staff', 'classes',
                             'equipment', 'analytics'],
            'front_desk' => ['members', 'payments', 'attendance', 'classes'],
            'trainer'    => ['classes', 'attendance'],
            default      => [],
        };
    }

    public function logout()
    {
        JWTAuth::invalidate(JWTAuth::getToken());
        return response()->json(['message' => 'Logged out successfully']);
    }

    public function me()
    {
        $staff  = auth()->guard('gym')->user();
        $tenant = app('tenant');
        return response()->json([
            'user' => $staff,
            'gym'  => $tenant,
        ]);
    }
}
```

---

### 15.6 Auth Middleware — Per Portal

#### `app/Http/Middleware/DeveloperAuthMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class DeveloperAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Validate JWT and load developer from master DB
            $developer = JWTAuth::parseToken()->authenticate();

            if (!$developer) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            // Extra check: token must be for developer portal
            $payload = JWTAuth::getPayload();
            if ($payload->get('portal') !== 'developer') {
                return response()->json(['error' => 'Wrong portal token'], 403);
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Token invalid or expired'], 401);
        }

        return $next($request);
    }
}
```

#### `app/Http/Middleware/GymAuthMiddleware.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class GymAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        try {
            // Validate JWT
            $staff = JWTAuth::parseToken()->authenticate();

            if (!$staff) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }

            $payload  = JWTAuth::getPayload();
            $tenant   = app('tenant');   // Already set by TenantResolutionMiddleware

            // CRITICAL SECURITY CHECK:
            // The token's tenant_id MUST match the resolved gym's tenant_id
            // This prevents a staff from Gym A using their token on Gym B's subdomain
            if ($payload->get('tenant_id') !== $tenant->id) {
                return response()->json([
                    'error'   => 'Forbidden',
                    'message' => 'Token does not belong to this gym'
                ], 403);
            }

            // Make role available to controllers
            app()->instance('auth_role',   $payload->get('role'));
            app()->instance('auth_branch', $payload->get('branch_id'));

        } catch (\Exception $e) {
            return response()->json(['error' => 'Token invalid or expired'], 401);
        }

        return $next($request);
    }
}
```

---

### 15.7 Role-Based Access Control (RBAC) in Controllers

> After auth, you check the role before performing sensitive actions.

```php
<?php
// Example: only 'owner' or 'manager' can delete a staff member

public function destroy(int $id)
{
    $role = app('auth_role');   // Set by GymAuthMiddleware from JWT payload

    if (!in_array($role, ['owner', 'manager'])) {
        return response()->json(['error' => 'Insufficient permissions'], 403);
    }

    $staff = Staff::findOrFail($id);
    $staff->delete();

    return response()->json(['message' => 'Staff removed']);
}
```

**OR use a cleaner helper method in a base controller:**

```php
<?php
// app/Http/Controllers/Gym/BaseGymController.php

class BaseGymController extends Controller
{
    protected function requireRole(string|array $roles): void
    {
        $currentRole = app('auth_role');
        $allowed     = is_array($roles) ? $roles : [$roles];

        if (!in_array($currentRole, $allowed)) {
            abort(403, 'Insufficient permissions for this action.');
        }
    }
}

// Usage in any gym controller:
public function destroy(int $id)
{
    $this->requireRole(['owner', 'manager']);  // Only these roles allowed
    Staff::findOrFail($id)->delete();
    return response()->json(['message' => 'Staff removed']);
}
```

---

### 15.8 Token Refresh

```php
// POST /api/v1/auth/refresh  (available on all 3 portals)
public function refresh()
{
    try {
        $newToken = JWTAuth::parseToken()->refresh();
        return response()->json([
            'token'      => $newToken,
            'token_type' => 'bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Token cannot be refreshed, please login again'], 401);
    }
}
```

---

### 15.9 Full Auth Flow Summary

```
┌────────────────────────────────────────────────────────────────┐
│                    LOGIN                                   │
│  POST /auth/login {email, password}                        │
│  ↓                                                         │
│  Validate credentials against correct DB                   │
│  ↓                                                         │
│  Issue JWT with embedded: portal, role, tenant_id          │
│  ↓                                                         │
│  Frontend stores token in localStorage / secure cookie     │
├────────────────────────────────────────────────────────────────┤
│                 EVERY SUBSEQUENT REQUEST                   │
│  Header: Authorization: Bearer {token}                    │
│  ↓                                                         │
│  Auth Middleware: validates JWT signature                  │
│  ↓                                                         │
│  Reads payload: role, tenant_id (NO extra DB query)        │
│  ↓                                                         │
│  For gym: checks token tenant_id = resolved tenant_id      │
│  ↓                                                         │
│  Request proceeds to controller ✅                          │
├────────────────────────────────────────────────────────────────┤
│                       LOGOUT                              │
│  POST /auth/logout                                         │
│  JWT is blacklisted in Redis (cannot be reused)            │
└────────────────────────────────────────────────────────────────┘
```

> **Note:** JWT token blacklisting on logout is stored in **Redis** — another reason Redis is essential.

---

*© 2026 FitCore Technologies — Laravel 13 Implementation Guide v1.1*
