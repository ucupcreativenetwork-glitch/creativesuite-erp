<?php

namespace App\Console\Commands;

use App\Modules\Integration\Services\AutoReorderService;
use App\Modules\Integration\Services\WebhookService;
use Illuminate\Console\Command;

class ProcessIntegrationsCommand extends Command
{
    protected $signature = 'erp:integrations:process
                            {--auto-reorder : Jalankan auto-reorder rules}
                            {--webhooks : Retry webhook deliveries yang gagal}';

    protected $description = 'Proses integrasi: auto-reorder stok dan retry webhook';

    public function handle(AutoReorderService $autoReorder, WebhookService $webhooks): int
    {
        $runReorder = $this->option('auto-reorder') || ! $this->option('webhooks');
        $runWebhooks = $this->option('webhooks') || ! $this->option('auto-reorder');

        if ($runReorder) {
            $results = $autoReorder->runAll();
            $created = collect($results)->where('status', 'created')->count();
            $this->info("Auto-reorder: {$created} PO dibuat dari ".count($results).' rule.');
        }

        if ($runWebhooks) {
            $retried = $webhooks->retryPending();
            $this->info("Webhook retry: {$retried} delivery dicoba ulang.");
        }

        return self::SUCCESS;
    }
}