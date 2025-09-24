<?php

namespace Bu\Gws\Console\Commands;

use Illuminate\Console\Command;
use Bu\Gws\Services\GoogleWorkspaceService;

class TestGoogleWorkspaceAuth extends Command
{
    protected $signature = 'gws:test-auth';
    protected $description = 'Test Google Workspace authentication and fetch access token';

    protected $gws;

    public function __construct(GoogleWorkspaceService $gws)
    {
        parent::__construct();
        $this->gws = $gws;
    }

    public function handle()
    {
        $this->info("Testing Google Workspace authentication...");

        try {
            $client = (new \ReflectionClass($this->gws))
                ->getMethod('createClient')
                ->invoke($this->gws);

            // Try fetching an access token
            $token = $client->fetchAccessTokenWithAssertion();

            if (isset($token['access_token'])) {
                $this->info("✅ Successfully obtained access token");
                $this->line("Access Token: " . substr($token['access_token'], 0, 40) . "... (truncated)");
                $this->line("Expires In: " . ($token['expires_in'] ?? 'unknown') . " seconds");
            } else {
                $this->error("❌ Failed to obtain access token");
                $this->line(json_encode($token, JSON_PRETTY_PRINT));
            }
        } catch (\Exception $e) {
            $this->error("Authentication test failed: " . $e->getMessage());
        }
    }
}
