<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MigrateAllDatabases extends Command
{
    protected $signature = 'migrate:all';
    protected $description = 'Migrate all databases (master and shard)';

    public function handle()
    {
        $this->info('🔄 Migrating Master Database...');
        $this->call('migrate', [
            '--path' => 'database/migrations/master',
            '--database' => 'master',
        ]);

        $this->info('🔄 Migrating Shard Database...');
        $this->call('migrate', [
            '--path' => 'database/migrations/shard',
            '--database' => 'tenant',
        ]);

        $this->info('✅ All databases migrated successfully!');
    }
}
