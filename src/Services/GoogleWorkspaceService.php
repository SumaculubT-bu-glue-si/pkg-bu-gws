<?php

namespace Bu\Gws\Services;

use Google\Client;
use Google\Service\Directory;
use Exception;
use Bu\Gws\Services\GoogleWorkspace\Cache\GoogleWorkspaceCache;
use Bu\Gws\Services\GoogleWorkspace\Monitoring\GoogleWorkspaceMonitor;

class GoogleWorkspaceService
{
    protected $client;
    protected $directory;
    protected $cache;
    protected $monitor;

    public function __construct(
        Client $client = null, 
        Directory $directory = null,
        GoogleWorkspaceCache $cache = null,
        GoogleWorkspaceMonitor $monitor = null
    ) {
        $this->client = $client ?? $this->createClient();
        $this->directory = $directory ?? $this->createDirectoryService();
        $this->cache = $cache;
        $this->monitor = $monitor;
    }

    /**
     * Create a new Google Client
     * 
     * @return Client
     */
    protected function createClient(): Client
    {
        try {
            $credentialsPath = '/var/www/html/aims-471101-d969075b5f27.json';

            if (empty($credentialsPath)) {
                throw new \Exception('Google Workspace credentials path not configured');
            }

            $adminEmail = 'sumaculub_t@bu.glue-si.com';
            echo "Admin Email: {$adminEmail}\n";

            if (empty($adminEmail)) {
                throw new \Exception('Google Workspace admin email not configured');
            }

            if (!file_exists($credentialsPath) || !is_readable($credentialsPath)) {
                throw new \Exception("Credentials file not found or unreadable at {$credentialsPath}");
            }

            $client = new Client();
            $client->setAuthConfig($credentialsPath);
            $client->setApplicationName('AssetWise');
            $client->setScopes([
                'https://www.googleapis.com/auth/admin.directory.user',
                'https://www.googleapis.com/auth/calendar'
            ]);
            $client->setSubject($adminEmail);

            return $client;
        } catch (\Exception $e) {
            \Log::error('Google Workspace Client initialization failed', [
                'error_type'      => get_class($e),
                'message'         => $e->getMessage(),
                'credentialsPath' => $credentialsPath ?? null,
                'adminEmail'      => $adminEmail ?? null,
            ]);

            throw new \Exception("Failed to initialize Google Workspace Client: {$e->getMessage()}", 0, $e);
        }
    }


    /**
     * Create a new Directory Service
     * 
     * @return Directory
     */
    protected function createDirectoryService(): Directory
    {
        try {
            if (!$this->client) {
                throw new \Exception('Google Client not initialized before creating Directory Service');
            }

            return new Directory($this->client);
        } catch (\Exception $e) {
            \Log::error('Google Workspace Directory Service initialization failed', [
                'error_type' => get_class($e),
                'message'    => $e->getMessage(),
            ]);

            throw new \Exception("Failed to initialize Directory Service: {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * List users in the domain with pagination and advanced filtering
     *
     * @param string $domain Domain name (e.g., 'example.com')
     * @param array $options Additional options for filtering and sorting
     * @return array
     */
    public function listUsers(string $domain, array $options = [])
    {
        try {
            // Only log in debug mode with minimal information
            if (config('services.google.app_debug') && config('services.google.debug_logging')) {
                \Log::info('Listing Google Workspace users', [
                    'domain_configured' => !empty($domain),
                    'options_count' => count($options),
                    'max_results' => $options['maxResults'] ?? 100,
                ]);
            }

            $optParams = array_merge([
                'domain' => $domain,
                'projection' => 'full',
                'orderBy' => 'email',
                'maxResults' => 100,
            ], $options);

            // Try to get from cache first
            if ($this->cache) {
                $cachedResult = $this->cache->getCachedUserList($domain, $optParams);
                if ($cachedResult) {
                    $this->monitor?->trackCacheEvent('list_users', $domain, true);
                    return $cachedResult;
                }
                $this->monitor?->trackCacheEvent('list_users', $domain, false);
            }

            // Track API call start time
            $startTime = microtime(true);
            
            try {
                $results = $this->directory->users->listUsers($optParams);
                
                // Track successful API call
                $this->monitor?->trackApiCall('listUsers', $startTime, $domain);

                $response = [
                    'users' => $results->getUsers(),
                    'nextPageToken' => $results->nextPageToken,
                    'totalItems' => count($results->getUsers())
                ];

                // Cache the results
                if ($this->cache) {
                    $this->cache->cacheUserList($domain, $optParams, $response);
                }

                return $response;
            } catch (Exception $e) {
                // Track failed API call before throwing
                $this->monitor?->trackApiCall('listUsers', $startTime, $domain);
                throw $e;
            }
        } catch (Exception $e) {
            // Enhanced error logging without sensitive data
            $errorDetails = [
                'operation' => 'listUsers',
                'has_domain' => !empty($domain),
                'options_provided' => !empty($options),
                'error_type' => get_class($e),
                'environment' => app()->environment(),
            ];
            
            \Log::error('Google Workspace operation failed', $errorDetails);
            $this->monitor?->logError('listUsers', $e, $errorDetails);
            
            throw $e;
        }
    }

    /**
     * Search users by query with specific criteria
     *
     * @param string $domain Domain name
     * @param string $query Search query
     * @param array $options Additional options
     * @return array
     */
    public function searchUsers(string $domain, string $query, array $options = [])
    {
        try {
            $optParams = array_merge([
                'domain' => $domain,
                'projection' => 'full',
                'query' => $query,
                'orderBy' => 'email',
                'maxResults' => 100,
            ], $options);

            $results = $this->directory->users->listUsers($optParams);
            return [
                'users' => $results->getUsers(),
                'nextPageToken' => $results->nextPageToken,
                'totalItems' => count($results->getUsers())
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to search users in Google Workspace');
        }
    }

    /**
     * List users in specific organizational unit
     *
     * @param string $domain Domain name
     * @param string $orgUnitPath Organizational unit path
     * @param array $options Additional options
     * @return array
     */
    public function listUsersByOrgUnit(string $domain, string $orgUnitPath, array $options = [])
    {
        try {
            $optParams = array_merge([
                'domain' => $domain,
                'projection' => 'full',
                'orderBy' => 'email',
                'maxResults' => 100,
                'query' => "orgUnitPath='{$orgUnitPath}'", // Use query instead of orgUnitPath
            ], $options);

            $results = $this->directory->users->listUsers($optParams);
            return [
                'users' => $results->getUsers(),
                'nextPageToken' => $results->nextPageToken,
                'totalItems' => count($results->getUsers())
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list users in organizational unit');
        }
    }

    /**
     * List recently modified users
     *
     * @param string $domain Domain name
     * @param string $updatedMin Minimum time of last update (RFC 3339 timestamp)
     * @param array $options Additional options
     * @return array
     */
    public function listRecentlyModifiedUsers(string $domain, string $updatedMin, array $options = [])
    {
        try {
            $optParams = array_merge([
                'domain' => $domain,
                'projection' => 'full',
                'orderBy' => 'email',
                'maxResults' => 100,
                'updatedMin' => $updatedMin,
            ], $options);

            $results = $this->directory->users->listUsers($optParams);
            return [
                'users' => $results->getUsers(),
                'nextPageToken' => $results->nextPageToken,
                'totalItems' => count($results->getUsers())
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to list recently modified users');
        }
    }

    /**
     * Get user by email with specified projection
     *
     * @param string $email User email
     * @param string $projection The subset of fields to fetch ('basic', 'full', 'custom')
     * @return object
     */
    public function getUser(string $email, string $projection = 'full')
    {
        try {
            // Try to get from cache first
            if ($this->cache) {
                $cachedUser = $this->cache->rememberUser($email, function () use ($email, $projection) {
                    $startTime = microtime(true);
                    $user = $this->directory->users->get($email, ['projection' => $projection]);
                    $this->monitor?->trackApiCall('getUser', $startTime, $email);
                    return $user;
                });
                $this->monitor?->trackCacheEvent('get_user', $email, true);
                return $cachedUser;
            }

            // If no cache, direct API call
            $startTime = microtime(true);
            $user = $this->directory->users->get($email, ['projection' => $projection]);
            $this->monitor?->trackApiCall('getUser', $startTime, $email);
            return $user;
        } catch (Exception $e) {
            $this->monitor?->logError('getUser', $e, ['operation' => 'getUser']);
            throw new Exception('Failed to get user from Google Workspace');
        }
    }

    /**
     * Get basic user profile information
     *
     * @param string $email User email
     * @return object
     */
    public function getUserProfile(string $email)
    {
        try {
            $user = $this->getUser($email, 'basic');
            return [
                'id' => $user->getId(),
                'primaryEmail' => $user->getPrimaryEmail(),
                'name' => $user->getName(),
                'isAdmin' => $user->getIsAdmin(),
                'isDelegatedAdmin' => $user->getIsDelegatedAdmin(),
                'lastLoginTime' => $user->getLastLoginTime(),
                'creationTime' => $user->getCreationTime(),
            ];
        } catch (Exception $e) {
            throw new Exception('Failed to get user profile from Google Workspace');
        }
    }

    /**
     * Get user's organizational unit path
     *
     * @param string $email User email
     * @return string
     */
    public function getUserOrgUnit(string $email)
    {
        try {
            $user = $this->getUser($email, 'basic');
            return $user->getOrgUnitPath();
        } catch (Exception $e) {
            throw new Exception('Failed to get user organizational unit');
        }
    }

    /**
     * Update user information
     *
     * @param string $userKey User email or ID
     * @param array $userData User data to update
     * @return object
     */
    public function updateUser(string $userKey, array $userData)
    {
        try {
            $startTime = microtime(true);
            
            // Get existing user
            $user = $this->directory->users->get($userKey);
            $changes = [];
            
            // Update user properties properly
            if (isset($userData['name'])) {
                $user->setName(new Directory\UserName($userData['name']));
                $changes['name'] = true; // Don't log actual name
            }
            
            if (isset($userData['primaryEmail'])) {
                $user->setPrimaryEmail($userData['primaryEmail']);
                $changes['primaryEmail'] = true; // Don't log actual email
            }
            
            if (isset($userData['orgUnitPath'])) {
                $user->setOrgUnitPath($userData['orgUnitPath']);
                $changes['orgUnitPath'] = $userData['orgUnitPath']; // OK to log org unit
            }
            
            if (isset($userData['suspended'])) {
                $user->setSuspended($userData['suspended']);
                $changes['suspended'] = $userData['suspended']; // OK to log boolean
            }

            $updatedUser = $this->directory->users->update($userKey, $user);
            
            // Track API call
            $this->monitor?->trackApiCall('updateUser', $startTime, 'user_updated');
            
            // Dispatch event
            event(new \Bu\Gws\Events\GoogleWorkspace\UserUpdated($updatedUser, $changes, [
                'source' => 'api',
                'updater' => auth()->user()?->email ?? 'system'
            ]));
            
            // Invalidate cache
            if ($this->cache) {
                $this->cache->invalidateUser($userKey);
                if (isset($userData['primaryEmail'])) {
                    $this->cache->invalidateUser($userData['primaryEmail']);
                    $this->cache->invalidateDomain(explode('@', $userData['primaryEmail'])[1]);
                }
                if (strpos($userKey, '@') !== false) {
                    $this->cache->invalidateDomain(explode('@', $userKey)[1]);
                }
            }
            
            return $updatedUser;
        } catch (Exception $e) {
            $this->monitor?->logError('updateUser', $e, [
                'operation' => 'updateUser',
                'changes_count' => count($userData)
            ]);
            throw new Exception('Failed to update user in Google Workspace');
        }
    }

    /**
     * Check if the service is properly configured
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        try {
            // Try to list users (with a limit of 1) to verify configuration
            $this->directory->users->listUsers(['maxResults' => 1]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create a new user in Google Workspace
     *
     * @param array $userData User data including required fields
     * @return object
     */
    public function createUser(array $userData)
    {
        try {
            $startTime = microtime(true);
            
            // Only log in debug mode without sensitive data
            if (config('services.google.app_debug') && config('services.google.debug_logging')) {
                \Log::debug('Creating Google Workspace user', [
                    'has_email' => !empty($userData['primaryEmail']),
                    'has_name' => !empty($userData['name']),
                    'org_unit' => $userData['orgUnitPath'] ?? 'default',
                ]);
            }
            
            // Create user
            $user = new Directory\User($userData);
            $createdUser = $this->directory->users->insert($user);
            
            // Track API call
            $this->monitor?->trackApiCall('createUser', $startTime, 'user_created');

            // Dispatch event
            event(new \Bu\Gws\Events\GoogleWorkspace\UserCreated($createdUser, [
                'source' => 'api',
                'creator' => auth()->user()?->email ?? 'system'
            ]));

            // Invalidate cache
            if ($this->cache && isset($userData['primaryEmail'])) {
                $this->cache->invalidateUser($userData['primaryEmail']);
                $this->cache->invalidateDomain(explode('@', $userData['primaryEmail'])[1]);
            }

            return $createdUser;
        } catch (Exception $e) {
            $this->monitor?->logError('createUser', $e, [
                'operation' => 'createUser',
                'has_user_data' => !empty($userData)
            ]);
            throw new Exception('Failed to create user in Google Workspace');
        }
    }

    /**
     * Suspend a user account
     *
     * @param string $userKey User email or ID
     * @return object
     */
    public function suspendUser(string $userKey)
    {
        return $this->updateUser($userKey, ['suspended' => true]);
    }

    /**
     * Unsuspend a user account
     *
     * @param string $userKey User email or ID
     * @return object
     */
    public function unsuspendUser(string $userKey)
    {
        return $this->updateUser($userKey, ['suspended' => false]);
    }

    /**
     * Delete a user account
     *
     * @param string $userKey User email or ID
     * @return void
     */
    public function deleteUser(string $userKey)
    {
        try {
            $startTime = microtime(true);
            
            $this->directory->users->delete($userKey);
            
            // Track API call
            $this->monitor?->trackApiCall('deleteUser', $startTime, 'user_deleted');
            
            // Dispatch event
            event(new \Bu\Gws\Events\GoogleWorkspace\UserDeleted($userKey, [
                'source' => 'api',
                'deleter' => auth()->user()?->email ?? 'system'
            ]));
            
            // Invalidate cache
            if ($this->cache) {
                $this->cache->invalidateUser($userKey);
                if (strpos($userKey, '@') !== false) {
                    $this->cache->invalidateDomain(explode('@', $userKey)[1]);
                }
            }
            
        } catch (Exception $e) {
            $this->monitor?->logError('deleteUser', $e, ['operation' => 'deleteUser']);
            throw new Exception('Failed to delete user from Google Workspace');
        }
    }

    /**
     * Add an alias for a user
     *
     * @param string $userKey User email or ID
     * @param string $alias Alias email address
     * @return object
     */
    public function addUserAlias(string $userKey, string $alias)
    {
        try {
            $userAlias = new Directory\Alias(['alias' => $alias]);
            return $this->directory->users_aliases->insert($userKey, $userAlias);
        } catch (Exception $e) {
            throw new Exception('Failed to add user alias in Google Workspace');
        }
    }

    /**
     * Remove an alias from a user
     *
     * @param string $userKey User email or ID
     * @param string $alias Alias email address
     * @return void
     */
    public function removeUserAlias(string $userKey, string $alias)
    {
        try {
            $this->directory->users_aliases->delete($userKey, $alias);
        } catch (Exception $e) {
            throw new Exception('Failed to remove user alias from Google Workspace');
        }
    }

    /**
     * Set user password
     *
     * @param string $userKey User email or ID
     * @param string $password New password
     * @param bool $changePasswordAtNextLogin Whether user must change password at next login
     * @return object
     */
    public function setUserPassword(string $userKey, string $password, bool $changePasswordAtNextLogin = false)
    {
        return $this->updateUser($userKey, [
            'password' => $password,
            'changePasswordAtNextLogin' => $changePasswordAtNextLogin
        ]);
    }

    /**
     * Test calendar connection
     */
    public function testCalendarConnection(): bool
    {
        try {
            $calendar = new \Google\Service\Calendar($this->client);
            $calendar->calendarList->listCalendarList(['maxResults' => 1]);
            return true;
        } catch (Exception $e) {
            \Log::debug('Calendar connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create calendar event for user
     */
    public function createCalendarEvent(string $userEmail, array $eventData)
    {
        try {
            $startTime = microtime(true);
            
            // Create calendar service
            $calendar = new \Google\Service\Calendar($this->client);
            
            // Set up event
            $event = new \Google\Service\Calendar\Event([
                'summary' => $eventData['title'],
                'description' => $eventData['description'],
                'start' => [
                    'dateTime' => $eventData['start_datetime'],
                    'timeZone' => $eventData['timezone'] ?? 'Asia/Tokyo',
                ],
                'end' => [
                    'dateTime' => $eventData['end_datetime'],
                    'timeZone' => $eventData['timezone'] ?? 'Asia/Tokyo',
                ],
                'attendees' => [
                    ['email' => $userEmail]
                ],
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // 1 day before
                        ['method' => 'popup', 'minutes' => 60], // 1 hour before
                    ],
                ],
            ]);
            
            // Insert event
            $createdEvent = $calendar->events->insert($userEmail, $event);
            
            // Track API call
            $this->monitor?->trackApiCall('createCalendarEvent', $startTime, 'event_created');
            
            return $createdEvent;
        } catch (Exception $e) {
            $this->monitor?->logError('createCalendarEvent', $e, [
                'operation' => 'createCalendarEvent',
                'user_email' => $userEmail
            ]);
            throw new Exception('Failed to create calendar event');
        }
    }

    /**
     * Update calendar event
     */
    public function updateCalendarEvent(string $userEmail, string $eventId, array $eventData)
    {
        try {
            $calendar = new \Google\Service\Calendar($this->client);
            
            // Get existing event
            $event = $calendar->events->get($userEmail, $eventId);
            
            // Update event data
            $event->setSummary($eventData['title']);
            $event->setDescription($eventData['description']);
            $event->setStart(new \Google\Service\Calendar\EventDateTime([
                'dateTime' => $eventData['start_datetime'],
                'timeZone' => $eventData['timezone'] ?? 'Asia/Tokyo',
            ]));
            $event->setEnd(new \Google\Service\Calendar\EventDateTime([
                'dateTime' => $eventData['end_datetime'],
                'timeZone' => $eventData['timezone'] ?? 'Asia/Tokyo',
            ]));
            
            // Update event
            $updatedEvent = $calendar->events->update($userEmail, $eventId, $event);
            
            return $updatedEvent;
        } catch (Exception $e) {
            throw new Exception('Failed to update calendar event');
        }
    }

    /**
     * Delete calendar event
     */
    public function deleteCalendarEvent(string $userEmail, string $eventId)
    {
        try {
            $calendar = new \Google\Service\Calendar($this->client);
            $calendar->events->delete($userEmail, $eventId);
            return true;
        } catch (Exception $e) {
            throw new Exception('Failed to delete calendar event');
        }
    }
}