<?php

namespace Bu\Gws\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Bu\Gws\Services\GoogleWorkspaceService;
use Bu\Server\Models\AuditPlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CleanupAuditSpaces implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(GoogleWorkspaceService $gws)
    {
        try {
            Log::info('Starting audit spaces cleanup');

            $overdueAudits = AuditPlan::whereNotNull('chat_space_id')
                ->where('due_date', '<', Carbon::now())
                ->where('chat_space_cleanup_scheduled', false)
                ->get();

            Log::info('Found overdue audits with chat spaces', ['count' => $overdueAudits->count()]);

            $deletedSpaces = 0;
            $failedDeletions = 0;

            foreach ($overdueAudits as $auditPlan) {
                try {
                    $result = $gws->deleteChatSpace($auditPlan->chat_space_name);

                    if ($result['success']) {
                        $auditPlan->update([
                            'chat_space_cleanup_scheduled' => true,
                            'chat_space_id' => null,
                            'chat_space_name' => null
                        ]);

                        $deletedSpaces++;

                        Log::info('Chat space deleted successfully', [
                            'audit_plan_id' => $auditPlan->id,
                            'space_name' => $auditPlan->chat_space_name
                        ]);
                    } else {
                        $failedDeletions++;
                        Log::error('Failed to delete chat space', [
                            'audit_plan_id' => $auditPlan->id,
                            'space_name' => $auditPlan->chat_space_name,
                            'error' => $result['error']
                        ]);
                    }
                } catch (\Exception $e) {
                    $failedDeletions++;
                    Log::error('Failed to delete chat space', [
                        'audit_plan_id' => $auditPlan->id,
                        'space_name' => $auditPlan->chat_space_name,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            Log::info('Audit spaces cleanup completed', [
                'deleted_spaces' => $deletedSpaces,
                'failed_deletions' => $failedDeletions
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to cleanup audit spaces', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}