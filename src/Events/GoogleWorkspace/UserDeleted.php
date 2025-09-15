<?php

namespace Bu\Gws\Events\GoogleWorkspace;

class UserDeleted extends GoogleWorkspaceEvent
{
    public $email;
    public $metadata;

    public function __construct($email, array $metadata = [])
    {
        parent::__construct();
        $this->email = $email;
        $this->metadata = $metadata;
    }
}
