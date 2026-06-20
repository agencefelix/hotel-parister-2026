<?php

declare(strict_types=1);

namespace App\Service\Figma\Dto;

/**
 * Root of the dry-run CMS tree produced from a Figma `[page]` node.
 *
 * Pure value object: building it performs NO database write.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final readonly class ParsedPage
{
    /**
     * @param list<ParsedZone>                                                          $zones
     * @param list<string>                                                              $excluded     labels excluded from page generation (nav, footer)
     * @param list<string>                                                              $warnings     non-blocking issues raised during parsing
     * @param list<array{name: string, id: string, type: string, screenshot: string}> $excludedNodes layout elements to capture separately
     */
    public function __construct(
        public string $slug,
        public string $adminName,
        public array $zones,
        public array $excluded,
        public array $warnings,
        public bool $zonesDeduced,
        public float $figmaTop = 0.0,
        public float $figmaWidth = 0.0,
        public float $figmaHeight = 0.0,
        public array $excludedNodes = [],
        public float $figmaContentTop = 0.0,
        public float $figmaContentBottom = 0.0,
    ) {
    }
}
