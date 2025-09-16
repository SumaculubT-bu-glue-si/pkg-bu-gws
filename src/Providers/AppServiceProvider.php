<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Handle employee creation - provision new Google Workspace user
        \Bu\Server\Models\Employee::created(function ($employee) {
            if ($employee->email) {
                \Bu\Gws\Jobs\ProvisionGoogleWorkspaceUser::dispatch($employee);
            }
        });

        // Handle employee updates - update Google Workspace user if relevant fields changed
        \Bu\Server\Models\Employee::updated(function ($employee) {
            if ($employee->email && $employee->isDirty(['name', 'email'])) {
                // Use the employee_id (GWS User ID) to identify the user in Google Workspace
                $gwsUserKey = $employee->employee_id;

                // If employee_id is not set (shouldn't happen with GWS integration), fallback to email
                if (!$gwsUserKey || strpos($gwsUserKey, 'TEMP_') === 0) {
                    $gwsUserKey = $employee->email;
                }

                // Prepare user data for update
                $nameParts = explode(' ', $employee->name, 2);
                $userData = [
                    'name' => [
                        'givenName' => $nameParts[0] ?? $employee->name,
                        'familyName' => $nameParts[1] ?? '-',
                    ]
                ];

                // If email changed, include the new email
                if ($employee->isDirty('email')) {
                    $userData['primaryEmail'] = $employee->email;
                }

                \Bu\Gws\Jobs\UpdateGoogleWorkspaceUser::dispatch($gwsUserKey, $userData);
            }
        });

        // Handle employee deletion - delete Google Workspace user
        \Bu\Server\Models\Employee::deleting(function ($employee) {
            if ($employee->email) {
                // Use the employee_id (GWS User ID) to identify the user in Google Workspace
                $gwsUserKey = $employee->employee_id;

                // If employee_id is not set, fallback to email
                if (!$gwsUserKey || strpos($gwsUserKey, 'TEMP_') === 0) {
                    $gwsUserKey = $employee->email;
                }

                \Bu\Gws\Jobs\DeleteGoogleWorkspaceUser::dispatch($gwsUserKey);
            }
        });
    }
}
