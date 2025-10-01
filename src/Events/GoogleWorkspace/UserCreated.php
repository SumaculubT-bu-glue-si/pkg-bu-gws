<?php

namespace Bu\Gws\Events\GoogleWorkspace;

class UserCreated extends GoogleWorkspaceEvent
{
    public $user;
    public $metadata;

    public function __construct($user, array $metadata = [])
    {
        parent::__construct();
        $this->user = $user;
        $this->metadata = $metadata;
    }
}
