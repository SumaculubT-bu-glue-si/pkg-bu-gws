<?php

namespace Bu\Gws\Events\GoogleWorkspace;

class UserUpdated extends GoogleWorkspaceEvent
{
    public $user;
    public $changes;
    public $metadata;

    public function __construct($user, array $changes, array $metadata = [])
    {
        parent::__construct();
        $this->user = $user;
        $this->changes = $changes;
        $this->metadata = $metadata;
    }
}
