<?php

namespace App\Jobs\Discovery;

use RuntimeException;

/**
 * The node had nothing to do: the budget line it needed was spent, the run was
 * stopped, or the work was already done by somebody else. Not a failure: the
 * row says so and the run carries on.
 */
class TaskSkipped extends RuntimeException {}
