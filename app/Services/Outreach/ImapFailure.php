<?php

namespace App\Services\Outreach;

use RuntimeException;

/**
 * A mailbox that could not be read. Carried rather than swallowed: replies are
 * the only opt-out channel and the only metric, so a mailbox we cannot read is
 * a problem the user has to be told about, on the same screen as the send half.
 */
class ImapFailure extends RuntimeException {}
