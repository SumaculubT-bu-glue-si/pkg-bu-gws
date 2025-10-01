<?php

namespace Bu\Gws\Services;

use Bu\Server\Models\Employee;
use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Exception;

class GoogleWorkspaceSyncService
{
    protected $googleWorkspaceService;
    protected $syncStats = [
        'total_processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => 0,
        'start_time' => null,
        'end_time' => null,
    ];

    public function __construct(GoogleWorkspaceService $googleWorkspaceService)
    {
        $this->googleWorkspaceService = $googleWorkspaceService;
        $this->syncStats['start_time'] = now();
    }

    /**
     * Sync all users from Google Workspace to local database
     *
     * @param string $domain
     * @param array $options
     * @return array
     */
    public function syncAllUsers(string $domain, array $options = []): array
    {
        try {
            $allUsers = [];
            $nextPageToken = null;
            
            do {
                $params = [
                    'maxResults' => $options['batch_size'] ?? 100,
                    'pageToken' => $nextPageToken,
                ];

                $response = $this->googleWorkspaceService->listUsers($domain, $params);
                $users = $response['users'] ?? [];
                $nextPageToken = $response['nextPageToken'] ?? null;

                foreach ($users as $user) {
                    $this->syncUser($user);
                }

                $allUsers = array_merge($allUsers, $users);

                // Add delay between batches to avoid rate limiting
                if ($nextPageToken && !empty($options['delay_between_batches'])) {
                    sleep($options['delay_between_batches']);
                }

            } while ($nextPageToken);

            $this->syncStats['end_time'] = now();

            if (config('services.google.app_debug') && config('services.google.debug_logging')) {
                Log::info('Google Workspace sync completed', $this->getSyncStats());
            }

            return $this->getSyncStats();

        } catch (Exception $e) {
            Log::error('Google Workspace sync failed', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'stats' => $this->getSyncStats()
            ]);
            throw $e;
        }
    }

    /**
     * Sync a single user from Google Workspace
     *
     * @param object $gwsUser
     * @return Employee|null
     */
    public function syncUser($gwsUser): ?Employee
    {
        try {
            $this->syncStats['total_processed']++;

            $email = $gwsUser->getPrimaryEmail();
            $name = $gwsUser->getName();
            
            // Skip if no email
            if (empty($email)) {
                $this->syncStats['skipped']++;
                return null;
            }

            // Get the Google Workspace User ID as employee_id
            $gwsUserId = $gwsUser->getId();
            if (!$gwsUserId) {
                $this->syncStats['skipped']++;
                Log::warning('GWS user has no ID', ['email' => $email]);
                return null;
            }

            // Prepare employee data
            $employeeData = [
                'employee_id' => $gwsUserId, // Use GWS User ID directly
                'name' => $this->formatUserName($name),
                'email' => $email,
                'location' => $this->extractLocation($gwsUser),
                'projects' => $this->extractProjects($gwsUser),
            ];

            // Find existing employee by email or employee_id
            $employee = Employee::where('email', $email)
                ->orWhere('employee_id', $gwsUserId)
                ->first();

            if ($employee) {
                // Update existing employee
                $hasChanges = $this->hasChanges($employee, $employeeData);
                
                if ($hasChanges) {
                    $employee->update($employeeData);
                    $this->syncStats['updated']++;
                    
                    if (config('services.google.app_debug') && config('services.google.debug_logging')) {
                        Log::debug('Employee updated from GWS', [
                            'employee_id' => $employee->employee_id,
                            'has_changes' => true
                        ]);
                    }
                } else {
                    $this->syncStats['skipped']++;
                }
                
                return $employee;
            } else {
                // Create new employee
                $employee = Employee::create($employeeData);
                $this->syncStats['created']++;
                
                if (config('services.google.app_debug') && config('services.google.debug_logging')) {
                    Log::debug('Employee created from GWS', [
                        'employee_id' => $employee->employee_id,
                        'has_email' => !empty($employee->email)
                    ]);
                }
                
                return $employee;
            }

        } catch (Exception $e) {
            $this->syncStats['errors']++;
            Log::error('Failed to sync user from GWS', [
                'error' => $e->getMessage(),
                'user_email' => $gwsUser->getPrimaryEmail() ?? 'unknown'
            ]);
            return null;
        }
    }

    /**
     * Sync specific users by email addresses
     *
     * @param array $emails
     * @param string $domain
     * @return array
     */
    public function syncUsersByEmails(array $emails, string $domain): array
    {
        $results = [];
        
        foreach ($emails as $email) {
            try {
                $user = $this->googleWorkspaceService->getUser($email);
                $employee = $this->syncUser($user);
                $results[$email] = $employee ? 'Success' : 'Failed';
            } catch (Exception $e) {
                $results[$email] = 'Error: ' . $e->getMessage();
            }
        }
        
        return $results;
    }

    /**
     * Sync recently modified users
     *
     * @param string $domain
     * @param string $since
     * @return array
     */
    public function syncRecentlyModifiedUsers(string $domain, string $since): array
    {
        try {
            $response = $this->googleWorkspaceService->listRecentlyModifiedUsers($domain, $since);
            $users = $response['users'] ?? [];

            foreach ($users as $user) {
                $this->syncUser($user);
            }

            return $this->getSyncStats();

        } catch (Exception $e) {
            Log::error('Failed to sync recently modified users', [
                'error' => $e->getMessage(),
                'domain' => $domain,
                'since' => $since
            ]);
            throw $e;
        }
    }

    /**
     * Format user name from Google Workspace
     */
    protected function formatUserName($nameObj): string
    {
        if (!$nameObj) return 'Unknown User';
        
        $givenName = $nameObj->getGivenName() ?? '';
        $familyName = $nameObj->getFamilyName() ?? '';
        
        return trim($givenName . ' ' . $familyName) ?: 'Unknown User';
    }

    /**
     * Extract location from Google Workspace user (customize based on your setup)
     */
    protected function extractLocation($gwsUser): ?string
    {
        // You can customize this based on how you store location in GWS
        $orgUnitPath = $gwsUser->getOrgUnitPath();
        
        // Map org units to locations
        $locationMap = [
            '/一般' => 'General Office',
            '/営業' => 'Sales Office',
            '/開発' => 'Development Office',
            '/管理' => 'Admin Office',
            '/テスト' => 'Test Office',
        ];

        return $locationMap[$orgUnitPath] ?? null;
    }

    /**
     * Extract projects from Google Workspace user
     */
    protected function extractProjects($gwsUser): array
    {
        // for now, it's empty
        return [];
    }

    /**
     * Check if employee data has changes
     */
    protected function hasChanges(Employee $employee, array $newData): bool
    {
        $fields = ['name', 'email', 'location', 'employee_id'];
        
        foreach ($fields as $field) {
            if ($employee->$field !== $newData[$field]) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get sync statistics
     */
    public function getSyncStats(): array
    {
        return $this->syncStats;
    }
}

