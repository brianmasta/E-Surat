<?php

namespace App\Livewire\Concerns;

use App\Support\WorkspaceNavigation;
use Livewire\Attributes\Locked;

trait WorkspacePanel
{
    #[Locked]
    public bool $embedded = false;

    abstract protected function workspacePage(): string;

    public function bootWorkspacePanel(): void
    {
        WorkspaceNavigation::authorize($this->workspacePage());
    }
}
