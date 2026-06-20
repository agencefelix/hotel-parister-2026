<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Dto\ParsedBlock;
use App\Service\Figma\Dto\ParsedCol;
use App\Service\Figma\Dto\ParsedPage;
use App\Service\Figma\Dto\ParsedZone;

/**
 * Serializes a ParsedPage dry-run tree to a plain, human-editable array (JSON).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class PageTreeExporter
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(ParsedPage $page): array
    {
        return [
            'page' => [
                'slug' => $page->slug,
                'adminName' => $page->adminName,
                'zonesDeduced' => $page->zonesDeduced,
            ],
            'zones' => array_map($this->zoneToArray(...), $page->zones),
            'excluded' => $page->excluded,
            'warnings' => $page->warnings,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function zoneToArray(ParsedZone $zone): array
    {
        return [
            'label' => $zone->label,
            'screenshot' => $zone->screenshot,
            'deduced' => $zone->deduced,
            'fullSize' => $zone->fullSize,
            'colToRight' => $zone->colToRight,
            'background' => $zone->background,
            'cols' => array_map($this->colToArray(...), $zone->cols),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function colToArray(ParsedCol $col): array
    {
        return [
            'size' => $col->size,
            'deduced' => $col->deduced,
            'untaggedCount' => $col->untaggedCount,
            'blocks' => array_map($this->blockToArray(...), $col->blocks),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function blockToArray(ParsedBlock $block): array
    {
        return array_filter([
            'figmaName' => $block->figmaName,
            'kind' => $block->kind,
            'blockTypeSlug' => $block->blockTypeSlug,
            'moduleAction' => $block->moduleAction,
            'moduleEntity' => $block->moduleEntity,
            'moduleTemplate' => $block->moduleTemplate,
            'id' => $block->id,
            'variants' => $block->variants !== [] ? $block->variants : null,
            'text' => $block->text,
            'cms' => $this->blockCms($block),
            'note' => $block->note,
            'media' => $block->media !== [] ? $block->media : null,
        ], static fn ($v) => $v !== null);
    }

    /**
     * Explicit CMS mapping: the PageFixtures::addBlock() call this block translates to
     * (atom = BlockType slug ; module = core-action + Action + module entity).
     *
     * @return array<string, string>|null
     */
    private function blockCms(ParsedBlock $block): ?array
    {
        if ($block->kind === 'atom' && $block->blockTypeSlug !== null) {
            return [
                'entity' => 'App\\Entity\\Layout\\Block',
                'blockType' => $block->blockTypeSlug,
                'fixture' => sprintf("addBlock(\$col, '%s')", $block->blockTypeSlug),
            ];
        }

        if ($block->kind === 'module' && $block->moduleAction !== null) {
            return array_filter([
                'entity' => 'App\\Entity\\Layout\\Block + '.(string) $block->moduleEntity,
                'action' => $block->moduleAction,
                'template' => $block->moduleTemplate,
                'fixture' => sprintf("addBlock(\$col, 'core-action', '%s', \$entity->getId())", $block->moduleAction),
            ], static fn ($v) => $v !== null);
        }

        return null;
    }
}
