<?php

namespace Govorun\Drivers\Whatsapp;

use Govorun\Http\Request;
use GuzzleHttp\ClientInterface;
use Govorun\Http\WebhookResponse;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Contracts\WebhookResponder;
use Govorun\Exceptions\WebhookManualSetupException;
use Govorun\Drivers\Concerns\ConvertsHtmlToPlainText;

/** Драйвер WhatsApp Cloud API (Meta Graph API).
 * Реактивный режим: приём сообщений, отправка текста/медиа/интерактивных клавиатур,
 * бизнес-профиль. Редактирование/удаление и автоустановка вебхука не поддерживаются.
 */
class WhatsappDriver implements MessengerDriver, WebhookResponder
{
    use ConvertsHtmlToPlainText;

    private const BASE_URL = 'https://graph.facebook.com/';

    private const DEFAULT_API_VERSION = 'v25.0';

    private string $accessToken;

    private string $phoneNumberId;

    private string $wabaId;

    private string $appId;

    private string $verifyToken;

    private ?string $appSecret;

    private string $apiVersion;

    /** Создать драйвер.
     * @param array $config Конфиг messenger.whatsapp
     * @param ClientInterface $client HTTP-клиент Guzzle
     */
    public function __construct(
        private array $config,
        private ClientInterface $client,
    ) {
        $this->accessToken = $config['access_token'] ?? '';
        $this->phoneNumberId = $config['phone_number_id'] ?? '';
        $this->wabaId = $config['waba_id'] ?? '';
        $this->appId = $config['app_id'] ?? '';
        $this->verifyToken = $config['verify_token'] ?? '';
        $this->appSecret = $config['app_secret'] ?? null;
        $this->apiVersion = ($config['api_version'] ?? '') ?: self::DEFAULT_API_VERSION;
    }

    /** Проверить подлинность вебхука: GET пропускаем (аутентификация в preflight),
     * POST — HMAC-SHA256 по сырому телу против X-Hub-Signature-256.
     * @param Request $request Входящий запрос
     * @return bool Результат верификации
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($request->method() === 'GET') {
            return true;
        }

        if ($this->appSecret === null || $this->appSecret === '') {
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256');
        if (! is_string($signature)) {
            return false;
        }

        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent() ?? '', $this->appSecret);

        return hash_equals($expected, $signature);
    }

    /** Ответ до парсинга: GET-challenge подписки и игнор статусов доставки.
     * @param Request $request Входящий запрос
     * @return WebhookResponse|null Ответ для short-circuit или null
     */
    public function preflight(Request $request): ?WebhookResponse
    {
        if ($request->method() === 'GET') {
            if ($request->query('hub_mode') === 'subscribe'
                && $request->query('hub_verify_token') === $this->verifyToken) {
                return WebhookResponse::text((string) $request->query('hub_challenge'));
            }

            return WebhookResponse::forbidden();
        }

        $value = $this->extractValue($request->json());
        if (isset($value['statuses']) && ! isset($value['messages'])) {
            return WebhookResponse::ok();
        }

        return null;
    }

    /** Тело ответа после успешной обработки (WhatsApp достаточно пустого 200).
     * @return string Пустая строка
     */
    public function ackBody(): string
    {
        return '';
    }

    /** Разобрать вебхук в нормализованное сообщение.
     * @param Request $request Входящий запрос
     * @return IncomingMessage Сообщение
     * @throws \RuntimeException Если тип не поддержан
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        throw new \RuntimeException('not implemented');
    }

    /** Отправить сообщение.
     * @param OutgoingMessage $message Сообщение
     * @return string|null WAMID или null
     * @throws \Throwable
     */
    public function send(OutgoingMessage $message): ?string
    {
        throw new \RuntimeException('not implemented');
    }

    /** Редактирование не поддерживается WhatsApp.
     * @param string $messageId Идентификатор сообщения
     * @param OutgoingMessage $message Новое содержимое
     * @return void
     * @throws \RuntimeException Всегда
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        throw new \RuntimeException('WhatsApp does not support editing messages.');
    }

    /** Удаление не поддерживается WhatsApp.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     * @throws \RuntimeException Всегда
     */
    public function delete(string $messageId, string $chatId): void
    {
        throw new \RuntimeException('WhatsApp does not support deleting messages.');
    }

    /** Автоустановка вебхука не поддерживается (настройка вручную в Meta App Dashboard).
     * @param string $url URL вебхука
     * @return bool
     * @throws WebhookManualSetupException Всегда
     */
    public function installWebhook(string $url): bool
    {
        throw new WebhookManualSetupException(
            'WhatsApp: задайте Callback URL и Verify Token в Meta App Dashboard → WhatsApp → Configuration и подпишитесь на поле "messages".'
        );
    }

    /** Удаление вебхука не поддерживается (настройка вручную в Meta App Dashboard).
     * @return bool
     * @throws WebhookManualSetupException Всегда
     */
    public function removeWebhook(): bool
    {
        throw new WebhookManualSetupException(
            'WhatsApp: отключите вебхук в Meta App Dashboard → WhatsApp → Configuration.'
        );
    }

    /** Получить пользователя. WhatsApp не отдаёт профиль по номеру — минимальный DTO.
     * @param string $id Телефон (wa_id)
     * @return UserDto Минимальные данные
     */
    public function getUser(string $id): UserDto
    {
        return new UserDto(id: $id, phone: $id);
    }

    /** Извлечь value из конверта вебхука.
     * @param array $payload Декодированное тело
     * @return array entry[0].changes[0].value или []
     */
    private function extractValue(array $payload): array
    {
        return $payload['entry'][0]['changes'][0]['value'] ?? [];
    }
}