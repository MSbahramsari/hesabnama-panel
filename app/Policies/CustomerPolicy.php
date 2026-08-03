<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customers');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->isAdmin() || ($user->hasPermission('customers') && $customer->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customers');
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->view($user, $customer);
    }
}
