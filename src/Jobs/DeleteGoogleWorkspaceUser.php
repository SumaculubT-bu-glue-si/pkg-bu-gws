<?php

namespace Bu\Gws\Jobs;

use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteGoogleWorkspaceUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userKey;

    public function __construct(string $userKey)
    {
        $this->userKey = $userKey;
    }

    public function handle(GoogleWorkspaceService $gws)
    {
        $gws->deleteUser($this->userKey);
    }
}
