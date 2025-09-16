<?php

namespace Bu\Gws\Console\Commands;


use Bu\Server\Models\AuditPlan;
use Bu\Server\Models\Employee;
use Bu\Server\Models\Asset;
use Bu\Server\Models\AuditAsset;

use Bu\Gws\Jobs\CreateAuditCalendarEvents;
use Illuminate\Console\Command;

class TestAuditCalendar extends Command
{
    protected $signature = 'audit:test-calendar 
                            {location : Location to test}
                            {--email= : Specific employee email to test}';

    protected $description = 'Test audit calendar integration';

    public function handle()
    {
        $location = $this->argument('location');
        $specificEmail = $this->option('email');

        $this->info("Testing audit calendar integration for location: {$location}");

        // Create a test audit plan
        $auditPlan = AuditPlan::create([
            'name' => 'Test Audit - ' . now()->format('Y-m-d H:i:s'),
            'description' => 'This is a test audit to verify calendar integration',
            'start_date' => now()->addDays(1),
            'due_date' => now()->addDays(3),
            'status' => 'Planning',
            'created_by' => 1, // Assuming user ID 1 exists
        ]);

        $this->info("Created test audit plan with ID: {$auditPlan->id}");

        // Get employees with assets in the location
        $employees = $this->getEmployeesWithAssets($location, $specificEmail);

        if ($employees->isEmpty()) {
            $this->error("No employees with assets found in location: {$location}");
            return;
        }

        $this->info("Found {$employees->count()} employees with assets to test with:");
        foreach ($employees as $employee) {
            $assetCount = Asset::where('location', $location)
                ->where('user_id', $employee->id)
                ->count();
            $this->line("  - {$employee->name} ({$employee->email}) - {$assetCount} assets");
        }

        // Create audit assets for testing
        $this->createTestAuditAssets($auditPlan, $location, $employees);

        // Dispatch job
        CreateAuditCalendarEvents::dispatch($auditPlan, $location);


        $this->info("Calendar events creation job dispatched!");
        $this->info("Check the logs and employee calendars for the test events.");
    }

    protected function getEmployeesWithAssets($location, $specificEmail = null)
    {
        $query = Asset::where('location', $location)
            ->whereNotNull('user_id')
            ->with('employee');

        if ($specificEmail) {
            $query->whereHas('employee', function ($q) use ($specificEmail) {
                $q->where('email', $specificEmail);
            });
        }

        $assets = $query->get();
        return $assets->pluck('employee')->filter()->unique('id');
    }

    protected function createTestAuditAssets($auditPlan, $location, $employees)
    {
        $this->info("Creating test audit assets...");

        foreach ($employees as $employee) {
            $assets = Asset::where('location', $location)
                ->where('user_id', $employee->id)
                ->take(2) // Limit to 2 assets per employee for testing
                ->get();

            foreach ($assets as $asset) {
                AuditAsset::create([
                    'audit_plan_id' => $auditPlan->id,
                    'asset_id' => $asset->id,
                    'original_location' => $asset->location,
                    'original_user' => $asset->employee->name ?? 'Unknown',
                    'current_status' => $asset->status,
                    'audit_status' => false,
                    'resolved' => false,
                ]);
            }
        }

        $this->info("Created audit assets for testing");
    }
}
