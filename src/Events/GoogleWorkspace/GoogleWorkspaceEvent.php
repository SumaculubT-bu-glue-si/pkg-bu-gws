<?php

namespace Bu\Gws\Events\GoogleWorkspace;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

abstract class GoogleWorkspaceEvent
{
    use Dispatchable, SerializesModels;

    public $timestamp;

    public function __construct()
    {
        $this->timestamp = now();
    }
}
