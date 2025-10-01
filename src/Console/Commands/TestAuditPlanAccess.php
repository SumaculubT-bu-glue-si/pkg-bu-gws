<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Bu\Server\Models\AuditPlan;
use Bu\Server\Models\AuditAssignment;
use Bu\Server\Models\AuditAsset;
use Bu\Server\Models\Employee;
use Bu\Server\Models\Location;
use Bu\Server\Models\Asset;
use Carbon\Carbon;

class TestAuditPlanAccess extends Command
{
    protected $signature = 'test:audit-access {email} {audit_plan_id?}';
    protected $description = 'Test audit plan access for an employee';

    public function handle()
    {
        $email = $this->argument('email');
        $auditPlanId = $this->argument('audit_plan_id');

        $this->info("Testing audit access for employee: {$email}");

        // Find employee
        $employee = Employee::where('email', $email)->first();
        if (!$employee) {
            $this->error("Employee not found with email: {$email}");
            return 1;
        }

        $this->info("Found employee: {$employee->name} (ID: {$employee->id})");

        if ($auditPlanId) {
            // Test specific audit plan access
            $this->testSpecificPlanAccess($employee, $auditPlanId);
        } else {
            // Show all available plans for this employee
            $this->showAvailablePlans($employee);
        }

        return 0;
    }

    private function testSpecificPlanAccess($employee, $auditPlanId)
    {
        $this->info("\nTesting access to audit plan: {$auditPlanId}");

        // Check if audit plan exists
        $auditPlan = AuditPlan::find($auditPlanId);
        if (!$auditPlan) {
            $this->error("Audit plan not found with ID: {$auditPlanId}");
            return;
        }

        $this->info("Audit plan: {$auditPlan->name} (Status: {$auditPlan->status})");

        // Check assignments
        $assignments = AuditAssignment::where('audit_plan_id', $auditPlanId)
            ->where('auditor_id', $employee->id)
            ->get();

        if ($assignments->isEmpty()) {
            $this->error("No assignments found for employee {$employee->id} to audit plan {$auditPlanId}");

            // Show all assignments for this plan
            $allAssignments = AuditAssignment::where('audit_plan_id', $auditPlanId)->get();
            $this->info("All assignments for this plan:");
            foreach ($allAssignments as $assignment) {
                $this->line("  - Auditor ID: {$assignment->auditor_id}, Location ID: {$assignment->location_id}, Status: {$assignment->status}");
            }
        } else {
            $this->info("Found {$assignments->count()} assignments for this employee");
            foreach ($assignments as $assignment) {
                $this->line("  - Location ID: {$assignment->location_id}, Status: {$assignment->status}");
            }
        }

        // Check audit assets
        $totalAssets = AuditAsset::where('audit_plan_id', $auditPlanId)->count();
        $auditedAssets = AuditAsset::where('audit_plan_id', $auditPlanId)
            ->where('audit_status', true)
            ->count();

        $this->info("Audit assets: {$totalAssets} total, {$auditedAssets} audited");

        if ($totalAssets === 0) {
            $this->warn("No audit assets found for this plan!");

            // Check if there are assets in the locations
            $locations = AuditAssignment::where('audit_plan_id', $auditPlanId)
                ->pluck('location_id')
                ->unique();

            if ($locations->isNotEmpty()) {
                $locationNames = Location::whereIn('id', $locations)->pluck('name');
                $this->info("Plan locations: " . $locationNames->implode(', '));

                $assetCount = Asset::whereIn('location', $locationNames)->count();
                $this->info("Total assets in these locations: {$assetCount}");
            }
        }
    }

    private function showAvailablePlans($employee)
    {
        $this->info("\nAvailable audit plans for this employee:");

        // Get all active audit plans
        $auditPlans = AuditPlan::where('due_date', '>', Carbon::now()->toDateString())
            ->whereIn('status', ['Planning', 'In Progress'])
            ->orderBy('due_date', 'asc')
            ->get();

        if ($auditPlans->isEmpty()) {
            $this->warn("No active audit plans found");
            return;
        }

        foreach ($auditPlans as $plan) {
            $this->line("\nPlan: {$plan->name} (ID: {$plan->id})");
            $this->line("  Status: {$plan->status}");
            $this->line("  Due Date: {$plan->due_date}");

            // Check if employee has access
            $hasAccess = AuditAssignment::where('auditor_id', $employee->id)
                ->where('audit_plan_id', $plan->id)
                ->exists();

            if ($hasAccess) {
                $this->info("  ✓ Employee has access");

                // Show progress
                $totalAssets = AuditAsset::where('audit_plan_id', $plan->id)->count();
                $auditedAssets = AuditAsset::where('audit_plan_id', $plan->id)
                    ->where('audit_status', true)
                    ->count();
                $progress = $totalAssets > 0 ? round(($auditedAssets / $totalAssets) * 100) : 0;

                $this->line("  Progress: {$auditedAssets}/{$totalAssets} assets audited ({$progress}%)");
            } else {
                $this->error("  ✗ Employee does not have access");
            }
        }
    }
}
