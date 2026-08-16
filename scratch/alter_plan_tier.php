<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\Illuminate\Support\Facades\DB::connection('master')
    ->statement("ALTER TABLE tenants MODIFY COLUMN plan_tier ENUM('basic', 'starter', 'pro', 'enterprise') DEFAULT 'basic'");

echo "Tenants table plan_tier column modified successfully!\n";
