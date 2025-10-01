<?php

namespace Bu\Gws\Services;

use Google\Client;
use Google\Service\Calendar;
use Exception;
use Illuminate\Support\Facades\Log;

class GoogleCalendarService
{
    protected $client;
    protected $calendar;

    public function __construct()
    {
        $this->client = $this->createClient();
        $this->calendar = new Calendar($this->client);
    }

    /**
     * Create a client for Calendar operations
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
        $client->setScopes(['https://www.googleapis.com/auth/calendar']);
        $client->setSubject($adminEmail);

        $client->setHttpClient(new \GuzzleHttp\Client([
            'verify' => false,
            'timeout' => 60
        ]));

        return $client;
    }

    /**
     * Create calendar event for audit
     */
    public function createAuditEvent(array $eventData, array $attendeeEmails): array
    {
        try {
            // Prepare attendees
            $attendees = [];
            foreach ($attendeeEmails as $email) {
                $attendees[] = ['email' => $email];
            }

            $event = new \Google\Service\Calendar\Event([
                'summary' => $eventData['title'],
                'description' => $eventData['description'],
                'start' => [
                    'dateTime' => $eventData['start_datetime'],
                    'timeZone' => $eventData['timezone'] ?? 'UTC'
                ],
                'end' => [
                    'dateTime' => $eventData['end_datetime'],
                    'timeZone' => $eventData['timezone'] ?? 'UTC'
                ],
                'attendees' => $attendees,
                'reminders' => [
                    'useDefault' => false,
                    'overrides' => [
                        ['method' => 'email', 'minutes' => 24 * 60], // 1 day before
                        ['method' => 'popup', 'minutes' => 60] // 1 hour before
                    ]
                ]
            ]);

            $createdEvent = $this->calendar->events->insert('primary', $event);

            return [
                'success' => true,
                'event' => $createdEvent,
                'eventId' => $createdEvent->getId()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update calendar event
     */
    public function updateEvent(string $eventId, array $eventData): array
    {
        try {
            $event = $this->calendar->events->get('primary', $eventId);

            // Update event properties
            if (isset($eventData['title'])) {
                $event->setSummary($eventData['title']);
            }
            if (isset($eventData['description'])) {
                $event->setDescription($eventData['description']);
            }

            $updatedEvent = $this->calendar->events->update('primary', $eventId, $event);

            return [
                'success' => true,
                'event' => $updatedEvent
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete calendar event
     */
    public function deleteEvent(string $eventId): array
    {
        try {
            $this->calendar->events->delete('primary', $eventId);

            return [
                'success' => true,
                'message' => 'Event deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Test Calendar API connection
     */
    public function testConnection(): bool
    {
        try {
            $this->calendar->calendarList->listCalendarList();
            return true;
        } catch (Exception $e) {
            Log::debug('Calendar API connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
