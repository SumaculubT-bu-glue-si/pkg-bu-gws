<?php

namespace Bu\Gws\Console\Commands;

use Bu\Gws\Services\GoogleWorkspaceSyncService;
use Illuminate\Console\Command;

class SyncGoogleWorkspaceUsers extends Command
{
    protected $signature = 'gws:sync-users 
                          {domain : The domain to sync users from}
                          {--all : Sync all users from Google Workspace}
                          {--emails= : Comma-separated list of specific emails to sync}
                          {--since= : Sync users modified since this date (RFC 3339 format)}
                          {--batch-size=100 : Number of users to process per batch}
                          {--delay=1 : Delay between batches in seconds}
                          {--dry-run : Show what would be synced without making changes}';

    protected $description = 'Sync users from Google Workspace to local employee database';

    protected $syncService;

    public function __construct(GoogleWorkspaceSyncService $syncService)
    {
        parent::__construct();
        $this->syncService = $syncService;
    }

    public function handle()
    {
        $domain = $this->argument('domain');
        $isDryRun = $this->option('dry-run');

        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $this->info("Starting Google Workspace user sync for domain: {$domain}");

        try {
            if ($this->option('all')) {
                $this->syncAllUsers($domain, $isDryRun);
            } elseif ($this->option('emails')) {
                $this->syncSpecificUsers($domain, $isDryRun);
            } elseif ($this->option('since')) {
                $this->syncRecentUsers($domain, $isDryRun);
            } else {
                $this->error('Please specify --all, --emails, or --since option');
                return 1;
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    protected function syncAllUsers(string $domain, bool $isDryRun)
    {
        $this->info('Syncing all users from Google Workspace...');

        if ($isDryRun) {
            $this->warn('This would sync all users from Google Workspace');
            return;
        }

        $options = [
            'batch_size' => (int) $this->option('batch-size'),
            'delay_between_batches' => (int) $this->option('delay'),
        ];

        $stats = $this->syncService->syncAllUsers($domain, $options);
        $this->displayStats($stats);
    }

    protected function syncSpecificUsers(string $domain, bool $isDryRun)
    {
        $emails = explode(',', $this->option('emails'));
        $emails = array_map('trim', $emails);

        $this->info('Syncing specific users: ' . implode(', ', $emails));

        if ($isDryRun) {
            $this->warn('This would sync the specified users');
            return;
        }

        $results = $this->syncService->syncUsersByEmails($emails, $domain);
        
        $this->table(
            ['Email', 'Result'],
            collect($results)->map(fn($result, $email) => [$email, $result])
        );

        $stats = $this->syncService->getSyncStats();
        $this->displayStats($stats);
    }

    protected function syncRecentUsers(string $domain, bool $isDryRun)
    {
        $since = $this->option('since');
        $this->info("Syncing users modified since: {$since}");

        if ($isDryRun) {
            $this->warn('This would sync recently modified users');
            return;
        }

        $stats = $this->syncService->syncRecentlyModifiedUsers($domain, $since);
        $this->displayStats($stats);
    }

    protected function displayStats(array $stats)
    {
        $this->info("\n=== Sync Statistics ===");
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total Processed', $stats['total_processed']],
                ['Created', $stats['created']],
                ['Updated', $stats['updated']],
                ['Skipped', $stats['skipped']],
                ['Errors', $stats['errors']],
                ['Duration', ($stats['duration_seconds'] ?? 0) . ' seconds'],
            ]
        );

        if ($stats['errors'] > 0) {
            $this->warn("There were {$stats['errors']} errors during sync. Check the logs for details.");
        } else {
            $this->info('Sync completed successfully!');
        }
    }
}
