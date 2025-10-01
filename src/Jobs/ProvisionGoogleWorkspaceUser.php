<?php

namespace Bu\Gws\Jobs;

use Bu\Server\Models\Employee;
use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProvisionGoogleWorkspaceUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $employee;

    public function __construct(Employee $employee)
    {
        $this->employee = $employee;
    }

    public function handle(GoogleWorkspaceService $gws)
    {
        // Split name into given and family name (simple split, adjust as needed)
        $nameParts = explode(' ', $this->employee->name, 2);
        $givenName = $nameParts[0] ?? $this->employee->name;
        $familyName = $nameParts[1] ?? null;
        if ($familyName === null || trim($familyName) === '') {
            $familyName = '-';
        }

        $userData = [
            'primaryEmail' => $this->employee->email,
            'name' => [
                'givenName' => $givenName,
                'familyName' => $familyName,
            ],
            'password' => env('GWS_DEFAULT_PASSWORD', Str::random(16)),
            'orgUnitPath' => $this->employee->org_unit_path ?? env('GWS_DEFAULT_ORG_UNIT', '/テスト'),
        ];

        // Only log in debug mode without sensitive data
        if (env('APP_DEBUG', false) && env('GOOGLE_WORKSPACE_DEBUG_LOGGING', false)) {
            Log::debug('Provisioning Google Workspace user', [
                'employee_id' => $this->employee->employee_id,
                'has_email' => !empty($this->employee->email),
                'has_name' => !empty($this->employee->name),
                'org_unit' => $userData['orgUnitPath'],
            ]);
        }

        // Create user in Google Workspace
        $gwsUser = $gws->createUser($userData);

        // Update employee with Google Workspace User ID
        $this->employee->update([
            'employee_id' => $gwsUser->getId(),
        ]);

        // Log the successful creation
        if (env('APP_DEBUG', false) && env('GOOGLE_WORKSPACE_DEBUG_LOGGING', false)) {
            Log::debug('Google Workspace user created and employee ID updated', [
                'gws_user_id' => $gwsUser->getId(),
                'employee_database_id' => $this->employee->id,
            ]);
        }
    }
}