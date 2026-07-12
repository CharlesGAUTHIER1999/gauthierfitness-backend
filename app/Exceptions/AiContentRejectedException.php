<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the image provider (gpt-image-1) rejects a prompt for violating
 * its content policy (HTTP 400). Unlike a service outage (503), this is a
 * content rejection: the controller translates it into a 422.
 */
class AiContentRejectedException extends RuntimeException {}
