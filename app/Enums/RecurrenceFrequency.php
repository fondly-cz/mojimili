<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

enum RecurrenceFrequency: string
{
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    case YEARLY = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::DAILY => 'Denně',
            self::WEEKLY => 'Týdně',
            self::MONTHLY => 'Měsíčně',
            self::QUARTERLY => 'Čtvrtletně',
            self::YEARLY => 'Ročně',
        };
    }

    /**
     * Moves the date forward by one period; months never overflow (31. 1. + 1 month = 28./29. 2.).
     */
    public function advance(CarbonImmutable $date, int $interval, bool $workingDaysOnly = false): CarbonImmutable
    {
        $next = match ($this) {
            self::DAILY => $date->addDays($interval),
            self::WEEKLY => $date->addWeeks($interval),
            self::MONTHLY => $date->addMonthsNoOverflow($interval),
            self::QUARTERLY => $date->addMonthsNoOverflow(3 * $interval),
            self::YEARLY => $date->addYearsNoOverflow($interval),
        };

        // Working days only make sense for daily recurrence, as in Freelo.
        if ($this === self::DAILY && $workingDaysOnly) {
            while ($next->isWeekend()) {
                $next = $next->addDay();
            }
        }

        return $next;
    }
}
