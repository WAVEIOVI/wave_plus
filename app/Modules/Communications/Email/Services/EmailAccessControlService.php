<?php

declare(strict_types=1);

namespace App\Modules\Communications\Email\Services;

use App\Models\User;
use App\Modules\Communications\Email\Models\EmailConversation;
use App\Modules\Communications\Email\Models\EmailMailbox;
use App\Modules\Communications\Email\Models\EmailMessage;
use Illuminate\Database\Eloquent\Builder;

class EmailAccessControlService
{
    /**
     * All recognized restricted department slugs.
     *
     * @var array<string, array<string>>
     */
    protected const DEPARTMENT_ALIASES = [
        'sales' => ['sales', 'commercial', 'support'],
        'purchasing' => ['purchasing', 'procurement', 'achats'],
        'logistics' => ['logistics', 'transport', 'delivery'],
        'hr' => ['hr', 'human resources', 'ressources humaines', 'personnel'],
        'finance' => ['finance', 'accounting', 'comptabilité', 'comptabilite', 'billing'],
        'direction' => ['direction', 'executive', 'management', 'board'],
    ];

    /**
     * Check if user is an administrator with universal communications access.
     */
    public function isAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->hasRole('Admin');
    }

    /**
     * Normalize a department string to its canonical key.
     */
    public function normalizeDepartment(?string $dept): ?string
    {
        if ($dept === null || trim($dept) === '') {
            return null;
        }

        $clean = mb_strtolower(trim($dept));

        foreach (self::DEPARTMENT_ALIASES as $canonical => $aliases) {
            if ($clean === $canonical || in_array($clean, $aliases, true)) {
                return $canonical;
            }
        }

        return $clean;
    }

    /**
     * Determine if a user has authorization to access a given department's communications.
     */
    public function canAccessDepartment(User $user, ?string $department): bool
    {
        if ($this->isAdmin($user) || $user->can('manage-Communications')) {
            return true;
        }

        $canonical = $this->normalizeDepartment($department);

        // General / unassigned communication is accessible to all communication users
        if ($canonical === null || $canonical === 'general' || $canonical === 'all') {
            return $user->can('read-Communications');
        }

        // Check if user's employee record belongs to this department
        $empDept = $this->normalizeDepartment($user->employee?->department ?? null);
        if ($empDept !== null && $empDept === $canonical) {
            return true;
        }

        return match ($canonical) {
            'sales' => $user->can('read-CrmSales')
                || $user->can('read-crm-quotations')
                || $user->can('read-crm-sales-orders')
                || $user->hasRole('sales_responsible'),

            'purchasing' => $user->can('read-SupplyChainProcurement')
                || $user->can('read-procurement-pos')
                || $user->can('read-procurement-rfqs')
                || $user->can('read-procurement-iprs'),

            'logistics' => $user->can('read-Logistics')
                || $user->hasRole('operations_responsible'),

            'hr' => $user->can('read-PeopleSkills')
                || $user->can('read-Hr')
                || $user->can('manage-employees')
                || $user->can('create-users')
                || $user->can('edit-users')
                || $user->can('read-users'),

            'finance' => $user->can('read-FinancialCenter')
                || $user->can('read-Finance')
                || $user->can('manage-invoices')
                || $user->hasRole('financial_responsible'),

            'direction' => $user->hasRole(['Director', 'Responsable'])
                || $user->can('read-SettingsAdministration')
                || $user->can('send-as-direction'),

            default => false,
        };
    }

    /**
     * Check if user is authorized to access a specific company mailbox.
     */
    public function canAccessMailbox(User $user, EmailMailbox $mailbox): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $mailbox->is_active) {
            return false;
        }

        return $this->canAccessDepartment($user, $mailbox->department);
    }

    /**
     * Check if user is authorized to view a conversation thread.
     *
     * Invariant: Conversation assignment NEVER bypasses department authorization.
     * An employee assigned to a conversation must still belong/have permission for that department.
     */
    public function canAccessConversation(User $user, EmailConversation $conversation): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (! $user->can('read-Communications')) {
            return false;
        }

        // If conversation belongs to a mailbox, check mailbox access
        if ($conversation->mailbox_id) {
            $mailbox = $conversation->mailbox ?? EmailMailbox::find($conversation->mailbox_id);
            if ($mailbox && ! $this->canAccessMailbox($user, $mailbox)) {
                return false;
            }
        }

        // If conversation has a department set, check department access
        if ($conversation->department) {
            if (! $this->canAccessDepartment($user, $conversation->department)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Verify that an assignment operation is legal:
     * - The actor can access the conversation.
     * - The target assignee is authorized for that conversation's department/mailbox.
     */
    public function canAssignConversation(User $actor, EmailConversation $conversation, ?User $targetAssignee): bool
    {
        if (! $this->canAccessConversation($actor, $conversation)) {
            return false;
        }

        // Unassigning is always allowed if actor has conversation access
        if ($targetAssignee === null) {
            return true;
        }

        // Target assignee MUST be authorized for the department
        return $this->canAccessConversation($targetAssignee, $conversation);
    }

    /**
     * Check if user is authorized to access a specific email message.
     */
    public function canAccessMessage(User $user, EmailMessage $message): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        // The user who created the draft or sent the message always has access
        if ($message->created_by && (string) $message->created_by === (string) $user->id) {
            return true;
        }

        if (! $user->can('read-Communications')) {
            return false;
        }

        // Check conversation parent if available
        if ($message->conversation_id) {
            $conversation = $message->conversation ?? EmailConversation::find($message->conversation_id);
            if ($conversation && ! $this->canAccessConversation($user, $conversation)) {
                return false;
            }
        }

        // Check mailbox parent if available
        if ($message->mailbox_id) {
            $mailbox = $message->mailbox ?? EmailMailbox::find($message->mailbox_id);
            if ($mailbox && ! $this->canAccessMailbox($user, $mailbox)) {
                return false;
            }
        }

        // Check sender profile department if present
        if ($message->sender_profile_id) {
            $profile = $message->senderProfile;
            if ($profile && $profile->department && ! $this->canAccessDepartment($user, $profile->department)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get list of canonical department keys authorized for this user.
     *
     * @return array<int, string>
     */
    public function getAuthorizedDepartments(User $user): array
    {
        if ($this->isAdmin($user)) {
            return array_keys(self::DEPARTMENT_ALIASES);
        }

        $authorized = [];
        foreach (array_keys(self::DEPARTMENT_ALIASES) as $dept) {
            if ($this->canAccessDepartment($user, $dept)) {
                $authorized[] = $dept;
            }
        }

        return $authorized;
    }

    /**
     * Apply centralized isolation scope to EmailMessage queries.
     */
    public function applyMessageScope(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        $authorizedDepts = $this->getAuthorizedDepartments($user);
        $authorizedMailboxIds = EmailMailbox::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (EmailMailbox $m) => $this->canAccessMailbox($user, $m))
            ->pluck('id')
            ->all();

        return $query->where(function (Builder $q) use ($user, $authorizedDepts, $authorizedMailboxIds) {
            // 1. Messages created by the user
            $q->where('created_by', $user->id)
                // 2. Messages belonging to authorized mailboxes
                ->orWhereIn('mailbox_id', $authorizedMailboxIds)
                // 3. Messages belonging to conversations in authorized departments/mailboxes
                ->orWhereHas('conversation', function (Builder $cq) use ($authorizedDepts, $authorizedMailboxIds) {
                    $cq->where(function (Builder $sub) use ($authorizedDepts, $authorizedMailboxIds) {
                        $sub->whereIn('mailbox_id', $authorizedMailboxIds)
                            ->orWhere(function (Builder $noMailbox) use ($authorizedDepts) {
                                $noMailbox->whereNull('mailbox_id')
                                    ->where(function (Builder $dept) use ($authorizedDepts) {
                                        $dept->whereIn('department', $authorizedDepts)
                                            ->orWhereNull('department');
                                    });
                            });
                    });
                })
                // 4. Messages from interactive sender profiles in authorized departments
                ->orWhereHas('senderProfile', function (Builder $pq) use ($authorizedDepts) {
                    $pq->whereIn('department', $authorizedDepts)
                        ->orWhereNull('department');
                });
        });
    }

    /**
     * Apply centralized isolation scope to EmailConversation queries.
     */
    public function applyConversationScope(Builder $query, User $user): Builder
    {
        if ($this->isAdmin($user)) {
            return $query;
        }

        $authorizedDepts = $this->getAuthorizedDepartments($user);
        $authorizedMailboxIds = EmailMailbox::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (EmailMailbox $m) => $this->canAccessMailbox($user, $m))
            ->pluck('id')
            ->all();

        return $query->where(function (Builder $q) use ($authorizedDepts, $authorizedMailboxIds) {
            $q->whereIn('mailbox_id', $authorizedMailboxIds)
                ->orWhere(function (Builder $sub) use ($authorizedDepts) {
                    $sub->whereNull('mailbox_id')
                        ->where(function (Builder $dept) use ($authorizedDepts) {
                            $dept->whereIn('department', $authorizedDepts)
                                ->orWhereNull('department');
                        });
                });
        });
    }
}
