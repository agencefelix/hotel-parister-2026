<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Dto\ParsedBlock;

/**
 * Maps a Figma layer name (convention prefix) to its CMS target.
 *
 * Encodes `.claude/skills/figma-cms/models/mapping-blocktypes.md`. Pure, stateless, read-only.
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

    /**
     * Teaser modules (a carousel/preview of another module's items): the *-teaser token
     * => [Action slug, Teaser entity]. Distinct from the `-index` listing modules above.
     */
    private const array TEASERS = [
        'catalog-teaser' => ['catalog-teaser', 'App\\Entity\\Module\\Catalog\\Teaser'],
        'newscast-teaser' => ['newscast-teaser', 'App\\Entity\\Module\\Newscast\\Teaser'],
    ];

    private const array STRUCTURAL = ['page', 'zone', 'section', 'col'];
    private const array EXCLUDED = ['nav', 'footer', 'newsletter', 'socialwall'];

    /** Slider variant => Slider::template (drives prePersist). Other variants are modifiers/id. */
    private const array SLIDER_TEMPLATES = [
        'bootstrap' => 'bootstrap',
        'splide' => 'splide',
        'banner' => 'banner',
        'home' => 'main-home',
        'two-columns' => 'two-columns',
    ];

    /**
     * Teaser layout variant => BaseTeaser::template. The canonical form is
     * `[catalog|teaser|slider|splide]` (a Splide-powered slider) or `[catalog|teaser|list]`
     * (a plain list); `list` stands in for `slider` at the layout position. `splide` is the
     * carousel library that drives the `slider` template, so both resolve to `slider`.
     */
    private const array TEASER_TEMPLATES = [
        'slider' => 'slider',
        'splide' => 'slider',
        'list' => 'list',
    ];

    /**
     * Extracts the convention token from a layer name.
     *
     * Variants are pipe-separated (`[slider|splide|id:home-1]`). Conveniences :
     *  - `[slide-N|sliderId]` → type `slide`, position N (modifier `pos:N`), parent slider id ;
     *  - short hyphen form `[slider-home-1]` → type `slider`, id `home-1`.
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

        $type = $parts[0];
        $variants = array_slice($parts, 1);

        // Slide of a slider: `[slide-2|home-1]` → type `slide`, position 2, parent slider id `home-1`.
        if (preg_match('/^slide-(\d+)$/', $type, $sm)) {
            return ['type' => 'slide', 'variants' => array_merge(['pos:'.$sm[1]], $variants)];
        }

        // Short hyphen form: `[slider-home-1]` → type `slider`, id `home-1`.
        if (!$this->isKnownType($type) && str_contains($type, '-')) {
            [$head, $rest] = explode('-', $type, 2);
            if ($this->isKnownType($head)) {
                $type = $head;
                array_unshift($variants, 'id:'.$rest);
            }
        }

        return ['type' => $type, 'variants' => $variants];
    }

    private function isKnownType(string $type): bool
    {
        return isset(self::ATOMS[$type])
            || isset(self::MODULES[$type])
            || isset(self::TEASERS[$type])
            || 'teaser' === $type
            || $this->isStructural($type)
            || $this->isExcluded($type);
    }

    public function isStructural(string $type): bool
    {
        return in_array($type, self::STRUCTURAL, true);
    }

    /**
     * Whether a tag introduces a CMS zone (full-width band).
     *
     * Per the naming convention, both `[zone]` and `[section]` map 1:1 to a CMS zone
     * (`addZone()`); `[section]` (or the `section` variant of `[zone]`) additionally
     * asks for the zone to be rendered as a semantic `<section>`.
     */
    public function isZoneTag(string $type): bool
    {
        return 'zone' === $type || 'section' === $type;
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
        $id = $this->extractId($variants);

        // Teaser modules: detected by the *-teaser token wherever it sits — works for
        // [teaser|catalog-teaser], [slider|newscast-teaser], [catalog-teaser] and the
        // [teaser|catalog] / [teaser|newscast] shorthand. Runs first so a teaser never
        // collapses into a generic [slider].
        if (($teaser = $this->resolveTeaser($type, $variants)) !== null) {
            [$action, $entity] = $teaser;
            $template = $this->teaserTemplate($variants);

            return new ParsedBlock($figmaName, 'module', moduleAction: $action, moduleEntity: $entity, variants: $variants, id: $id, moduleTemplate: $template);
        }

        if (isset(self::ATOMS[$type])) {
            return new ParsedBlock($figmaName, 'atom', blockTypeSlug: self::ATOMS[$type], variants: $variants, id: $id);
        }

        if (isset(self::MODULES[$type])) {
            [$action, $entity] = self::MODULES[$type];
            $note = 'faq' === $type ? 'arbitrage : bloc "collapse" possible au lieu du module faq-view' : null;
            $template = $type === 'slider' ? $this->sliderTemplate($variants) : null;

            return new ParsedBlock($figmaName, 'module', moduleAction: $action, moduleEntity: $entity, note: $note, variants: $variants, id: $id, moduleTemplate: $template);
        }

        // Bare [teaser] without a kind: ask which teaser it is.
        if ('teaser' === $type) {
            return new ParsedBlock($figmaName, 'unknown', note: 'préfixe [teaser] ambigu — préciser [teaser|catalog-teaser] ou [teaser|newscast-teaser]', variants: $variants, id: $id);
        }

        $note = sprintf('préfixe [%s] non mappé', $type);
        // Suggest a correction on the type, or on its hyphen-head (e.g. `silder-home-1` → `slider`).
        $head = str_contains($type, '-') ? explode('-', $type, 2)[0] : $type;
        $suggestion = $this->closestType($type) ?? $this->closestType($head);
        if ($suggestion !== null) {
            $fixed = $head === $type ? $suggestion : $suggestion.'|id:'.explode('-', $type, 2)[1];
            $note .= sprintf(' — vouliez-vous [%s] ?', $fixed);
        }

        return new ParsedBlock($figmaName, 'unknown', note: $note, variants: $variants, id: $id);
    }

    /**
     * Resolves a teaser module from a tag's type + variants, accepting every documented form:
     *  - `[catalog|teaser]` / `[newscast|teaser]` — domain head + `teaser` variant ;
     *  - `[teaser|catalog-teaser]` / `[slider|newscast-teaser]` — explicit `*-teaser` token ;
     *  - `[catalog-teaser]` direct ; `[teaser|catalog]` / `[teaser|newscast]` shorthand.
     *
     * Runs before the generic [slider]/[catalog]/[newscast] mapping so a teaser never
     * collapses into a plain slider or a listing index.
     *
     * @param list<string> $variants
     *
     * @return array{0: string, 1: string}|null [Action slug, Teaser entity]
     */
    private function resolveTeaser(string $type, array $variants): ?array
    {
        // Domain head + `teaser` variant: [catalog|teaser], [newscast|teaser].
        if (in_array('teaser', $variants, true) && isset(self::TEASERS[$type.'-teaser'])) {
            return self::TEASERS[$type.'-teaser'];
        }

        // Explicit `*-teaser` token anywhere: [teaser|catalog-teaser], [slider|newscast-teaser], [catalog-teaser].
        foreach (array_merge([$type], $variants) as $token) {
            if (isset(self::TEASERS[$token])) {
                return self::TEASERS[$token];
            }
        }

        // Shorthand under a [teaser] head: [teaser|catalog], [teaser|newscast].
        if ('teaser' === $type) {
            foreach ($variants as $token) {
                if (isset(self::TEASERS[$token.'-teaser'])) {
                    return self::TEASERS[$token.'-teaser'];
                }
            }
        }

        return null;
    }

    /**
     * Reads the `id:<value>` modifier from the variants (the block's stable identifier).
     *
     * @param list<string> $variants
     */
    public function extractId(array $variants): ?string
    {
        foreach ($variants as $v) {
            if (str_starts_with($v, 'id:')) {
                $id = trim(substr($v, 3));

                return $id === '' ? null : $id;
            }
        }

        return null;
    }

    /**
     * Reads the `pos:<n>` modifier (a slide's position within its slider). 0 if absent.
     *
     * @param list<string> $variants
     */
    public function extractPosition(array $variants): int
    {
        foreach ($variants as $v) {
            if (str_starts_with($v, 'pos:')) {
                return (int) substr($v, 4);
            }
        }

        return 0;
    }

    /**
     * Resolves a slider variant to its Slider::template (drives prePersist), if any.
     *
     * @param list<string> $variants
     */
    private function sliderTemplate(array $variants): ?string
    {
        foreach ($variants as $v) {
            if (isset(self::SLIDER_TEMPLATES[$v])) {
                return self::SLIDER_TEMPLATES[$v];
            }
        }

        return null;
    }

    /**
     * Resolves a teaser layout variant to its BaseTeaser::template, if any.
     *
     * Canonical forms: `[catalog|teaser|slider|splide]` → `slider`, `[catalog|teaser|list]` → `list`
     * (idem newscast). `list` takes precedence over the slider layout when both are present.
     *
     * @param list<string> $variants
     */
    private function teaserTemplate(array $variants): ?string
    {
        if (in_array('list', $variants, true)) {
            return self::TEASER_TEMPLATES['list'];
        }

        foreach ($variants as $v) {
            if (isset(self::TEASER_TEMPLATES[$v])) {
                return self::TEASER_TEMPLATES[$v];
            }
        }

        return null;
    }

    /**
     * Suggests the closest known type for an unknown prefix (e.g. `silder` → `slider`),
     * to surface a typo without silently accepting it.
     */
    private function closestType(string $type): ?string
    {
        $known = array_merge(array_keys(self::ATOMS), array_keys(self::MODULES), array_keys(self::TEASERS), self::STRUCTURAL, self::EXCLUDED, ['teaser', 'slide']);
        $best = null;
        $bestDistance = \PHP_INT_MAX;

        foreach ($known as $candidate) {
            $distance = levenshtein($type, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $best = $candidate;
            }
        }

        // Only suggest when really close (1-2 edits), so unrelated names stay silent.
        return $bestDistance <= 2 ? $best : null;
    }
}
