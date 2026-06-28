<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Levée lorsque le fournisseur d'images (gpt-image-1) refuse un prompt pour
 * violation de sa politique de contenu (HTTP 400). Contrairement à une panne
 * (503), c'est un rejet de contenu : le contrôleur le traduit en 422.
 */
class AiContentRejectedException extends RuntimeException {}
