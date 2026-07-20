<?php

namespace App\Exceptions;

use RuntimeException;

// Thrown when image provider (gpt-image-1) rejects a prompt for violating its content policy (HTTP 400)
class AiContentRejectedException extends RuntimeException {}
