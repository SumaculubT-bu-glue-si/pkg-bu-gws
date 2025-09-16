<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestGoogleWorkspaceUsers extends Command
{
    protected $signature = 'gws:list-users 
                          {domain : The domain to list users from} 
                          {--max-results=100 : Maximum number of results to return} 
                          {--page-token= : Token for getting the next page} 
                          {--query= : Search query to filter users} 
                          {--org-unit= : Organization unit path to filter users}';

    protected $description = 'Test Google Workspace users listing via GraphQL';

    public function handle()
    {
        $query = <<<'GRAPHQL'
        query ListGoogleWorkspaceUsers(
            $domain: String!
            $maxResults: Int
            $pageToken: String
            $query: String
            $orgUnitPath: String
        ) {
            googleWorkspaceUsers(
                domain: $domain
                maxResults: $maxResults
                pageToken: $pageToken
                query: $query
                orgUnitPath: $orgUnitPath
            ) {
                users {
                    id
                    primaryEmail
                    name {
                        givenName
                        familyName
                        fullName
                    }
                    isAdmin
                    orgUnitPath
                    lastLoginTime
                    creationTime
                }
                nextPageToken
                totalItems
            }
        }
        GRAPHQL;

        $variables = [
            'domain' => $this->argument('domain'),
            'maxResults' => (int) $this->option('max-results'),
            'pageToken' => $this->option('page-token'),
            'query' => $this->option('query'),
            'orgUnitPath' => $this->option('org-unit'),
        ];

        // Filter out null values
        $variables = array_filter($variables, fn($value) => !is_null($value));

        $response = Http::post('http://127.0.0.1:8000/api/graphql', [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($response->successful()) {
            $data = $response->json();

            if (isset($data['errors'])) {
                $this->error('GraphQL Error:');
                foreach ($data['errors'] as $error) {
                    $this->error($error['message']);
                }
                return 1;
            }

            $users = $data['data']['googleWorkspaceUsers']['users'];
            $nextPageToken = $data['data']['googleWorkspaceUsers']['nextPageToken'];
            $totalItems = $data['data']['googleWorkspaceUsers']['totalItems'];

            // Display results in a table with masked emails in production
            $this->table(
                ['Email', 'Name', 'Admin', 'Org Unit', 'Last Login'],
                collect($users)->map(fn($user) => [
                    $this->maskEmailForDisplay($user['primaryEmail']),
                    $user['name']['fullName'],
                    $user['isAdmin'] ? 'Yes' : 'No',
                    $user['orgUnitPath'],
                    $user['lastLoginTime'] ?? 'Never',
                ])
            );

            $this->info("Total items: $totalItems");
            if ($nextPageToken) {
                $this->info("Next page available");
                if (env('APP_DEBUG', false)) {
                    $this->info("To get next page, run:");
                    $this->info("php artisan gws:list-users {$this->argument('domain')} --page-token=\"$nextPageToken\"");
                }
            }

            return 0;
        }

        $this->error('HTTP Error: ' . $response->status());
        return 1;
    }

    /**
     * Mask email for display in production
     */
    private function maskEmailForDisplay(string $email): string
    {
        if (env('APP_DEBUG', false) || env('GOOGLE_WORKSPACE_DEBUG_LOGGING', false)) {
            return $email; // Show full email in debug mode
        }

        // Mask email in production: user@domain.com -> u***@domain.com
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            $username = $parts[0];
            $domain = $parts[1];
            $maskedUsername = substr($username, 0, 1) . str_repeat('*', max(3, strlen($username) - 1));
            return $maskedUsername . '@' . $domain;
        }

        return $email;
    }
}
