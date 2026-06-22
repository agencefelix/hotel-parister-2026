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

    /** A full-screen image hero gets a closing boundary at its bottom only if the next band starts at least this far below. */
    private const float MIN_IMAGE_TAIL = 200.0;

    /** A node in the bottom of the page below this ratio is a layout-element candidate. */
    private const float LAYOUT_BAND_RATIO = 0.55;

    public function __construct(
        private readonly FigmaApiClientInterface $figma,
        private readonly ConventionMapper $mapper,
    ) {
    }

    /**
     * Slides declared separately and linked to their slider by id, grouped by slider id.
     * Reset on each parse().
     *
     * @var array<string, list<array{position: int, figmaNodeId: string, image: string, imageRef: string, width: int, format: string}>>
     */
    private array $slidesBySlider = [];

    /**
     * Page font scale: body size (most frequent) + larger sizes ranked into heading levels.
     *
     * @var array{body: float, levels: array<string, int>}
     */
    private array $fontScale = ['body' => 0.0, 'levels' => []];

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

        // Auto-exclusion des éléments de layout NON taggés (footer/newsletter/mur social) : ils ne
        // doivent jamais être des sections de page. Détectés par contenu + position basse, retirés
        // du contenu (et capturés comme layout). Vaut pour TOUTE page, pas que la home.
        $content = $this->excludeUntaggedLayout($content, $pageBox, $excluded, $excludedRaw, $warnings);

        // Slides declared separately (`[slide-N|id]`) are collected first, then attached
        // to their `[slider|id:…]` block — so a carousel can carry images posed elsewhere.
        $this->slidesBySlider = $this->collectSlides($content);

        // Font scale: lets untagged text be classified as title (h1…h6, by size rank) or body text.
        $this->fontScale = $this->computeFontScale($content);

        [$contentTop, $contentBottom, $excludedNodes] = $this->resolveLayout($excludedRaw, $content, $pageBox);

        // Couleur de fond de page (fill SOLID du nœud racine) : repli pour les bandes sans fond propre.
        $rootFill = $this->nodeFill($doc);
        $pageBackground = ($rootFill !== null && $rootFill['kind'] === 'solid') ? $rootFill['value'] : null;

        // A zone is introduced by [zone] OR [section] (both = one CMS zone, 1:1 with the band).
        // Descend through untagged wrapper frames/groups so nested [section]/[zone] tags
        // (e.g. grouped under a "Group sections" frame) are surfaced — not only top-level ones.
        $taggedZones = $this->collectZoneNodes($content);
        $zonesDeduced = $taggedZones === [];

        // Explicit ordering: [section|N] forces the slot; sections without a number keep
        // their document order (the number defaults to the document index). Stable sort
        // so equal keys never shuffle.
        if (!$zonesDeduced) {
            $indexed = [];
            foreach ($taggedZones as $i => $node) {
                $indexed[] = ['node' => $node, 'pos' => $this->zonePosition($node, $i), 'i' => $i];
            }
            usort($indexed, static fn (array $a, array $b): int => [$a['pos'], $a['i']] <=> [$b['pos'], $b['i']]);
            $taggedZones = array_map(static fn (array $e): array => $e['node'], $indexed);
        }

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
     * Removes [nav]/[footer]-tagged subtrees, recording their labels and raw nodes.
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
     * Removes UNTAGGED layout elements (footer / newsletter / social wall) sitting at the page
     * bottom, detected by their text content. Shared layout — never a page section.
     *
     * @param list<array<string, mixed>>                            $content
     * @param array{x: float, y: float, w: float, h: float}         $pageBox
     * @param list<string>                                          $excluded
     * @param list<array{node: array<string, mixed>, type: string}> $excludedRaw
     * @param list<string>                                          $warnings
     *
     * @return list<array<string, mixed>>
     */
    private function excludeUntaggedLayout(array $content, array $pageBox, array &$excluded, array &$excludedRaw, array &$warnings): array
    {
        $bottomFrom = $pageBox['y'] + $pageBox['h'] * self::LAYOUT_BAND_RATIO;

        $found = [];
        $layoutTop = null;
        foreach ($content as $node) {
            $bb = $this->bbox($node);
            if ($bb['y'] < $bottomFrom) {
                continue;
            }
            $text = mb_strtolower($this->allText($node), 'UTF-8');
            $type = match (true) {
                $text === '' => null,
                preg_match('/newsletter|inscrivez|inscription.{0,15}(lettre|news)/u', $text) === 1 => 'newsletter',
                preg_match('/suivez[\s-]?nous|instagram|\binsta\b|facebook|youtube|tiktok/u', $text) === 1 => 'socialwall',
                preg_match('/mentions l[ée]gales|copyright|©|tous droits|gestion des cookies|plan du site/u', $text) === 1 => 'footer',
                default => null,
            };
            if ($type !== null) {
                $found[$type] = $node;
                $layoutTop = $layoutTop === null ? $bb['y'] : min($layoutTop, $bb['y']);
            }
        }

        if ($layoutTop === null) {
            return $content;
        }

        $kept = [];
        foreach ($content as $node) {
            if ($this->bbox($node)['y'] >= $layoutTop) {
                continue;
            }
            $kept[] = $node;
        }
        foreach ($found as $type => $node) {
            $excluded[] = sprintf('[auto:%s] %s', $type, $node['name'] ?? '?');
            $excludedRaw[] = ['node' => $node, 'type' => $type];
        }

        $warnings[] = sprintf(
            'Éléments de layout détectés et exclus de la page (non taggés) : %s. À tagger dans la maquette pour fiabiliser.',
            implode(', ', array_keys($found))
        );

        return $kept;
    }

    /**
     * Concatenates all text content (`characters`) found in a node subtree.
     *
     * @param array<string, mixed> $node
     */
    private function allText(array $node): string
    {
        $parts = [];
        if (($node['type'] ?? '') === 'TEXT' && isset($node['characters'])) {
            $parts[] = (string) $node['characters'];
        }
        foreach ($node['children'] ?? [] as $child) {
            $parts[] = $this->allText($child);
        }

        return trim(implode(' ', array_filter($parts)));
    }

    /**
     * Resolves the content vertical bounds (excluding stacked layout bands) and the layout nodes to capture.
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

            if ($center > $mid) {
                $contentBottom = min($contentBottom, $bb['y']);
            } elseif ($bottom <= $firstContentTop + 5) {
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
        $candidates = $this->backgroundCandidates($content, $threshold);

        // Band boundaries = top (y) of full-width, tall-enough backgrounds, deduplicated.
        // TEXT/LINE/VECTOR excluded: a full-width title/watermark is NOT a band background.
        $bgs = [];
        $imageBottoms = [];
        foreach ($content as $node) {
            if (in_array($node['type'] ?? '', ['TEXT', 'LINE', 'VECTOR'], true)) {
                continue;
            }
            $bb = $this->bbox($node);
            if ($bb['w'] >= $threshold && $bb['h'] >= self::MIN_BAND_HEIGHT) {
                $bgs[] = $bb['y'];
                // A full-screen image hero is self-contained: remember its bottom.
                if ($this->isImageBand($node, $threshold)) {
                    $imageBottoms[] = $bb['y'] + $bb['h'];
                }
            }
        }
        sort($bgs);

        $boundaries = [];
        foreach ($bgs as $y) {
            if ($boundaries === [] || abs($y - end($boundaries)) > self::BAND_MERGE_TOLERANCE) {
                $boundaries[] = $y;
            }
        }

        // Close an image hero with a bottom boundary only when no other band edge sits near it.
        foreach ($imageBottoms as $bottom) {
            if ($bottom >= $pageBottom) {
                continue;
            }
            $nearest = $pageBottom;
            foreach ($boundaries as $b) {
                $nearest = min($nearest, abs($b - $bottom));
            }
            if ($nearest >= self::MIN_IMAGE_TAIL) {
                $boundaries[] = $bottom;
            }
        }
        sort($boundaries);

        if ($boundaries === []) {
            $warnings[] = 'Aucun fond pleine largeur détecté : page traitée comme une zone unique.';
            $top = $content === [] ? 0.0 : $this->bbox($content[0])['y'];
            $bg = $this->backgroundForRange($candidates, $top, $pageBottom, $pageBackground);

            return [$this->buildZoneFromElements('zone déduite', $content, $pageWidth, $top, $pageBottom - $top, $slug, 1, $bg)];
        }

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
            $top = $boundaries[$i];
            $bottom = $boundaries[$i + 1] ?? $pageBottom;
            $bg = $this->backgroundForRange($candidates, $top, $bottom, $pageBackground);

            // Sous-bande : une rangée basse de cards alignées (≥3) sous un contenu d'intro de MÊME
            // fond = en réalité DEUX zones (intro + teaser/carrousel). On scinde (cf. convention).
            $split = $this->splitTrailingCardRow($elements, $pageWidth);
            if ($split !== null) {
                ++$position;
                $zones[] = $this->buildZoneFromElements(sprintf('bande %d (déduite)', $position), $split['upper'], $pageWidth, $top, $split['rowTop'] - $top, $slug, $position, $bg);
                ++$position;
                $zones[] = $this->buildZoneFromElements(sprintf('bande %d (déduite, teaser croppé à droite)', $position), $split['row'], $pageWidth, $split['rowTop'], $bottom - $split['rowTop'], $slug, $position, $bg, true);
                continue;
            }

            ++$position;
            $zones[] = $this->buildZoneFromElements(
                sprintf('bande %d (déduite)', $position),
                $elements,
                $pageWidth,
                $top,
                $bottom - $top,
                $slug,
                $position,
                $bg,
            );
        }

        return $zones;
    }

    /**
     * Detects a trailing horizontal row of ≥3 similarly-sized images (a card carousel/teaser)
     * sitting BELOW other intro content of the same band → the band is really two zones.
     *
     * @param list<array<string, mixed>> $elements
     *
     * @return array{rowTop: float, upper: list<array<string, mixed>>, row: list<array<string, mixed>>}|null
     */
    private function splitTrailingCardRow(array $elements, float $pageWidth): ?array
    {
        // Candidate cards = images noticeably narrower than the page (not full-width heroes).
        $cards = [];
        foreach ($elements as $el) {
            $bb = $this->bbox($el);
            if ($this->collectImages($el) !== [] && $bb['w'] < $pageWidth * 0.5) {
                $cards[] = ['el' => $el, 'y' => $bb['y'], 'w' => $bb['w']];
            }
        }
        if (count($cards) < 3) {
            return null;
        }

        // Lowest aligned row: cluster cards whose tops are within tolerance of the lowest card.
        usort($cards, fn ($a, $b) => $b['y'] <=> $a['y']);
        $refY = $cards[0]['y'];
        $refW = $cards[0]['w'];
        $rowEls = [];
        foreach ($cards as $c) {
            if (abs($c['y'] - $refY) <= 40 && abs($c['w'] - $refW) <= $refW * 0.35) {
                $rowEls[] = $c['el'];
            }
        }
        if (count($rowEls) < 3) {
            return null;
        }

        $rowTop = $refY;
        foreach ($rowEls as $el) {
            $rowTop = min($rowTop, $this->bbox($el)['y']);
        }

        // Everything whose center is above the row top = the intro; require it to be non-empty.
        $upper = [];
        $row = $rowEls;
        $rowIds = array_map(static fn ($el) => $el['id'] ?? spl_object_id((object) $el), $rowEls);
        foreach ($elements as $el) {
            $bb = $this->bbox($el);
            $center = $bb['y'] + $bb['h'] / 2;
            $isRow = in_array($el['id'] ?? spl_object_id((object) $el), $rowIds, true);
            if (!$isRow && $center < $rowTop) {
                $upper[] = $el;
            } elseif (!$isRow && $center >= $rowTop) {
                // A title/label sitting at the row level (e.g. "Derniers événements") stays with the teaser.
                $row[] = $el;
            }
        }
        if ($upper === []) {
            return null;
        }

        return ['rowTop' => $rowTop, 'upper' => $upper, 'row' => $row];
    }

    /**
     * Builds a zone from a tagged [zone] node.
     *
     * @param array<string, mixed> $node
     */
    private function buildZone(array $node, float $pageWidth, string $slug, int $position, ?string $pageBackground = null): ParsedZone
    {
        // Semantic <section> when tagged [section] or [zone|section].
        $token = $this->mapper->extract($node['name'] ?? '');
        $type = $token['type'] ?? '';
        $variants = $token['variants'] ?? [];
        $semantic = ('section' === $type || in_array('section', $variants, true)) ? 'section' : null;

        // Honour the explicit [zone|fullwidth] / [section|fullwidth] modifier (full-bleed band).
        $fullSize = in_array('fullwidth', $variants, true);

        $children = $node['children'] ?? [];
        $taggedCols = array_values(array_filter($children, fn (array $c) => $this->tokenType($c) === 'col'));

        if ($taggedCols !== []) {
            $cols = array_map(fn (array $c) => $this->buildColFromElements(($c['children'] ?? []), $pageWidth, false), $taggedCols);
        } else {
            $cols = $this->deduceCols($children, $pageWidth);
        }

        $bb = $this->bbox($node);
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;
        $candidates = $this->backgroundCandidates([$node], $threshold);

        return new ParsedZone(
            label: $this->cleanName($node['name'] ?? 'zone'),
            cols: array_values($cols),
            deduced: false,
            fullSize: $fullSize,
            background: $this->backgroundForRange($candidates, $bb['y'], $bb['y'] + $bb['h'], $pageBackground),
            figmaTop: $bb['y'],
            figmaHeight: $bb['h'],
            screenshot: $this->screenshotName($slug, $position),
            colToRight: $this->overflowsRight($children, $pageWidth),
            semantic: $semantic,
        );
    }

    /**
     * Whether a full-width band is backed by an IMAGE hero (full-screen photo) rather than a colour.
     *
     * @param array<string, mixed> $node
     */
    private function isImageBand(array $node, float $threshold): bool
    {
        $bb = $this->bbox($node);
        $center = $bb['y'] + $bb['h'] / 2.0;
        $hasImage = false;

        foreach ($this->backgroundCandidates([$node], $threshold) as $c) {
            if ($c['y'] > $center || $center > $c['y'] + $c['h']) {
                continue;
            }
            if ($c['kind'] === 'solid' || $c['kind'] === 'gradient') {
                return false;
            }
            if ($c['kind'] === 'image') {
                $hasImage = true;
            }
        }

        return $hasImage;
    }

    /**
     * Collects full-width, fill-bearing background candidates (nodes + descendants), excluding TEXT/LINE/VECTOR.
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
     * Background colour of a vertical range (smallest covering SOLID/GRADIENT; null on image; else page bg).
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
                return null;
            }
        }

        return $pageBackground;
    }

    /**
     * Visible background fill of a node, ignoring TEXT/LINE/VECTOR.
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
    private function buildZoneFromElements(string $label, array $elements, float $pageWidth, float $top, float $height, string $slug, int $position, ?string $background = null, bool $forceColToRight = false): ParsedZone
    {
        $colToRight = $forceColToRight || $this->overflowsRight($elements, $pageWidth);
        $cols = array_values($this->deduceCols($elements, $pageWidth));

        // Règle : une zone dont un élément est CROPPÉ À DROITE (rangée de cards qui déborde,
        // flèches de carrousel) qui n'est PAS déjà un teaser/module (actu/produit/slider tagué)
        // est forcément un slider|splide. On remplace alors les colonnes déduites par un module.
        if ($colToRight && !$this->colsHaveModule($cols)) {
            $cols = [$this->buildSplideSliderCol($elements, $pageWidth)];
        }

        return new ParsedZone(
            label: $label,
            cols: $cols,
            deduced: true,
            fullSize: $this->hasFullWidth($elements, $pageWidth),
            background: $background,
            figmaTop: $top,
            figmaHeight: $height,
            screenshot: $this->screenshotName($slug, $position),
            colToRight: $colToRight,
        );
    }

    /**
     * True if any deduced col already carries a module block (slider/teaser actu/produit…).
     *
     * @param list<ParsedCol> $cols
     */
    private function colsHaveModule(array $cols): bool
    {
        foreach ($cols as $col) {
            foreach ($col->blocks as $block) {
                if ($block->kind === 'module') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Builds a single full-width col holding a deduced `slider|splide` module that
     * carries the row images as slides (cropped-right carousel that is not a teaser).
     *
     * @param list<array<string, mixed>> $elements
     */
    private function buildSplideSliderCol(array $elements, float $pageWidth): ParsedCol
    {
        // Conserver le TITRE (et tout bloc non-média) de la zone ; regrouper les images en slides.
        $base = $this->buildColFromElements($elements, $pageWidth, true, 12);
        $kept = [];
        $media = [];
        foreach ($base->blocks as $b) {
            if ($b->blockTypeSlug === 'media' && $b->media !== []) {
                array_push($media, ...$b->media);
            } else {
                $kept[] = $b;
            }
        }

        $slider = $this->mapper->toBlock('Carrousel déduit (débordant à droite)', 'slider', ['splide']);
        $slider = new ParsedBlock(
            figmaName: $slider->figmaName,
            kind: $slider->kind,
            blockTypeSlug: $slider->blockTypeSlug,
            moduleAction: $slider->moduleAction,
            moduleEntity: $slider->moduleEntity,
            note: 'Slider|splide déduit (zone croppée à droite, ni teaser actu ni teaser produit) — paddingRight pe-0',
            media: $media,
            variants: $slider->variants,
            id: $slider->id,
            moduleTemplate: $slider->moduleTemplate,
        );

        return new ParsedCol(size: 12, blocks: [...$kept, $slider], deduced: true);
    }

    /**
     * A teaser slider that bleeds past the right edge (element noticeably wider than the page)
     * → the zone is rendered right-aligned (Zone::setColToRight()).
     *
     * @param list<array<string, mixed>> $elements
     */
    private function overflowsRight(array $elements, float $pageWidth): bool
    {
        foreach ($elements as $el) {
            if ($this->bbox($el)['w'] > $pageWidth * 1.05) {
                return true;
            }
        }

        return false;
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

        // Bootstrap splits a row's 12 units AMONG its columns (gutters not counted) :
        // width / sum(column widths) × 12 → 6/6 for two equal columns, proportional otherwise.
        $totalWidth = 0.0;
        foreach ($clusters as $cluster) {
            $totalWidth += $cluster['x2'] - $cluster['x1'];
        }

        $sizes = [];
        foreach ($clusters as $i => $cluster) {
            $width = $cluster['x2'] - $cluster['x1'];
            $sizes[$i] = $totalWidth > 0.0 ? max(1, min(12, (int) round($width / $totalWidth * 12))) : 12;
        }

        // Correct rounding drift so a single row of columns sums to exactly 12.
        $drift = 12 - array_sum($sizes);
        if ($drift !== 0 && count($sizes) > 1) {
            $widest = array_keys($sizes, max($sizes), true)[0];
            $sizes[$widest] = max(1, min(12, $sizes[$widest] + $drift));
        }

        $cols = $fullWidthCols;
        foreach ($clusters as $i => $cluster) {
            $cols[] = $this->buildColFromElements($cluster['els'], $pageWidth, true, $sizes[$i]);
        }

        return $cols;
    }

    /**
     * Builds a column: maps tagged descendants to blocks, auto-detects CTA/image/text, counts untagged.
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
                continue;
            }
            if (!$this->isSignificant($el) || $this->isSlide($el)) {
                continue;
            }

            // A layer named like a button/CTA → link block (BlockType `link`).
            if (preg_match('/\b(cta|bouton|button|btn)\b/i', (string) ($el['name'] ?? ''))) {
                $blocks[] = new ParsedBlock($this->cleanName($el['name'] ?? 'cta'), 'atom', blockTypeSlug: 'link', note: 'CTA déduit (nom de calque)', text: $this->firstText($el));
                continue;
            }

            // Any remaining (untagged) image maps to an [image] block (BlockType `media`).
            $media = $this->collectImages($el);
            if ($media !== []) {
                foreach ($media as $m) {
                    $blocks[] = new ParsedBlock($this->cleanName($el['name'] ?? 'image'), 'atom', blockTypeSlug: 'media', media: [$m]);
                }
                continue;
            }

            // Untagged text: a title (h1…h6 by font-size rank) or a plain text block.
            $textBlock = $this->textBlock($el);
            if ($textBlock !== null) {
                $blocks[] = $textBlock;
                continue;
            }

            ++$untagged;
        }

        return new ParsedCol(
            size: $size ?? 12,
            blocks: $blocks,
            deduced: $deduced,
            untaggedCount: $untagged,
        );
    }

    /**
     * Recursively collects mapped blocks from tagged descendants. Stops at structural/excluded tags.
     *
     * @param array<string, mixed> $node
     *
     * @return list<ParsedBlock>
     */
    private function collectTaggedBlocks(array $node): array
    {
        $token = $this->mapper->extract($node['name'] ?? '');

        // Slides are not standalone blocks: they are collected and attached to their slider.
        if ($token !== null && $token['type'] === 'slide') {
            return [];
        }

        if ($token !== null && !$this->mapper->isStructural($token['type']) && !$this->mapper->isExcluded($token['type'])) {
            $block = $this->mapper->toBlock($this->cleanName($node['name'] ?? '?'), $token['type'], $token['variants']);

            // Media: slides linked by id (separate nodes) take precedence; else images within the node.
            $media = ($block->id !== null && isset($this->slidesBySlider[$block->id]))
                ? $this->slidesForId($block->id)
                : $this->collectImages($node);

            if ($media !== []) {
                $block = new ParsedBlock(
                    figmaName: $block->figmaName,
                    kind: $block->kind,
                    blockTypeSlug: $block->blockTypeSlug,
                    moduleAction: $block->moduleAction,
                    moduleEntity: $block->moduleEntity,
                    note: $block->note,
                    media: $media,
                    variants: $block->variants,
                    id: $block->id,
                    moduleTemplate: $block->moduleTemplate,
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
     * Walks the content subtree and groups slides (`[slide-N|sliderId]`) by their slider id.
     *
     * @param list<array<string, mixed>> $content
     *
     * @return array<string, list<array{position: int, figmaNodeId: string, image: string, imageRef: string, width: int, format: string}>>
     */
    private function collectSlides(array $content): array
    {
        $slides = [];
        $walk = function (array $node) use (&$walk, &$slides): void {
            $token = $this->mapper->extract($node['name'] ?? '');
            if ($token !== null && $token['type'] === 'slide') {
                $sliderId = $this->sliderIdOf($token['variants']);
                $image = $this->collectImages($node);
                if ($sliderId !== null && $image !== []) {
                    $first = $image[0];
                    $pos = $this->mapper->extractPosition($token['variants']);
                    $ext = $first['format'] ?? 'jpg';
                    $slides[$sliderId][] = [
                        'position' => $pos,
                        'figmaNodeId' => $first['figmaNodeId'],
                        'image' => 'slide-'.$this->slugify($sliderId).'-'.$pos.'.'.$ext,
                        'imageRef' => $first['imageRef'],
                        'width' => $first['width'],
                        'format' => $ext,
                    ];
                }
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child);
            }
        };
        foreach ($content as $node) {
            $walk($node);
        }

        return $slides;
    }

    /**
     * The parent slider id carried by a slide's variants (first bare token, i.e. not a modifier).
     *
     * @param list<string> $variants
     */
    private function sliderIdOf(array $variants): ?string
    {
        foreach ($variants as $v) {
            if (!str_contains($v, ':')) {
                return $v;
            }
        }

        return $this->mapper->extractId($variants);
    }

    /**
     * Slides of a slider id, ordered by position.
     *
     * @return list<array{figmaNodeId: string, image: string, imageRef: string, width: int, format: string}>
     */
    private function slidesForId(string $id): array
    {
        $slides = $this->slidesBySlider[$id] ?? [];
        usort($slides, static fn (array $a, array $b) => $a['position'] <=> $b['position']);

        return array_map(static fn (array $s) => [
            'figmaNodeId' => $s['figmaNodeId'],
            'image' => $s['image'],
            'imageRef' => $s['imageRef'],
            'width' => $s['width'],
            'format' => $s['format'] ?? 'jpg',
        ], $slides);
    }

    /**
     * Collects image-bearing nodes (IMAGE fills) within a node subtree, in document order.
     *
     * @param array<string, mixed> $node
     *
     * @return list<array{figmaNodeId: string, image: string, imageRef: string, width: int, format: string}>
     */
    private function collectImages(array $node): array
    {
        $media = [];
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['type'] ?? '') === 'IMAGE') {
                $id = (string) ($node['id'] ?? '');
                $format = $this->mediaFormat($node);
                $media[] = [
                    'figmaNodeId' => $id,
                    'image' => $this->mediaName($node, $id, $format),
                    'imageRef' => (string) ($fill['imageRef'] ?? ''),
                    'width' => (int) round($this->bbox($node)['w']),
                    'format' => $format,
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
     * Semantic file name for a media: slug of the layer name when meaningful, else `media-<id>`.
     *
     * @param array<string, mixed> $node
     */
    private function mediaName(array $node, string $id, string $format): string
    {
        $name = trim((string) ($node['name'] ?? ''));
        $generic = $name === ''
            || preg_match('/^img[\s_-]?\d/i', $name) === 1
            || preg_match('/^(image|rectangle|group|component|frame|vector|ellipse|union|subtract|mask|calque)\b/i', $name) === 1;
        $slug = $generic ? '' : $this->slugify($name);
        if ($slug === 'page') {
            $slug = '';
        }

        $base = $slug !== '' ? $slug : 'media-'.str_replace(':', '-', $id);

        return $base.'.'.$format;
    }

    /**
     * Export format for a media node (the CMS later derives WebP itself):
     * logo → SVG ; opaque photo (RECTANGLE w/ image fill) → JPG ; else (transparency-prone) → PNG.
     *
     * @param array<string, mixed> $node
     */
    private function mediaFormat(array $node): string
    {
        $name = strtolower((string) ($node['name'] ?? ''));
        if (str_contains($name, 'logo')) {
            return 'svg';
        }

        return ($node['type'] ?? '') === 'RECTANGLE' ? 'jpg' : 'png';
    }

    /**
     * First text content found in a node subtree (e.g. a CTA label nested in an instance).
     *
     * @param array<string, mixed> $node
     */
    private function firstText(array $node): ?string
    {
        if (($node['type'] ?? '') === 'TEXT' && isset($node['characters'])) {
            $text = trim((string) $node['characters']);
            if ($text !== '') {
                return $this->normalizeText($text);
            }
        }
        foreach ($node['children'] ?? [] as $child) {
            $found = $this->firstText($child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Normalizes extracted copy: all-caps display text → sentence case; always a leading capital.
     */
    private function normalizeText(?string $text): ?string
    {
        if ($text === null || $text === '') {
            return $text;
        }

        $rest = mb_substr($text, 1, null, 'UTF-8');
        if (mb_strtoupper($text, 'UTF-8') === $text) {
            $rest = mb_strtolower($rest, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($text, 0, 1, 'UTF-8'), 'UTF-8').$rest;
    }

    /**
     * @param array<string, mixed> $node
     */
    private function isSlide(array $node): bool
    {
        return ($this->mapper->extract($node['name'] ?? '')['type'] ?? null) === 'slide';
    }

    /**
     * Classifies an untagged TEXT node: a heading (`title`, level h1…h6) or a plain `text` block.
     *
     * @param array<string, mixed> $el
     */
    private function textBlock(array $el): ?ParsedBlock
    {
        if (($el['type'] ?? '') !== 'TEXT') {
            return null;
        }

        $name = $this->cleanName($el['name'] ?? 'texte');
        $text = isset($el['characters']) ? $this->normalizeText(trim((string) $el['characters'])) : null;
        $fontSize = isset($el['style']['fontSize']) && is_numeric($el['style']['fontSize'])
            ? (string) round((float) $el['style']['fontSize'], 1)
            : null;

        if ($fontSize !== null && isset($this->fontScale['levels'][$fontSize])) {
            $level = $this->fontScale['levels'][$fontSize];

            return new ParsedBlock($name, 'atom', blockTypeSlug: 'title', note: sprintf('titre h%d déduit (%s px)', $level, $fontSize), variants: ['h'.$level], text: $text);
        }

        return new ParsedBlock($name, 'atom', blockTypeSlug: 'text', note: 'texte déduit', text: $text);
    }

    /**
     * Builds the page font scale: body size (most frequent) + larger sizes ranked into heading levels.
     *
     * @param list<array<string, mixed>> $content
     *
     * @return array{body: float, levels: array<string, int>}
     */
    private function computeFontScale(array $content): array
    {
        $sizes = [];
        $walk = function (array $node) use (&$walk, &$sizes): void {
            if (($node['type'] ?? '') === 'TEXT' && isset($node['style']['fontSize']) && is_numeric($node['style']['fontSize'])) {
                $sizes[] = (string) round((float) $node['style']['fontSize'], 1);
            }
            foreach ($node['children'] ?? [] as $child) {
                $walk($child);
            }
        };
        foreach ($content as $node) {
            $walk($node);
        }

        if ($sizes === []) {
            return ['body' => 0.0, 'levels' => []];
        }

        $freq = array_count_values($sizes);
        arsort($freq);
        $body = (float) array_key_first($freq);

        $distinct = [];
        foreach (array_unique($sizes) as $s) {
            if ((float) $s > $body) {
                $distinct[] = (float) $s;
            }
        }
        rsort($distinct);

        $levels = [];
        foreach ($distinct as $i => $size) {
            $levels[(string) round($size, 1)] = min(6, $i + 1);
        }

        return ['body' => $body, 'levels' => $levels];
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
     * Collects zone-tagged nodes ([zone]/[section]) from a node list, descending through
     * UNTAGGED wrapper containers (groups/frames merely used to organise the canvas) so a
     * `[section]` nested one or more levels deep is still discovered. A tagged zone is taken
     * as-is and NOT descended into (its inner sections are its own content). Order is preserved.
     *
     * @param list<array<string, mixed>> $nodes
     *
     * @return list<array<string, mixed>>
     */
    private function collectZoneNodes(array $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            $type = $this->tokenType($node);
            if ($type !== null && $this->mapper->isZoneTag($type)) {
                $out[] = $node;
                continue;
            }

            $children = $node['children'] ?? [];
            if ($children !== [] && $this->containsZoneTag($children)) {
                foreach ($this->collectZoneNodes($children) as $nested) {
                    $out[] = $nested;
                }
            }
        }

        return $out;
    }

    /**
     * Whether any node in the (sub)tree carries a [zone]/[section] tag — used to decide
     * whether an untagged wrapper is worth descending into.
     *
     * @param list<array<string, mixed>> $nodes
     */
    private function containsZoneTag(array $nodes): bool
    {
        foreach ($nodes as $node) {
            $type = $this->tokenType($node);
            if ($type !== null && $this->mapper->isZoneTag($type)) {
                return true;
            }
            if ($this->containsZoneTag($node['children'] ?? [])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sort position of a zone/section: the first bare numeric variant (`[section|2]` → 2),
     * else the document index (so untagged-position sections keep their natural order).
     *
     * @param array<string, mixed> $node
     */
    private function zonePosition(array $node, int $documentIndex): int
    {
        $variants = $this->mapper->extract($node['name'] ?? '')['variants'] ?? [];
        foreach ($variants as $v) {
            if (preg_match('/^\d+$/', $v) === 1) {
                return (int) $v;
            }
        }

        return $documentIndex;
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
        $name = preg_replace('/\[[^\]]*\]/', '', $name) ?? $name;
        // Transliterate common accents so « décorées » → « decorees », not « d-cor-es ».
        $name = strtr(mb_strtolower($name, 'UTF-8'), [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a',
            'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o', 'õ' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);

        return trim(preg_replace('/[^a-z0-9]+/', '-', $name) ?? $name, '-') ?: 'page';
    }
}
