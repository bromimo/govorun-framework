<?php

namespace Govorun\Http;

use GuzzleHttp\Client;

/** HTTP-клиент для конкретного BotConnection-подключения. */
class ConnectionClient
{
    private Client $guzzle;
    private ?array $apiKeyQuery = null;

    /** @param array<string, mixed> $config Конфиг подключения из connections.php */
    public function __construct(array $config)
    {
        $baseUrl = rtrim($config['base_url'] ?? '', '/').'/';
        $auth = $config['auth'] ?? ['type' => 'none'];
        $defaultHeaders = (array) ($config['default_headers'] ?? []);

        switch ($auth['type'] ?? 'none') {
            case 'bearer':
                $defaultHeaders['Authorization'] = 'Bearer '.($auth['token'] ?? '');
                break;
            case 'api_key':
                if (($auth['in'] ?? 'header') === 'header') {
                    $defaultHeaders[$auth['key'] ?? 'X-Api-Key'] = $auth['value'] ?? '';
                } else {
                    $this->apiKeyQuery = [$auth['key'] ?? 'api_key' => $auth['value'] ?? ''];
                }
                break;
            case 'basic':
                $defaultHeaders['Authorization'] = 'Basic '.base64_encode(
                    ($auth['login'] ?? '').':'.($auth['password'] ?? '')
                );
                break;
        }

        $this->guzzle = new Client([
            'base_uri' => $baseUrl,
            'headers' => $defaultHeaders,
            'http_errors' => false,
            'timeout' => 10,
        ]);
    }

    /** GET-запрос.
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle (query, headers, …)
     * @return HttpResponse
     */
    public function get(string $path, array $options = []): HttpResponse
    {
        return $this->request('GET', $path, $options);
    }

    /** POST-запрос.
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle (json, form_params, headers, …)
     * @return HttpResponse
     */
    public function post(string $path, array $options = []): HttpResponse
    {
        return $this->request('POST', $path, $options);
    }

    /** PUT-запрос.
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle
     * @return HttpResponse
     */
    public function put(string $path, array $options = []): HttpResponse
    {
        return $this->request('PUT', $path, $options);
    }

    /** PATCH-запрос.
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle
     * @return HttpResponse
     */
    public function patch(string $path, array $options = []): HttpResponse
    {
        return $this->request('PATCH', $path, $options);
    }

    /** DELETE-запрос.
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle
     * @return HttpResponse
     */
    public function delete(string $path, array $options = []): HttpResponse
    {
        return $this->request('DELETE', $path, $options);
    }

    /** Выполнить HTTP-запрос.
     * @param string $method HTTP-метод
     * @param string $path Путь
     * @param array<string, mixed> $options Опции Guzzle
     * @return HttpResponse
     */
    private function request(string $method, string $path, array $options): HttpResponse
    {
        if ($this->apiKeyQuery !== null) {
            $options['query'] = array_merge($this->apiKeyQuery, $options['query'] ?? []);
        }

        $response = $this->guzzle->request($method, ltrim($path, '/'), $options);

        return new HttpResponse($response);
    }
}