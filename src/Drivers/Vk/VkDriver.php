<?php

namespace Govorun\Drivers\Vk;

use Govorun\Http\Request;
use GuzzleHttp\ClientInterface;
use Govorun\Http\WebhookResponse;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Contracts\WebhookResponder;
use Govorun\Exceptions\WebhookManualSetupException;
use Govorun\Drivers\Concerns\ConvertsHtmlToPlainText;

/** Драйвер мессенджера ВКонтакте (Callback API).
 * Реализует приём событий Callback API, подтверждение сервера,
 * отправку/редактирование/удаление сообщений и получение пользователей.
 */
class VkDriver implements MessengerDriver, WebhookResponder
{
    use ConvertsHtmlToPlainText;

    /** @var string Базовый URL VK API */
    private const BASE_URL = 'https://api.vk.com/method/';

    /** @var string Версия VK API */
    private const API_VERSION = '5.199';

    /** @var string Токен доступа сообщества */
    private string $token;

    /** @var string|null Секретный ключ Callback API (проверяется в теле запроса) */
    private ?string $secret;

    /** @var string Строка подтверждения Callback-сервера */
    private string $confirmation;

    /** Создать экземпляр драйвера ВКонтакте.
     * @param array<string, mixed> $config Конфигурация (token, secret, confirmation)
     * @param ClientInterface $client HTTP-клиент для запросов к API
     */
    public function __construct(
        private array $config,
        private ClientInterface $client,
    ) {
        $this->token = $config['token'] ?? '';
        $this->secret = $config['secret'] ?? null;
        $this->confirmation = $config['confirmation'] ?? '';
    }

    /** Проверить подлинность входящего вебхук-запроса по секрету в теле.
     * @param Request $request Входящий HTTP-запрос
     * @return bool Результат верификации
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->secret === null || $this->secret === '') {
            return true;
        }

        $secret = $request->json()['secret'] ?? null;

        return is_string($secret) && hash_equals($this->secret, $secret);
    }

    /** Сформировать ответ-подтверждение Callback-сервера.
     * @param Request $request Входящий HTTP-запрос
     * @return WebhookResponse|null Строка подтверждения или null для прочих событий
     */
    public function preflight(Request $request): ?WebhookResponse
    {
        if (($request->json()['type'] ?? '') === 'confirmation') {
            return WebhookResponse::text($this->confirmation);
        }

        return null;
    }

    /** Тело ответа на обработанное событие.
     * @return string Всегда "ok"
     */
    public function ackBody(): string
    {
        return 'ok';
    }

    /** Разобрать входящий вебхук-запрос в объект сообщения.
     * @param Request $request Входящий HTTP-запрос
     * @return IncomingMessage Разобранное входящее сообщение
     * @throws \RuntimeException Если тип события не поддерживается
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        $data = $request->json();
        $type = $data['type'] ?? '';
        $object = $data['object'] ?? [];

        return match ($type) {
            'message_new' => $this->parseMessageNew($object['message'] ?? $object),
            'message_event' => $this->parseMessageEvent($object),
            'group_join', 'group_leave' => $this->parseGroupEvent($object, $type),
            default => throw new \RuntimeException("Unsupported VK event: {$type}"),
        };
    }

    /** Отправить исходящее сообщение пользователю.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return string|null Идентификатор отправленного сообщения или null
     */
    public function send(OutgoingMessage $message): ?string
    {
        throw new \RuntimeException('not implemented yet');
    }

    /** Редактировать ранее отправленное сообщение.
     * @param string $messageId Идентификатор сообщения
     * @param OutgoingMessage $message Новое содержимое
     * @return void
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        throw new \RuntimeException('not implemented yet');
    }

    /** Удалить сообщение из чата.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     */
    public function delete(string $messageId, string $chatId): void
    {
        throw new \RuntimeException('not implemented yet');
    }

    /** Установить вебхук — VK требует ручной настройки Callback-сервера.
     * @param string $url URL-адрес вебхука
     * @return bool
     * @throws WebhookManualSetupException Всегда
     */
    public function installWebhook(string $url): bool
    {
        throw new WebhookManualSetupException(
            'VK Callback API настраивается вручную: Управление сообществом → Работа с API → '
            . "Callback API. Укажите адрес {$url}, впишите строку подтверждения из конфига "
            . 'и включите события message_new.',
        );
    }

    /** Удалить вебхук — VK требует ручного отключения Callback-сервера.
     * @return bool
     * @throws WebhookManualSetupException Всегда
     */
    public function removeWebhook(): bool
    {
        throw new WebhookManualSetupException(
            'Отключите Callback-сервер вручную: Управление сообществом → Работа с API → Callback API.',
        );
    }

    /** Получить информацию о пользователе по идентификатору.
     * @param string $id Идентификатор пользователя
     * @return UserDto Данные пользователя
     */
    public function getUser(string $id): UserDto
    {
        throw new \RuntimeException('not implemented yet');
    }

    /** Разобрать событие message_new в зависимости от содержимого.
     * @param array<string, mixed> $message Объект сообщения VK
     * @return IncomingMessage Входящее сообщение
     */
    private function parseMessageNew(array $message): IncomingMessage
    {
        $messageId = (string) ($message['id'] ?? '');
        $chatId = (string) ($message['peer_id'] ?? $message['from_id'] ?? '');
        $user = new UserDto(id: (string) ($message['from_id'] ?? ''), raw: $message);

        if (! empty($message['payload'])) {
            [$action, $params] = $this->parsePayload($message['payload']);

            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'vk',
                text: null, user: $user, type: ContentType::Action,
                action: $action, actionParams: $params, raw: $message,
            );
        }

        if (! empty($message['attachments'])) {
            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'vk',
                text: $message['text'] ?? null, user: $user, type: ContentType::Media,
                media: $this->parseAttachment($message['attachments'][0]), raw: $message,
            );
        }

        if (isset($message['geo']['coordinates'])) {
            $coords = $message['geo']['coordinates'];

            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'vk',
                text: null, user: $user, type: ContentType::Location,
                location: new LocationDto(
                    latitude: (float) $coords['latitude'],
                    longitude: (float) $coords['longitude'],
                    raw: $message['geo'],
                ),
                raw: $message,
            );
        }

        return new IncomingMessage(
            id: $messageId, chatId: $chatId, driverName: 'vk',
            text: $message['text'] ?? null, user: $user, type: ContentType::Text,
            raw: $message,
        );
    }

    /** Разобрать message_event (нажатие inline-callback кнопки).
     * @param array<string, mixed> $object Объект события VK
     * @return IncomingMessage Входящее сообщение типа Action
     */
    private function parseMessageEvent(array $object): IncomingMessage
    {
        [$action, $params] = $this->parsePayload($object['payload'] ?? []);
        $chatId = (string) ($object['peer_id'] ?? $object['user_id'] ?? '');

        return new IncomingMessage(
            id: (string) ($object['event_id'] ?? ''),
            chatId: $chatId, driverName: 'vk',
            text: null,
            user: new UserDto(id: (string) ($object['user_id'] ?? ''), raw: $object),
            type: ContentType::Action,
            action: $action, actionParams: $params, raw: $object,
        );
    }

    /** Разобрать сервисное событие сообщества (вступление/выход).
     * @param array<string, mixed> $object Объект события VK
     * @param string $event Тип события
     * @return IncomingMessage Входящее сообщение типа Event
     */
    private function parseGroupEvent(array $object, string $event): IncomingMessage
    {
        $userId = (string) ($object['user_id'] ?? '');

        return new IncomingMessage(
            id: '', chatId: $userId, driverName: 'vk',
            text: null, user: new UserDto(id: $userId, raw: $object),
            type: ContentType::Event, event: $event, raw: $object,
        );
    }

    /** Разобрать JSON-payload кнопки в действие и параметры.
     * @param mixed $payload Payload (JSON-строка из message_new или массив из message_event)
     * @return array{string, array<string, mixed>} Действие и параметры
     */
    private function parsePayload(mixed $payload): array
    {
        $decoded = is_array($payload) ? $payload : (json_decode((string) $payload, true) ?: []);

        return [
            (string) ($decoded['action'] ?? ''),
            (array) ($decoded['param'] ?? []),
        ];
    }

    /** Разобрать вложение VK в DTO медиа (best-effort URL).
     * @param array<string, mixed> $attachment Вложение из attachments[0]
     * @return MediaDto Объект медиа
     */
    private function parseAttachment(array $attachment): MediaDto
    {
        $type = $attachment['type'] ?? 'doc';
        $payload = $attachment[$type] ?? [];

        $url = null;
        if ($type === 'photo' && ! empty($payload['sizes'])) {
            $largest = end($payload['sizes']);
            $url = $largest['url'] ?? null;
        } elseif (isset($payload['url'])) {
            $url = $payload['url'];
        }

        $typeMap = [
            'photo' => 'photo',
            'video' => 'video',
            'doc' => 'document',
            'audio' => 'audio',
            'audio_message' => 'voice',
        ];

        return new MediaDto(
            type: $typeMap[$type] ?? 'document',
            url: $url,
            raw: $attachment,
        );
    }
}