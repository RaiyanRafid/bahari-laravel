<?php

namespace Bahari\Laravel;

use Bahari\Sso\Exception\BahariException;
use Bahari\Sso\Http\HttpClient;
use Bahari\Sso\Http\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Sends SDK requests through Laravel's HTTP client, so Http::fake() works in your tests.
 */
class LaravelHttpClient implements HttpClient
{
    public function send(string $method, string $url, array $headers = [], ?string $body = null): Response
    {
        $contentType = $headers['Content-Type'] ?? 'application/json';
        unset($headers['Content-Type']);
        $request = Http::withHeaders($headers)->withoutRedirecting()->connectTimeout(3)->timeout(10);
        if ($body !== null) {
            $request = $request->withBody($body, $contentType);
        }
        try {
            $response = $request->send($method, $url);
        } catch (ConnectionException $exception) {
            throw new BahariException('Bahari Accounts could not be reached.', previous: $exception);
        }

        return new Response($response->status(), $response->body(), collect($response->headers())->mapWithKeys(fn ($values, $name) => [strtolower($name) => is_array($values) ? (string) ($values[0] ?? '') : (string) $values])->all());
    }
}
