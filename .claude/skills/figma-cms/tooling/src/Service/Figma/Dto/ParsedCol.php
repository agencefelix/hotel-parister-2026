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
    ) {
    }
}
