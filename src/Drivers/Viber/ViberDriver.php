<?php

namespace Govorun\Drivers\Viber;

use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use GuzzleHttp\ClientInterface;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;
use Govorun\Drivers\Concerns\ConvertsHtmlToPlainText;

/** Драйвер мессенджера Viber.
 * Реализует взаимодействие с Viber REST API: верификацию вебхука,
 * разбор входящих событий, отправку сообщений и управление вебхуком.
 */
class ViberDriver implements MessengerDriver
{
    use ConvertsHtmlToPlainText;

    /** @var string Базовый URL Viber REST API */
    private const BASE_URL = 'https://chatapi.viber.com/pa/';

    /** @var string Токен аутентификации бота */
    private string $authToken;

    /** Создать экземпляр драйвера Viber.
     * @param array<string, mixed> $config Конфигурация драйвера (auth_token, profile)
     * @param ClientInterface $client HTTP-клиент для запросов к API
     */
    public function __construct(
        private array $config,
        private ClientInterface $client,
    ) {
        $this->authToken = $config['auth_token'] ?? '';
    }

    /** Проверить подлинность входящего вебхук-запроса через HMAC-SHA256.
     * @param Request $request Входящий HTTP-запрос
     * @return bool Результат верификации
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->authToken === '') {
            return false;
        }

        $signature = $request->header('X-Viber-Content-Signature');

        if ($signature === null || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $request->getContent() ?? '', $this->authToken);

        return hash_equals($expected, $signature);
    }

    /** Разобрать входящий вебхук-запрос в объект сообщения.
     * @param Request $request Входящий HTTP-запрос
     * @return IncomingMessage Разобранное входящее сообщение
     * @throws \RuntimeException Если тип события не поддерживается
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        $data = $request->json();
        $event = $data['event'] ?? '';

        return match ($event) {
            'message' => $this->parseMessageEvent($data),
            'conversation_started' => $this->parseConversationStartedEvent($data),
            'subscribed', 'unsubscribed' => $this->parseSubscriptionEvent($data, $event),
            'delivered', 'seen', 'failed' => $this->parseDeliveryEvent($data, $event),
            default => throw new \RuntimeException("Unsupported Viber event: {$event}"),
        };
    }

    /** Разобрать событие входящего сообщения.
     * @param array<string, mixed> $data Сырые данные вебхука
     * @return IncomingMessage Входящее сообщение
     * @throws \RuntimeException Если тип сообщения не поддерживается
     */
    private function parseMessageEvent(array $data): IncomingMessage
    {
        $sender = $data['sender'];
        $message = $data['message'];
        $user = $this->parseUser($sender);
        $messageId = (string) ($data['message_token'] ?? '');
        $chatId = (string) $sender['id'];
        $messageType = $message['type'] ?? 'text';

        if ($messageType === 'text') {
            return new IncomingMessage(
                id: $messageId,
                chatId: $chatId,
                driverName: 'viber',
                text: $message['text'] ?? null,
                user: $user,
                type: ContentType::Text,
                raw: $data,
            );
        }

        $mediaTypeMap = ['picture' => 'photo', 'video' => 'video', 'file' => 'document'];

        if (isset($mediaTypeMap[$messageType])) {
            $mediaType = $mediaTypeMap[$messageType];

            return new IncomingMessage(
                id: $messageId,
                chatId: $chatId,
                driverName: 'viber',
                text: $message['text'] ?? null,
                user: $user,
                type: ContentType::Media,
                media: new MediaDto(
                    type: $mediaType,
                    url: $message['media'] ?? null,
                    fileSize: isset($message['size']) ? (int) $message['size'] : null,
                    raw: $message,
                ),
                raw: $data,
            );
        }

        if ($messageType === 'contact') {
            $contact = $message['contact'];

            return new IncomingMessage(
                id: $messageId,
                chatId: $chatId,
                driverName: 'viber',
                text: null,
                user: $user,
                type: ContentType::Contact,
                contact: new ContactDto(
                    phone: $contact['phone_number'],
                    firstName: $contact['name'] ?? null,
                    raw: $contact,
                ),
                raw: $data,
            );
        }

        if ($messageType === 'location') {
            $loc = $message['location'];

            return new IncomingMessage(
                id: $messageId,
                chatId: $chatId,
                driverName: 'viber',
                text: null,
                user: $user,
                type: ContentType::Location,
                location: new LocationDto(
                    latitude: (float) $loc['lat'],
                    longitude: (float) $loc['lon'],
                    raw: $loc,
                ),
                raw: $data,
            );
        }

        if ($messageType === 'sticker') {
            return new IncomingMessage(
                id: $messageId,
                chatId: $chatId,
                driverName: 'viber',
                text: '',
                user: $user,
                type: ContentType::Text,
                raw: $data,
            );
        }

        throw new \RuntimeException("Viber message type not yet supported: {$messageType}");
    }

    /** Разобрать данные пользователя в DTO.
     * @param array<string, mixed> $sender Сырые данные отправителя из Viber
     * @return UserDto Объект данных пользователя
     */
    private function parseUser(array $sender): UserDto
    {
        return new UserDto(
            id: (string) ($sender['id'] ?? ''),
            firstName: $sender['name'] ?? null,
            locale: $sender['language'] ?? null,
            raw: $sender,
        );
    }

    /** Разобрать событие начала диалога (conversation_started).
     * @param array<string, mixed> $data Сырые данные вебхука
     * @return IncomingMessage Входящее сообщение типа Event
     */
    private function parseConversationStartedEvent(array $data): IncomingMessage
    {
        $user = $this->parseUser($data['user'] ?? []);

        return new IncomingMessage(
            id: (string) ($data['message_token'] ?? ''),
            chatId: $user->id,
            driverName: 'viber',
            text: null,
            user: $user,
            type: ContentType::Event,
            event: 'conversation_started',
            raw: $data,
        );
    }

    /** Разобрать событие подписки/отписки.
     * @param array<string, mixed> $data Сырые данные вебхука
     * @param string $event Тип события (subscribed/unsubscribed)
     * @return IncomingMessage Входящее сообщение типа Event
     */
    private function parseSubscriptionEvent(array $data, string $event): IncomingMessage
    {
        $sender = $data['user'] ?? ['id' => $data['user_id'] ?? ''];
        $user = $this->parseUser($sender);

        return new IncomingMessage(
            id: (string) ($data['message_token'] ?? ''),
            chatId: $user->id,
            driverName: 'viber',
            text: null,
            user: $user,
            type: ContentType::Event,
            event: $event,
            raw: $data,
        );
    }

    /** Разобрать событие доставки/просмотра/ошибки.
     * @param array<string, mixed> $data Сырые данные вебхука
     * @param string $event Тип события (delivered/seen/failed)
     * @return IncomingMessage Входящее сообщение типа Event
     */
    private function parseDeliveryEvent(array $data, string $event): IncomingMessage
    {
        $userId = (string) ($data['user_id'] ?? '');
        $user = new UserDto(id: $userId);

        return new IncomingMessage(
            id: (string) ($data['message_token'] ?? ''),
            chatId: $userId,
            driverName: 'viber',
            text: null,
            user: $user,
            type: ContentType::Event,
            event: $event,
            raw: $data,
        );
    }

    /** Отправить исходящее сообщение пользователю.
     * @param OutgoingMessage $message Исходящее сообщение для отправки
     * @return string|null Токен отправленного сообщения или null при ошибке
     */
    public function send(OutgoingMessage $message): ?string
    {
        if ($message->keyboard !== null) {
            return $this->sendRichMedia($message);
        }

        if ($message->media !== null) {
            $payload = $this->buildMediaPayload($message);
        } else {
            $payload = array_merge($this->buildBasePayload($message), [
                'type' => 'text',
                'text' => $this->htmlToPlainText($message->text ?? ''),
            ]);
        }

        $response = $this->post('send_message', $payload);

        if (($response['status'] ?? 1) !== 0) {
            return null;
        }

        return (string) ($response['message_token'] ?? '');
    }

    /** Редактировать ранее отправленное сообщение.
     * @param string $messageId Идентификатор сообщения для редактирования
     * @param OutgoingMessage $message Новое содержимое сообщения
     * @return void
     * @throws \RuntimeException Всегда — Viber не поддерживает редактирование
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        throw new \RuntimeException('Viber API does not support message editing');
    }

    /** Удалить сообщение из чата.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     * @throws \RuntimeException Всегда — Viber не поддерживает удаление
     */
    public function delete(string $messageId, string $chatId): void
    {
        throw new \RuntimeException('Viber API does not support message deletion');
    }

    /** Установить вебхук для получения обновлений.
     * @param string $url URL-адрес для установки вебхука
     * @return bool Успешность установки
     */
    public function installWebhook(string $url): bool
    {
        $payload = [
            'url' => $url,
            'event_types' => $this->config['profile']['event_types'] ?? ['message'],
            'send_name' => true,
            'send_photo' => true,
        ];

        $response = $this->post('set_webhook', $payload);

        return ($response['status'] ?? 1) === 0;
    }

    /** Удалить установленный вебхук.
     * @return bool Успешность удаления
     */
    public function removeWebhook(): bool
    {
        $response = $this->post('set_webhook', ['url' => '']);

        return ($response['status'] ?? 1) === 0;
    }

    /** Получить информацию о пользователе по идентификатору.
     * @param string $id Идентификатор пользователя
     * @return UserDto Данные пользователя
     */
    public function getUser(string $id): UserDto
    {
        $response = $this->post('get_user_details', ['id' => $id]);
        $user = $response['user'] ?? ['id' => $id];

        return $this->parseUser($user);
    }

    /** Выполнить POST-запрос к Viber REST API.
     * @param string $endpoint Эндпоинт API (например, send_message)
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Декодированный ответ API
     */
    private function post(string $endpoint, array $payload): array
    {
        $response = $this->client->request('POST', self::BASE_URL . $endpoint, [
            'headers' => [
                'X-Viber-Auth-Token' => $this->authToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($payload),
            'http_errors' => false,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /** Сформировать базовую часть payload с получателем и данными отправителя.
     * @param OutgoingMessage $message Исходящее сообщение
     * @return array<string, mixed> Базовый payload
     */
    private function buildBasePayload(OutgoingMessage $message): array
    {
        $profile = $this->config['profile'] ?? [];

        return [
            'receiver' => $message->chatId,
            'sender' => [
                'name' => $profile['sender_name'] ?? 'Bot',
                'avatar' => $profile['sender_avatar'] ?? null,
            ],
        ];
    }

    /** Сформировать payload для отправки медиасообщения.
     * @param OutgoingMessage $message Исходящее сообщение с медиа
     * @return array<string, mixed> Payload для API
     */
    private function buildMediaPayload(OutgoingMessage $message): array
    {
        $media = $message->media;
        $typeMap = ['photo' => 'picture', 'video' => 'video', 'document' => 'file'];
        $viberType = $typeMap[$media['type']] ?? 'picture';

        $payload = [
            'type' => $viberType,
            'media' => $media['url'] ?? '',
        ];

        if ($viberType === 'picture' && $message->text !== null) {
            $payload['text'] = $this->htmlToPlainText($message->text);
        }

        if ($viberType === 'video') {
            $payload['size'] = (int) ($media['size'] ?? 0);
            if (isset($media['duration'])) {
                $payload['duration'] = (int) $media['duration'];
            }
        }

        if ($viberType === 'file') {
            $payload['file_name'] = $media['file_name'] ?? 'file';
            $payload['size'] = (int) ($media['size'] ?? 0);
        }

        return array_merge($this->buildBasePayload($message), $payload);
    }

    /** Отправить сообщение с клавиатурой через rich_media.
     * @param OutgoingMessage $message Исходящее сообщение с клавиатурой
     * @return string|null Токен отправленного сообщения или null при ошибке
     */
    private function sendRichMedia(OutgoingMessage $message): ?string
    {
        $payload = array_merge($this->buildBasePayload($message), [
            'type' => 'rich_media',
            'min_api_version' => 7,
            'text' => $this->htmlToPlainText($message->text ?? ''),
            'rich_media' => $this->serializeRichMedia($message->keyboard),
        ]);

        $response = $this->post('send_message', $payload);

        if (($response['status'] ?? 1) !== 0) {
            return null;
        }

        return (string) ($response['message_token'] ?? '');
    }

    /** Сериализовать клавиатуру в формат Viber rich_media.
     * @param array<string, mixed> $keyboard Данные клавиатуры
     * @return array<string, mixed> Объект rich_media для Viber API
     */
    private function serializeRichMedia(array $keyboard): array
    {
        $buttons = [];

        foreach ($keyboard['rows'] as $row) {
            foreach ($row as $btn) {
                $buttons[] = $this->buildRichMediaButton($btn);
            }
        }

        return [
            'Type' => 'rich_media',
            'ButtonsGroupColumns' => 6,
            'ButtonsGroupRows' => 1,
            'BgColor' => '#FFFFFF',
            'Buttons' => $buttons,
        ];
    }

    /** Сформировать данные одной кнопки для rich_media.
     * @param array<string, mixed> $btn Данные кнопки
     * @return array<string, mixed> Данные кнопки для Viber API
     */
    private function buildRichMediaButton(array $btn): array
    {
        if (isset($btn['url'])) {
            $actionType = 'open-url';
            $actionBody = $btn['url'];
        } elseif (isset($btn['action'])) {
            $actionType = 'reply';
            $actionBody = $btn['action'];
        } else {
            $actionType = 'reply';
            $actionBody = $btn['text'] ?? '';
        }

        return [
            'Columns' => 6,
            'Rows' => 1,
            'ActionType' => $actionType,
            'ActionBody' => $actionBody,
            'Text' => $btn['text'] ?? '',
            'TextSize' => 'regular',
        ];
    }

}