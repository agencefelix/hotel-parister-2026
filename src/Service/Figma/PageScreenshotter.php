<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Dto\ParsedPage;
use App\Service\Figma\Exception\FigmaApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Renders the full Figma page once, then crops one screenshot per zone band so each deduced
 * band can be visually identified, plus the media carried by blocks. Read-only (no DB write).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class PageScreenshotter
{
    /** Target render width (px) — scale is derived from the page width. */
    private const float TARGET_WIDTH = 900.0;

    /** Full-screen / full-width hero media: rendered at least this wide (px). */
    private const float FULLSCREEN_WIDTH = 3840.0;

    /** Other (non full-width) media: retina width cap (px) — web-sane, target < 1 Mo. */
    private const float CONTENT_MAX_WIDTH = 2048.0;

    /** JPEG quality for web export (visually lossless, keeps files reasonable). */
    private const int JPEG_QUALITY = 82;

    public function __construct(
        private readonly FigmaApiClientInterface $figma,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * Writes, under $baseDir:
     *  - one PNG per content zone into `<baseDir>/<slug>/` (clamped to the content region) ;
     *  - one image per excluded layout element (nav, footer…) into `<baseDir>/layout/`.
     *
     * @return list<string> written file paths
     */
    public function capture(string $fileKey, string $nodeId, ParsedPage $page, string $baseDir): array
    {
        if (!\function_exists('imagecreatefromstring')) {
            throw new FigmaApiException('Extension GD requise pour générer les captures.');
        }

        $baseDir = rtrim($baseDir, '/\\');
        $written = $this->captureZones($fileKey, $nodeId, $page, $baseDir.'/'.$page->slug);

        return array_merge($written, $this->captureLayout($fileKey, $page, $baseDir.'/layout'));
    }

    /**
     * @return list<string>
     */
    private function captureZones(string $fileKey, string $nodeId, ParsedPage $page, string $dir): array
    {
        $this->ensureDir($dir);

        // Nettoyer les anciennes captures de bandes de CETTE page (évite les orphelines
        // quand le nombre de zones diminue, ex. après auto-exclusion d'éléments de layout).
        foreach (glob($dir.'/section-*.png') ?: [] as $stale) {
            @unlink($stale);
        }

        $scale = max(0.1, min(1.0, self::TARGET_WIDTH / max($page->figmaWidth, 1.0)));
        $src = $this->decode($this->renderFullPage($fileKey, $nodeId, $scale));
        $imgHeight = imagesy($src);
        $imgWidth = imagesx($src);

        // Content region in pixels — excludes stacked layout bands (footer/nav).
        $clampTop = (int) round(($page->figmaContentTop - $page->figmaTop) * $scale);
        $clampBottom = (int) round(($page->figmaContentBottom - $page->figmaTop) * $scale);

        $written = [];
        foreach ($page->zones as $zone) {
            if ($zone->screenshot === null) {
                continue;
            }

            $top = (int) round(($zone->figmaTop - $page->figmaTop) * $scale);
            $bottom = $top + (int) round($zone->figmaHeight * $scale);

            $top = max($top, $clampTop, 0);
            $bottom = min($bottom, $clampBottom, $imgHeight);
            $height = $bottom - $top;

            if ($height < 1) {
                continue;
            }

            $crop = imagecreatetruecolor($imgWidth, $height);
            imagecopy($crop, $src, 0, 0, 0, $top, $imgWidth, $height);
            $path = $dir.'/'.$zone->screenshot;
            imagepng($crop, $path);
            $written[] = $path;
        }

        return $written;
    }

    /**
     * Renders the content media carried by blocks (slider slides, media…) into $mediaDir.
     * Group ids by (format, scale): one Figma /images call per bucket. The native asset is
     * downloaded then re-encoded web-optimised (JPG q82 / PNG / SVG as-is) — the CMS derives WebP.
     *
     * @return list<string> written file paths
     */
    public function captureMedia(string $fileKey, ParsedPage $page, string $mediaDir): array
    {
        $buckets = [];
        $names = [];
        $fullWidthThreshold = $page->figmaWidth * 0.92;
        foreach ($page->zones as $zone) {
            foreach ($zone->cols as $col) {
                foreach ($col->blocks as $block) {
                    foreach ($block->media as $media) {
                        if (empty($media['figmaNodeId']) || empty($media['image'])) {
                            continue;
                        }
                        $id = (string) $media['figmaNodeId'];
                        $names[$id] = (string) $media['image'];
                        $format = (string) ($media['format'] ?? 'jpg');
                        // SVG is vector (no scale); raster gets a retina-grade scale.
                        $scale = $format === 'svg' ? 1.0 : $this->mediaScale((int) ($media['width'] ?? 0), $fullWidthThreshold);
                        // String key: a float array key would be truncated to int by PHP.
                        $buckets[$format.'@'.$scale][$id] = true;
                    }
                }
            }
        }

        if ($names === []) {
            return [];
        }

        $this->ensureDir($mediaDir);

        $written = [];
        foreach ($buckets as $bucket => $idSet) {
            [$format, $scale] = explode('@', $bucket, 2);
            $ids = array_keys($idSet);
            $images = $this->figma->getImages($fileKey, $ids, $format, (float) $scale);
            foreach ($ids as $id) {
                $url = $images[$id] ?? null;
                if (!is_string($url) || $url === '') {
                    continue;
                }
                $this->writeWebMedia($this->download($url), rtrim($mediaDir, '/\\').'/'.$names[$id], $format);
                $written[] = $names[$id];
            }
        }

        return $written;
    }

    /**
     * Writes a media web-optimised (target < 1 Mo, no WebP — the CMS derives that):
     * SVG saved as-is (vector); JPG/PNG re-encoded via GD (progressive JPEG q82,
     * PNG compressed with preserved alpha).
     */
    private function writeWebMedia(string $binary, string $path, string $format): void
    {
        if ($format === 'svg' || !\function_exists('imagecreatefromstring')) {
            file_put_contents($path, $binary);

            return;
        }

        $img = imagecreatefromstring($binary);
        if ($img === false) {
            file_put_contents($path, $binary);

            return;
        }

        if ($format === 'png') {
            imagesavealpha($img, true);
            imagepng($img, $path, 9);
        } else {
            imageinterlace($img, true); // progressive JPEG
            imagejpeg($img, $path, self::JPEG_QUALITY);
        }
        imagedestroy($img);
    }

    /**
     * Render scale to reach a correct asset width: up to FULLSCREEN_WIDTH for full-width media,
     * 2× (retina) capped at CONTENT_MAX_WIDTH otherwise. Downscales oversized nodes. Bounded by 4.
     */
    private function mediaScale(int $nodeWidth, float $fullWidthThreshold): float
    {
        if ($nodeWidth <= 0) {
            return 2.0;
        }

        // Full-screen heroes must be ≥ 3840px wide; other media stay retina-capped (web-sane).
        $target = $nodeWidth >= $fullWidthThreshold ? self::FULLSCREEN_WIDTH : min(self::CONTENT_MAX_WIDTH, $nodeWidth * 2.0);

        // Floor (not round) so width*scale never overshoots the target; allow <1 to shrink oversized nodes.
        $scale = max(0.01, min(4.0, $target / $nodeWidth));

        return floor($scale * 100) / 100;
    }

    /**
     * Renders each excluded layout element (nav, footer…) as its own image.
     *
     * @return list<string>
     */
    private function captureLayout(string $fileKey, ParsedPage $page, string $dir): array
    {
        $ids = array_values(array_filter(array_map(static fn (array $n) => $n['id'], $page->excludedNodes)));
        if ($ids === []) {
            return [];
        }

        $this->ensureDir($dir);
        $images = $this->figma->getImages($fileKey, $ids, 'png', 1.0);

        $written = [];
        foreach ($page->excludedNodes as $node) {
            $url = $images[$node['id']] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            $src = $this->decode($this->download($url));
            $path = $dir.'/'.$node['screenshot'];
            imagepng($src, $path);
            $written[] = $path;
        }

        return $written;
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new FigmaApiException(sprintf('Dossier de capture non créé : %s', $dir));
        }
    }

    private function decode(string $binary): \GdImage
    {
        $img = imagecreatefromstring($binary);
        if ($img === false) {
            throw new FigmaApiException('Impossible de décoder un rendu Figma.');
        }

        return $img;
    }

    private function renderFullPage(string $fileKey, string $nodeId, float $scale): string
    {
        $images = $this->figma->getImages($fileKey, [$nodeId], 'png', $scale);
        $url = $images[$nodeId] ?? null;

        if (!is_string($url) || $url === '') {
            throw new FigmaApiException(sprintf('Figma n\'a pas renvoyé de rendu pour le nœud "%s".', $nodeId));
        }

        return $this->download($url);
    }

    private function download(string $url): string
    {
        try {
            return $this->httpClient->request('GET', $url)->getContent();
        } catch (HttpExceptionInterface $e) {
            throw new FigmaApiException('Échec du téléchargement d\'un rendu Figma.', previous: $e);
        }
    }
}
