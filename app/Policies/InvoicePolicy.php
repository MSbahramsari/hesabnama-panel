<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->isAdmin() || ($user->hasPermission('invoices') && $invoice->user_id === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->isEditable();
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::PendingSend, InvoiceStatus::MoadianError], true);
    }

    public function confirm(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status === InvoiceStatus::AwaitingConfirmation;
    }

    public function inquire(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice)
            && filled($invoice->reference_number)
            && in_array($invoice->status, [InvoiceStatus::AwaitingConfirmation, InvoiceStatus::MoadianError], true);
    }

    public function updateBuyerStatus(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status === InvoiceStatus::Confirmed;
    }
}
