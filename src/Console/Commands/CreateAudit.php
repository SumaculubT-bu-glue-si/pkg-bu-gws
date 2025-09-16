<?php

namespace Bu\Gws\Console\Commands;

use Bu\Server\Models\AuditPlan;
use Bu\Gws\Jobs\CreateAuditCalendarEvents;
use Illuminate\Console\Command;

class CreateAudit extends Command
{
    protected $signature = 'audit:create 
                            {title : Audit title}
                            {location : Location to audit}
                            {start_date : Start date (Y-m-d)}
                            {end_date : End date (Y-m-d)}
                            {--description= : Audit description}';

    protected $description = 'Create a new audit and schedule calendar events';

    public function handle()
    {
        $title = $this->argument('title');
        $location = $this->argument('location');
        $startDate = $this->argument('start_date');
        $endDate = $this->argument('end_date');
        $description = $this->option('description');

        // Create audit
        $audit = AuditPlan::create([
            'title' => $title,
            'description' => $description,
            'location' => $location,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => 'planned',
        ]);

        $this->info("Audit created with ID: {$audit->id}");

        // Dispatch job to create calendar events
        CreateAuditCalendarEvents::dispatch($audit, $location);

        $this->info('Calendar events creation job dispatched');
    }
}
