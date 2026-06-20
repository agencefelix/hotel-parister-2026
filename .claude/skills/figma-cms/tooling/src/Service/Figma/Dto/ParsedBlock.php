<?php

declare(strict_types=1);

namespace App\Service\Figma\Dto;

/**
 * One block of the dry-run CMS tree (read-only preview, never persisted).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final readonly class ParsedBlock
{
    /**
     * @param 'atom'|'module'|'unknown'                                       $kind
     * @param list<array{figmaNodeId: string, image: string, imageRef: string, width: int}> $media images carried by the block (slider slides, media…)
     */
    public function __construct(
        public string $figmaName,
        public string $kind,
        public ?string $blockTypeSlug = null,
        public ?string $moduleAction = null,
        public ?string $moduleEntity = null,
        public ?string $note = null,
        public array $media = [],
        public array $variants = [],
        public ?string $id = null,
        public ?string $moduleTemplate = null,
        public ?string $text = null,
    ) {
    }
}
