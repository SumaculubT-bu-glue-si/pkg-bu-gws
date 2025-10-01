<?php

namespace Bu\Gws\Jobs;

use Bu\Server\Models\AuditPlan;

use Bu\Server\Models\Employee;
use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateAuditCalendarEvents implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $auditPlan;
    protected $location;

    public function __construct(AuditPlan $auditPlan, string $location)
    {
        $this->auditPlan = $auditPlan;
        $this->location = $location;
    }

    public function handle(GoogleWorkspaceService $gws)
    {
        try {
            // Get employees who have assets in this audit plan for this location
            $employees = $this->getEmployeesWithAssetsInLocation();

            Log::info('Creating audit calendar events', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location,
                'employee_count' => $employees->count()
            ]);

            $createdEvents = 0;
            $failedEvents = 0;
            $eventIds = [];

            foreach ($employees as $employee) {
                if (!$employee->email) {
                    Log::warning('Employee has no email, skipping calendar event', [
                        'employee_id' => $employee->id,
                        'employee_name' => $employee->name
                    ]);
                    continue;
                }

                try {
                    $eventData = [
                        'title' => "Asset Audit - {$this->auditPlan->name}",
                        'description' => $this->buildEventDescription($employee),
                        'start_datetime' => $this->auditPlan->start_date->toISOString(),
                        'end_datetime' => $this->auditPlan->due_date->toISOString(),

                        'timezone' => 'Asia/Tokyo',
                    ];

                    $event = $gws->createCalendarEvent($employee->email, $eventData);
                    $eventIds[$employee->email] = $event->getId();
                    $createdEvents++;

                    Log::info('Calendar event created successfully', [
                        'employee_email' => $employee->email,
                        'event_id' => $event->getId()
                    ]);
                } catch (\Exception $e) {
                    $failedEvents++;
                    Log::error('Failed to create calendar event', [
                        'employee_email' => $employee->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Store event IDs in audit plan record
            $this->auditPlan->update(['calendar_events' => $eventIds]);

            Log::info('Audit calendar events creation completed', [
                'audit_plan_id' => $this->auditPlan->id,
                'created_events' => $createdEvents,
                'failed_events' => $failedEvents
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit calendar events', [
                'audit_plan_id' => $this->auditPlan->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function getEmployeesWithAssetsInLocation()
    {
        // Get all audit assets for this audit plan in this location
        $auditAssets = \Bu\Server\Models\AuditAsset::where('audit_plan_id', $this->auditPlan->id)
            ->whereHas('asset', function ($query) {
                $query->where('location', $this->location);
            })
            ->with('asset.employee')
            ->get();

        // Extract unique employees who have assets
        $employeeIds = $auditAssets->pluck('asset.employee.id')->filter()->unique();

        if ($employeeIds->isEmpty()) {
            Log::warning('No employees with assets found for calendar events', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location
            ]);
            return collect();
        }

        // Get the actual employee records
        $employees = Employee::whereIn('id', $employeeIds)->get();

        Log::info('Found employees with assets for calendar events', [
            'audit_plan_id' => $this->auditPlan->id,
            'location' => $this->location,
            'employee_count' => $employees->count(),
            'employee_names' => $employees->pluck('name')->toArray()
        ]);

        return $employees;
    }

    protected function buildEventDescription(Employee $employee): string
    {
        // Get the count of assets for this employee in this audit plan
        $assetCount = \Bu\Server\Models\AuditAsset::where('audit_plan_id', $this->auditPlan->id)
            ->whereHas('asset', function ($query) use ($employee) {
                $query->where('location', $this->location)
                    ->where('user_id', $employee->id);
            })
            ->count();

        return "Asset Audit Schedule\n\n" .
            "Audit: {$this->auditPlan->name}\n\n" .
            "Location: {$this->location}\n" .
            "Employee: {$employee->name}\n" .
            "Email: {$employee->email}\n\n" .
            "Assets to audit: {$assetCount}\n\n" .
            "Please ensure all your assigned assets are ready for audit during this period.\n\n" .
            "For questions, contact: sumaculub_t@bu.glue-si.com";
    }
}
