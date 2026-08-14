<?php

namespace App\Enums;

/**
 * What a learned path fragment is for.
 */
enum PathHintKind: string
{
    /** Pages that publish a human name, an address or a phone number. */
    case Contact = 'contact';

    /** Pages that say what a product is, who it is for and what it costs. */
    case Product = 'product';
}
