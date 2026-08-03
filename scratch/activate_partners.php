<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

\App\Models\Master\Partner::query()->update(['status' => 'active']);
echo "All Partners successfully set to active!\n";
