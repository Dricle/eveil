<?php

namespace App\Enums;

/**
 * Who a profile describes. A partner is not a buyer: it is whoever already
 * touches the buyer — the wholesaler who delivers to them, the accountant who
 * invoices them monthly, the body a regulation imposes on them. Found and
 * qualified exactly like a customer, written to very differently.
 */
enum TargetProfileType: string
{
    case Customer = 'customer';
    case Partner = 'partner';
}
