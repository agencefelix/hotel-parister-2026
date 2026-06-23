<?php

declare(strict_types=1);

namespace App\Service\Figma\Dto;

/**
 * One column of the dry-run CMS tree (read-only preview, never persisted).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final readonly class ParsedCol
{
    /**
     * @param list<ParsedBlock> $blocks
     */
    public function __construct(
        public int $size,
        public array $blocks,
        public bool $deduced,
        public int $untaggedCount = 0,
        /** Contenu centré verticalement dans la bande → Col::setVerticalAlign(true) (JAMAIS de CSS). */
        public bool $verticalAlign = false,
        /** Contenu aligné en fin (bas) de la bande → Col::setEndAlign(true) (JAMAIS de CSS). */
        public bool $endAlign = false,
    ) {
    }
}
