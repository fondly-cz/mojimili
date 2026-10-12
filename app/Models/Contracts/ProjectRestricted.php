<?php

namespace App\Models\Contracts;

use App\Models\User;

/**
 * A record that belongs to a project and is hidden from people who are not assigned to it.
 */
interface ProjectRestricted
{
    public function isVisibleTo(?User $user): bool;
}
