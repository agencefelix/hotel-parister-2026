<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Dto\ParsedBlock;

/**
 * Maps a Figma layer name (convention prefix) to its CMS target.
 *
 * Encodes `.claude/figma/mapping-blocktypes.md`. Pure, stateless, read-only.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class ConventionMapper
{
    /** Atomic blocks: convention type => BlockType slug. */
    private const array ATOMS = [
        'title' => 'title',
        'intro' => 'title-header',
        'text' => 'text',
        'image' => 'media',
        'media' => 'media',
        'video' => 'video',
        'blockquote' => 'blockquote',
        'quote' => 'blockquote',
        'card' => 'card',
        'modal' => 'modal',
        'icon' => 'icon',
        'btn' => 'link',
        'cta' => 'link',
        'link' => 'link',
        'separator' => 'separator',
        'counter' => 'counter',
        'alert' => 'alert',
    ];

    /** Business modules: convention type => [Action slug, module entity]. */
    private const array MODULES = [
        'slider' => ['slider-view', 'App\\Entity\\Module\\Slider\\Slider'],
        'gallery' => ['gallery-view', 'App\\Entity\\Module\\Gallery'],
        'catalog' => ['catalog-index', 'App\\Entity\\Module\\Catalog'],
        'newscast' => ['newscast-index', 'App\\Entity\\Module\\Newscast'],
        'portfolio' => ['portfolio-index', 'App\\Entity\\Module\\Portfolio'],
        'form' => ['form-view', 'App\\Entity\\Module\\Form\\Form'],
        'contact' => ['form-view', 'App\\Entity\\Module\\Form\\Form'],
        'tab' => ['tab-view', 'App\\Entity\\Module\\Tab'],
        'faq' => ['faq-view', 'App\\Entity\\Module\\Faq'],
        'map' => ['map-view', 'App\\Entity\\Module\\Map\\Map'],
    ];

    private const array STRUCTURAL = ['page', 'zone', 'col'];
    private const array EXCLUDED = ['nav', 'footer'];

    /**
     * Extracts the convention token from a layer name.
     *
     * @return array{type: string, variants: list<string>}|null
     */
    public function extract(string $layerName): ?array
    {
        if (!preg_match('/\[([^\]]+)\]/', $layerName, $m)) {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode('|', strtolower($m[1])))));

        if ($parts === []) {
            return null;
        }

        return ['type' => $parts[0], 'variants' => array_slice($parts, 1)];
    }

    public function isStructural(string $type): bool
    {
        return in_array($type, self::STRUCTURAL, true);
    }

    public function isExcluded(string $type): bool
    {
        return in_array($type, self::EXCLUDED, true);
    }

    /**
     * Maps a convention type to a ParsedBlock (atom, module or unknown).
     *
     * @param list<string> $variants
     */
    public function toBlock(string $figmaName, string $type, array $variants = []): ParsedBlock
    {
        if (isset(self::ATOMS[$type])) {
            return new ParsedBlock($figmaName, 'atom', blockTypeSlug: self::ATOMS[$type]);
        }

        if (isset(self::MODULES[$type])) {
            [$action, $entity] = self::MODULES[$type];
            $note = 'faq' === $type ? 'arbitrage : bloc "collapse" possible au lieu du module faq-view' : null;

            return new ParsedBlock($figmaName, 'module', moduleAction: $action, moduleEntity: $entity, note: $note);
        }

        return new ParsedBlock($figmaName, 'unknown', note: sprintf('préfixe [%s] non mappé', $type));
    }
}
