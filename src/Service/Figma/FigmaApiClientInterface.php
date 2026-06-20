<?php

declare(strict_types=1);

namespace App\Service\Figma;

use App\Service\Figma\Exception\FigmaApiException;

/**
 * Read-only access to the Figma REST API.
 *
 * The configured personal access token currently carries the `file_content:read`
 * scope: reading files, nodes and rendered images is supported; user/account
 * endpoints are not.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
interface FigmaApiClientInterface
{
    /**
     * Returns the full document tree of a Figma file.
     *
     * @return array<string, mixed>
     *
     * @throws FigmaApiException
     */
    public function getFile(string $fileKey): array;

    /**
     * Returns a subset of a file limited to the given node identifiers.
     *
     * @param non-empty-list<string> $nodeIds
     *
     * @return array<string, mixed>
     *
     * @throws FigmaApiException
     */
    public function getFileNodes(string $fileKey, array $nodeIds): array;

    /**
     * Renders the given nodes as images and returns a map of nodeId => image URL.
     *
     * @param non-empty-list<string> $nodeIds
     * @param string                 $format  png, jpg, svg or pdf
     * @param float                  $scale   render scale between 0.01 and 4
     *
     * @return array<string, string|null>
     *
     * @throws FigmaApiException
     */
    public function getImages(string $fileKey, array $nodeIds, string $format = 'png', float $scale = 1.0): array;
}
