<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Exception\FigmaApiException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Renders shared layout elements (nav, footer…) declared in the layout JSON files
 * into the screenshots/layout folder. Read-only (no DB write).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class LayoutScreenshotter
{
    public function __construct(
        private readonly FigmaApiClientInterface $figma,
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param list<array{figmaNodeId: string, screenshot: string}> $captures
     *
     * @return list<string> written file paths
     */
    public function capture(string $fileKey, array $captures, string $outputDir): array
    {
        $ids = array_values(array_unique(array_filter(array_map(static fn (array $c) => $c['figmaNodeId'], $captures))));
        if ($ids === []) {
            return [];
        }

        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            throw new FigmaApiException(sprintf('Dossier de capture non créé : %s', $outputDir));
        }

        $images = $this->figma->getImages($fileKey, $ids, 'png', 1.0);

        $written = [];
        foreach ($captures as $capture) {
            $url = $images[$capture['figmaNodeId']] ?? null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            file_put_contents(rtrim($outputDir, '/\\').'/'.$capture['screenshot'], $this->download($url));
            $written[] = $capture['screenshot'];
        }

        return $written;
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
