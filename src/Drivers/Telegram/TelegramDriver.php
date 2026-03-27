<?php

namespace Govorun\Drivers\Telegram;

use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\ContactDto;
use GuzzleHttp\ClientInterface;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Contracts\MessengerDriver;

/** Драйвер мессенджера Telegram.
 * Реализует взаимодействие с Telegram Bot API: разбор входящих обновлений,
 * отправку/редактирование/удаление сообщений, управление вебхуками
 * и получение информации о пользователях.
 */
class TelegramDriver implements MessengerDriver
{
    /** @var string Токен бота */
    private string $token;

    /** @var string|null Секретный ключ для верификации вебхуков */
    private ?string $secret;

    /** Создать экземпляр драйвера Telegram.
     * @param array<string, mixed> $config Конфигурация драйвера (token, secret)
     * @param ClientInterface $client HTTP-клиент для запросов к API
     */
    public function __construct(
        private array $config,
        private ClientInterface $client,
    ) {
        $this->token = $config['token'];
        $this->secret = $config['secret'] ?? null;
    }

    /** Проверить подлинность входящего вебхук-запроса.
     * @param Request $request Входящий HTTP-запрос
     * @return bool Результат верификации
     */
    public function verifyWebhook(Request $request): bool
    {
        if ($this->secret === null) {
            return true;
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return $header !== null && hash_equals($this->secret, $header);
    }

    /** Разобрать входящий вебхук-запрос в объект сообщения.
     * @param Request $request Входящий HTTP-запрос
     * @return IncomingMessage Разобранное входящее сообщение
     * @throws \RuntimeException Если тип обновления не поддерживается
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        $data = $request->json();

        if (isset($data['callback_query'])) {
            return $this->parseCallbackQuery($data);
        }

        if (!isset($data['message'])) {
            throw new \RuntimeException('Unsupported Telegram update type');
        }

        return $this->parseMessage($data);
    }

    /** Разобрать callback-запрос (нажатие inline-кнопки).
     * @param array<string, mixed> $data Сырые данные обновления
     * @return IncomingMessage Входящее сообщение типа Action
     */
    private function parseCallbackQuery(array $data): IncomingMessage
    {
        $cq = $data['callback_query'];
        $from = $cq['from'];
        $chat = $cq['message']['chat'] ?? $cq['from'];
        $messageId = (string) ($cq['message']['message_id'] ?? $cq['id']);

        [$action, $params] = $this->parseCallbackData($cq['data'] ?? '');

        return new IncomingMessage(
            id: $messageId,
            chatId: (string) $chat['id'],
            driverName: 'telegram',
            text: null,
            user: $this->parseUser($from),
            type: ContentType::Action,
            action: $action,
            actionParams: $params,
            raw: $data,
        );
    }

    /** Разобрать обычное сообщение (текст, медиа, контакт, локация, событие).
     * @param array<string, mixed> $data Сырые данные обновления
     * @return IncomingMessage Входящее сообщение соответствующего типа
     */
    private function parseMessage(array $data): IncomingMessage
    {
        $message = $data['message'];
        $from = $message['from'];
        $chatId = (string) $message['chat']['id'];
        $messageId = (string) $message['message_id'];
        $user = $this->parseUser($from);

        // Events
        foreach (['new_chat_members', 'left_chat_member', 'new_chat_title', 'new_chat_photo', 'group_chat_created'] as $eventKey) {
            if (isset($message[$eventKey])) {
                return new IncomingMessage(
                    id: $messageId, chatId: $chatId, driverName: 'telegram',
                    text: null, user: $user, type: ContentType::Event,
                    event: $eventKey, raw: $data,
                );
            }
        }

        // Location
        if (isset($message['location'])) {
            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'telegram',
                text: null, user: $user, type: ContentType::Location,
                location: new LocationDto(
                    latitude: $message['location']['latitude'],
                    longitude: $message['location']['longitude'],
                    raw: $message['location'],
                ),
                raw: $data,
            );
        }

        // Contact
        if (isset($message['contact'])) {
            $c = $message['contact'];
            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'telegram',
                text: null, user: $user, type: ContentType::Contact,
                contact: new ContactDto(
                    phone: $c['phone_number'],
                    firstName: $c['first_name'] ?? null,
                    lastName: $c['last_name'] ?? null,
                    userId: isset($c['user_id']) ? (string) $c['user_id'] : null,
                    raw: $c,
                ),
                raw: $data,
            );
        }

        // Media types
        $mediaType = $this->detectMediaType($message);
        if ($mediaType !== null) {
            return new IncomingMessage(
                id: $messageId, chatId: $chatId, driverName: 'telegram',
                text: $message['caption'] ?? null, user: $user, type: ContentType::Media,
                media: $this->parseMedia($message, $mediaType),
                raw: $data,
            );
        }

        // Text (default)
        $text = $message['text'] ?? null;
        $referral = null;

        // /start with deep link parameter
        if ($text !== null && preg_match('/^\/start\s+(.+)$/u', $text, $m)) {
            $referral = $m[1];
        }

        return new IncomingMessage(
            id: $messageId, chatId: $chatId, driverName: 'telegram',
            text: $text, user: $user, type: ContentType::Text,
            referral: $referral, raw: $data,
        );
    }

    /** Разобрать данные пользователя в DTO.
     * @param array<string, mixed> $from Сырые данные пользователя из Telegram
     * @return UserDto Объект данных пользователя
     */
    private function parseUser(array $from): UserDto
    {
        return new UserDto(
            id: (string) $from['id'],
            firstName: $from['first_name'] ?? null,
            lastName: $from['last_name'] ?? null,
            username: $from['username'] ?? null,
            locale: $from['language_code'] ?? null,
            raw: $from,
        );
    }

    /** Разобрать строку callback_data в действие и параметры.
     * @param string $data Строка callback_data (формат: act:action;key:value)
     * @return array{string, array<string, string>} Массив из действия и параметров
     */
    private function parseCallbackData(string $data): array
    {
        $parts = explode(';', $data);
        $action = '';
        $params = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, 'act:')) {
                $action = substr($part, 4);
            } else {
                $kv = explode(':', $part, 2);
                if (count($kv) === 2) {
                    $params[$kv[0]] = $kv[1];
                }
            }
        }

        return [$action, $params];
    }

    /** Определить тип медиа в сообщении.
     * @param array<string, mixed> $message Данные сообщения
     * @return string|null Тип медиа или null, если медиа нет
     */
    private function detectMediaType(array $message): ?string
    {
        $types = ['photo', 'video', 'voice', 'audio', 'document', 'sticker', 'video_note', 'animation'];

        foreach ($types as $type) {
            if (isset($message[$type])) {
                return $type;
            }
        }

        return null;
    }

    /** Разобрать медиа-данные из сообщения в DTO.
     * @param array<string, mixed> $message Данные сообщения
     * @param string $type Тип медиа
     * @return MediaDto Объект данных медиа
     */
    private function parseMedia(array $message, string $type): MediaDto
    {
        if ($type === 'photo') {
            $photos = $message['photo'];
            $photo = end($photos); // largest size
            return new MediaDto(
                type: 'photo',
                fileId: $photo['file_id'],
                raw: $message['photo'],
            );
        }

        $mediaData = $message[$type];

        return new MediaDto(
            type: $type,
            fileId: $mediaData['file_id'] ?? null,
            mimeType: $mediaData['mime_type'] ?? null,
            fileSize: $mediaData['file_size'] ?? null,
            raw: $mediaData,
        );
    }

    /** Отправить исходящее сообщение пользователю.
     * @param OutgoingMessage $message Исходящее сообщение для отправки
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function send(OutgoingMessage $message): void
    {
        if ($message->media !== null) {
            $this->sendMedia($message);
            return;
        }

        $payload = [
            'chat_id' => $message->chatId,
            'text' => $message->text ?? '',
        ];

        if ($message->parseMode !== null) {
            $payload['parse_mode'] = $message->parseMode;
        }

        if ($message->keyboard !== null) {
            $payload['reply_markup'] = $this->buildKeyboardMarkup($message->keyboard);
        }

        $this->apiCall('sendMessage', $payload);
    }

    /** Редактировать ранее отправленное сообщение.
     * @param string $messageId Идентификатор сообщения для редактирования
     * @param OutgoingMessage $message Новое содержимое сообщения
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        $payload = [
            'chat_id' => $message->chatId,
            'message_id' => (int) $messageId,
            'text' => $message->text ?? '',
        ];

        if ($message->parseMode !== null) {
            $payload['parse_mode'] = $message->parseMode;
        }

        if ($message->keyboard !== null) {
            $payload['reply_markup'] = $this->buildKeyboardMarkup($message->keyboard);
        }

        $this->apiCall('editMessageText', $payload);
    }

    /** Удалить сообщение из чата.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function delete(string $messageId, string $chatId): void
    {
        $this->apiCall('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
        ]);
    }

    /** Отправить медиа-сообщение (фото, видео, документ и др.).
     * @param OutgoingMessage $message Исходящее сообщение с медиа
     * @return void
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function sendMedia(OutgoingMessage $message): void
    {
        $media = $message->media;
        $type = $media['type'];

        $methodMap = [
            'photo' => 'sendPhoto',
            'document' => 'sendDocument',
            'voice' => 'sendVoice',
            'video' => 'sendVideo',
            'audio' => 'sendAudio',
            'animation' => 'sendAnimation',
            'sticker' => 'sendSticker',
        ];

        $method = $methodMap[$type] ?? 'sendDocument';

        $payload = [
            'chat_id' => $message->chatId,
            $type => $media['url'],
        ];

        if ($message->text !== null) {
            $payload['caption'] = $message->text;
        }

        if ($message->parseMode !== null) {
            $payload['parse_mode'] = $message->parseMode;
        }

        $this->apiCall($method, $payload);
    }

    /** Построить разметку клавиатуры для Telegram API.
     * @param array<string, mixed> $keyboard Данные клавиатуры
     * @return array<string, mixed> Разметка клавиатуры для reply_markup
     */
    private function buildKeyboardMarkup(array $keyboard): array
    {
        if ($keyboard['remove']) {
            return ['remove_keyboard' => true];
        }

        $rows = [];
        foreach ($keyboard['rows'] as $row) {
            $buttons = [];
            foreach ($row as $btn) {
                $buttons[] = $this->buildButton($btn, $keyboard['type']);
            }
            $rows[] = $buttons;
        }

        if ($keyboard['type'] === 'reply') {
            return ['keyboard' => $rows, 'resize_keyboard' => true, 'one_time_keyboard' => true];
        }

        return ['inline_keyboard' => $rows];
    }

    /** Построить данные одной кнопки для Telegram API.
     * @param array<string, mixed> $btn Данные кнопки
     * @param string $keyboardType Тип клавиатуры (reply/inline)
     * @return array<string, mixed> Данные кнопки для API
     */
    private function buildButton(array $btn, string $keyboardType): array
    {
        $result = ['text' => $btn['text']];

        if ($keyboardType === 'reply') {
            if (!empty($btn['requestContact'])) {
                $result['request_contact'] = true;
            }
            if (!empty($btn['requestLocation'])) {
                $result['request_location'] = true;
            }
            return $result;
        }

        // Inline button
        if (isset($btn['url'])) {
            $result['url'] = $btn['url'];
        } elseif (isset($btn['action'])) {
            $result['callback_data'] = $this->buildCallbackData($btn['action'], $btn['param'] ?? []);
        }

        return $result;
    }

    /** Построить строку callback_data из действия и параметров.
     * @param string $action Имя действия
     * @param array<string, string> $params Параметры действия
     * @return string Сериализованная строка callback_data
     */
    private function buildCallbackData(string $action, array $params): string
    {
        $parts = ['act:' . $action];
        foreach ($params as $key => $value) {
            $parts[] = $key . ':' . $value;
        }
        return implode(';', $parts);
    }

    /** Выполнить вызов Telegram Bot API.
     * @param string $method Метод API (например, sendMessage)
     * @param array<string, mixed> $payload Данные запроса
     * @return array<string, mixed> Ответ API
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function apiCall(string $method, array $payload): array
    {
        $response = $this->client->request('POST', $this->apiUrl($method), [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    /** Установить вебхук для получения обновлений.
     * @param string $url URL-адрес для установки вебхука
     * @return bool Успешность установки
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function installWebhook(string $url): bool
    {
        $payload = ['url' => $url];

        if ($this->secret !== null) {
            $payload['secret_token'] = $this->secret;
        }

        $result = $this->apiCall('setWebhook', $payload);

        return ($result['ok'] ?? false) === true;
    }

    /** Удалить установленный вебхук.
     * @return bool Успешность удаления
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function removeWebhook(): bool
    {
        $result = $this->apiCall('deleteWebhook', []);

        return ($result['ok'] ?? false) === true;
    }

    /** Получить информацию о пользователе по идентификатору.
     * @param string $id Идентификатор пользователя
     * @return UserDto Данные пользователя
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getUser(string $id): UserDto
    {
        $result = $this->apiCall('getChat', ['chat_id' => $id]);
        $data = $result['result'] ?? [];

        return new UserDto(
            id: (string) ($data['id'] ?? $id),
            firstName: $data['first_name'] ?? null,
            lastName: $data['last_name'] ?? null,
            username: $data['username'] ?? null,
            locale: $data['language_code'] ?? null,
            raw: $data,
        );
    }

    /** Сформировать полный URL метода Telegram Bot API.
     * @param string $method Метод API
     * @return string Полный URL
     */
    protected function apiUrl(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
