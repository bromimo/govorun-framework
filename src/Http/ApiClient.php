<?php

namespace Govorun\Http;

use GuzzleHttp\ClientInterface;
use Govorun\Exceptions\ApiException;
use GuzzleHttp\Exception\GuzzleException;

/** Абстрактный базовый класс API-клиента.
 * Предоставляет общую логику для выполнения HTTP-запросов к внешним API:
 * повторные попытки, обработку ошибок и декодирование JSON-ответов.
 */
abstract class ApiClient
{
    /** @var ClientInterface HTTP-клиент */
    protected ClientInterface $client;

    /** @var int Таймаут запроса в секундах */
    protected int $timeout = 5;

    /** @var int Количество повторных попыток */
    protected int $retries = 0;

    /** Получить базовый URL API.
     * @return string Базовый URL
     */
    abstract protected function baseUrl(): string;

    /** Получить дополнительные HTTP-заголовки для запросов.
     * @return array<string, string> Массив заголовков
     */
    protected function headers(): array
    {
        return [];
    }

    /** Выполнить GET-запрос.
     * @param string $uri URI ресурса
     * @param array<string, mixed> $params Параметры строки запроса
     * @return mixed Декодированный ответ
     * @throws ApiException При ошибке API или исчерпании попыток
     */
    protected function get(string $uri, array $params = []): mixed
    {
        return $this->request('GET', $uri, ['query' => $params]);
    }

    /** Выполнить POST-запрос.
     * @param string $uri URI ресурса
     * @param array<string, mixed> $data Данные тела запроса
     * @return mixed Декодированный ответ
     * @throws ApiException При ошибке API или исчерпании попыток
     */
    protected function post(string $uri, array $data = []): mixed
    {
        return $this->request('POST', $uri, ['json' => $data]);
    }

    /** Выполнить PUT-запрос.
     * @param string $uri URI ресурса
     * @param array<string, mixed> $data Данные тела запроса
     * @return mixed Декодированный ответ
     * @throws ApiException При ошибке API или исчерпании попыток
     */
    protected function put(string $uri, array $data = []): mixed
    {
        return $this->request('PUT', $uri, ['json' => $data]);
    }

    /** Выполнить DELETE-запрос.
     * @param string $uri URI ресурса
     * @return mixed Декодированный ответ
     * @throws ApiException При ошибке API или исчерпании попыток
     */
    protected function delete(string $uri): mixed
    {
        return $this->request('DELETE', $uri);
    }

    /** Обработать ошибку HTTP-клиента.
     * @param GuzzleException $e Исключение Guzzle
     * @return void
     * @throws ApiException Всегда выбрасывается при вызове
     */
    protected function handleError(GuzzleException $e): void
    {
        throw new ApiException($e->getMessage(), (int) $e->getCode(), $e);
    }

    /** Выполнить HTTP-запрос с повторными попытками.
     * @param string $method HTTP-метод
     * @param string $uri URI ресурса
     * @param array<string, mixed> $options Опции запроса Guzzle
     * @return mixed Декодированный JSON-ответ
     * @throws ApiException При ошибке API или исчерпании попыток
     */
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
