<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Dto\ParsedBlock;
use App\Service\Figma\Dto\ParsedCol;
use App\Service\Figma\Dto\ParsedPage;
use App\Service\Figma\Dto\ParsedZone;
use App\Service\Figma\Exception\FigmaApiException;

/**
 * Dry-run parser: turns a Figma `[page]` node into a CMS architecture tree
 * (Page → Zones → Cols → Blocks/Modules).
 *
 * Strictly read-only: performs NO database write. Tagged structure is
 * authoritative; when `[zone]`/`[col]` are missing, structure is deduced from
 * geometry (full-width bands, x-clustering) and flagged as such.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class PageParser
{
    /** A child spanning at least this ratio of the page width is a band background. */
    private const float FULL_WIDTH_RATIO = 0.92;

    /** Vertical tolerance (px) to dedupe band starts sharing the same top. */
    private const float BAND_MERGE_TOLERANCE = 50.0;

    /** Min height (px) for a full-width element to count as a band background (filters lines/thin bars). */
    private const float MIN_BAND_HEIGHT = 120.0;

    public function __construct(
        private readonly FigmaApiClientInterface $figma,
        private readonly ConventionMapper $mapper,
    ) {
    }

    public function parse(string $fileKey, string $nodeId): ParsedPage
    {
        $nodes = $this->figma->getFileNodes($fileKey, [$nodeId]);
        $doc = $nodes['nodes'][$nodeId]['document'] ?? null;

        if (!is_array($doc)) {
            throw new FigmaApiException(sprintf('Nœud "%s" introuvable dans le fichier Figma.', $nodeId));
        }

        $warnings = [];
        $token = $this->mapper->extract($doc['name'] ?? '');

        if ($token === null || $token['type'] !== 'page') {
            $warnings[] = sprintf('Le nœud racine "%s" n\'est pas taggé [page] — parsing tenté quand même.', $doc['name'] ?? '?');
        }

        $slug = $token['variants'][0] ?? $this->slugify($doc['name'] ?? 'page');
        $pageBox = $this->bbox($doc);
        $pageWidth = $pageBox['w'];
        $pageBottom = $pageBox['y'] + $pageBox['h'];
        $children = $doc['children'] ?? [];

        $excluded = [];
        $excludedRaw = [];
        $content = $this->filterExcluded($children, $excluded, $excludedRaw);

        [$contentTop, $contentBottom, $excludedNodes] = $this->resolveLayout($excludedRaw, $content, $pageBox);

        // Couleur de fond de page (fill SOLID du nœud racine) : repli pour les bandes
        // sans fond propre. Les fonds image/dégradé ne donnent pas de couleur de repli.
        $rootFill = $this->nodeFill($doc);
        $pageBackground = ($rootFill !== null && $rootFill['kind'] === 'solid') ? $rootFill['value'] : null;

        $taggedZones = array_values(array_filter($content, fn (array $c) => $this->tokenType($c) === 'zone'));
        $zonesDeduced = $taggedZones === [];

        if (!$zonesDeduced) {
            $zones = [];
            foreach ($taggedZones as $i => $z) {
                $zones[] = $this->buildZone($z, $pageWidth, $slug, $i + 1, $pageBackground);
            }
        } else {
            $warnings[] = 'Aucun tag [zone] : zones déduites de la géométrie (fonds pleine largeur). Compte indicatif.';
            $zones = $this->deduceZones($content, $pageWidth, $pageBottom, $slug, $warnings, $pageBackground);
        }

        return new ParsedPage(
            slug: $slug,
            adminName: ucfirst($slug),
            zones: array_values($zones),
            excluded: $excluded,
            warnings: $warnings,
            zonesDeduced: $zonesDeduced,
            figmaTop: $pageBox['y'],
            figmaWidth: $pageBox['w'],
            figmaHeight: $pageBox['h'],
            excludedNodes: $excludedNodes,
            figmaContentTop: $contentTop,
            figmaContentBottom: $contentBottom,
        );
    }

    /**
     * Resolves the content vertical bounds (excluding stacked layout bands such as a
     * bottom footer) and the list of layout nodes to capture separately.
     *
     * @param list<array{node: array<string, mixed>, type: string}> $excludedRaw
     * @param list<array<string, mixed>>                            $content
     * @param array{x: float, y: float, w: float, h: float}        $pageBox
     *
     * @return array{0: float, 1: float, 2: list<array{name: string, id: string, type: string, screenshot: string}>}
     */
    private function resolveLayout(array $excludedRaw, array $content, array $pageBox): array
    {
        $pageTop = $pageBox['y'];
        $pageBottom = $pageBox['y'] + $pageBox['h'];
        $mid = $pageTop + $pageBox['h'] / 2;

        $firstContentTop = $pageBottom;
        foreach ($content as $node) {
            $firstContentTop = min($firstContentTop, $this->bbox($node)['y']);
        }

        $contentTop = $pageTop;
        $contentBottom = $pageBottom;
        $nodes = [];
        $seen = [];

        foreach ($excludedRaw as $entry) {
            $bb = $this->bbox($entry['node']);
            $center = $bb['y'] + $bb['h'] / 2;
            $bottom = $bb['y'] + $bb['h'];

            // Bottom layout band (footer): shrink content bottom to its top.
            if ($center > $mid) {
                $contentBottom = min($contentBottom, $bb['y']);
            } elseif ($bottom <= $firstContentTop + 5) {
                // Top layout band that does NOT overlap content (a stacked nav, not an overlay).
                $contentTop = max($contentTop, $bottom);
            }

            $type = $entry['type'];
            $name = $type;
            $i = 1;
            while (isset($seen[$name])) {
                $name = $type.'-'.(++$i);
            }
            $seen[$name] = true;

            $nodes[] = [
                'name' => $name,
                'id' => (string) ($entry['node']['id'] ?? ''),
                'type' => (string) ($entry['node']['type'] ?? '?'),
                'screenshot' => $name.'.png',
            ];
        }

        return [$contentTop, $contentBottom, $nodes];
    }

    /**
     * Removes [nav]/[footer] subtrees from the children, recording their labels and raw nodes.
     *
     * @param list<array<string, mixed>>                            $children
     * @param list<string>                                          $excluded
     * @param list<array{node: array<string, mixed>, type: string}> $excludedRaw
     *
     * @return list<array<string, mixed>>
     */
    private function filterExcluded(array $children, array &$excluded, array &$excludedRaw): array
    {
        $kept = [];
        foreach ($children as $child) {
            $type = $this->tokenType($child);
            if ($type !== null && $this->mapper->isExcluded($type)) {
                $excluded[] = sprintf('[%s] %s', $type, $child['name'] ?? '?');
                $excludedRaw[] = ['node' => $child, 'type' => $type];
                continue;
            }
            $kept[] = $child;
        }

        return $kept;
    }

    /**
     * Deduces zones as full-width horizontal bands.
     *
     * @param list<array<string, mixed>> $content
     * @param list<string>               $warnings
     *
     * @return list<ParsedZone>
     */
    private function deduceZones(array $content, float $pageWidth, float $pageBottom, string $slug, array &$warnings, ?string $pageBackground = null): array
    {
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;

        // Candidats de fond : nœuds pleine largeur, porteurs d'un fill, hors TEXT/LINE/VECTOR.
        // Sert à trouver le fond qui COUVRE chaque bande (pas seulement à son sommet).
        $candidates = $this->backgroundCandidates($content, $threshold);

        // Band boundaries = top (y) of full-width, tall-enough backgrounds, deduplicated.
        $bgs = [];
        foreach ($content as $node) {
            $bb = $this->bbox($node);
            if ($bb['w'] >= $threshold && $bb['h'] >= self::MIN_BAND_HEIGHT) {
                $bgs[] = $bb['y'];
            }
        }
        sort($bgs);

        $boundaries = [];
        foreach ($bgs as $y) {
            if ($boundaries === [] || abs($y - end($boundaries)) > self::BAND_MERGE_TOLERANCE) {
                $boundaries[] = $y;
            }
        }

        if ($boundaries === []) {
            $warnings[] = 'Aucun fond pleine largeur détecté : page traitée comme une zone unique.';
            $top = $content === [] ? 0.0 : $this->bbox($content[0])['y'];
            $bg = $this->backgroundForRange($candidates, $top, $pageBottom, $pageBackground);

            return [$this->buildZoneFromElements('zone déduite', $content, $pageWidth, $top, $pageBottom - $top, $slug, 1, $bg)];
        }

        // Assign each content element to the band whose range contains its vertical center.
        $bands = array_fill(0, count($boundaries), []);
        foreach ($content as $node) {
            $bb = $this->bbox($node);
            $center = $bb['y'] + $bb['h'] / 2;
            $index = 0;
            foreach ($boundaries as $i => $start) {
                if ($center >= $start) {
                    $index = $i;
                }
            }
            $bands[$index][] = $node;
        }

        $zones = [];
        $position = 0;
        foreach ($bands as $i => $elements) {
            if ($elements === []) {
                continue;
            }
            ++$position;
            $top = $boundaries[$i];
            $bottom = $boundaries[$i + 1] ?? $pageBottom;
            $zones[] = $this->buildZoneFromElements(
                sprintf('bande %d (déduite)', $position),
                $elements,
                $pageWidth,
                $top,
                $bottom - $top,
                $slug,
                $position,
                $this->backgroundForRange($candidates, $top, $bottom, $pageBackground),
            );
        }

        return $zones;
    }

    /**
     * Builds a zone from a tagged [zone] node.
     *
     * @param array<string, mixed> $node
     */
    private function buildZone(array $node, float $pageWidth, string $slug, int $position, ?string $pageBackground = null): ParsedZone
    {
        $children = $node['children'] ?? [];
        $taggedCols = array_values(array_filter($children, fn (array $c) => $this->tokenType($c) === 'col'));

        if ($taggedCols !== []) {
            $cols = array_map(fn (array $c) => $this->buildColFromElements(($c['children'] ?? []), $pageWidth, false), $taggedCols);
        } else {
            $cols = $this->deduceCols($children, $pageWidth);
        }

        $bb = $this->bbox($node);
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;

        // Candidats = la zone elle-même + ses descendants pleine largeur.
        $candidates = $this->backgroundCandidates([$node], $threshold);

        return new ParsedZone(
            label: $this->cleanName($node['name'] ?? 'zone'),
            cols: array_values($cols),
            deduced: false,
            fullSize: false,
            background: $this->backgroundForRange($candidates, $bb['y'], $bb['y'] + $bb['h'], $pageBackground),
            figmaTop: $bb['y'],
            figmaHeight: $bb['h'],
            screenshot: $this->screenshotName($slug, $position),
        );
    }

    /**
     * Collects full-width, fill-bearing background candidates (the nodes themselves
     * and their descendants), excluding TEXT/LINE/VECTOR. Used to find the colour
     * that COVERS a band, not merely one starting at its top.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array{y: float, h: float, kind: string, value: ?string}>
     */
    private function backgroundCandidates(array $nodes, float $threshold): array
    {
        $out = [];
        $walk = function (array $node) use (&$walk, &$out, $threshold): void {
            $bb = $this->bbox($node);
            if ($bb['w'] >= $threshold) {
                $fill = $this->nodeFill($node);
                if ($fill !== null) {
                    $out[] = ['y' => $bb['y'], 'h' => $bb['h']] + $fill;
                }
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child);
            }
        };
        foreach ($nodes as $node) {
            $walk($node);
        }

        return $out;
    }

    /**
     * Background of a vertical range: the most specific (smallest) full-width SOLID/
     * GRADIENT candidate covering the range centre ; else null if an image covers it ;
     * else the page background as a fallback.
     *
     * @param list<array{y: float, h: float, kind: string, value: ?string}> $candidates
     */
    private function backgroundForRange(array $candidates, float $top, float $bottom, ?string $pageBackground): ?string
    {
        $center = ($top + $bottom) / 2.0;
        $covering = array_filter($candidates, fn (array $c) => $c['y'] <= $center && $center <= $c['y'] + $c['h']);

        $colors = array_values(array_filter($covering, fn (array $c) => $c['kind'] === 'solid' || $c['kind'] === 'gradient'));
        if ($colors !== []) {
            usort($colors, fn (array $a, array $b) => $a['h'] <=> $b['h']);

            return $colors[0]['value'];
        }

        foreach ($covering as $c) {
            if ($c['kind'] === 'image') {
                return null; // fond image : pas de couleur unie
            }
        }

        return $pageBackground;
    }

    /**
     * Visible background fill of a node, ignoring TEXT/LINE/VECTOR (whose fills are
     * the text/stroke colour, not a background). Returns:
     *  - ['kind'=>'solid','value'=>'#rrggbb' | '#rrggbbaa']
     *  - ['kind'=>'gradient','value'=>'gradient(linear #a,#b)']
     *  - ['kind'=>'image','value'=>null]
     *  - null when there is no usable fill.
     *
     * @param array<string, mixed> $node
     *
     * @return array{kind: string, value: ?string}|null
     */
    private function nodeFill(array $node): ?array
    {
        if (in_array($node['type'] ?? '', ['TEXT', 'LINE', 'VECTOR'], true)) {
            return null;
        }

        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) === false) {
                continue;
            }
            $type = (string) ($fill['type'] ?? '');

            if ($type === 'SOLID') {
                $opacity = (float) ($fill['opacity'] ?? 1.0);
                if ($opacity <= 0.0) {
                    continue;
                }
                $color = $fill['color'] ?? null;
                if (!is_array($color)) {
                    continue;
                }
                $r = (int) round(((float) ($color['r'] ?? 0.0)) * 255);
                $g = (int) round(((float) ($color['g'] ?? 0.0)) * 255);
                $b = (int) round(((float) ($color['b'] ?? 0.0)) * 255);
                $alpha = ((float) ($color['a'] ?? 1.0)) * $opacity;
                $hex = sprintf('#%02x%02x%02x', $r, $g, $b);
                if ($alpha < 0.999) {
                    $hex .= sprintf('%02x', (int) round($alpha * 255));
                }

                return ['kind' => 'solid', 'value' => $hex];
            }

            if (str_starts_with($type, 'GRADIENT')) {
                $stops = [];
                foreach ($fill['gradientStops'] ?? [] as $stop) {
                    $c = $stop['color'] ?? [];
                    $stops[] = sprintf('#%02x%02x%02x', (int) round(((float) ($c['r'] ?? 0)) * 255), (int) round(((float) ($c['g'] ?? 0)) * 255), (int) round(((float) ($c['b'] ?? 0)) * 255));
                }
                $kind = strtolower(str_replace('GRADIENT_', '', $type));

                return ['kind' => 'gradient', 'value' => sprintf('gradient(%s %s)', $kind, implode(',', array_values(array_unique($stops))))];
            }

            if ($type === 'IMAGE') {
                return ['kind' => 'image', 'value' => null];
            }
        }

        return null;
    }

    /**
     * Builds a zone from a flat list of deduced elements.
     *
     * @param list<array<string, mixed>> $elements
     */
    private function buildZoneFromElements(string $label, array $elements, float $pageWidth, float $top, float $height, string $slug, int $position, ?string $background = null): ParsedZone
    {
        return new ParsedZone(
            label: $label,
            cols: array_values($this->deduceCols($elements, $pageWidth)),
            deduced: true,
            fullSize: $this->hasFullWidth($elements, $pageWidth),
            background: $background,
            figmaTop: $top,
            figmaHeight: $height,
            screenshot: $this->screenshotName($slug, $position),
        );
    }

    /**
     * Deduces columns by clustering elements on their horizontal ranges.
     *
     * @param list<array<string, mixed>> $elements
     *
     * @return list<ParsedCol>
     */
    private function deduceCols(array $elements, float $pageWidth): array
    {
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;

        // A full-width element is a band background and dropped — UNLESS it carries a
        // convention tag (e.g. a full-width [slider] hero), in which case it is a size-12 column.
        $fullWidthCols = [];
        $contentEls = [];
        foreach ($elements as $el) {
            if ($this->bbox($el)['w'] >= $threshold) {
                if ($this->collectTaggedBlocks($el) !== []) {
                    $fullWidthCols[] = $this->buildColFromElements([$el], $pageWidth, true, 12);
                }
                continue;
            }
            $contentEls[] = $el;
        }

        if ($contentEls === []) {
            return $fullWidthCols !== [] ? $fullWidthCols : [$this->buildColFromElements($elements, $pageWidth, true)];
        }

        usort($contentEls, fn (array $a, array $b) => $this->bbox($a)['x'] <=> $this->bbox($b)['x']);

        $clusters = [];
        foreach ($contentEls as $el) {
            $bb = $this->bbox($el);
            $x1 = $bb['x'];
            $x2 = $bb['x'] + $bb['w'];
            $merged = false;
            foreach ($clusters as &$cluster) {
                if ($x1 < $cluster['x2'] - 5 && $x2 > $cluster['x1'] + 5) {
                    $cluster['x1'] = min($cluster['x1'], $x1);
                    $cluster['x2'] = max($cluster['x2'], $x2);
                    $cluster['els'][] = $el;
                    $merged = true;
                    break;
                }
            }
            unset($cluster);
            if (!$merged) {
                $clusters[] = ['x1' => $x1, 'x2' => $x2, 'els' => [$el]];
            }
        }

        $cols = $fullWidthCols;
        foreach ($clusters as $cluster) {
            $width = $cluster['x2'] - $cluster['x1'];
            $size = max(1, min(12, (int) round($width / max($pageWidth, 1) * 12)));
            $cols[] = $this->buildColFromElements($cluster['els'], $pageWidth, true, $size);
        }

        return $cols;
    }

    /**
     * Builds a column: maps tagged descendants to blocks, counts untagged elements.
     *
     * @param list<array<string, mixed>> $elements
     */
    private function buildColFromElements(array $elements, float $pageWidth, bool $deduced, ?int $size = null): ParsedCol
    {
        $blocks = [];
        $untagged = 0;

        foreach ($elements as $el) {
            $found = $this->collectTaggedBlocks($el);
            if ($found !== []) {
                array_push($blocks, ...$found);
            } elseif ($this->isSignificant($el)) {
                ++$untagged;
            }
        }

        return new ParsedCol(
            size: $size ?? 12,
            blocks: $blocks,
            deduced: $deduced,
            untaggedCount: $untagged,
        );
    }

    /**
     * Recursively collects mapped blocks from tagged descendants.
     * Stops at structural/excluded tags.
     *
     * @param array<string, mixed> $node
     *
     * @return list<ParsedBlock>
     */
    private function collectTaggedBlocks(array $node): array
    {
        $token = $this->mapper->extract($node['name'] ?? '');

        if ($token !== null && !$this->mapper->isStructural($token['type']) && !$this->mapper->isExcluded($token['type'])) {
            $block = $this->mapper->toBlock($this->cleanName($node['name'] ?? '?'), $token['type'], $token['variants']);
            $media = $this->collectImages($node);

            if ($media !== []) {
                $block = new ParsedBlock(
                    figmaName: $block->figmaName,
                    kind: $block->kind,
                    blockTypeSlug: $block->blockTypeSlug,
                    moduleAction: $block->moduleAction,
                    moduleEntity: $block->moduleEntity,
                    note: $block->note,
                    media: $media,
                );
            }

            return [$block];
        }

        $blocks = [];
        foreach ($node['children'] ?? [] as $child) {
            array_push($blocks, ...$this->collectTaggedBlocks($child));
        }

        return $blocks;
    }

    /**
     * Collects image-bearing nodes (IMAGE fills) within a node subtree, in document order.
     * Used to surface slider slides / media carried by a block.
     *
     * @param array<string, mixed> $node
     *
     * @return list<array{figmaNodeId: string, image: string, imageRef: string}>
     */
    private function collectImages(array $node): array
    {
        $media = [];
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['type'] ?? '') === 'IMAGE') {
                $id = (string) ($node['id'] ?? '');
                $media[] = [
                    'figmaNodeId' => $id,
                    'image' => 'slide-'.str_replace(':', '-', $id).'.webp',
                    'imageRef' => (string) ($fill['imageRef'] ?? ''),
                    'width' => (int) round($this->bbox($node)['w']),
                ];
                break; // one entry per node is enough
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            array_push($media, ...$this->collectImages($child));
        }

        return $media;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isSignificant(array $node): bool
    {
        $type = $node['type'] ?? '';

        return in_array($type, ['TEXT', 'RECTANGLE', 'INSTANCE', 'COMPONENT', 'FRAME', 'GROUP'], true);
    }

    /**
     * @param list<array<string, mixed>> $elements
     */
    private function hasFullWidth(array $elements, float $pageWidth): bool
    {
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;
        foreach ($elements as $el) {
            if ($this->bbox($el)['w'] >= $threshold) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function tokenType(array $node): ?string
    {
        return $this->mapper->extract($node['name'] ?? '')['type'] ?? null;
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array{x: float, y: float, w: float, h: float}
     */
    private function bbox(array $node): array
    {
        $bb = $node['absoluteBoundingBox'] ?? [];

        return [
            'x' => (float) ($bb['x'] ?? 0.0),
            'y' => (float) ($bb['y'] ?? 0.0),
            'w' => (float) ($bb['width'] ?? 0.0),
            'h' => (float) ($bb['height'] ?? 0.0),
        ];
    }

    private function screenshotName(string $slug, int $position): string
    {
        return sprintf('section-%s-%d.png', $slug, $position);
    }

    private function cleanName(string $name): string
    {
        return trim($name);
    }

    private function slugify(string $name): string
    {
        $name = strtolower(preg_replace('/\[[^\]]*\]/', '', $name) ?? $name);

        return trim(preg_replace('/[^a-z0-9]+/', '-', $name) ?? $name, '-') ?: 'page';
    }
}
