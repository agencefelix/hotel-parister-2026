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
     * Page font scale: body/paragraph size + larger sizes ranked into heading levels + the
     * dominant (body) font family. Reset on each parse().
     *
     * @var array{body: float, levels: array<string, int>, bodyFamily: ?string}
     */
    private array $fontScale = ['body' => 0.0, 'levels' => [], 'bodyFamily' => null];

    /**
     * Named text styles of the file (styleId => human name, e.g. "H2", "Sous-titre H3", "p/16"),
     * the authoritative source for classifying a TEXT as heading vs body. Reset on each parse().
     *
     * @var array<string, string>
     */
    private array $namedStyles = [];

    /** Largeur de page Figma (px) — référence DESIGN (les créateurs maquettent en réso laptop, ~1440).
     *  Détectée sur parse() (jamais supposée). Base de TOUS les calculs de ratios (ex. slides/vue). */
    private float $pageWidth = 0.0;

    /**
     * Largeur de référence par breakpoint, en FRACTION de la largeur de page Figma.
     *
     * Le rendu web vise ~1920 dans un container Bootstrap (container / -fluid / -fluid-right), MAIS les
     * slides scalent dans le container → le nombre d'items visibles est un RATIO du design, indépendant
     * du viewport réel. On le calcule donc sur la largeur de page Figma (laptop) et non sur 1920. Les
     * fractions ≈ largeurs de container Bootstrap relatives au xxl (desktop / xl-lg / md / sm).
     */
    private const array VIEWPORT_FRACTIONS = ['desktop' => 1.0, 'miniPC' => 0.84, 'tablet' => 0.53, 'mobile' => 0.26];

    public function parse(string $fileKey, string $nodeId): ParsedPage
    {
        $nodes = $this->figma->getFileNodes($fileKey, [$nodeId]);
        $doc = $nodes['nodes'][$nodeId]['document'] ?? null;

        if (!is_array($doc)) {
            throw new FigmaApiException(sprintf('Nœud "%s" introuvable dans le fichier Figma.', $nodeId));
        }

        // Named text styles (H1…Hn, Sous-titre, p/16…) — authoritative for heading vs body.
        $this->namedStyles = $this->indexStyles($nodes['nodes'][$nodeId]['styles'] ?? []);

        $warnings = [];
        $token = $this->mapper->extract($doc['name'] ?? '');

        if ($token === null || $token['type'] !== 'page') {
            $warnings[] = sprintf('Le nœud racine "%s" n\'est pas taggé [page] — parsing tenté quand même.', $doc['name'] ?? '?');
        }

        $slug = $token['variants'][0] ?? $this->slugify($doc['name'] ?? 'page');
        $pageBox = $this->bbox($doc);
        $pageWidth = $pageBox['w'];
        $this->pageWidth = $pageWidth;
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
            if (!$this->isVisible($child)) {
                continue;
            }
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

        $children = $node['children'] ?? [];
        $taggedCols = array_values(array_filter($children, fn (array $c) => $this->tokenType($c) === 'col'));

        // Géométrie de bande (pour déduire l'alignement vertical du contenu des colonnes).
        $bb = $this->bbox($node);

        if ($taggedCols !== []) {
            $cols = array_map(fn (array $c) => $this->buildColFromElements(($c['children'] ?? []), $pageWidth, false, null, $bb['y'], $bb['h']), $taggedCols);
        } else {
            $cols = $this->deduceCols($children, $pageWidth, $bb['y'], $bb['h']);
        }

        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;
        $candidates = $this->backgroundCandidates([$node], $threshold);

        // Full-bleed (bord à bord), 3 voies :
        //  1. tag explicite [zone|fullwidth] (autorité : force/garantit le bord à bord) ;
        //  2. la bande a un fond PROPRE pleine largeur (couleur ≠ page, dégradé, ou image hero) ;
        //  3. bande cinématique : un module slider/média OCCUPE la bande et AUCUN titre/intro/texte
        //     n'est posé sur son fond (cf. isCinematicMediaBand) — un carrousel bord à bord sans
        //     fond solide propre (l'image vit dans les slides) serait sinon rendu boxé.
        $fullSize = in_array('fullwidth', $variants, true)
            || $this->hasOwnFullWidthBackground($candidates, $bb['y'], $bb['y'] + $bb['h'], $pageBackground)
            || $this->isCinematicMediaBand($cols);

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
     * Whether the band has its OWN full-width background (≠ page) → full-bleed band (bord à bord) :
     * un fond plein largeur couleur distincte du fond de page, un dégradé, ou une image hero.
     *
     * @param list<array{y: float, h: float, kind: string, value: ?string}> $candidates
     */
    private function hasOwnFullWidthBackground(array $candidates, float $top, float $bottom, ?string $pageBackground): bool
    {
        $center = ($top + $bottom) / 2.0;
        foreach ($candidates as $c) {
            if ($c['y'] > $center || $center > $c['y'] + $c['h']) {
                continue;
            }
            if ('image' === $c['kind'] || 'gradient' === $c['kind']) {
                return true;
            }
            if ('solid' === $c['kind'] && $c['value'] !== null && $c['value'] !== $pageBackground) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bande cinématique full-bleed : la bande est OCCUPÉE par un module slider/média (carrousel,
     * galerie, image) et ne porte AUCUN titre/intro/texte sur son fond — seuls le module et ses
     * contrôles de navigation (liens/flèches) sont présents.
     *
     * Pourquoi ce critère : la pure géométrie ne suffit pas à distinguer un carrousel cinématique
     * bord à bord d'un teaser de contenu (mêmes slides, même débordement). Le signal fiable est
     * sémantique : un titre de section posé sur le fond marque une bande de CONTENU (boxée, sur le
     * fond de page) ; une bande qui n'est QUE le module est une bande MÉDIA (bord à bord). Le tag
     * [zone|fullwidth] reste l'autorité explicite quand cette déduction ne convient pas.
     *
     * @param list<ParsedCol> $cols
     */
    private function isCinematicMediaBand(array $cols): bool
    {
        $hasMedia = false;
        foreach ($cols as $col) {
            foreach ($col->blocks as $b) {
                // Un titre/intro/texte autonome sur le fond = bande de contenu → jamais cinématique.
                if (in_array($b->blockTypeSlug, ['title', 'title-header', 'text', 'blockquote'], true)) {
                    return false;
                }
                if ('module' === $b->kind || 'media' === $b->blockTypeSlug) {
                    $hasMedia = true;
                }
            }
        }

        return $hasMedia;
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
        $cols = array_values($this->deduceCols($elements, $pageWidth, $top, $height));

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
    private function deduceCols(array $elements, float $pageWidth, ?float $bandTop = null, ?float $bandHeight = null): array
    {
        $threshold = $pageWidth * self::FULL_WIDTH_RATIO;

        $fullWidthCols = [];
        $contentEls = [];
        foreach ($elements as $el) {
            if ($this->bbox($el)['w'] >= $threshold) {
                if ($this->collectTaggedBlocks($el) !== []) {
                    $fullWidthCols[] = $this->buildColFromElements([$el], $pageWidth, true, 12, $bandTop, $bandHeight);
                }
                continue;
            }
            $contentEls[] = $el;
        }

        if ($contentEls === []) {
            return $fullWidthCols !== [] ? $fullWidthCols : [$this->buildColFromElements($elements, $pageWidth, true, null, $bandTop, $bandHeight)];
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
            $cols[] = $this->buildColFromElements($cluster['els'], $pageWidth, true, $sizes[$i], $bandTop, $bandHeight);
        }

        return $cols;
    }

    /**
     * Builds a column: maps tagged descendants to blocks, auto-detects CTA/image/text, counts untagged.
     *
     * @param list<array<string, mixed>> $elements
     */
    /**
     * Émet les blocs d'un élément de contenu (non taggé) en DESCENDANT dans les wrappers (GROUP/FRAME/
     * INSTANCE) — sinon un titre/intro emballé dans un groupe, ou voisin d'une image, est perdu.
     * Ordre : modules taggés > CTA (par nom) > TEXT (titre/texte) > image propre > descente récursive.
     *
     * @param array<string, mixed> $el
     * @param list<ParsedBlock>    $blocks
     */
    private function emitBlocks(array $el, array &$blocks, int &$untagged): void
    {
        // Un nœud qui porte SON PROPRE tag de contenu/module = un bloc, collecté en entier et NON
        // traversé (ses textes internes sont le contenu du bloc). On ne court-circuite que sur le tag
        // du nœud lui-même : un wrapper NON taggé qui ne fait que CONTENIR un module taggé doit quand
        // même émettre ses voisins non taggés (ex. un titre de hero à côté d'un [slider]) au lieu
        // d'être réduit au seul module — sinon ce texte est perdu.
        $token = $this->mapper->extract($el['name'] ?? '');
        if ($token !== null
            && 'slide' !== $token['type']
            && !$this->mapper->isStructural($token['type'])
            && !$this->mapper->isExcluded($token['type'])) {
            array_push($blocks, ...$this->collectTaggedBlocks($el));

            return;
        }
        if (!$this->isSignificant($el) || $this->isSlide($el)) {
            return;
        }

        // CTA par nom de calque → link (on NE descend PAS : les textes internes sont le libellé).
        if (preg_match('/\b(cta|bouton|button|btn)\b/i', (string) ($el['name'] ?? ''))) {
            $blocks[] = new ParsedBlock($this->cleanName($el['name'] ?? 'cta'), 'atom', blockTypeSlug: 'link', note: 'CTA déduit (nom de calque)', text: $this->firstText($el));

            return;
        }

        // Nœud TEXT → titre (h1…h6 par rang de taille) ou texte.
        if (($el['type'] ?? '') === 'TEXT') {
            $tb = $this->textBlock($el);
            if ($tb !== null) {
                $blocks[] = $tb;
            }

            return;
        }

        // Image PROPRE de ce nœud (fill IMAGE direct) → media.
        $img = $this->ownImageEntry($el);
        if ($img !== null) {
            $blocks[] = new ParsedBlock($this->cleanName($el['name'] ?? 'image'), 'atom', blockTypeSlug: 'media', media: [$img]);

            return;
        }

        // Wrapper (GROUP/FRAME/INSTANCE) → DESCENDRE pour récupérer titre/intro/image/CTA imbriqués.
        if (($el['children'] ?? []) !== []) {
            foreach ($el['children'] as $child) {
                $this->emitBlocks($child, $blocks, $untagged);
            }

            return;
        }

        ++$untagged;
    }

    /**
     * Entrée média de l'image PROPRE d'un nœud (premier fill IMAGE direct, non récursif), ou null.
     *
     * @param array<string, mixed> $node
     *
     * @return array{figmaNodeId: string, image: string, imageRef: string, width: int, format: string}|null
     */
    private function ownImageEntry(array $node): ?array
    {
        foreach ($node['fills'] ?? [] as $fill) {
            if (($fill['type'] ?? '') === 'IMAGE') {
                $id = (string) ($node['id'] ?? '');
                $format = $this->mediaFormat($node);

                return ['figmaNodeId' => $id, 'image' => $this->mediaName($node, $id, $format), 'imageRef' => (string) ($fill['imageRef'] ?? ''), 'width' => (int) round($this->bbox($node)['w']), 'format' => $format];
            }
        }

        return null;
    }

    private function buildColFromElements(array $elements, float $pageWidth, bool $deduced, ?int $size = null, ?float $bandTop = null, ?float $bandHeight = null): ParsedCol
    {
        $blocks = [];
        $untagged = 0;

        foreach ($elements as $el) {
            $this->emitBlocks($el, $blocks, $untagged);
        }

        [$verticalAlign, $endAlign] = $this->colVerticalAlign($elements, $bandTop, $bandHeight);

        return new ParsedCol(
            size: $size ?? 12,
            blocks: $blocks,
            deduced: $deduced,
            untaggedCount: $untagged,
            verticalAlign: $verticalAlign,
            endAlign: $endAlign,
        );
    }

    /**
     * Détecte l'alignement vertical du contenu d'une colonne DANS sa bande, par géométrie : un
     * contenu nettement plus court que la bande et qui « flotte » (espaces haut ET bas) est centré
     * verticalement ; ancré en bas = fin. Sert à poser Col::setVerticalAlign()/setEndAlign() —
     * l'intention vit sur l'ENTITÉ Col, JAMAIS en CSS sur la colonne (cf. mapping-blocktypes.md).
     *
     * Les `[section]` étant souvent de simples GROUP (pas d'auto-layout → pas de counterAxisAlignItems),
     * la déduction est géométrique. Garde-fous : on n'aligne que si le contenu remplit < 85 % de la
     * hauteur de bande (sinon il la remplit, rien à aligner).
     *
     * @param list<array<string, mixed>> $elements
     *
     * @return array{0: bool, 1: bool} [verticalAlign, endAlign]
     */
    private function colVerticalAlign(array $elements, ?float $bandTop, ?float $bandHeight): array
    {
        if ($bandTop === null || $bandHeight === null || $bandHeight <= 0.0 || $elements === []) {
            return [false, false];
        }

        $top = null;
        $bottom = null;
        foreach ($elements as $el) {
            if (!$this->isVisible($el)) {
                continue;
            }
            $bb = $this->bbox($el);
            if ($bb['h'] <= 0.0) {
                continue;
            }
            $top = $top === null ? $bb['y'] : min($top, $bb['y']);
            $bottom = $bottom === null ? $bb['y'] + $bb['h'] : max($bottom, $bb['y'] + $bb['h']);
        }
        if ($top === null) {
            return [false, false];
        }

        // Contenu qui remplit (presque) la bande → pas d'alignement à déduire (image/slider plein).
        if ($bottom - $top > $bandHeight * 0.85) {
            return [false, false];
        }

        $topGap = $top - $bandTop;
        $botGap = ($bandTop + $bandHeight) - $bottom;
        $slack = $bandHeight * 0.06;

        // Espaces haut ET bas, sensiblement équilibrés → centré verticalement.
        if ($topGap >= $slack && $botGap >= $slack && abs($topGap - $botGap) <= $bandHeight * 0.20) {
            return [true, false];
        }
        // Collé en bas (gros espace en haut, ~rien en bas) → aligné en fin.
        if ($topGap >= $bandHeight * 0.15 && $botGap <= $slack) {
            return [false, true];
        }

        return [false, false];
    }

    /**
     * Déduit le nombre d'items visibles par vue d'un carrousel, par breakpoint, à partir du PAS de
     * slide (largeur de piste ÷ nb de slides) confronté à la largeur de page FIGMA (design laptop) ×
     * fraction du breakpoint. Le rendu web vise ~1920 dans un container Bootstrap, mais les slides
     * scalent → le compte est un RATIO du design, donc calculé sur la largeur de page Figma (détectée).
     * Empêche les slides de déborder (mauvais `data-items`). Borné à [1, nb de slides].
     *
     * Ex. page Figma 1440, piste 2050 / 4 slides = pas 512 → desktop 1440/512→2, miniPC 1210/512→2,
     * tablet 763/512→1, mobile 374/512→1.
     *
     * @return array<string, int> {itemsPerSlide, itemsPerSlideMiniPC, itemsPerSlideTablet, itemsPerSlideMobile}
     */
    private function slidesPerView(float $trackWidth, int $count): array
    {
        if ($count <= 0 || $trackWidth <= 0.0 || $this->pageWidth <= 0.0) {
            return [];
        }
        $pitch = $trackWidth / $count;
        // Base = largeur de page Figma (design laptop) × fraction du breakpoint. Borné à [1, nb slides].
        $per = fn (float $frac): int => max(1, min($count, (int) floor(($this->pageWidth * $frac) / $pitch)));

        return [
            'itemsPerSlide' => $per(self::VIEWPORT_FRACTIONS['desktop']),
            'itemsPerSlideMiniPC' => $per(self::VIEWPORT_FRACTIONS['miniPC']),
            'itemsPerSlideTablet' => $per(self::VIEWPORT_FRACTIONS['tablet']),
            'itemsPerSlideMobile' => $per(self::VIEWPORT_FRACTIONS['mobile']),
        ];
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

            // Text-bearing atoms (alert/title/text/blockquote/link…) carry the layer's copy.
            // Sans ça un [alert]/[title] enveloppant un TEXT produisait un bloc VIDE (copie perdue).
            $block = $this->withTextContent($block, $node);

            // Media: slides linked by id (separate nodes) take precedence ; else, pour un module à
            // cards (slider/teaser) on collecte image + texte PAR card ; sinon images à plat.
            $isCardModule = $block->moduleAction === 'slider-view' || str_ends_with((string) $block->moduleAction, '-teaser');
            $media = ($block->id !== null && isset($this->slidesBySlider[$block->id]))
                ? $this->slidesForId($block->id)
                : ($isCardModule ? $this->collectCards($node) : $this->collectImages($node));

            if ($media !== []) {
                // Items visibles par vue (carrousel) déduits du PAS de slide vs largeur d'écran :
                // évite les slides qui débordent / le mauvais compte data-items.
                $itemsPerView = $isCardModule ? $this->slidesPerView($this->bbox($node)['w'], count($media)) : [];
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
                    itemsPerView: $itemsPerView,
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

    /** Atom slugs whose content IS text: they must carry the layer's copy (else the block is empty). */
    private const array TEXT_BEARING = ['title', 'title-header', 'text', 'blockquote', 'alert', 'link', 'counter'];

    /**
     * Populates a text-bearing atom block with the layer's copy + the primary text node's style.
     * A `link` keeps only its first text (the label) ; other atoms take the whole subtree's text.
     * Leaves modules and non-text atoms untouched.
     *
     * @param array<string, mixed> $node
     */
    private function withTextContent(ParsedBlock $block, array $node): ParsedBlock
    {
        if ('atom' !== $block->kind
            || $block->blockTypeSlug === null
            || !in_array($block->blockTypeSlug, self::TEXT_BEARING, true)
            || ($block->text !== null && $block->text !== '')) {
            return $block;
        }

        $text = 'link' === $block->blockTypeSlug
            ? $this->firstText($node)
            : $this->normalizeText($this->allText($node));
        if ($text === null || $text === '') {
            return $block;
        }

        $style = $block->style;
        if ($style === []) {
            $primary = $this->firstTextNode($node);
            if ($primary !== null) {
                $style = $this->textStyle($primary);
            }
        }

        return new ParsedBlock(
            figmaName: $block->figmaName,
            kind: $block->kind,
            blockTypeSlug: $block->blockTypeSlug,
            moduleAction: $block->moduleAction,
            moduleEntity: $block->moduleEntity,
            note: $block->note,
            media: $block->media,
            variants: $block->variants,
            id: $block->id,
            moduleTemplate: $block->moduleTemplate,
            text: $text,
            style: $style,
        );
    }

    /**
     * First TEXT node found in a subtree (depth-first), to read its style; null if none.
     *
     * @param array<string, mixed> $node
     *
     * @return array<string, mixed>|null
     */
    private function firstTextNode(array $node): ?array
    {
        if (($node['type'] ?? '') === 'TEXT') {
            return $node;
        }
        foreach ($node['children'] ?? [] as $child) {
            if (!$this->isVisible($child)) {
                continue;
            }
            $found = $this->firstTextNode($child);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
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
                    $text = $this->cardText($node);
                    $slides[$sliderId][] = array_filter([
                        'position' => $pos,
                        'figmaNodeId' => $first['figmaNodeId'],
                        'image' => 'slide-'.$this->slugify($sliderId).'-'.$pos.'.'.$ext,
                        'imageRef' => $first['imageRef'],
                        'width' => $first['width'],
                        'format' => $ext,
                        'title' => $text['title'],
                        'subtitle' => $text['subtitle'],
                        'introduction' => $text['introduction'],
                        'targetLabel' => $text['targetLabel'],
                        'style' => $text['style'] !== [] ? $text['style'] : null,
                    ], static fn ($v) => $v !== null);
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

        return array_map(static fn (array $s) => array_filter([
            'figmaNodeId' => $s['figmaNodeId'],
            'image' => $s['image'],
            'imageRef' => $s['imageRef'],
            'width' => $s['width'],
            'format' => $s['format'] ?? 'jpg',
            'title' => $s['title'] ?? null,
            'subtitle' => $s['subtitle'] ?? null,
            'introduction' => $s['introduction'] ?? null,
            'targetLabel' => $s['targetLabel'] ?? null,
            'style' => ($s['style'] ?? []) !== [] ? $s['style'] : null,
        ], static fn ($v) => $v !== null), $slides);
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
            if (!$this->isVisible($child)) {
                continue; // image d'une variante masquée → ne pas l'exporter
            }
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
            if (!$this->isVisible($child)) {
                continue;
            }
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
        // Style relevé du node Figma : porté sur le bloc (et donc dans le JSON) pour être appliqué
        // fidèlement — une taille hors-échelle n'est plus perdue, elle reste lisible sur le bloc.
        $style = $this->textStyle($el);

        [$slug, $variants, $note] = $this->classifyText($el);

        return new ParsedBlock($name, 'atom', blockTypeSlug: $slug, note: $note, variants: $variants, text: $text, style: $style);
    }

    /**
     * Classifies a TEXT node as a heading (`title` h1…h6), a subtitle (`title-header`), a quote
     * (`blockquote`) or plain `text`, combining three signals in order of authority :
     *  1. the Figma NAMED text style (H2 / Sous-titre H3 / Citation / p-16) — authoritative ;
     *  2. a DISPLAY font (≠ the page's dominant body family, e.g. a script font) → heading ;
     *  3. the size relative to the body/paragraph size (above body = heading, ranked into levels).
     *
     * @param array<string, mixed> $el
     *
     * @return array{0: string, 1: list<string>, 2: ?string} [BlockType slug, variants, note]
     */
    private function classifyText(array $el): array
    {
        $named = $this->namedTextStyle($el);
        if ($named !== null) {
            if (preg_match('/sous[\s-]?titre|subtitle|surtitre|eyebrow/i', $named) === 1) {
                return ['title-header', [], sprintf('sous-titre (style nommé « %s »)', $named)];
            }
            if (preg_match('/cita[ti]?on|quote|verbatim/i', $named) === 1) {
                return ['blockquote', [], sprintf('citation (style nommé « %s »)', $named)];
            }
            if (preg_match('/h\s*([1-6])|titre|heading|display/i', $named, $m) === 1) {
                $level = isset($m[1]) && $m[1] !== '' ? (int) $m[1] : 2;

                return ['title', ['h'.$level], sprintf('titre h%d (style nommé « %s »)', $level, $named)];
            }
            if (preg_match('~^p[\s/._-]|paragraph|body|texte|corps|legal|caption~i', $named) === 1) {
                return ['text', [], sprintf('texte (style nommé « %s »)', $named)];
            }
        }

        $fontSize = isset($el['style']['fontSize']) && is_numeric($el['style']['fontSize'])
            ? (string) round((float) $el['style']['fontSize'], 1)
            : null;

        // Police d'affichage (≠ police de corps dominante) = titre, quelle que soit la taille.
        $family = isset($el['style']['fontFamily']) ? (string) $el['style']['fontFamily'] : null;
        $bodyFamily = $this->fontScale['bodyFamily'] ?? null;
        if ($family !== null && $bodyFamily !== null && $family !== $bodyFamily) {
            $level = $fontSize !== null ? ($this->fontScale['levels'][$fontSize] ?? 2) : 2;

            return ['title', ['h'.$level], sprintf('titre h%d déduit (police d\'affichage « %s »)', $level, $family)];
        }

        // Taille au-dessus du corps (paragraphe) = titre ; sinon texte.
        if ($fontSize !== null && isset($this->fontScale['levels'][$fontSize])) {
            $level = $this->fontScale['levels'][$fontSize];

            return ['title', ['h'.$level], sprintf('titre h%d déduit (%s px)', $level, $fontSize)];
        }

        return ['text', [], 'texte déduit'];
    }

    /**
     * Human name of a TEXT node's bound named style (e.g. "Sous-titre H3"), or null when the
     * node carries no named text style.
     *
     * @param array<string, mixed> $el
     */
    private function namedTextStyle(array $el): ?string
    {
        $id = $el['styles']['text'] ?? null;
        if (!is_string($id) || $id === '') {
            return null;
        }

        return $this->namedStyles[$id] ?? null;
    }

    /**
     * Indexes the file's style dictionary (from the /nodes response) as styleId => human name.
     *
     * @param array<string, mixed> $styles
     *
     * @return array<string, string>
     */
    private function indexStyles(array $styles): array
    {
        $out = [];
        foreach ($styles as $id => $meta) {
            if (is_array($meta) && isset($meta['name']) && is_string($meta['name'])) {
                $out[(string) $id] = $meta['name'];
            }
        }

        return $out;
    }

    /**
     * Relève le style texte d'un node Figma TEXT (taille/poids/famille/tracking/interligne/casse/
     * couleur) pour le porter sur le bloc — l'intégrateur dispose ainsi des valeurs exactes inline,
     * et le GATE styles (`tooling/verify-styles.mjs`) peut les confronter au rendu.
     *
     * @param array<string, mixed> $el
     *
     * @return array<string, mixed>
     */
    private function textStyle(array $el): array
    {
        $s = $el['style'] ?? null;
        if (!is_array($s)) {
            return [];
        }

        $style = [];
        if (isset($s['fontSize']) && is_numeric($s['fontSize'])) {
            $style['fontSize'] = round((float) $s['fontSize'], 1);
        }
        if (isset($s['fontWeight']) && is_numeric($s['fontWeight'])) {
            $style['fontWeight'] = (int) $s['fontWeight'];
        }
        if (!empty($s['fontFamily'])) {
            $style['fontFamily'] = (string) $s['fontFamily'];
        }
        if (isset($s['letterSpacing']) && is_numeric($s['letterSpacing']) && abs((float) $s['letterSpacing']) > 0.001) {
            $style['letterSpacing'] = round((float) $s['letterSpacing'], 2);
        }
        if (isset($s['lineHeightPx']) && is_numeric($s['lineHeightPx'])) {
            $style['lineHeight'] = round((float) $s['lineHeightPx'], 1);
        }
        if (!empty($s['textCase']) && 'ORIGINAL' !== $s['textCase']) {
            $style['textCase'] = (string) $s['textCase'];
        }
        $color = $this->textColor($el);
        if ($color !== null) {
            $style['color'] = $color;
        }

        return $style;
    }

    /**
     * Couleur du texte (premier fill SOLID visible) d'un node TEXT, en hex.
     *
     * @param array<string, mixed> $el
     */
    private function textColor(array $el): ?string
    {
        foreach ($el['fills'] ?? [] as $fill) {
            if (($fill['visible'] ?? true) === false || ($fill['type'] ?? '') !== 'SOLID') {
                continue;
            }
            $color = $fill['color'] ?? null;
            if (!is_array($color)) {
                continue;
            }
            $r = (int) round(((float) ($color['r'] ?? 0.0)) * 255);
            $g = (int) round(((float) ($color['g'] ?? 0.0)) * 255);
            $b = (int) round(((float) ($color['b'] ?? 0.0)) * 255);

            return sprintf('#%02x%02x%02x', $r, $g, $b);
        }

        return null;
    }

    /** Libellés d'appel à l'action (normalisés, sans accent) pour repérer un CTA dans une card. */
    private const array CTA_LABELS = [
        'decouvrir', 'decouvrez', 'en savoir plus', 'en savoir', 'reserver', 'reservez', 'je reserve',
        'voir', 'voir plus', 'voir le menu', 'contact', 'contactez', 'telecharger', 'reserver une table',
    ];

    /**
     * Collecte les TEXT feuilles d'un sous-arbre (slide/card) avec taille, position et nœud source.
     *
     * @param array<string, mixed>                                                                            $node
     * @param list<array{chars: string, size: float, y: float, family: string, node: array<string, mixed>}> $out
     */
    private function textLeaves(array $node, array &$out): void
    {
        foreach ($node['children'] ?? [] as $c) {
            if (!$this->isVisible($c)) {
                continue; // calque masqué (variante de composant) → ne pas faire baver son texte
            }
            if (($c['type'] ?? '') === 'TEXT') {
                $t = trim((string) ($c['characters'] ?? ''));
                if ($t !== '') {
                    $out[] = ['chars' => $t, 'size' => (float) ($c['style']['fontSize'] ?? 0.0), 'y' => (float) ($c['absoluteBoundingBox']['y'] ?? 0.0), 'family' => (string) ($c['style']['fontFamily'] ?? ''), 'node' => $c];
                }
            }
            $this->textLeaves($c, $out);
        }
    }

    /** Un libellé court figurant au lexique CTA (insensible casse/accents). */
    private function isCtaLabel(string $chars): bool
    {
        $n = strtr(mb_strtolower(trim($chars)), ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', '’' => "'"]);
        $n = preg_replace('/\s+/', ' ', $n);
        if (str_word_count($n) > 4) {
            return false;
        }
        foreach (self::CTA_LABELS as $l) {
            if ($n === $l || str_starts_with($n, $l)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Texte structuré d'une slide/card : titre / introduction / libellé CTA + style du titre,
     * classé par rang de taille de police + position (logique validée par POC sur la home).
     * Les lignes de plus grande taille forment le titre (motif 2 lignes), la taille mini = intro,
     * les libellés du lexique CTA sont retirés (un seul gardé comme targetLabel).
     *
     * @param array<string, mixed> $node
     *
     * @return array{title: ?string, subtitle: ?string, introduction: ?string, targetLabel: ?string, style: array<string, mixed>}
     */
    private function cardText(array $node): array
    {
        $leaves = [];
        $this->textLeaves($node, $leaves);

        // 1. CTA explicite (lexique) retirés (tous), un gardé comme label.
        $cta = null;
        $rest = [];
        foreach ($leaves as $leaf) {
            if ($this->isCtaLabel($leaf['chars'])) {
                $cta ??= $this->normalizeText($leaf['chars']);
                continue;
            }
            $rest[] = $leaf;
        }
        if ($rest === []) {
            return ['title' => null, 'subtitle' => null, 'introduction' => null, 'targetLabel' => $cta, 'style' => []];
        }

        // 2. Méta (libellés purement numériques : prix « 120 € », « 17 m² », unités) écartés du titre.
        $isMeta = static fn (string $c): bool => preg_match('/^[\s\d.,:€$£%°\/x×+-]+$/u', trim($c)) === 1;
        $content = array_values(array_filter($rest, static fn (array $l) => !$isMeta($l['chars'])));
        if ($content === []) {
            $content = $rest;
        }

        if (count($content) === 1) {
            return ['title' => $this->normalizeText($content[0]['chars']), 'subtitle' => null, 'introduction' => null, 'targetLabel' => $cta, 'style' => $this->textStyle($content[0]['node'])];
        }

        // 3. CTA implicite : si AUCUN libellé du lexique ET au moins 3 tiers de taille distincts,
        // le plus petit tier (s'il est court) est le CTA — sinon il polluerait l'intro/le titre.
        // (≥3 tiers garantit qu'un titre + une intro subsistent ; à 2 tiers le plus petit = intro.)
        $distinct = array_values(array_unique(array_map(static fn (array $l) => $l['size'], $content)));
        if ($cta === null && count($distinct) >= 3) {
            $min = min($distinct);
            $smallestTier = array_values(array_filter($content, static fn (array $l) => $l['size'] === $min));
            usort($smallestTier, static fn (array $a, array $b) => $b['y'] <=> $a['y']);
            $short = $smallestTier[0] ?? null;
            if ($short !== null && $this->wordCount($short['chars']) <= 3) {
                $cta = $this->normalizeText($short['chars']);
                $content = array_values(array_filter($content, static fn (array $l) => $l !== $short));
            }
        }

        // 4. Titre vs introduction. L'introduction est un PARAGRAPHE de CORPS (police de corps, taille
        //    ≈ corps de page) ; une ligne en police d'AFFICHAGE (script) ou plus grande que le corps est
        //    un TITRE. Sans ça, un titre en deux lignes « sans + fioriture script » (cartes spa : « les
        //    soins » + script « massages », sans aucun paragraphe) voyait sa ligne script prise pour une
        //    intro et stylée en corps (cf. classifyText #2b, même signal de police).
        $body = (float) ($this->fontScale['body'] ?? 0.0);
        $bodyFamily = $this->fontScale['bodyFamily'] ?? null;
        $isDisplay = static fn (array $l): bool => $bodyFamily !== null && ($l['family'] ?? '') !== '' && $l['family'] !== $bodyFamily;

        // Ligne en police d'AFFICHAGE (script) = SOUS-TITRE (fioriture du titre), jamais une intro.
        // La card porte title + subTitle (BaseIntl), exactement comme le motif « les soins » (sans)
        // + « massages » (script) : deux parties d'un même titre, stylées distinctement.
        $subtitleLeaves = array_values(array_filter($content, $isDisplay));
        $rest = array_values(array_filter($content, static fn (array $l) => !$isDisplay($l)));

        if ($body > 0.0) {
            $introLeaves = array_values(array_filter($rest, static fn (array $l) => $l['size'] <= $body * 1.25));
            $titleLeaves = array_values(array_filter($rest, static fn (array $l) => $l['size'] > $body * 1.25));
        } elseif ($rest !== []) {
            // Repli sans échelle connue (corps inconnu) : rang de taille — plus petite = intro.
            $minSize = min(array_map(static fn (array $l) => $l['size'], $rest));
            $titleLeaves = array_values(array_filter($rest, static fn (array $l) => $l['size'] > $minSize));
            $introLeaves = array_values(array_filter($rest, static fn (array $l) => $l['size'] <= $minSize));
        } else {
            $titleLeaves = [];
            $introLeaves = [];
        }

        // Aucun titre ET aucun sous-titre → la 1re ligne (par position) devient le titre.
        if ($titleLeaves === [] && $subtitleLeaves === []) {
            usort($content, static fn (array $a, array $b) => $a['y'] <=> $b['y']);
            $titleLeaves = [array_shift($content)];
            $introLeaves = $content;
        }

        usort($titleLeaves, static fn (array $a, array $b) => $a['y'] <=> $b['y']);
        usort($subtitleLeaves, static fn (array $a, array $b) => $a['y'] <=> $b['y']);
        usort($introLeaves, static fn (array $a, array $b) => $a['y'] <=> $b['y']);

        $join = fn (array $ls) => $this->normalizeText(implode(' ', array_map(static fn (array $l) => $l['chars'], $ls)));
        $primaryPool = $titleLeaves !== [] ? $titleLeaves : $subtitleLeaves;
        $primary = $primaryPool[0] ?? null;
        foreach ($primaryPool as $l) {
            if ($primary === null || $l['size'] > $primary['size']) {
                $primary = $l;
            }
        }

        return [
            'title' => $titleLeaves === [] ? null : $join($titleLeaves),
            'subtitle' => $subtitleLeaves === [] ? null : $join($subtitleLeaves),
            'introduction' => $introLeaves === [] ? null : $join($introLeaves),
            'targetLabel' => $cta,
            'style' => $primary !== null ? $this->textStyle($primary['node']) : [],
        ];
    }

    /** Nombre de mots (après normalisation accents) — pour distinguer un CTA court d'un texte long. */
    private function wordCount(string $s): int
    {
        return str_word_count(strtr(mb_strtolower($s), ['à' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'î' => 'i', 'ô' => 'o', 'û' => 'u', 'ç' => 'c', '’' => "'"]));
    }

    /**
     * Médias par card d'un module (slider/teaser) : chaque enfant direct = une card portant
     * son image (1er IMAGE du sous-arbre) + son texte structuré. Les enfants sans image ni
     * titre (flèches, contrôles) sont ignorés.
     *
     * @param array<string, mixed> $node
     *
     * @return list<array<string, mixed>>
     */
    private function collectCards(array $node): array
    {
        $children = $node['children'] ?? [];
        // W4 : cards regroupées sous un (ou des) wrapper(s) non significatif(s) → descendre pour ne
        // pas fusionner toutes les cards en une seule. On descend tant qu'il n'y a qu'UN enfant et
        // que cet enfant est un conteneur de cards (≥2 enfants majoritairement conteneurs).
        $guard = 0;
        while (count($children) === 1 && $this->isCardWrapper($children[0]) && $guard++ < 5) {
            $children = $children[0]['children'] ?? [];
        }

        $cards = [];
        $pos = 0;
        foreach ($children as $child) {
            if (!$this->isVisible($child)) {
                continue; // carte/variante masquée → ignorée
            }
            $images = $this->collectImages($child);
            $text = $this->cardText($child);
            if ($images === [] && $text['title'] === null && $text['subtitle'] === null) {
                continue;
            }
            ++$pos;
            $entry = $images[0] ?? ['figmaNodeId' => (string) ($child['id'] ?? ''), 'image' => null, 'imageRef' => '', 'width' => 0, 'format' => 'jpg'];
            $entry['position'] = $pos;
            foreach (['title', 'subtitle', 'introduction', 'targetLabel'] as $k) {
                if ($text[$k] !== null) {
                    $entry[$k] = $text[$k];
                }
            }
            if ($text['style'] !== []) {
                $entry['style'] = $text['style'];
            }
            $cards[] = $entry;
        }

        return $cards;
    }

    /**
     * Un nœud qui n'est qu'un WRAPPER de cards (≥ 2 enfants majoritairement conteneurs) : à traverser
     * pour atteindre les vraies cards. Une card isolée (enfants = textes/images feuilles) renvoie false.
     *
     * @param array<string, mixed> $node
     */
    private function isCardWrapper(array $node): bool
    {
        $kids = $node['children'] ?? [];
        if (count($kids) < 2) {
            return false;
        }
        $containers = 0;
        foreach ($kids as $k) {
            if (($k['children'] ?? []) !== []) {
                ++$containers;
            }
        }

        return $containers >= count($kids) / 2;
    }

    /**
     * Builds the page font scale used to classify untagged TEXT as heading vs body.
     *
     * The body/paragraph size is read from PARAGRAPH runs (multi-word texts in the dominant
     * font family) rather than from the most frequent size overall — otherwise short labels
     * and buttons (often the single most frequent size) get mistaken for the body, and every
     * paragraph one notch larger is wrongly promoted to a heading. Sizes strictly above the
     * body are ranked into heading levels h1…h6.
     *
     * @param list<array<string, mixed>> $content
     *
     * @return array{body: float, levels: array<string, int>, bodyFamily: ?string}
     */
    private function computeFontScale(array $content): array
    {
        $sizes = [];
        $paragraphSizes = [];
        $families = [];
        $walk = function (array $node) use (&$walk, &$sizes, &$paragraphSizes, &$families): void {
            if (($node['type'] ?? '') === 'TEXT' && isset($node['style']['fontSize']) && is_numeric($node['style']['fontSize'])) {
                $size = (string) round((float) $node['style']['fontSize'], 1);
                $sizes[] = $size;
                $family = isset($node['style']['fontFamily']) ? (string) $node['style']['fontFamily'] : null;
                if ($family !== null) {
                    $families[] = $family;
                }
                // Paragraph run = a sentence-like text (≥ 4 words) — the real body copy.
                if ($this->wordCount((string) ($node['characters'] ?? '')) >= 4) {
                    $paragraphSizes[] = ['size' => $size, 'family' => $family];
                }
            }
            foreach ($node['children'] ?? [] as $child) {
                if (!$this->isVisible($child)) {
                    continue; // les variantes masquées ne doivent pas peser dans l'échelle typo
                }
                $walk($child);
            }
        };
        foreach ($content as $node) {
            $walk($node);
        }

        if ($sizes === []) {
            return ['body' => 0.0, 'levels' => [], 'bodyFamily' => null];
        }

        // Dominant (body) family = the most frequent family across all text.
        $bodyFamily = null;
        if ($families !== []) {
            $famFreq = array_count_values($families);
            arsort($famFreq);
            $bodyFamily = (string) array_key_first($famFreq);
        }

        // Body size = most frequent paragraph size in the dominant family; fall back to the most
        // frequent paragraph size, then to the most frequent size overall.
        $bodyCandidates = array_values(array_map(
            static fn (array $p): string => $p['size'],
            array_filter($paragraphSizes, static fn (array $p): bool => $bodyFamily === null || $p['family'] === $bodyFamily)
        ));
        if ($bodyCandidates === []) {
            $bodyCandidates = array_map(static fn (array $p): string => $p['size'], $paragraphSizes);
        }
        if ($bodyCandidates === []) {
            $bodyCandidates = $sizes;
        }
        $bodyFreq = array_count_values($bodyCandidates);
        arsort($bodyFreq);
        $body = (float) array_key_first($bodyFreq);

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

        return ['body' => $body, 'levels' => $levels, 'bodyFamily' => $bodyFamily];
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
     * Whether a node is rendered (not hidden in Figma). Hidden layers (`visible:false`) are the
     * unselected states of component variants (ex. « Intime et élégante » répété, masqué) — les
     * inclure ferait BAVER du texte/des images fantômes entre cartes. On les ignore partout où on
     * collecte du contenu (texte, médias, échelle typo).
     *
     * @param array<string, mixed> $node
     */
    private function isVisible(array $node): bool
    {
        return ($node['visible'] ?? true) !== false;
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
