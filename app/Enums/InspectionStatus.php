<?php

namespace App\Enums;

enum InspectionStatus: string
{
    case Pending = 'pending';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function next(): ?self
    {
        return match ($this) {
            self::Pending => self::Assigned,
            self::Assigned => self::InProgress,
            self::InProgress => self::Completed,
            self::Completed => null,
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return $this->next() === $to;
    }

    public function nextAction(): ?string
    {
        return match ($this) {
            self::Pending => 'assign',
            self::Assigned => 'start',
            self::InProgress => 'complete',
            self::Completed => null,
        };
    }
}
