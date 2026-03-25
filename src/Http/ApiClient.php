<?php

namespace Govorun\Http;

use Govorun\Exceptions\ApiException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

abstract class ApiClient
{
    protected ClientInterface $client;
    protected int $timeout = 5;
    protected int $retries = 0;

    abstract protected function baseUrl(): string;

    protected function headers(): array
    {
        return [];
    }

    protected function get(string $uri, array $params = []): mixed
    {
        return $this->request('GET', $uri, ['query' => $params]);
    }

    protected function post(string $uri, array $data = []): mixed
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    protected function put(string $uri, array $data = []): mixed
    {
        return $this->request('PUT', $uri, ['json' => $data]);
    }

    protected function delete(string $uri): mixed
    {
        return $this->request('DELETE', $uri);
    }

    protected function handleError(GuzzleException $e): void
    {
        throw new ApiException($e->getMessage(), (int) $e->getCode(), $e);
    }

    private function request(string $method, string $uri, array $options = []): mixed
    {
        $url = rtrim($this->baseUrl(), '/') . '/' . ltrim($uri, '/');

        $options['timeout'] = $this->timeout;
        $options['headers'] = array_merge($options['headers'] ?? [], $this->headers());
        $options['http_errors'] = false;

        $attempts = 0;
        $maxAttempts = 1 + $this->retries;

        while (true) {
            $attempts++;

            try {
                $response = $this->client->request($method, $url, $options);

                $statusCode = $response->getStatusCode();

                if ($statusCode >= 400) {
                    if ($attempts < $maxAttempts) {
                        continue;
                    }
                    throw new ApiException(
                        "API request failed with status {$statusCode}",
                        $statusCode,
                    );
                }

                $body = $response->getBody()->getContents();

                return json_decode($body, true) ?? [];
            } catch (ApiException $e) {
                throw $e;
            } catch (GuzzleException $e) {
                if ($attempts < $maxAttempts) {
                    continue;
                }
                $this->handleError($e);
            }
        }
    }
}
