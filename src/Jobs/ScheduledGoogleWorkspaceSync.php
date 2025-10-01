<?php

namespace Bu\Gws\Jobs;

use Bu\Gws\Services\GoogleWorkspaceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ScheduledGoogleWorkspaceSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $domain;
    protected $syncType;
    protected $options;

    public function __construct(string $domain, string $syncType = 'recent', array $options = [])
    {
        $this->domain = $domain;
        $this->syncType = $syncType;
        $this->options = $options;
    }

    public function handle(GoogleWorkspaceSyncService $syncService)
    {
        try {
            Log::info('Starting scheduled Google Workspace sync', [
                'domain' => $this->domain,
                'sync_type' => $this->syncType
            ]);

            switch ($this->syncType) {
                case 'all':
                    $stats = $syncService->syncAllUsers($this->domain, $this->options);
                    break;

                case 'recent':
                    $since = $this->options['since'] ?? now()->subHours(24)->toISOString();
                    $stats = $syncService->syncRecentlyModifiedUsers($this->domain, $since);
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid sync type: {$this->syncType}");
            }

            Log::info('Scheduled Google Workspace sync completed', $stats);
        } catch (\Exception $e) {
            Log::error('Scheduled Google Workspace sync failed', [
                'error' => $e->getMessage(),
                'domain' => $this->domain,
                'sync_type' => $this->syncType
            ]);
            throw $e;
        }
    }
}
