<?php

return [
    /*
    |--------------------------------------------------------------------------
    | FitCore Platform Configuration
    |--------------------------------------------------------------------------
    */

    'developer_domain' => env('FITCORE_DEVELOPER_DOMAIN', 'admin.fitcore.io'),
    'partner_domain'   => env('FITCORE_PARTNER_DOMAIN', 'partner.fitcore.io'),
    'main_domain'      => env('FITCORE_MAIN_DOMAIN', 'fitcore.io'),

    // Maximum number of tenants allowed per database shard
    'default_shard_max_tenants' => env('FITCORE_DEFAULT_SHARD_MAX', 20),

    // Header key for API tenant resolution testing
    'tenant_header' => 'X-Tenant-Slug',
];
