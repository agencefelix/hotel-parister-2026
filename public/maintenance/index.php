<?php

declare(strict_types=1);

use Maintenance\Gate;

/**
 * Acces direct a /maintenance/ : sert la meme page, avec le meme statut HTTP 503,
 * que le portail declenche depuis public/index.php.
 */

require_once __DIR__.'/maintenance.php';

Gate::render();
