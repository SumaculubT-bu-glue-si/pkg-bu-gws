<?php

namespace Bu\Gws\Jobs;

use Bu\Server\Models\AuditPlan;
use Bu\Server\Models\Employee;
use Bu\Server\Models\Location;
use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateAuditNotifications implements ShouldQueue
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
            Log::info('Starting comprehensive audit notification process', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location
            ]);

            // Get employees for the audit
            $employeesResult = $this->getAuditEmployees($gws);
            if (!$employeesResult['success']) {
                throw new \Exception('Failed to get employees: ' . $employeesResult['error']);
            }

            $employees = $employeesResult['employees'];
            $employeeEmails = array_column($employees, 'email');

            // Create calendar event
            $calendarResult = $this->createCalendarEvent($gws, $employeeEmails);
            if (!$calendarResult['success']) {
                Log::warning('Calendar event creation failed', [
                    'audit_plan_id' => $this->auditPlan->id,
                    'error' => $calendarResult['error']
                ]);
            }

            // Create chat space
            $chatResult = $this->createChatSpace($gws, $employeeEmails);
            if (!$chatResult['success']) {
                Log::warning('Chat space creation failed', [
                    'audit_plan_id' => $this->auditPlan->id,
                    'error' => $chatResult['error']
                ]);
                // Don't throw exception - continue with other notifications
            }

            // Update audit plan with Google Workspace information
            $this->auditPlan->update([
                'chat_space_id' => $chatResult['spaceId'] ?? null,
                'chat_space_name' => $chatResult['spaceName'] ?? null,
                'chat_space_created_at' => $chatResult['success'] ? now() : null,
                'calendar_events' => $calendarResult['success'] ? [
                    [
                        'id' => $calendarResult['eventId'],
                        'title' => "Asset Audit - {$this->auditPlan->name}",
                        'created_at' => now()->toISOString(),
                        'attendees_count' => count($employeeEmails)
                    ]
                ] : []
            ]);

            Log::info('Audit notification process completed successfully', [
                'audit_plan_id' => $this->auditPlan->id,
                'chat_space_id' => $chatResult['spaceId'] ?? null,
                'calendar_event_id' => $calendarResult['eventId'] ?? null,
                'employees_notified' => count($employeeEmails)
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create audit notifications', [
                'audit_plan_id' => $this->auditPlan->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    protected function getAuditEmployees(GoogleWorkspaceService $gws): array
    {
        try {
            // Get both asset owners and assigned auditors for this audit location
            $assetOwners = Employee::whereHas('assignedAssets', function ($query) {
                $query->where('location', $this->location);
            })->get();

            $assignedAuditors = Employee::whereHas('auditAssignments', function ($query) {
                $query->whereHas('auditPlan', function ($planQuery) {
                    $planQuery->where('id', $this->auditPlan->id);
                });
            })->get();

            // Combine and deduplicate employees
            $allEmployees = $assetOwners->merge($assignedAuditors)->unique('id');

            Log::info('Found employees for audit notifications', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location,
                'asset_owners_count' => $assetOwners->count(),
                'assigned_auditors_count' => $assignedAuditors->count(),
                'total_unique_employees' => $allEmployees->count()
            ]);

            // Convert to array format expected by Google Workspace services
            $employees = $allEmployees->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'name' => $employee->name,
                    'email' => $employee->email,
                    'employee_id' => $employee->employee_id,
                    'role' => $employee->role,
                    'department' => $employee->department,
                    'is_asset_owner' => $employee->assignedAssets()->where('location', $this->location)->exists(),
                    'is_auditor' => $employee->auditAssignments()->whereHas('auditPlan', function ($query) {
                        $query->where('id', $this->auditPlan->id);
                    })->exists()
                ];
            })->toArray();

            return [
                'success' => true,
                'employees' => $employees,
                'total' => count($employees),
                'asset_owners' => $assetOwners->count(),
                'assigned_auditors' => $assignedAuditors->count()
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get audit employees', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'employees' => [],
                'total' => 0
            ];
        }
    }

    protected function createCalendarEvent(GoogleWorkspaceService $gws, array $employeeEmails): array
    {
        try {
            $eventData = [
                'title' => "Asset Audit - {$this->auditPlan->name}",
                'description' => $this->buildEventDescription(),
                'start_datetime' => $this->auditPlan->start_date->toISOString(),
                'end_datetime' => $this->auditPlan->due_date->toISOString(),
                'timezone' => 'UTC'
            ];

            return $gws->createAuditCalendarEvent($eventData, $employeeEmails);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function createChatSpace(GoogleWorkspaceService $gws, array $employeeEmails): array
    {
        try {
            $spaceName = "{$this->auditPlan->name} - {$this->location}";

            // Get employee details for the message
            $assetOwners = Employee::whereHas('assignedAssets', function ($query) {
                $query->where('location', $this->location);
            })->get();

            $assignedAuditors = Employee::whereHas('auditAssignments', function ($query) {
                $query->whereHas('auditPlan', function ($planQuery) {
                    $planQuery->where('id', $this->auditPlan->id);
                });
            })->get();

            // Create detailed message
            $message = [
                'text' => "**Audit Plan Created: {$this->auditPlan->title}**\n\n" .
                    "**Location**: {$this->location}\n" .
                    "**Due Date**: {$this->auditPlan->due_date->format('M d, Y')}\n" .
                    "**Start Date**: {$this->auditPlan->start_date->format('M d, Y')}\n" .
                    "**Description**: {$this->auditPlan->description}\n\n" .
                    "**Participants**: " . count($employeeEmails) . " people\n\n" .
                    "**Asset Owners** (" . $assetOwners->count() . "):\n" .
                    $assetOwners->map(function ($emp) {
                        return "• {$emp->name} ({$emp->email})";
                    })->join("\n") . "\n\n" .
                    "**Assigned Auditors** (" . $assignedAuditors->count() . "):\n" .
                    $assignedAuditors->map(function ($emp) {
                        return "• {$emp->name} ({$emp->email})";
                    })->join("\n") . "\n\n" .
                    "Please coordinate and complete the audit by the due date. Good luck!"
            ];

            $result = $gws->createAuditChatSpace($spaceName, $this->location, $employeeEmails, $message);

            if ($result['success']) {
                Log::info('Chat space created successfully', [
                    'audit_plan_id' => $this->auditPlan->id,
                    'space_id' => $result['spaceId'],
                    'space_name' => $result['spaceName'],
                    'total_members' => count($employeeEmails),
                    'asset_owners' => $assetOwners->count(),
                    'assigned_auditors' => $assignedAuditors->count()
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Failed to create chat space', [
                'audit_plan_id' => $this->auditPlan->id,
                'location' => $this->location,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    protected function buildEventDescription(): string
    {
        $description = "Asset Audit Notification\n\n";
        $description .= "Audit Plan: {$this->auditPlan->name}\n";
        $description .= "Location: {$this->location}\n";
        $description .= "Start Date: {$this->auditPlan->start_date->format('Y-m-d')}\n";
        $description .= "Due Date: {$this->auditPlan->due_date->format('Y-m-d')}\n\n";

        if ($this->auditPlan->description) {
            $description .= "Description: {$this->auditPlan->description}\n\n";
        }

        $description .= "Please ensure all assets are ready for the audit.";

        return $description;
    }
}