<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Bu\Server\Services\CorrectiveActionNotificationService;
use Illuminate\Support\Facades\Log;

class SendCorrectiveActionReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'corrective-actions:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for overdue corrective actions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚨 Starting corrective action reminder notifications...');

        try {
            $notificationService = new CorrectiveActionNotificationService();

            $result = $notificationService->sendOverdueReminders();

            if ($result['success']) {
                if ($result['reminders_sent'] > 0) {
                    $this->info("✅ Successfully sent {$result['reminders_sent']} overdue reminders");
                    $this->info("📊 Total overdue actions processed: {$result['total_actions']}");
                } else {
                    $this->info("ℹ️  No overdue corrective actions found");
                }

                Log::info('Corrective action reminders command completed successfully', $result);
            } else {
                $this->error("❌ Failed to send reminders: {$result['message']}");
                Log::error('Corrective action reminders command failed', $result);
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("❌ Command failed with error: {$e->getMessage()}");
            Log::error('Corrective action reminders command failed with exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }

        $this->info('🎉 Corrective action reminder notifications completed!');
        return 0;
    }
}