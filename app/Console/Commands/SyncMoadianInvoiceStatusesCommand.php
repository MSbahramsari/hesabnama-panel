<?php

namespace App\Console\Commands;

use App\Actions\InquireInvoiceAction;
use App\Enums\InvoiceStatus;
use App\Exceptions\MoadianApiException;
use App\Exceptions\MoadianConfigurationException;
use App\Models\Invoice;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('moadian:sync-invoices {--limit=500 : Maximum invoices to inquire in one run}')]
#[Description('Synchronize pending invoice processing statuses with Moadian')]
class SyncMoadianInvoiceStatusesCommand extends Command
{
    public function handle(InquireInvoiceAction $action): int
    {
        $limit = max(1, min((int) $this->option('limit'), 5000));
        $processed = 0;
        $failed = 0;

        Invoice::query()
            ->where('status', InvoiceStatus::AwaitingConfirmation)
            ->whereNotNull('submission_uid')
            ->with('user.taxpayerProfile')
            ->oldest('last_inquired_at')
            ->oldest('id')
            ->limit($limit)
            ->get()
            ->each(function (Invoice $invoice) use ($action, &$processed, &$failed): void {
                try {
                    $action->handle($invoice);
                    $processed++;
                } catch (MoadianConfigurationException|MoadianApiException $exception) {
                    $invoice->update([
                        'last_inquired_at' => now(),
                        'error_message' => 'آخرین تلاش همگام‌سازی: '.$exception->getMessage(),
                    ]);
                    $failed++;
                }
            });

        $this->info("{$processed} صورتحساب همگام شد؛ {$failed} استعلام ناموفق بود.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
