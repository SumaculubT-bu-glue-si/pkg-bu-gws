<?php

namespace Bu\Gws\Services;

use Google\Client;
use Google\Service\HangoutsChat;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleChatService
{
    protected $client;
    protected $chat;

    public function __construct()
    {
        $this->client = $this->createClient();
        $this->chat = new \Google\Service\HangoutsChat($this->client);
    }

    /**
     * Create a client for Chat operations
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
        $client->setScopes([
            'https://www.googleapis.com/auth/chat',
            'https://www.googleapis.com/auth/chat.messages',
            'https://www.googleapis.com/auth/chat.spaces',
            'https://www.googleapis.com/auth/chat.memberships'
        ]);
        $client->setSubject($adminEmail);

        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 60
        ]));

        return $client;
    }

    /**
     * Create a client for Chat operations that require app authentication (no domain-wide delegation)
     */
    protected function createAppClient(): Client
    {
        $client = new Client();

        $credentialsPath = config('services.google.credentials_path') ?? env('GOOGLE_WORKSPACE_CREDENTIALS_PATH');

        if (empty($credentialsPath)) {
            throw new \Exception('Google Workspace credentials not configured');
        }

        $client->setAuthConfig($credentialsPath);
        $client->setApplicationName('AIMS Studio');
        $client->setScopes([
            'https://www.googleapis.com/auth/chat.app.delete'  // App authentication only
        ]);
        // No setSubject() - this is app authentication, not domain-wide delegation

        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 60
        ]));

        return $client;
    }

    /**
     * Create a client for Chat operations with delete scope
     */
    protected function createDeleteClient(): Client
    {
        $client = new Client();

        $credentialsPath = config('services.google.credentials_path') ?? env('GOOGLE_WORKSPACE_CREDENTIALS_PATH');
        $adminEmail = config('services.google.admin_email') ?? env('GOOGLE_WORKSPACE_ADMIN_EMAIL');

        if (empty($credentialsPath) || empty($adminEmail)) {
            throw new \Exception('Google Workspace credentials not configured');
        }

        $client->setAuthConfig($credentialsPath);
        $client->setApplicationName('AIMS Studio');
        $client->setScopes([
            'https://www.googleapis.com/auth/chat.delete'  // Try this scope with domain-wide delegation
        ]);
        $client->setSubject($adminEmail);

        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 60
        ]));

        return $client;
    }

    /**
     * List all Chat spaces
     */
    public function listSpaces(): array
    {
        try {
            $spaces = $this->chat->spaces->listSpaces();

            return [
                'success' => true,
                'spaces' => $spaces->getSpaces(),
                'total' => count($spaces->getSpaces())
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create a new Chat space
     */
    public function createSpace(string $displayName, string $description = null): array
    {
        try {
            $space = new \Google\Service\HangoutsChat\Space([
                'displayName' => $displayName,
                'spaceType' => 'SPACE'
            ]);

            $createdSpace = $this->chat->spaces->create($space);

            // Send description as first message if provided
            if ($description) {
                try {
                    $message = new \Google\Service\HangoutsChat\Message([
                        'text' => "📋 **Space Description:**\n{$description}"
                    ]);
                    $this->chat->spaces_messages->create($createdSpace->getName(), $message);
                } catch (Exception $e) {
                    Log::warning('Failed to send description message', [
                        'space_name' => $createdSpace->getName(),
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'success' => true,
                'space' => $createdSpace,
                'spaceId' => $createdSpace->getName(),
                'spaceName' => $createdSpace->getName()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Add members to a Chat space
     */
    public function addMembers(string $spaceName, array $memberEmails): array
    {
        try {
            $addedMembers = [];
            $failedMembers = [];

            foreach ($memberEmails as $email) {
                try {
                    $membership = new \Google\Service\HangoutsChat\Membership([
                        'member' => [
                            'name' => "users/{$email}",
                            'type' => 'HUMAN'
                        ]
                    ]);

                    $this->chat->spaces_members->create($spaceName, $membership);
                    $addedMembers[] = $email;
                } catch (Exception $e) {
                    $failedMembers[] = [
                        'email' => $email,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return [
                'success' => true,
                'addedMembers' => $addedMembers,
                'failedMembers' => $failedMembers,
                'totalAdded' => count($addedMembers),
                'totalFailed' => count($failedMembers)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Send message to a Chat space
     */
    public function sendMessage(string $spaceName, array $messageData): array
    {
        try {
            $message = new \Google\Service\HangoutsChat\Message([
                'text' => $messageData['text'] ?? '',
                'thread' => [
                    'name' => $messageData['thread_name'] ?? null
                ]
            ]);

            // Add cards if provided
            if (isset($messageData['cards'])) {
                $message->setCards($messageData['cards']);
            }

            $createdMessage = $this->chat->spaces_messages->create($spaceName, $message);

            return [
                'success' => true,
                'message' => $createdMessage,
                'messageId' => $createdMessage->getName()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a Chat space using domain-wide delegation
     */
    public function deleteSpace(string $spaceName): array
    {
        try {
            // First try with domain-wide delegation using chat.delete scope
            $deleteClient = $this->createDeleteClient();
            $deleteChat = new \Google\Service\HangoutsChat($deleteClient);

            $deleteChat->spaces->delete($spaceName);

            return [
                'success' => true,
                'message' => 'Space deleted successfully'
            ];
        } catch (Exception $e) {
            // If domain-wide delegation fails, try app authentication
            try {
                $appClient = $this->createAppClient();
                $appChat = new \Google\Service\HangoutsChat($appClient);

                $appChat->spaces->delete($spaceName);

                return [
                    'success' => true,
                    'message' => 'Space deleted successfully (app auth)'
                ];
            } catch (Exception $appError) {
                return [
                    'success' => false,
                    'error' => "Domain delegation failed: " . $e->getMessage() .
                        " | App auth failed: " . $appError->getMessage()
                ];
            }
        }
    }

    /**
     * Create audit chat space with members and initial message
     */
    public function createAuditSpace(string $auditName, string $location, array $memberEmails, array $customMessage = null): array
    {
        try {
            // Create space
            $spaceResult = $this->createSpace($auditName);
            if (!$spaceResult['success']) {
                return $spaceResult;
            }

            $spaceName = $spaceResult['spaceName'];

            // Add members
            $memberResult = $this->addMembers($spaceName, $memberEmails);
            if (!$memberResult['success']) {
                Log::warning('Failed to add some members to chat space', [
                    'space_name' => $spaceName,
                    'error' => $memberResult['error']
                ]);
            }

            // Send initial message
            $message = $customMessage ?? [
                'text' => "Audit Plan Created: {$auditName}\n\n" .
                    "Location: {$location}\n" .
                    "Participants: " . count($memberEmails) . " people\n\n" .
                    "Please coordinate and complete the audit. Good luck!"
            ];

            $messageResult = $this->sendMessage($spaceName, $message);
            if (!$messageResult['success']) {
                Log::warning('Failed to send initial message to chat space', [
                    'space_name' => $spaceName,
                    'error' => $messageResult['error']
                ]);
            }

            return [
                'success' => true,
                'spaceId' => $spaceResult['spaceId'],
                'spaceName' => $spaceName,
                'totalMembers' => count($memberEmails),
                'messageId' => $messageResult['messageId'] ?? null
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create audit chat space', [
                'audit_name' => $auditName,
                'location' => $location,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get the service account email from credentials
     */
    protected function getServiceAccountEmail(): string
    {
        $credentialsPath = config('services.google.credentials_path') ?? env('GOOGLE_WORKSPACE_CREDENTIALS_PATH');
        $credentialsFile = storage_path('app/' . $credentialsPath);

        if (file_exists($credentialsFile)) {
            $credentials = json_decode(file_get_contents($credentialsFile), true);
            return $credentials['client_email'] ?? 'unknown@serviceaccount.com';
        }

        return 'unknown@serviceaccount.com';
    }

    /**
     * Test Chat API connection
     */
    public function testConnection(): bool
    {
        try {
            $this->chat->spaces->listSpaces();
            return true;
        } catch (Exception $e) {
            Log::debug('Chat API connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}