<?php

namespace Bu\Gws\Console\Commands;

use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Console\Command;

class GoogleWorkspaceMonitor extends Command
{
    protected $signature = 'gws:monitor {action=status : Action to perform (status|clear-cache|metrics)}';
    protected $description = 'Monitor Google Workspace service performance';

    public function handle(GoogleWorkspaceService $gws)
    {
        $action = $this->argument('action');

        switch ($action) {
            case 'status':
                $this->showStatus($gws);
                break;
            case 'clear-cache':
                $this->clearCache($gws);
                break;
            case 'metrics':
                $this->showMetrics($gws);
                break;
            default:
                $this->error("Unknown action: {$action}");
        }
    }

    protected function showStatus(GoogleWorkspaceService $gws)
    {
        $this->info('Google Workspace Service Status');
        $this->line('===============================');

        $metrics = $gws->getMetrics();

        if (empty($metrics)) {
            $this->warn('No metrics available. Service may not be initialized.');
            return;
        }

        if (isset($metrics['api_calls'])) {
            $this->info('API Calls:');
            foreach ($metrics['api_calls'] as $method => $durations) {
                $avg = array_sum($durations) / count($durations);
                $this->line("  {$method}: " . count($durations) . " calls, avg: " . round($avg, 2) . "ms");
            }
        }

        if (isset($metrics['cache'])) {
            $this->info('Cache Performance:');
            foreach ($metrics['cache'] as $type => $stats) {
                $hitRate = $stats['hits'] / ($stats['hits'] + $stats['misses']) * 100;
                $this->line("  {$type}: {$stats['hits']} hits, {$stats['misses']} misses, " . round($hitRate, 1) . "% hit rate");
            }
        }

        if (isset($metrics['errors'])) {
            $this->warn('Errors:');
            foreach ($metrics['errors'] as $operation => $count) {
                $this->line("  {$operation}: {$count} errors");
            }
        }
    }

    protected function clearCache(GoogleWorkspaceService $gws)
    {
        if ($gws->clearCache()) {
            $this->info('Cache cleared successfully');
        } else {
            $this->warn('Cache is not enabled or failed to clear');
        }
    }

    protected function showMetrics(GoogleWorkspaceService $gws)
    {
        $metrics = $gws->getMetrics();
        $this->info('Raw Metrics:');
        $this->line(json_encode($metrics, JSON_PRETTY_PRINT));
    }
}