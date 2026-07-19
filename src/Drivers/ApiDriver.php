<?php

namespace Imran\DynamicConfig\Drivers;

use Imran\DynamicConfig\Contracts\ConfigDriver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Loads configuration from a remote JSON endpoint, e.g. a central config
 * server shared across services. Expects the endpoint to return a JSON
 * object mapping config keys to values (the same shape as the json driver).
 */
class ApiDriver implements ConfigDriver
{
    public function load(array $sourceConfig): array
    {
        $url = $sourceConfig['url'] ?? null;

        if (!$url) {
            return [];
        }

        $timeout = $sourceConfig['timeout'] ?? 5;
        $token = $sourceConfig['token'] ?? null;
        $headers = $sourceConfig['headers'] ?? [];

        try {
            $request = Http::timeout($timeout)->withHeaders($headers);

            if ($token) {
                $request = $request->withToken($token);
            }

            $response = $request->get($url);
        } catch (\Throwable $e) {
            Log::warning("[dynamic-config] ApiDriver could not reach [{$url}]: " . $e->getMessage());
            return [];
        }

        if (!$response->successful()) {
            Log::warning("[dynamic-config] ApiDriver received a non-successful response from [{$url}]: HTTP {$response->status()}.");
            return [];
        }

        $decoded = $response->json();

        return is_array($decoded) ? $decoded : [];
    }
}
