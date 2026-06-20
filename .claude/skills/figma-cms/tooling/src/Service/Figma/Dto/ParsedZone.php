<?php

declare(strict_types=1);

namespace App\Service\Figma\Dto;

/**
 * One zone (full-width band) of the dry-run CMS tree (read-only preview).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final readonly class ParsedZone
{
    /**
     * @param list<ParsedCol> $cols
     */
    public function __construct(
        public string $label,
        public array $cols,
        public bool $deduced,
        public bool $fullSize = false,
        public ?string $background = null,
        public float $figmaTop = 0.0,
        public float $figmaHeight = 0.0,
        public ?string $screenshot = null,
        public bool $colToRight = false,
    ) {
    }
}
