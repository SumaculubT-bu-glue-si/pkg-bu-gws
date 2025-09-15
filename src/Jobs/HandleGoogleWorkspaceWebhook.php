<?php

namespace Bu\Gws\Jobs;

use Bu\Server\Models\Employee;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleGoogleWorkspaceWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $eventType;
    protected $userData;
    protected $userEmail;
    protected $domain;

    public function __construct(string $eventType, array $userData, string $userEmail = null, string $domain = null)
    {
        $this->eventType = $eventType;
        $this->userData = $userData;
        $this->userEmail = $userEmail;
        $this->domain = $domain;
    }

    public function handle()
    {
        try {
            Log::info('Processing GWS webhook', [
                'event_type' => $this->eventType,
                'user_email' => $this->userEmail
            ]);

            switch ($this->eventType) {
                case 'user.created':
                    $this->handleUserCreated();
                    break;
                case 'user.updated':
                    $this->handleUserUpdated();
                    break;
                case 'user.deleted':
                    $this->handleUserDeleted();
                    break;
                default:
                    Log::warning('Unknown GWS webhook event type', ['event' => $this->eventType]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to handle GWS webhook', [
                'event_type' => $this->eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    protected function handleUserCreated()
    {
        $email = $this->userData['primaryEmail'] ?? null;
        if (!$email) {
            Log::warning('No email found in GWS user creation webhook');
            return;
        }

        // Check if employee already exists
        $existingEmployee = Employee::where('email', $email)->first();
        if ($existingEmployee) {
            Log::info('Employee already exists for new GWS user', [
                'email' => $email,
                'employee_id' => $existingEmployee->employee_id
            ]);
            return;
        }

        // Create new employee
        $employeeData = [
            'employee_id' => $this->userData['id'] ?? $this->generateEmployeeId($email),
            'name' => $this->formatUserName($this->userData['name'] ?? []),
            'email' => $email,
            'location' => $this->extractLocation($this->userData),
            'projects' => [],
        ];

        $employee = Employee::create($employeeData);
        Log::info('Employee created from GWS webhook', [
            'email' => $email,
            'employee_id' => $employee->employee_id
        ]);
    }

    protected function handleUserUpdated()
    {
        $email = $this->userData['primaryEmail'] ?? $this->userEmail;
        if (!$email) {
            Log::warning('No email found in GWS user update webhook');
            return;
        }

        $employee = Employee::where('email', $email)->first();
        if (!$employee) {
            Log::warning('Employee not found for GWS user update', ['email' => $email]);
            return;
        }

        $updateData = [];

        // Update name if provided
        if (isset($this->userData['name'])) {
            $updateData['name'] = $this->formatUserName($this->userData['name']);
        }

        // Update email if provided
        if (isset($this->userData['primaryEmail']) && $this->userData['primaryEmail'] !== $email) {
            $updateData['email'] = $this->userData['primaryEmail'];
        }

        // Update location if provided
        if (isset($this->userData['orgUnitPath'])) {
            $updateData['location'] = $this->extractLocation($this->userData);
        }

        if (!empty($updateData)) {
            $employee->update($updateData);
            Log::info('Employee updated from GWS webhook', [
                'email' => $email,
                'updated_fields' => array_keys($updateData)
            ]);
        }
    }

    protected function handleUserDeleted()
    {
        $email = $this->userEmail;
        if (!$email) {
            Log::warning('No email found in GWS user deletion webhook');
            return;
        }

        $employee = Employee::where('email', $email)->first();
        if ($employee) {
            $employee->delete();
            Log::info('Employee deleted from GWS webhook', ['email' => $email]);
        } else {
            Log::warning('Employee not found for GWS user deletion', ['email' => $email]);
        }
    }

    protected function formatUserName($nameData): string
    {
        if (!$nameData || !is_array($nameData)) return 'Unknown User';

        $givenName = $nameData['givenName'] ?? '';
        $familyName = $nameData['familyName'] ?? '';

        return trim($givenName . ' ' . $familyName) ?: 'Unknown User';
    }

    protected function extractLocation($userData): ?string
    {
        $orgUnitPath = $userData['orgUnitPath'] ?? null;

        $locationMap = [
            '/一般' => 'General Office',
            '/営業' => 'Sales Office',
            '/開発' => 'Development Office',
            '/管理' => 'Admin Office',
            '/テスト' => 'Test Office',
        ];

        return $locationMap[$orgUnitPath] ?? null;
    }

    protected function generateEmployeeId(string $email): string
    {
        $username = explode('@', $email)[0];
        $timestamp = date('Ymd');
        return strtoupper($username . '-' . $timestamp);
    }
}
