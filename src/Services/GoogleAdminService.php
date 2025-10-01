<?php

namespace Bu\Gws\Services;

use Google\Client;
use Google\Service\Directory;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleAdminService
{
    protected $client;
    protected $directory;

    public function __construct()
    {
        $this->client = $this->createClient();
        $this->directory = new Directory($this->client);
    }

    /**
     * Create a client for Admin SDK operations
     */
    protected function createClient(): Client
    {
        $client = new Client();

        $credentialsPath = config('services.google.credentials_path') ?? env('GOOGLE_WORKSPACE_CREDENTIALS_PATH');
        $adminEmail = config('services.google.admin_email') ?? env('GOOGLE_WORKSPACE_ADMIN_EMAIL');

        if (empty($credentialsPath) || empty($adminEmail)) {
            throw new \Exception('Google Workspace credentials not configured');
        }

        $client->setAuthConfig($credentialsPath);
        $client->setApplicationName('AIMS Studio');
        $client->setScopes(['https://www.googleapis.com/auth/admin.directory.user']);
        $client->setSubject($adminEmail);

        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 60
        ]));

        return $client;
    }

    /**
     * Get employees for audit
     */
    public function getAuditEmployees(string $location, array $filters = []): array
    {
        try {
            $domain = config('services.google.domain') ?? env('GOOGLE_WORKSPACE_DOMAIN');

            $params = [
                'domain' => $domain,
                'maxResults' => 100
            ];

            // Add filters if provided
            if (!empty($filters)) {
                $params = array_merge($params, $filters);
            }

            $users = $this->directory->users->listUsers($params);

            return [
                'success' => true,
                'employees' => $users->getUsers(),
                'total' => count($users->getUsers())
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get user details by email
     */
    public function getUser(string $email): array
    {
        try {
            $user = $this->directory->users->get($email);

            return [
                'success' => true,
                'user' => $user
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Search users
     */
    public function searchUsers(string $query, array $options = []): array
    {
        try {
            $domain = config('services.google.domain') ?? env('GOOGLE_WORKSPACE_DOMAIN');

            $params = array_merge([
                'domain' => $domain,
                'query' => $query,
                'maxResults' => 100
            ], $options);

            $users = $this->directory->users->listUsers($params);

            return [
                'success' => true,
                'users' => $users->getUsers(),
                'total' => count($users->getUsers())
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Admin SDK connection
     */
    public function testConnection(): bool
    {
        try {
            $domain = config('services.google.domain') ?? env('GOOGLE_WORKSPACE_DOMAIN');
            $this->directory->users->listUsers(['domain' => $domain, 'maxResults' => 1]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
