# Software Requirements Specification (SRS)
## FitCore — SaaS Gym Management System

**Document Version:** 1.1  
**Prepared By:** Product & Engineering Team  
**Date:** August 2, 2026  
**Status:** ✅ Updated — Aligned with TechArch v1.2  
**Confidential:** Yes — For Internal Use Only

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [Overall Description](#2-overall-description)
3. [System Architecture Overview](#3-system-architecture-overview)
4. [Stakeholders & User Roles](#4-stakeholders--user-roles)
5. [Functional Requirements](#5-functional-requirements)
6. [Non-Functional Requirements](#6-non-functional-requirements)
7. [SaaS & Multi-Tenancy Requirements](#7-saas--multi-tenancy-requirements)
8. [Subscription & Pricing Plans](#8-subscription--pricing-plans)
9. [External Integrations](#9-external-integrations)
10. [Data Requirements](#10-data-requirements)
11. [Security Requirements](#11-security-requirements)
12. [UI/UX Requirements](#12-uiux-requirements)
13. [Constraints & Assumptions](#13-constraints--assumptions)
14. [Glossary](#14-glossary)

---

## 1. Introduction

### 1.1 Purpose

This Software Requirements Specification (SRS) defines the complete functional and non-functional requirements for **FitCore**, a cloud-based, multi-tenant SaaS Gym Management System. This document is intended to guide the design, development, testing, and deployment of the system.

### 1.2 Project Scope

**FitCore** is a comprehensive, scalable SaaS platform designed to help gym owners, fitness centers, yoga studios, and multi-branch fitness chains manage their entire operations digitally — from member onboarding to billing, attendance, trainer scheduling, and business analytics.

The system is designed to be **sold as a subscription-based service** to gym businesses of all sizes, enabling them to:
- Replace paper-based or spreadsheet-based management
- Automate billing and membership renewals
- Improve member engagement and retention
- Gain actionable business insights through real-time analytics

### 1.3 Product Vision

> *"One platform. Every gym. Zero hassle."*

FitCore will be the go-to digital backbone for fitness businesses across India and globally, enabling even single-owner gyms to operate like professional chains.

### 1.4 Intended Audience

| Audience | Purpose |
|---|---|
| Product Managers | Feature scoping and roadmap planning |
| Software Architects | System design and technical decisions |
| Developers | Implementation reference |
| QA Engineers | Test case preparation |
| Investors / Stakeholders | Understanding product scope |
| Sales Team | Understanding features for pitching |

### 1.5 Definitions, Acronyms, and Abbreviations

| Term | Definition |
|---|---|
| SaaS | Software as a Service |
| Tenant | A gym business using the FitCore platform |
| Member | A gym customer/subscriber |
| Trainer | A gym staff member (personal trainer / instructor) |
| Admin | Gym owner or manager |
| Super Admin | FitCore platform administrator |
| API | Application Programming Interface |
| SRS | Software Requirements Specification |
| RBAC | Role-Based Access Control |
| MRR | Monthly Recurring Revenue |

### 1.6 References

- ISO/IEC/IEEE 29148:2018 — Systems and Software Requirements
- OWASP Security Guidelines
- PCI DSS (for payment card data security)
- GDPR / Indian IT Act for data privacy compliance

---

## 2. Overall Description

### 2.1 Product Perspective

FitCore is a **standalone SaaS platform** delivered via:
- **Web Application** — For gym admins and staff (browser-based dashboard)
- **Partner Web Portal** — For FitCore resellers/partners to manage gym instances
- **Developer Admin Portal** — For FitCore team to manage partners, shards, and platform health

> **Phase 1 Scope:** Web application only. Mobile app is planned for a future phase.

It integrates with:
- Email notifications via **Brevo** (SMTP)
- **WhatsApp Business API** *(Phase 2 — via Interakt/AiSensy)*
- **AI features** *(Phase 3 — churn prediction, workout plans, revenue forecasting)*
- Payment Gateways *(Phase 2 — Razorpay / Stripe)*
- Biometric attendance devices *(optional, Phase 2)*

### 2.2 Product Functions (Summary)

```
┌─────────────────────────────────────────────────────────────┐
│                    FITCORE SAAS PLATFORM                    │
├──────────────────┬──────────────────┬───────────────────────┤
│  MEMBER MODULE   │  OPERATIONS      │  BUSINESS INTELLIGENCE│
│  • Onboarding    │  • Scheduling    │  • Revenue Reports    │
│  • Memberships   │  • Attendance    │  • Member Analytics   │
│  • Payments      │  • Trainer Mgmt  │  • Retention Metrics  │
│  • Self-Service  │  • Equipment     │  • Forecasting        │
├──────────────────┼──────────────────┼───────────────────────┤
│  COMMUNICATION   │  MULTI-BRANCH    │  PLATFORM ADMIN       │
│  • SMS/WhatsApp  │  • Branch Mgmt   │  • Tenant Management  │
│  • Email Alerts  │  • Centralized   │  • Subscription Mgmt  │
│  • Push Notifs   │    Reporting     │  • Platform Analytics │
└──────────────────┴──────────────────┴───────────────────────┘
```

### 2.3 User Classes and Characteristics

| User Class | Description | Technical Level |
|---|---|---|
| Super Admin | FitCore internal team | High |
| Gym Owner/Admin | Business owner managing one or more branches | Low–Medium |
| Branch Manager | Manages day-to-day operations of a branch | Medium |
| Front Desk Staff | Handles walk-ins, check-ins, and basic tasks | Low |
| Trainer | Manages schedules, assigns workout plans | Low–Medium |
| Member | End gym customer using the mobile app | Low |

### 2.4 Operating Environment

| Component | Technology | Notes |
|---|---|---|
| Backend API | **Laravel 13** (PHP 8.3) | REST API |
| Frontend (Web) | **React 18 + Vite 5** | 3 separate portals |
| Mobile App | ❌ Not in Phase 1 | Planned Phase 3 |
| Primary Database | **MySQL 8.0** | Shard-Pool architecture |
| Caching + Queue | **Redis 7.x** | From Phase 1 |
| Web Server | **Nginx** | Wildcard SSL |
| Process Manager | **Supervisor** | Queue workers |
| Email | **Brevo** (SMTP) | Transactional email |
| File Storage | **Local VPS** (`storage/app`) | → AWS S3 in Phase 2 |
| Hosting | **VPS** (Ubuntu 22.04) | Phase 1: 1 VPS |
| Auth | **JWT** (tymon/jwt-auth) | Per-portal guards |
| SSL | **Let's Encrypt** (Certbot) | Wildcard `*.fitcore.io` |

---

## 3. System Architecture Overview

### 3.1 Multi-Tenant Architecture

FitCore follows a **Configurable Shard-Pool** multi-tenant architecture with three portals:

```
┌───────────────────────────────────────────────────────────┐
│          FITCORE THREE-PORTAL SYSTEM                      │
├────────────────────┬────────────────────┬─────────────────┤
│  Developer Portal  │   Partner Portal   │ Gym Instance    │
│  admin.fitcore.io  │ partner.fitcore.io │ {slug}.fitcore  │
│  FitCore team only │ Reseller/Partner   │ Gym owner+staff │
└────────────────────┴────────────────────┴─────────────────┘
            │                       │                    │
            ▼                       ▼                    ▼
┌───────────────────────────────────────────────────────────┐
│          fitcore_master DB (MySQL 8.0)                    │
│          developers, partners, tenants, shards            │
└───────────────────────────────────────────────────────────┘
            │ TenantResolutionMiddleware routes to:
            ▼
┌───────────────┐  ┌───────────────┐  ┌───────────────┐
│ Shard 01      │  │ Shard 02      │  │ Shard 03 ...  │
│ Gym 01–20     │  │ Gym 21–40     │  │ Gym 41–60 ... │
│ (MySQL DB)    │  │ (MySQL DB)    │  │ (MySQL DB)    │
└───────────────┘  └───────────────┘  └───────────────┘
```

> Each shard holds **20 gyms** by default (configurable). For 5,000 gyms = ~250 shards.  
> All data isolation is enforced via `WHERE tenant_id = ?` on every shard query.

### 3.2 High-Level Module Map

```
FitCore Platform
│
├── 🏢 Tenant Onboarding Module
├── 👥 Member Management Module
├── 💳 Membership & Plans Module
├── 💰 Billing & Payments Module
├── 📅 Class & Schedule Module
├── 🏋️ Trainer Management Module
├── 📍 Attendance & Check-in Module
├── 🛠️ Equipment Management Module
├── 📊 Reports & Analytics Module
├── 📲 Notifications Module
├── 🌐 Multi-Branch Module
├── 📱 Member Mobile App
└── 🔐 Authentication & RBAC Module
```

---

## 4. Stakeholders & User Roles

### 4.1 Role Hierarchy

```
Developer (FitCore Super Admin - admin.fitcore.io)
    └── Partner (Reseller / Franchise Admin - partner.fitcore.io)
            └── Gym Owner / Admin ({slug}.fitcore.io)
                    ├── Branch Manager
                    │       ├── Front Desk Staff
                    │       └── Trainer
                    └── Member (Phase 3 Mobile App)
```

### 4.2 Role Permissions Matrix

| Feature | Super Admin | Gym Owner | Branch Manager | Front Desk | Trainer | Member |
|---|:---:|:---:|:---:|:---:|:---:|:---:|
| Manage Tenants | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| Manage Branches | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| Add/Edit Members | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| View Own Profile | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| Manage Plans | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Process Payments | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ |
| View Analytics | ✅ | ✅ | ✅ (own branch) | ❌ | ❌ | ❌ |
| Manage Trainers | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Schedule Classes | ✅ | ✅ | ✅ | ❌ | ✅ | ❌ |
| Book Classes | ❌ | ❌ | ❌ | ✅ | ❌ | ✅ |
| Mark Attendance | ✅ | ✅ | ✅ | ✅ | ✅ | ❌ |
| Manage Equipment | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| Raise Support | ❌ | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 5. Functional Requirements

---

### 5.1 Tenant Onboarding & Gym Setup

**FR-TO-001** — Self-Service Signup  
The system shall allow a gym owner to register for FitCore by providing:
- Gym name, owner name, email, phone number
- City, state, country
- Number of branches
- Selected subscription plan

**FR-TO-002** — Gym Profile Configuration  
After signup, the admin shall be able to configure:
- Gym logo, brand color
- Operating hours (per day)
- Holiday calendar
- Currency and tax settings (GST, VAT etc.)

**FR-TO-003** — Custom Subdomain  
Each tenant shall get a unique subdomain:
`gymname.fitcore.io` for their web portal.

**FR-TO-004** — White-label Support (Premium Plan)  
Premium tenants shall be able to use their own custom domain (e.g., `app.mygym.com`) with SSL.

**FR-TO-005** — Onboarding Wizard  
A step-by-step onboarding wizard shall guide new gym admins through:
1. Gym setup
2. Adding first membership plans
3. Adding staff
4. Importing or adding members

---

### 5.2 Member Management

**FR-MM-001** — Member Registration  
The system shall allow creation of member profiles with:
- Full name, date of birth, gender
- Contact: phone, email, emergency contact
- Address
- Profile photo
- Health info (optional): blood group, medical conditions, fitness goals
- Membership plan assignment
- Unique Member ID (auto-generated)

**FR-MM-002** — Member Search & Filters  
Staff shall be able to search members by:
- Name, phone, email, Member ID
- Membership status (Active / Expired / Paused / Frozen)
- Membership plan
- Join date range
- Assigned trainer

**FR-MM-003** — Member Profile View  
Each member profile shall display:
- Personal details
- Membership history
- Payment history
- Attendance log
- Assigned trainer
- Class bookings
- Body measurement history
- Notes / remarks

**FR-MM-004** — Membership Freeze / Pause  
Admins shall be able to freeze/pause a member's membership for a defined number of days (e.g., due to illness or travel), automatically extending the membership end date.

**FR-MM-005** — Member Import (Bulk)  
Admins shall be able to import existing members via CSV/Excel template with field mapping.

**FR-MM-006** — Member Transfer  
Members shall be transferable between branches within the same gym chain.

**FR-MM-007** — Member ID Card  
The system shall generate a printable/digital member ID card with QR code for check-in.

---

### 5.3 Membership Plans & Packages

**FR-MP-001** — Plan Creation  
Admins shall be able to create membership plans with:
- Plan name (e.g., "Monthly Basic", "Annual Premium")
- Duration (days/months/years)
- Price
- Services included (gym access, pool, classes, personal training)
- Maximum freeze days allowed
- Guest passes (number of complimentary guest visits)

**FR-MP-002** — Plan Variants  
Plans shall support variants such as:
- Off-peak hours only
- Specific days (weekdays / weekends)
- Age-based pricing (student, senior citizen)

**FR-MP-003** — Add-on Services  
Admins shall be able to define add-ons that can be attached to any plan:
- Locker rental
- Personal training sessions (X sessions)
- Diet consultation
- Supplement packages

**FR-MP-004** — Plan Upgrade / Downgrade  
The system shall support upgrading or downgrading a member's plan with prorated billing calculation.

**FR-MP-005** — Corporate / Group Plans  
Admins shall be able to create corporate plans for a company, enrolling multiple employees under one billing entity.

---

### 5.4 Billing & Payments

**FR-BP-001** — Manual Payment Recording  
Staff shall record payments as:
- Cash
- Card (POS)
- UPI / Bank Transfer
- Cheque

Each payment record shall include: amount, date, mode, reference number, received by.

**FR-BP-002** — Online Payment Collection  
The system shall integrate with payment gateways (Razorpay, Stripe, PayU) to accept:
- Online plan purchases from the member app
- Payment links sent via SMS/WhatsApp/email
- Auto-recurring subscriptions (for supported modes)

**FR-BP-003** — Invoice & Receipt Generation  
Every payment shall automatically generate:
- A downloadable/printable PDF invoice
- GST-compliant format (for Indian gyms)
- Unique invoice number

**FR-BP-004** — Due & Overdue Tracking  
The system shall:
- Track members with pending dues
- Send automated reminders (3 days before, on due date, 3 days after)
- Highlight overdue members in the dashboard

**FR-BP-005** — Expense Management  
Admins shall record gym expenses:
- Category (rent, utilities, salary, equipment, maintenance)
- Amount, date, vendor, notes
- Recurring expense setup

**FR-BP-006** — Discount & Coupon Management  
Admins shall create:
- Flat / percentage discount codes
- Referral discounts
- Seasonal offer pricing
- Coupon expiry and usage limits

**FR-BP-007** — Refund Management  
Admins shall process full or partial refunds, linked to the original invoice, with reason capturing.

---

### 5.5 Attendance & Check-in

**FR-AT-001** — Manual Check-in  
Staff shall mark member attendance manually by searching name/ID/phone.

**FR-AT-002** — QR Code Check-in  
Members shall check in by scanning their QR code (on ID card or mobile app) at the front desk.

**FR-AT-003** — Biometric Integration  
The system shall support integration with biometric fingerprint/face recognition devices via API for automated attendance marking.

**FR-AT-004** — Self Check-in Kiosk  
A kiosk mode shall allow members to self-check-in using their phone number or QR code.

**FR-AT-005** — Attendance Reports  
The system shall generate:
- Daily attendance log (with time-in, time-out)
- Monthly attendance per member
- Peak hours heatmap
- Absent members list (members who haven't visited in X days)

**FR-AT-006** — Guest Pass Tracking  
The system shall track guest visits linked to the host member's profile, deducting from their allocated guest passes.

---

### 5.6 Class & Schedule Management

**FR-CS-001** — Class Creation  
Admins/Managers shall create fitness classes with:
- Class name (e.g., Zumba, Yoga, CrossFit, HIIT)
- Assigned trainer
- Schedule (recurring or one-time)
- Room / Area
- Maximum capacity
- Class duration
- Difficulty level

**FR-CS-002** — Class Booking  
Members shall be able to book classes via the mobile app or web portal.

**FR-CS-003** — Waitlist Management  
When a class is full, members can join a waitlist and are automatically notified when a slot becomes available.

**FR-CS-004** — Class Cancellation  
Members can cancel bookings (within a configurable cancellation window). Late cancellations can be penalized (configurable).

**FR-CS-005** — Trainer View  
Trainers shall see their upcoming class schedule and enrolled members.

**FR-CS-006** — Class Attendance  
Trainers/Staff shall mark attendance for each class session.

**FR-CS-007** — Schedule Calendar View  
A visual weekly/monthly calendar shall display all scheduled classes.

---

### 5.7 Trainer Management

**FR-TR-001** — Trainer Profile  
Each trainer profile shall include:
- Name, photo, contact info
- Specializations / Certifications
- Bio
- Working hours / days
- Assigned members (personal training)
- Commission structure

**FR-TR-002** — Trainer Assignment  
Admins shall assign trainers to:
- Individual members (personal training)
- Group classes

**FR-TR-003** — Trainer Attendance & Salary  
The system shall track trainer attendance and calculate salary based on:
- Fixed monthly salary, OR
- Per-class/per-session commission, OR
- Hybrid model

**FR-TR-004** — Trainer Performance  
Dashboard showing per-trainer:
- Number of sessions conducted
- Member ratings / feedback scores
- Attendance rate

---

### 5.8 Equipment Management

**FR-EQ-001** — Equipment Inventory  
Admins shall maintain a log of all gym equipment:
- Equipment name, type, brand, model
- Purchase date, purchase cost
- Serial number
- Warranty expiry
- Location (branch, area)

**FR-EQ-002** — Maintenance Scheduling  
Admins shall schedule maintenance for each equipment item with:
- Maintenance type (routine, repair, AMC)
- Assigned vendor
- Date, cost, notes

**FR-EQ-003** — Equipment Status  
Each equipment item shall have a status: Active / Under Maintenance / Retired.

**FR-EQ-004** — Maintenance Alerts  
The system shall send automated alerts for:
- Upcoming maintenance due dates
- Warranty expiry reminders

---

### 5.9 Reports & Analytics Dashboard

**FR-RA-001** — Business Overview Dashboard  
Real-time dashboard showing:
- Total active members
- New members this month
- Revenue this month vs last month
- Today's attendance count
- Upcoming renewals (next 7/15/30 days)
- Expiring memberships alert

**FR-RA-002** — Revenue Reports  
- Daily / Monthly / Annual revenue
- Revenue by plan type
- Revenue by branch (multi-branch)
- Payment mode breakdown
- Outstanding dues total

**FR-RA-003** — Member Reports  
- Member growth trend (monthly)
- Churn rate (members not renewing)
- Retention rate
- Member demographics (age, gender distribution)
- Inactive member list

**FR-RA-004** — Attendance Reports  
- Daily attendance graph
- Peak hour analysis (heatmap)
- Attendance per class
- Member visit frequency segments

**FR-RA-005** — Trainer Reports  
- Sessions per trainer
- Revenue generated per trainer
- Class fill rate per trainer

**FR-RA-006** — Export  
All reports shall be exportable as:
- PDF
- Excel / CSV

---

### 5.10 Notifications & Communication

**FR-NC-001** — Automated Notifications  
The system shall send automated notifications via SMS, WhatsApp, Email, and Push Notification for:

| Event | Channels |
|---|---|
| Welcome new member | Email + SMS + WhatsApp |
| Membership expiry (7 days before) | SMS + WhatsApp + Push |
| Membership expiry (1 day before) | SMS + WhatsApp + Push |
| Membership expired | Email + SMS |
| Payment received | Email + WhatsApp |
| Payment due reminder | SMS + WhatsApp |
| Class booking confirmed | Push + Email |
| Class cancellation | Push + SMS |
| Birthday wishes | Email + WhatsApp |
| Offer / Promo announcements | All channels |

**FR-NC-002** — Bulk Messaging  
Admins shall send bulk messages to:
- All members
- Specific plan holders
- Members expiring in X days
- Inactive members

**FR-NC-003** — Message Templates  
Admins shall create and manage custom message templates with dynamic variables (e.g., `{member_name}`, `{expiry_date}`).

**FR-NC-004** — WhatsApp Business Integration  
The system shall integrate with the WhatsApp Business API for transactional and promotional messaging.

---

### 5.11 Multi-Branch Management

**FR-MB-001** — Branch Setup  
Gym owners can create and manage multiple branches under one account with:
- Branch name, address, contact
- Branch-specific operating hours
- Branch-specific plans and pricing
- Assigned managers

**FR-MB-002** — Centralized Dashboard  
Gym owners shall view consolidated analytics across all branches:
- Total revenue (all branches vs per-branch)
- Total members (all branches vs per-branch)
- Attendance across branches

**FR-MB-003** — Cross-Branch Membership  
Plans can be configured as:
- Single-branch only
- All-branch access (member can visit any branch)

**FR-MB-004** — Staff Assignment  
Staff and trainers shall be assigned to specific branches.

---

### 5.12 Member Mobile App

**FR-MA-001** — Member Self-Service  
Members shall be able to via the mobile app:
- View membership details and expiry
- View attendance history
- Book / cancel classes
- View and pay dues online
- Download invoices and receipts
- View workout plans assigned by trainer

**FR-MA-002** — QR Code  
The app shall display the member's unique QR code for gym check-in.

**FR-MA-003** — Push Notifications  
Members shall receive push notifications for all relevant events.

**FR-MA-004** — Body Measurement Tracker  
Members shall log body measurements (weight, BMI, body fat %, etc.) over time with a visual progress chart.

**FR-MA-005** — Workout Plans  
Trainers shall assign workout plans to members. Members shall view their daily workout plan in the app.

**FR-MA-006** — Referral System  
Members shall share a referral link/code. Successful referrals reward both referrer and new member (configurable by gym admin).

---

### 5.13 Super Admin Panel (FitCore Platform Admin)

**FR-SA-001** — Tenant Management  
FitCore admins shall manage all gym tenants:
- View, activate, suspend, delete tenants
- View subscription status
- Manually override billing

**FR-SA-002** — Platform Analytics  
- Total tenants (all plans)
- MRR / ARR
- New signups per month
- Churn rate (gyms cancelling)
- Feature usage statistics

**FR-SA-003** — Support Ticket System  
A built-in support ticketing system for gym admins to raise issues with FitCore support team.

**FR-SA-004** — Feature Flags  
The ability to toggle features per tenant or plan (e.g., enable WhatsApp for Premium only).

**FR-SA-005** — Announcement System  
Platform-wide or targeted announcements to tenant admins (e.g., maintenance notices, new features).

---

## 6. Non-Functional Requirements

### 6.1 Performance

| Metric | Requirement |
|---|---|
| Page Load Time | < 2 seconds (P95) |
| API Response Time | < 500ms (P95) |
| Dashboard Load | < 3 seconds |
| Concurrent Users | Support 10,000+ concurrent users |
| Report Generation | < 10 seconds for complex reports |

### 6.2 Scalability

- **Horizontal scaling** via containerized microservices (Kubernetes)
- Auto-scaling policies triggered by CPU/memory thresholds
- Database read replicas for report-heavy queries
- CDN-cached static assets

### 6.3 Availability & Reliability

| Metric | Requirement |
|---|---|
| Uptime SLA | 99.9% (≈ 8.76 hours downtime/year) |
| Backup Frequency | Daily automated backups |
| Backup Retention | 30 days |
| RTO (Recovery Time Objective) | < 4 hours |
| RPO (Recovery Point Objective) | < 1 hour |

### 6.4 Usability

- Mobile-responsive web application
- UI accessible on mobile browsers without needing native app
- Onboarding completion target: < 30 minutes for basic setup
- Support for 5+ languages (English, Hindi, Tamil, Telugu, Kannada) — Phase 2

### 6.5 Maintainability

- Modular codebase with clear separation of concerns
- RESTful API with OpenAPI (Swagger) documentation
- Automated unit and integration test coverage > 80%
- CI/CD pipeline for automated deployment

---

## 7. SaaS & Multi-Tenancy Requirements

### 7.1 Tenant Isolation

- All data strictly isolated per tenant via `tenant_id`
- No tenant shall access another tenant's data under any circumstances
- Tenant-level encryption keys for sensitive data

### 7.2 Custom Branding

| Feature | Basic | Pro | Enterprise |
|---|:---:|:---:|:---:|
| Custom Gym Logo | ✅ | ✅ | ✅ |
| Custom Brand Color | ✅ | ✅ | ✅ |
| Custom Subdomain | ✅ | ✅ | ✅ |
| White-label (custom domain) | ❌ | ❌ | ✅ |
| Remove FitCore Branding | ❌ | ❌ | ✅ |

### 7.3 Data Portability

- Gym owners can export ALL their data (members, payments, attendance) as CSV/Excel at any time
- Upon subscription cancellation, data is available for download for 90 days, then permanently deleted

---

## 8. Subscription & Pricing Plans

### 8.1 SaaS Pricing Tiers

| Feature | **Starter** | **Pro** | **Enterprise** |
|---|:---:|:---:|:---:|
| **Price (Monthly)** | ₹999/mo | ₹2,499/mo | Custom |
| Branches | 1 | Up to 3 | Unlimited |
| Members | Up to 200 | Up to 1,000 | Unlimited |
| Staff Accounts | 3 | 10 | Unlimited |
| Member Mobile App | ✅ | ✅ | ✅ |
| Online Payments | ❌ | ✅ | ✅ |
| WhatsApp Notifications | ❌ | ✅ | ✅ |
| Advanced Analytics | ❌ | ✅ | ✅ |
| Equipment Management | ❌ | ✅ | ✅ |
| API Access | ❌ | ❌ | ✅ |
| White-label | ❌ | ❌ | ✅ |
| Dedicated Support | ❌ | Email | Phone + Email |
| Custom Integrations | ❌ | ❌ | ✅ |
| SLA Guarantee | ❌ | 99.5% | 99.9% |

### 8.2 Trial Period

- All new tenants receive a **14-day free trial** (Pro plan features)
- No credit card required during trial
- Automated reminder at Day 7 and Day 12 of trial

---

## 9. External Integrations

| Integration | Purpose | Priority |
|---|---|---|
| **Razorpay** | Online payment collection (India) | P0 — Must Have |
| **Stripe** | Global online payments | P1 — Should Have |
| **PayU** | Alternative payment gateway | P2 — Nice to Have |
| **WhatsApp Business API** | Transactional messaging | P0 — Must Have |
| **Twilio / MSG91** | SMS notifications | P0 — Must Have |
| **SendGrid / AWS SES** | Email delivery | P0 — Must Have |
| **Firebase** | Push notifications (mobile app) | P0 — Must Have |
| **Google Calendar** | Trainer/class schedule sync | P1 — Should Have |
| **Biometric Devices** | Attendance via fingerprint/face | P1 — Should Have |
| **Tally / QuickBooks** | Accounting export | P2 — Nice to Have |
| **Zapier** | Workflow automation | P2 — Nice to Have |

---

## 10. Data Requirements

### 10.1 Key Data Entities

```
GymTenant
  ├── Branches (1 to many)
  │     ├── Staff (many to many)
  │     ├── Equipment (1 to many)
  │     └── Classes (1 to many)
  ├── Members (1 to many)
  │     ├── Memberships (1 to many)
  │     ├── Payments (1 to many)
  │     ├── Attendance (1 to many)
  │     ├── ClassBookings (1 to many)
  │     └── Measurements (1 to many)
  ├── MembershipPlans (1 to many)
  ├── Trainers (1 to many)
  │     ├── TrainerAttendance
  │     └── WorkoutPlans (1 to many)
  ├── Expenses (1 to many)
  ├── Notifications (1 to many)
  └── SupportTickets (1 to many)
```

### 10.2 Data Retention Policy

| Data Type | Retention Period |
|---|---|
| Member records | Duration of membership + 5 years |
| Payment records | 7 years (compliance) |
| Attendance logs | 3 years |
| System logs | 90 days |
| Deleted member data | 30 days (soft delete) |

---

## 11. Security Requirements

### 11.1 Authentication & Authorization

- **JWT-based authentication** with refresh token rotation
- **Multi-Factor Authentication (MFA)** for admin accounts
- **RBAC** (Role-Based Access Control) enforced at API level
- Session timeout after 30 minutes of inactivity

### 11.2 Data Security

- All data encrypted **in transit** (TLS 1.3)
- Sensitive data encrypted **at rest** (AES-256)
- Passwords hashed with **bcrypt** (cost factor ≥ 12)
- PII data masked in logs
- Credit/debit card data — never stored (fully tokenized via payment gateway)

### 11.3 Application Security

- OWASP Top 10 vulnerabilities mitigated
- SQL injection prevention via parameterized queries / ORM
- Rate limiting on all API endpoints
- CAPTCHA on registration and login forms
- Regular security audits and penetration testing (quarterly)

### 11.4 Compliance

| Regulation | Applicability |
|---|---|
| **Indian IT Act 2000** | Data protection for Indian users |
| **GDPR** | For EU customers (future expansion) |
| **PCI DSS** | Payment card data security |
| **GST Compliance** | For Indian invoicing |

---

## 12. UI/UX Requirements

### 12.1 Design Principles

- **Mobile-first** responsive design
- **Minimal cognitive load** — front desk staff should complete check-in in < 3 taps/clicks
- **Consistent design system** — shared component library across web and app
- **Dark mode support** — optional for users

### 12.2 Web App Requirements

- Compatible browsers: Chrome, Firefox, Safari, Edge (latest 2 versions)
- Minimum screen resolution: 1280 × 768
- Keyboard accessible (WCAG 2.1 AA compliance)

### 12.3 Mobile App Requirements

- iOS 14+ and Android 8+ support
- Offline capability for QR code display (check-in)
- App size < 30 MB

### 12.4 Key UX Flows

| Flow | Target Completion Time |
|---|---|
| Member check-in | < 15 seconds |
| New member registration | < 5 minutes |
| Payment recording | < 1 minute |
| Class booking | < 30 seconds |
| Report generation | < 10 seconds |

---

## 13. Constraints & Assumptions

### 13.1 Constraints

- **Internet Dependency**: Core functionality requires stable internet. Limited offline mode (check-in only) for the mobile app.
- **Biometric Integration**: Hardware compatibility limited to supported device models.
- **Payment Gateways**: Online payment features subject to payment gateway approval and KYC (for the gym as merchant).
- **WhatsApp API**: Subject to Meta's messaging limits and approval policies.
- **Budget**: Initial launch to focus on Starter and Pro tier features; Enterprise features in Phase 2.

### 13.2 Assumptions

- Gym owners have basic smartphone/computer literacy.
- Members have access to a smartphone for the mobile app.
- Internet connectivity available at the gym premises.
- Gyms will manage their own WhatsApp Business account setup (or FitCore provides shared messaging at a usage cost).
- Initial market focus is **India**; international expansion in Phase 2.

### 13.3 Out of Scope (v1.0)

- Point-of-sale (POS) for gym merchandise/supplements (Phase 2)
- Native Tally / accounting integration (Phase 2)
- AI-powered workout recommendations (Phase 3)
- Live streaming of classes (Phase 3)
- Custom native apps per gym (Enterprise add-on, Phase 3)
- Franchise management module (Phase 3)

---

## 14. Glossary

| Term | Definition |
|---|---|
| **Tenant** | A gym business registered on FitCore |
| **Member** | A customer enrolled in the gym |
| **Membership** | A time-bound subscription a member purchases |
| **Plan** | A membership package defined by the gym |
| **Branch** | A physical gym location |
| **Freeze** | Temporary pause of a membership |
| **Churn** | Members or tenants who discontinue |
| **MRR** | Monthly Recurring Revenue |
| **ARR** | Annual Recurring Revenue |
| **QR Check-in** | Using a QR code to mark attendance |
| **White-label** | Branding the product as the gym's own |
| **RBAC** | Role-Based Access Control |
| **KYC** | Know Your Customer (required for payment gateway) |
| **P0/P1/P2** | Priority levels: Must Have / Should Have / Nice to Have |

---

## Appendix A — Phased Rollout Plan

| Phase | Timeline | Features |
|---|---|---|
| **Phase 1 (MVP)** | Month 1–4 | Tenant onboarding, Member management, Basic billing, Attendance (manual + QR), Basic reports, Email + SMS notifications |
| **Phase 2** | Month 5–8 | Class scheduling, Trainer management, Online payments, WhatsApp integration, Multi-branch, Mobile app (basic) |
| **Phase 3** | Month 9–12 | Advanced analytics, Biometric integration, Equipment management, Referral system, Body measurement tracker |
| **Phase 4** | Year 2 | POS (merchandise), AI recommendations, Live class streaming, Franchise module, International expansion |

---

## Appendix B — Success Metrics (KPIs)

| KPI | Target (Year 1) |
|---|---|
| Paying Tenants | 500+ gyms |
| MRR | ₹5,00,000+ |
| Member App Downloads | 50,000+ |
| Customer Churn Rate | < 5% monthly |
| NPS (Net Promoter Score) | > 50 |
| Average Onboarding Time | < 30 minutes |

---

*Document End*

---
**Document Control**

| Version | Date | Author | Changes |
|---|---|---|---|
| 1.0 | Aug 2, 2026 | Product Team | Initial Draft |

*© 2026 FitCore Technologies. All Rights Reserved. Confidential.*
