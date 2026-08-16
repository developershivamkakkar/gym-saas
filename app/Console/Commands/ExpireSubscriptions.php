<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Master\Tenant;
use App\Models\Shard\MemberSubscription;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';
    protected $description = 'Transition past-due subscriptions past their 15-day grace period to expired status across all tenant shards';

    public function handle(): void
    {
        $this->info('Starting subscription expiration process...');

        $tenants = Tenant::where('status', 'active')->get();

        foreach ($tenants as $tenant) {
            app()->instance('tenant', $tenant);

            config([
                'database.connections.tenant.host' => $tenant->shard->db_host ?? '127.0.0.1',
                'database.connections.tenant.port' => $tenant->shard->db_port ?? 3306,
                'database.connections.tenant.database' => $tenant->shard->db_name,
                'database.connections.tenant.username' => $tenant->shard->db_username ?? config('database.connections.master.username'),
                'database.connections.tenant.password' => $tenant->shard->db_password ?? config('database.connections.master.password'),
            ]);

            \DB::purge('tenant');
            \DB::reconnect('tenant');

            $cutoffDate = Carbon::today()->subDays(15)->toDateString();

            // 1. Transition expired Active subscriptions to Past Due
            MemberSubscription::where('tenant_id', $tenant->id)
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->where('end_date', '<', Carbon::today()->toDateString())
                ->update(['status' => SubscriptionStatus::PAST_DUE->value]);

            // 2. Transition Past Due subscriptions past 15-day grace period to Expired
            $expiredCount = MemberSubscription::where('tenant_id', $tenant->id)
                ->where('status', SubscriptionStatus::PAST_DUE->value)
                ->where('end_date', '<=', $cutoffDate)
                ->update(['status' => SubscriptionStatus::EXPIRED->value]);

            if ($expiredCount > 0) {
                $this->info("Expired {$expiredCount} past-due subscriptions for Tenant [{$tenant->slug}]");
            }
        }

        $this->info('Subscription expiration completed.');
    }
}
