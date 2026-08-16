<?php

namespace App\Console\Commands;

use App\Enums\SubscriptionStatus;
use App\Models\Master\Tenant;
use App\Models\Shard\MemberSubscription;
use App\Services\SubscriptionService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ProcessSubscriptionRenewals extends Command
{
    protected $signature = 'subscriptions:process-renewals';
    protected $description = 'Process automatic subscription renewals across all active tenant shards';

    protected SubscriptionService $subscriptionService;

    public function __construct(SubscriptionService $subscriptionService)
    {
        parent::__construct();
        $this->subscriptionService = $subscriptionService;
    }

    public function handle(): void
    {
        $this->info('Starting automated subscription renewals...');

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

            $today = Carbon::today()->toDateString();
            $subscriptions = MemberSubscription::where('tenant_id', $tenant->id)
                ->where('auto_renew', true)
                ->where('status', SubscriptionStatus::ACTIVE->value)
                ->where('end_date', '<=', $today)
                ->get();

            foreach ($subscriptions as $subscription) {
                try {
                    $this->subscriptionService->renew($subscription->id, null, null, true, null);
                    $this->info("Auto-renewed subscription #{$subscription->id} for Tenant [{$tenant->slug}]");
                } catch (\Exception $e) {
                    $this->error("Failed auto-renewing subscription #{$subscription->id}: " . $e->getMessage());
                }
            }
        }

        $this->info('Subscription renewals completed.');
    }
}
