<?php

declare(strict_types=1);

namespace App\Service\Core;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * AI Service.
 *
 * Handles API requests and performs cosine similarity calculations.
 *
 * @author Sébastien FOURNIER <fournier.sebastien@outlook.com>
 */
#[Autoconfigure(tags: [
    ['name' => AI::class, 'key' => 'ai_service'],
])]
class AI
{
    private string $host = "prompt.agence-felix.fr";
    private string $baseUrl = "https://prompt.agence-felix.fr/v1/run-tool";
    private string $bearerToken = "VQu8F8qXd3FNR70lMrQ3la0EaLC01VsrRMFcKjlwjHh3FXasRM";
    private int $defaultSite = 40;

    public function __construct()
    {
    }

    /**
     * Executes the API request.
     */
    public function runApi(Request $request, $options = []): array
    {
        // Merge request data with additional options
        $data = array_merge($_POST, $options);
        $data = array_merge($request->query->all(), $data);
        if (empty($data['site'])) {
            $data['site'] = $this->defaultSite;
        }

        $curl = curl_init($this->baseUrl);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_USERAGENT => 'API FELIX',
            CURLOPT_HTTPHEADER => [
                'Host: ' . $this->host,
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->bearerToken,
            ],
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HEADER => true,
        ]);

        $response = curl_exec($curl);

        if ($response === false) {
            return [
                'curl_error' => curl_error($curl),
                'curl_errno' => curl_errno($curl),
            ];
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($curl, CURLINFO_HEADER_SIZE);

        $rawHeaders = substr($response, 0, $headerSize);
        $rawBody = substr($response, $headerSize);

        curl_close($curl);

        if (is_array($response) && array_key_exists('message', $response) && $response['message'] && str_contains(strtolower($response['message']), 'not working')) {
            $response['response']['error']['message'] = $response['message'];
            $response['response']['error']['type'] = 'error';
        }

        if (is_array($response) && array_key_exists('response', $response) && is_array($response['response']) && array_key_exists('error', $response['response'])) {
            $message = array_key_exists('message', $response['response']['error'])
                ? $response['response']['error']['message']
                : 'An error occurred.';
            $type = array_key_exists('type', $response['response']['error']) ? $response['response']['error']['type'] : 'error';
            return [
                'httpCode' => $type && str_contains($type, 'insufficient_quota') ? Response::HTTP_PAYMENT_REQUIRED : Response::HTTP_INTERNAL_SERVER_ERROR,
                'error' => $message,
                'message' => $message,
                'response' => ['error' => $message]
            ];
        }

        // Handle cURL errors
        if (curl_errno($curl)) {
            return ['error' => 'cURL Error: ' . curl_error($curl)];
        }
        curl_close($curl);

        return is_array($response) ? $response : [
            'httpCode' => $httpCode,
            'headers' => $rawHeaders,
            'body' => $rawBody,
        ];
    }

    /**
     * Computes the cosine similarity between two vectors.
     */
    public function cosineSimilarity(array $vecA, array $vecB): float
    {
        // Precompute magnitudes
        $magnitudeA = sqrt(array_sum(array_map(function ($x) {
            return $x * $x;
        }, $vecA)));

        $magnitudeB = sqrt(array_sum(array_map(function ($x) {
            return $x * $x;
        }, $vecB)));

        // Avoid division by zero
        if ($magnitudeA == 0 || $magnitudeB == 0) {
            return 0.0;
        }

        // Compute dot product
        $dotProduct = array_sum(array_map(function ($a, $b) {
            return $a * $b;
        }, $vecA, $vecB));

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }
}