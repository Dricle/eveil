<?php

namespace App\Services\Video;

use RuntimeException;

/**
 * The composition did not build, or the frames could not be encoded. The
 * message carries the page's own error, which is what lets `VideoDirector`
 * fix its script on a second attempt.
 */
class VideoRenderFailed extends RuntimeException {}
