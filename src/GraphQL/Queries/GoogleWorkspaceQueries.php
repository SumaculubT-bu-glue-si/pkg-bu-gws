<?php

namespace Bu\Gws\GraphQL\Queries;

use Bu\Gws\Services\GoogleWorkspaceService;
use GraphQL\Type\Definition\ResolveInfo;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class GoogleWorkspaceQueries
{
    protected $googleWorkspaceService;

    public function __construct(GoogleWorkspaceService $googleWorkspaceService)
    {
        $this->googleWorkspaceService = $googleWorkspaceService;
    }

    /**
     * List users from Google Workspace
     *
     * @param mixed $root
     * @param array $args
     * @param GraphQLContext $context
     * @param ResolveInfo $resolveInfo
     * @return array
     */
    public function listUsers($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        // Extract arguments
        $domain = $args['domain'];
        $options = array_filter([
            'maxResults' => $args['maxResults'] ?? 100,
            'pageToken' => $args['pageToken'] ?? null,
            'query' => $args['query'] ?? null,
            'orgUnitPath' => $args['orgUnitPath'] ?? null,
            'updatedMin' => $args['updatedMin'] ?? null,
        ]);

        // Call the service
        return $this->googleWorkspaceService->listUsers($domain, $options);
    }

    /**
     * Test Google Workspace calendar connection
     */
    public function testCalendarConnection($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->googleWorkspaceService->testCalendarConnection();
    }

    /**
     * Test Google Workspace chat connection
     */
    public function testChatConnection($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->googleWorkspaceService->testChatConnection();
    }

    /**
     * Check if Google Workspace service is configured
     */
    public function isConfigured($root, array $args, GraphQLContext $context, ResolveInfo $resolveInfo)
    {
        return $this->googleWorkspaceService->isConfigured();
    }
}