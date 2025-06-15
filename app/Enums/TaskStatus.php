<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PENDING = "PENDING";
    case IN_PROGRESS = "IN_PROGRESS";
    case COMPLETED = "COMPLETED";

    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isPending(): bool
    {
        return $this === self::PENDING;
    }

    public function isInProgress(): bool
    {
        return $this === self::IN_PROGRESS;
    }
}
