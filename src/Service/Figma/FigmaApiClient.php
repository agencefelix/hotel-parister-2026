<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Exception\FigmaApiException;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Stateless client for the Figma REST API, authenticated with the project's
 * personal access token (FIGMA_TOKEN).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
final class FigmaApiClient implements FigmaApiClientInterface
{
    private const BASE_URI = 'https://api.figma.com/v1/';

    private readonly HttpClientInterface $client;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $figmaToken,
    ) {
        if ($figmaToken === '') {
            throw FigmaApiException::missingToken();
        }

        $this->client = $httpClient->withOptions([
            'base_uri' => self::BASE_URI,
            'headers' => [
                'X-Figma-Token' => $figmaToken,
            ],
        ]);
    }

    public function getFile(string $fileKey): array
    {
        return $this->request('GET', 'files/' . rawurlencode($fileKey));
    }

    public function getFileNodes(string $fileKey, array $nodeIds): array
    {
        return $this->request('GET', 'files/' . rawurlencode($fileKey) . '/nodes', [
            'ids' => implode(',', $nodeIds),
        ]);
    }

    public function getImages(string $fileKey, array $nodeIds, string $format = 'png', float $scale = 1.0): array
    {
        $payload = $this->request('GET', 'images/' . rawurlencode($fileKey), [
            'ids' => implode(',', $nodeIds),
            'format' => $format,
            'scale' => $scale,
        ]);

        $images = $payload['images'] ?? null;

        if (!is_array($images)) {
            throw new FigmaApiException('Réponse Figma inattendue : clé "images" absente.');
        }

        return $images;
    }

    /**
     * @param array<string, scalar> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $query = []): array
    {
        try {
            $response = $this->client->request($method, $path, ['query' => $query]);

            return $this->decode($response);
        } catch (TransportExceptionInterface $e) {
            throw new FigmaApiException(
                sprintf('Échec de la connexion à l\'API Figma (%s %s).', $method, $path),
                previous: $e,
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        try {
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $error = $this->extractError($response);

                throw new FigmaApiException(
                    sprintf('L\'API Figma a renvoyé une erreur %d : %s', $statusCode, $error),
                    $statusCode,
                );
            }

            return $response->toArray();
        } catch (JsonException $e) {
            throw new FigmaApiException('Réponse Figma illisible (JSON invalide).', previous: $e);
        } catch (HttpExceptionInterface $e) {
            throw new FigmaApiException('Erreur lors de la lecture de la réponse Figma.', previous: $e);
        }
    }

    private function extractError(ResponseInterface $response): string
    {
        try {
            $body = $response->toArray(false);

            return (string) ($body['err'] ?? $body['message'] ?? 'erreur inconnue');
        } catch (HttpExceptionInterface) {
            return 'erreur inconnue';
        }
    }
}
