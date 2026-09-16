<?php

namespace App\Enums;

/**
 * How often a project wants a new LinkedIn post generated. `Off` is the
 * default: nothing is drafted until the user opts in, same reasoning as a
 * project starting with no mailbox attached.
 */
enum LinkedinPostFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * Days until the next generation is due. `Off` never asks: the scheduler
     * filters it out before this is ever called.
     */
    public function days(): int
    {
        return match ($this) {
            self::Off => 0,
            self::Daily => 1,
            self::Weekly => 7,
            self::Biweekly => 14,
            self::Monthly => 30,
        };
    }
}
