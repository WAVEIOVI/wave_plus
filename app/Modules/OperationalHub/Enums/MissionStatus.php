<?php

declare(strict_types=1);

namespace App\Modules\OperationalHub\Enums;

enum MissionStatus: string
{
    case Planned            = 'planned';
    case Ready              = 'ready';
    case Started            = 'started';
    case Paused             = 'paused';
    case InProgress         = 'in_progress';
    case PartiallyCompleted = 'partially_completed';
    case Completed          = 'completed';
    case Cancelled          = 'cancelled';
    case Closed             = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned            => 'Planned',
            self::Ready              => 'Ready',
            self::Started            => 'Started',
            self::Paused             => 'Paused',
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
            self::Planned            => 'blue-grey',
            self::Ready              => 'indigo',
            self::Started            => 'cyan',
            self::Paused             => 'amber',
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
            self::Planned => [
                self::Ready,
                self::Started,
                self::InProgress,
                self::Cancelled,
            ],
            self::Ready => [
                self::Started,
                self::InProgress,
                self::Planned,
                self::Cancelled,
            ],
            self::Started => [
                self::InProgress,
                self::Paused,
                self::PartiallyCompleted,
                self::Completed,
                self::Cancelled,
            ],
            self::Paused => [
                self::Started,
                self::InProgress,
                self::PartiallyCompleted,
                self::Completed,
                self::Cancelled,
            ],
            self::InProgress => [
                self::Paused,
                self::PartiallyCompleted,
                self::Completed,
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
