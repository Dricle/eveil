<?php

namespace App\Ai;

use RuntimeException;

/**
 * Raised instead of calling the provider when the guard says no.
 *
 * A distinct class because it is not a failure of the work: nothing was broken,
 * nothing is worth retrying, and a queue that keeps retrying it would burn
 * attempts on a wallet that will still be empty. Callers that can pause
 * something should catch this one rather than treat it as an outage.
 */
class OutOfCredit extends RuntimeException {}
