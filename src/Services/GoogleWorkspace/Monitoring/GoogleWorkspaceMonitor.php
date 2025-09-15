<?php

namespace Bu\Gws\Services\GoogleWorkspace\Monitoring;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class GoogleWorkspaceMonitor
{
    protected $logger;
    protected $metrics = [];
    protected $startTime;

    /**
     * Create a new monitor instance.
     *
     * @param mixed $logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
        $this->startTime = Carbon::now();
    }

    /**
     * Track an API call.
     *
     * @param string $method
     * @param float $startTime
     * @param string|null $email
     * @return void
     */
    public function trackApiCall($method, $startTime, $email = null)
    {
        $duration = (microtime(true) - $startTime) * 1000; // Convert to milliseconds
        $this->metrics['api_calls'][$method][] = $duration;

        // Log slow requests
        if ($duration > config('google-workspace.monitoring.slow_request_threshold')) {
            $context = [
                'duration' => $duration,
                'threshold' => config('google-workspace.monitoring.slow_request_threshold'),
                'method' => $method
            ];

            if ($email) {
                $context['user'] = $email;
            }

            $this->logger->warning("Slow Google Workspace API call detected", $context);
        }

        // Update metrics
        $this->updateMetrics($method, $duration, $email);
    }

    /**
     * Check rate limit and alert if threshold is reached.
     *
     * @param int $current
     * @param int $limit
     * @return void
     */
    public function checkRateLimit($current, $limit)
    {
        $percentage = ($current / $limit) * 100;

        if ($percentage >= config('google-workspace.monitoring.rate_limit_threshold')) {
            $this->logger->alert("Google Workspace API rate limit threshold reached", [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $percentage
            ]);
        }

        $this->metrics['rate_limit'] = [
            'current' => $current,
            'limit' => $limit,
            'percentage' => $percentage
        ];
    }

    /**
     * Log cache events.
     *
     * @param string $type
     * @param string $key
     * @param bool $hit
     * @return void
     */
    public function trackCacheEvent($type, $key, $hit)
    {
        if (!isset($this->metrics['cache'][$type])) {
            $this->metrics['cache'][$type] = [
                'hits' => 0,
                'misses' => 0
            ];
        }

        if ($hit) {
            $this->metrics['cache'][$type]['hits']++;
        } else {
            $this->metrics['cache'][$type]['misses']++;
        }
    }

    /**
     * Log error events.
     *
     * @param string $operation
     * @param \Exception $exception
     * @param array $context
     * @return void
     */
    public function logError($operation, $exception, array $context = [])
    {
        $this->logger->error("Google Workspace error: {$operation}", [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
            'context' => $context
        ]);

        if (!isset($this->metrics['errors'][$operation])) {
            $this->metrics['errors'][$operation] = 0;
        }
        $this->metrics['errors'][$operation]++;
    }

    /**
     * Get current metrics.
     *
     * @return array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * Update metrics for an API call.
     *
     * @param string $method
     * @param float $duration
     * @param string|null $email
     * @return void
     */
    protected function updateMetrics($method, $duration, $email = null)
    {
        if (!isset($this->metrics['methods'][$method])) {
            $this->metrics['methods'][$method] = [
                'count' => 0,
                'total_duration' => 0,
                'avg_duration' => 0,
                'max_duration' => 0,
                'min_duration' => $duration
            ];
        }

        $stats = &$this->metrics['methods'][$method];
        $stats['count']++;
        $stats['total_duration'] += $duration;
        $stats['avg_duration'] = $stats['total_duration'] / $stats['count'];
        $stats['max_duration'] = max($stats['max_duration'], $duration);
        $stats['min_duration'] = min($stats['min_duration'], $duration);

        // Track by hour for trending
        $hour = date('Y-m-d H:00:00');
        if (!isset($this->metrics['hourly'][$hour][$method])) {
            $this->metrics['hourly'][$hour][$method] = 0;
        }
        $this->metrics['hourly'][$hour][$method]++;
    }
}
