<?php

namespace Bu\Gws\Jobs;

use Bu\Gws\Services\GoogleWorkspaceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateGoogleWorkspaceUser implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userKey;
    protected $userData;

    public function __construct(string $userKey, array $userData)
    {
        $this->userKey = $userKey;
        $this->userData = $userData;
    }

    public function handle(GoogleWorkspaceService $gws)
    {
        $gws->updateUser($this->userKey, $this->userData);
    }
}
