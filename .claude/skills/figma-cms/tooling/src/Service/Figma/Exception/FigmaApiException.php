<?php

declare(strict_types=1);

namespace App\Service\Figma\Exception;

use RuntimeException;
use Throwable;

/**
 * Raised when a call to the Figma REST API fails or returns an unusable payload.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class FigmaApiException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?int $statusCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public static function missingToken(): self
    {
        return new self('Le token Figma (FIGMA_TOKEN) n\'est pas configuré.');
    }
}
