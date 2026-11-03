<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Policies;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailSenderProfile;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailSenderProfilePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
            || $user->can('read-Communications');
    }

    public function view(User $user, EmailSenderProfile $profile): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Determines whether the user can send emails from this profile.
     * System profiles (system@waveplus.xyz) can NEVER be used for user-composed emails.
     */
    public function use(User $user, EmailSenderProfile $profile): bool
    {
        // System profile is strictly reserved for automated system emails
        if ($profile->is_system) {
            return false;
        }

        if (! $profile->is_active) {
            return false;
        }

        if ($user->hasRole(['Super Admin', 'Admin'])) {
            return true;
        }

        // Check by email or department
        $email = mb_strtolower(trim($profile->email));
        $dept = mb_strtolower(trim($profile->department ?? ''));

        if ($email === 'sales@waveplus.xyz' || $dept === 'commercial' || $dept === 'sales') {
            return $user->can('read-CrmSales')
                || $user->can('create-crm-quotations')
                || $user->can('create-crm-sales-orders')
                || $user->can('edit-crm-sales-orders')
                || $user->can('read-crm-sales-orders');
        }

        if ($email === 'purchasing@waveplus.xyz' || $dept === 'procurement' || $dept === 'purchasing') {
            return $user->can('read-SupplyChainProcurement')
                || $user->can('create-procurement-pos')
                || $user->can('create-procurement-rfqs')
                || $user->can('create-procurement-iprs')
                || $user->can('read-procurement-pos');
        }

        if ($email === 'hr@waveplus.xyz' || $dept === 'human resources' || $dept === 'hr') {
            return $user->can('read-PeopleSkills')
                || $user->can('create-users')
                || $user->can('edit-users')
                || $user->can('read-users');
        }

        if ($email === 'finance@waveplus.xyz' || $dept === 'finance') {
            return $user->can('read-FinancialCenter');
        }

        if ($email === 'direction@waveplus.xyz' || $dept === 'direction' || $dept === 'executive') {
            return $user->hasRole(['Super Admin', 'Admin', 'Responsable'])
                || $user->can('send-as-direction');
        }

        return false;
    }
}
