<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Bu\Server\Services\AuditNotificationService;

class SendAuditReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audits:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails for pending audits';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting audit reminder process...');
        
        try {
            $notificationService = new AuditNotificationService();
            $totalReminders = $notificationService->sendReminders();
            
            if ($totalReminders > 0) {
                $this->info("✅ Audit reminder process completed successfully!");
                $this->info("📧 Total reminders sent: {$totalReminders}");
            } else {
                $this->info("ℹ️  No reminders needed at this time.");
            }
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send audit reminders: " . $e->getMessage());
            $this->error("Check the logs for more details.");
            return 1;
        }
        
        return 0;
    }
}