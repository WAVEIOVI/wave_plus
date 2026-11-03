<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum SwoStatus: string
{
    case Draft              = 'draft';
    case Accepted           = 'accepted';
    case ReadyForPlanning   = 'ready_for_planning';
    case Planned            = 'planned';
    case Scheduled          = 'scheduled';
    case InProgress         = 'in_progress';
    case PartiallyCompleted = 'partially_completed';
    case Completed          = 'completed';
    case Cancelled          = 'cancelled';
    case Closed             = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft              => 'Draft',
            self::Accepted           => 'Accepted',
            self::ReadyForPlanning   => 'Ready for Planning',
            self::Planned            => 'Planned',
            self::Scheduled          => 'Scheduled',
            self::InProgress         => 'In Progress',
            self::PartiallyCompleted => 'Partially Completed',
            self::Completed          => 'Completed',
            self::Cancelled          => 'Cancelled',
            self::Closed             => 'Closed',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Draft              => 'grey',
            self::Accepted           => 'info',
            self::ReadyForPlanning   => 'indigo',
            self::Planned            => 'blue-grey',
            self::Scheduled          => 'cyan',
            self::InProgress         => 'warning',
            self::PartiallyCompleted => 'amber-darken-2',
            self::Completed          => 'success',
            self::Cancelled          => 'error',
            self::Closed             => 'secondary',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [
                self::Accepted,
                self::ReadyForPlanning,
                self::Planned,
                self::Scheduled,
                self::Cancelled,
            ],
            self::Accepted => [
                self::ReadyForPlanning,
                self::Planned,
                self::Cancelled,
            ],
            self::ReadyForPlanning => [
                self::Planned,
                self::Scheduled,
                self::Cancelled,
            ],
            self::Planned => [
                self::Scheduled,
                self::InProgress,
                self::ReadyForPlanning,
                self::Cancelled,
            ],
            self::Scheduled => [
                self::InProgress,
                self::Planned,
                self::Cancelled,
            ],
            self::InProgress => [
                self::PartiallyCompleted,
                self::Completed,
                self::Scheduled,
                self::Cancelled,
            ],
            self::PartiallyCompleted => [
                self::InProgress,
                self::Completed,
                self::Cancelled,
            ],
            self::Completed => [
                self::Closed,
            ],
            self::Cancelled => [],
            self::Closed    => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
