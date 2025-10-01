<?php

namespace Bu\Gws\GraphQL\Mutations;

use Bu\Gws\Services\GoogleWorkspaceSyncService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GoogleWorkspaceMutations
{
    protected $syncService;

    public function __construct(GoogleWorkspaceSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Sync users from Google Workspace
     */
    public function syncUsers($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        try {
            $domain = $args['domain'];
            $syncType = $args['syncType'] ?? 'recent';
            $batchSize = $args['batchSize'] ?? 100;

            switch ($syncType) {
                case 'all':
                    $stats = $this->syncService->syncAllUsers($domain, [
                        'batch_size' => $batchSize
                    ]);
                    break;

                case 'recent':
                    $since = $args['since'] ?? now()->subHours(24)->toISOString();
                    $stats = $this->syncService->syncRecentlyModifiedUsers($domain, $since);
                    break;

                case 'specific':
                    if (empty($args['emails'])) {
                        throw new \InvalidArgumentException('Emails are required for specific sync');
                    }
                    $results = $this->syncService->syncUsersByEmails($args['emails'], $domain);
                    $stats = $this->syncService->getSyncStats();
                    break;

                default:
                    throw new \InvalidArgumentException("Invalid sync type: {$syncType}");
            }

            return [
                'success' => true,
                'stats' => [
                    'totalProcessed' => $stats['total_processed'],
                    'created' => $stats['created'],
                    'updated' => $stats['updated'],
                    'skipped' => $stats['skipped'],
                    'errors' => $stats['errors'],
                    'durationSeconds' => $stats['duration_seconds'] ?? 0,
                ],
                'message' => 'Sync completed successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stats' => [
                    'totalProcessed' => 0,
                    'created' => 0,
                    'updated' => 0,
                    'skipped' => 0,
                    'errors' => 1,
                    'durationSeconds' => 0,
                ],
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a test Google Chat message
     */
    public function sendTestChatMessage($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        try {
            $userEmail = $args['userEmail'];
            $message = $args['message'];

            $googleWorkspaceService = app(\Bu\Gws\Services\GoogleWorkspaceService::class);

            $messageData = [
                'text' => $message
            ];

            $result = $googleWorkspaceService->sendChatMessage($userEmail, $messageData);

            return [
                'success' => true,
                'messageId' => $result->getName(),
                'message' => 'Test message sent successfully'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'messageId' => null,
                'message' => $e->getMessage()
            ];
        }
    }

    public function clearCache($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $gws = app(\Bu\Gws\Services\GoogleWorkspaceService::class);
        return $gws->clearCache();
    }

    public function getMetrics($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        $gws = app(\Bu\Gws\Services\GoogleWorkspaceService::class);
        return $gws->getMetrics();
    }
}