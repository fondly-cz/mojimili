<?php

namespace App\Enums;

enum TodoPriority: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public function label(): string
    {
        return match ($this) {
            self::HIGH => 'Vysoká',
            self::MEDIUM => 'Střední',
            self::LOW => 'Nízká',
        };
    }

    /**
     * Freelo sends the priority as "h", "m" or "l".
     */
    public static function fromFreelo(?string $value): ?self
    {
        return match ($value) {
            'h' => self::HIGH,
            'm' => self::MEDIUM,
            'l' => self::LOW,
            default => null,
        };
    }
}
