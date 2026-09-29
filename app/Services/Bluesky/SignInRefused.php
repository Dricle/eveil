<?php

namespace App\Services\Bluesky;

use RuntimeException;

/**
 * Bluesky refused the handle and app password: the user revoked it, or the
 * handle moved. Distinct from any other failure because the fix is on the
 * account, not on the post.
 */
class SignInRefused extends RuntimeException {}
