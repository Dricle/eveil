<?php

namespace App\Enums;

/**
 * How often a project wants a new SEO article drafted. Copy of
 * `LinkedinPostFrequency`, including the opt-in `Off` default: nothing is
 * written until the user turns it on from the SEO page.
 */
enum ArticleFrequency: string
{
    case Off = 'off';
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    /**
     * Days until the next scan is due. `Off` never asks: the scheduler
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
