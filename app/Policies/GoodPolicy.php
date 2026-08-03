<?php

namespace App\Policies;

use App\Models\Good;
use App\Models\User;

class GoodPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods');
    }

    public function view(User $user, Good $good): bool
    {
        return $user->isAdmin() || ($user->hasPermission('goods') && $good->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods');
    }

    public function update(User $user, Good $good): bool
    {
        return $this->view($user, $good);
    }
}
