<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Bu\Server\Models\AuditPlan;
use Bu\Server\Models\AuditAssignment;
use Bu\Server\Models\AuditAsset;
use Bu\Server\Models\Asset;
use Bu\Server\Models\Location;
use Bu\Server\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TestAuditSystem extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'audit:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the audit system components';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Audit System Components');
        $this->info('==================================');
        $this->newLine();

        try {
            // Test 1: Check if models can be instantiated
            $this->info('✅ Testing model instantiation...');
            $auditPlan = new AuditPlan();
            $auditAssignment = new AuditAssignment();
            $auditAsset = new AuditAsset();
            $this->info('   - All models instantiated successfully');
            $this->newLine();

            // Test 2: Check database connection
            $this->info('✅ Testing database connection...');
            try {
                $db = DB::connection();
                $db->getPdo();
                $this->info('   - Database connection successful');
                $this->newLine();
            } catch (\Exception $e) {
                $this->error('   ❌ Database connection failed: ' . $e->getMessage());
                $this->newLine();
                return 1;
            }

            // Test 3: Check if audit tables exist
            $this->info('✅ Testing audit table existence...');
            $tables = ['audit_plans', 'audit_assignments', 'audit_assets', 'audit_logs'];
            foreach ($tables as $table) {
                try {
                    $exists = Schema::hasTable($table);
                    $status = $exists ? '✅ exists' : '❌ missing';
                    $this->info("   - Table '$table': $status");
                } catch (\Exception $e) {
                    $this->error("   - Table '$table': ❌ error checking - " . $e->getMessage());
                }
            }
            $this->newLine();

            // Test 4: Check if we have sample data
            $this->info('✅ Testing sample data availability...');
            try {
                $userCount = User::count();
                $locationCount = Location::count();
                $employeeCount = Employee::count();
                $assetCount = Asset::count();

                $this->info("   - Users: $userCount");
                $this->info("   - Locations: $locationCount");
                $this->info("   - Employees: $employeeCount");
                $this->info("   - Assets: $assetCount");
                $this->newLine();

                if ($userCount === 0 || $locationCount === 0 || $employeeCount === 0 || $assetCount === 0) {
                    $this->warn('   ⚠️  Some tables are empty. You may need to run seeders.');
                    $this->newLine();
                }
            } catch (\Exception $e) {
                $this->error('   ❌ Data query failed: ' . $e->getMessage());
                $this->newLine();
            }

            // Test 5: Check audit data
            $this->info('✅ Testing audit data...');
            try {
                $auditPlanCount = AuditPlan::count();
                $auditAssignmentCount = AuditAssignment::count();
                $auditAssetCount = AuditAsset::count();

                $this->info("   - Audit Plans: $auditPlanCount");
                $this->info("   - Audit Assignments: $auditAssignmentCount");
                $this->info("   - Audit Assets: $auditAssetCount");
                $this->newLine();
            } catch (\Exception $e) {
                $this->error('   ❌ Audit data query failed: ' . $e->getMessage());
                $this->newLine();
            }

            $this->info('🎉 Testing completed successfully!');
            $this->newLine();

            $this->info('📋 Next Steps:');
            $this->info('1. If tables are missing: php artisan migrate');
            $this->info('2. If data is missing: php artisan db:seed --class=AuditSeeder');
            $this->info('3. Test GraphQL: Visit http://localhost:8000/api/graphql-playground');
            $this->info('4. Check Laravel logs: tail -f storage/logs/laravel.log');
        } catch (\Exception $e) {
            $this->error('❌ Test failed with error: ' . $e->getMessage());
            $this->error('Stack trace:');
            $this->error($e->getTraceAsString());
            return 1;
        }

        return 0;
    }
}