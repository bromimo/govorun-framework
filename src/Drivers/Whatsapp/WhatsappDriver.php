<?php

namespace Govorun\Drivers\Whatsapp;

use Govorun\Http\Request;
use GuzzleHttp\ClientInterface;
use Govorun\Http\WebhookResponse;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use Govorun\Messaging\Dto\LocationDto;
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
     * @throws \RuntimeException Если тип не поддержан или сообщение отсутствует
     */
    public function parseUpdate(Request $request): IncomingMessage
    {
        $value = $this->extractValue($request->json());
        $message = $value['messages'][0] ?? null;

        if ($message === null) {
            throw new \RuntimeException('WhatsApp: no message in webhook payload');
        }

        $chatId = (string) ($message['from'] ?? '');
        $messageId = (string) ($message['id'] ?? '');
        $type = (string) ($message['type'] ?? '');
        $contact = $value['contacts'][0] ?? [];

        $user = new UserDto(
            id: $chatId,
            firstName: $contact['profile']['name'] ?? null,
            phone: $chatId,
            raw: $contact,
        );

        return match ($type) {
            'text'        => $this->buildText($message, $messageId, $chatId, $user),
            'interactive' => $this->parseInteractive($message, $messageId, $chatId, $user),
            'button'      => $this->buildButtonReply($message, $messageId, $chatId, $user),
            'image', 'video', 'audio', 'voice', 'document', 'sticker'
                          => $this->parseMedia($message, $type, $messageId, $chatId, $user),
            'location'    => $this->parseLocation($message, $messageId, $chatId, $user),
            'contacts'    => $this->parseContact($message, $messageId, $chatId, $user),
            default       => throw new \RuntimeException("Unsupported WhatsApp message type: {$type}"),
        };
    }

    /** Отправить сообщение.
     * @param OutgoingMessage $message Сообщение
     * @return string|null WAMID или null
     * @throws \RuntimeException При ошибке API
     */
    public function send(OutgoingMessage $message): ?string
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $message->chatId,
        ];

        $hasKeyboard = $message->keyboard !== null && ($message->keyboard['remove'] ?? false) !== true;

        if ($hasKeyboard) {
            $payload = array_merge($payload, $this->buildInteractive($message));
        } elseif ($message->media !== null && isset($message->media['url'])) {
            $payload = array_merge($payload, $this->buildMedia($message));
        } else {
            $payload['type'] = 'text';
            $payload['text'] = [
                'preview_url' => true,
                'body' => $this->htmlToPlainText($message->text ?? ''),
            ];
        }

        $response = $this->apiCall("{$this->phoneNumberId}/messages", $payload);

        return $response['messages'][0]['id'] ?? null;
    }

    /** Редактирование не поддерживается WhatsApp.
     * @param string $messageId Идентификатор сообщения
     * @param OutgoingMessage $message Новое содержимое
     * @return void
     * @throws \RuntimeException Всегда
     */
    public function edit(string $messageId, OutgoingMessage $message): void
    {
        throw new \RuntimeException('WhatsApp не поддерживает редактирование сообщений.');
    }

    /** Удаление не поддерживается WhatsApp.
     * @param string $messageId Идентификатор сообщения
     * @param string $chatId Идентификатор чата
     * @return void
     * @throws \RuntimeException Всегда
     */
    public function delete(string $messageId, string $chatId): void
    {
        throw new \RuntimeException('WhatsApp не поддерживает удаление сообщений.');
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

    /** Текстовое сообщение.
     * @param array $message Сообщение
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function buildText(array $message, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: $message['text']['body'] ?? '', user: $user, type: ContentType::Text,
            raw: $message,
        );
    }

    /** Нажатие interactive-кнопки (button_reply/list_reply) → Action.
     * @param array $message Сообщение
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function parseInteractive(array $message, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        $interactive = $message['interactive'] ?? [];
        $reply = $interactive['button_reply'] ?? $interactive['list_reply'] ?? [];

        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: null, user: $user, type: ContentType::Action,
            action: (string) ($reply['id'] ?? ''), actionParams: [], raw: $message,
        );
    }

    /** Ответ на quick-reply кнопку шаблона → Action.
     * @param array $message Сообщение
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function buildButtonReply(array $message, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        $action = (string) ($message['button']['payload'] ?? $message['button']['text'] ?? '');

        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: null, user: $user, type: ContentType::Action,
            action: $action, actionParams: [], raw: $message,
        );
    }

    /** Медиа: заполняем fileId (бинарь тянется лениво через downloadMedia).
     * @param array $message Сообщение
     * @param string $type Тип WhatsApp
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function parseMedia(array $message, string $type, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        $typeMap = [
            'image' => 'photo', 'video' => 'video', 'audio' => 'audio',
            'voice' => 'voice', 'document' => 'document', 'sticker' => 'photo',
        ];
        $data = $message[$type] ?? [];

        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: $data['caption'] ?? null, user: $user, type: ContentType::Media,
            media: new MediaDto(
                type: $typeMap[$type] ?? $type,
                fileId: $data['id'] ?? null,
                mimeType: $data['mime_type'] ?? null,
                fileSize: isset($data['file_size']) ? (int) $data['file_size'] : null,
                raw: $data,
            ),
            raw: $message,
        );
    }

    /** Геолокация.
     * @param array $message Сообщение
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function parseLocation(array $message, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        $loc = $message['location'] ?? [];

        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: null, user: $user, type: ContentType::Location,
            location: new LocationDto(
                latitude: (float) ($loc['latitude'] ?? 0),
                longitude: (float) ($loc['longitude'] ?? 0),
                raw: $loc,
            ),
            raw: $message,
        );
    }

    /** Контакт (первый из массива).
     * @param array $message Сообщение
     * @param string $id WAMID
     * @param string $chatId Телефон
     * @param UserDto $user Пользователь
     * @return IncomingMessage
     */
    private function parseContact(array $message, string $id, string $chatId, UserDto $user): IncomingMessage
    {
        $c = $message['contacts'][0] ?? [];

        return new IncomingMessage(
            id: $id, chatId: $chatId, driverName: 'whatsapp',
            text: null, user: $user, type: ContentType::Contact,
            contact: new ContactDto(
                phone: (string) ($c['phones'][0]['phone'] ?? ''),
                firstName: $c['name']['first_name'] ?? null,
                lastName: $c['name']['last_name'] ?? null,
                raw: $c,
            ),
            raw: $message,
        );
    }

    /** Построить блок медиа Graph API.
     * @param OutgoingMessage $message Сообщение
     * @return array Часть payload (type + блок по типу)
     */
    private function buildMedia(OutgoingMessage $message): array
    {
        $map = [
            'photo' => 'image', 'image' => 'image', 'video' => 'video',
            'audio' => 'audio', 'voice' => 'audio', 'document' => 'document', 'animation' => 'video',
        ];
        $type = $map[$message->media['type'] ?? 'document'] ?? 'document';

        $node = ['link' => $message->media['url']];
        $caption = $this->htmlToPlainText($message->text ?? '');
        if ($caption !== '' && $type !== 'audio') {
            $node['caption'] = $caption;
        }
        if ($type === 'document' && isset($message->media['filename'])) {
            $node['filename'] = $message->media['filename'];
        }

        return ['type' => $type, $type => $node];
    }

    /** Построить interactive-сообщение: ≤3 кнопок → button, 4–10 → list.
     * @param OutgoingMessage $message Сообщение
     * @return array Часть payload (type=interactive + interactive)
     */
    private function buildInteractive(OutgoingMessage $message): array
    {
        $buttons = $this->flattenButtons($message->keyboard['rows'] ?? []);
        $bodyText = $this->htmlToPlainText($message->text ?? '');
        if ($bodyText === '') {
            $bodyText = ' ';
        }

        if (count($buttons) <= 3) {
            $replies = array_map(
                fn (array $b) => ['type' => 'reply', 'reply' => ['id' => $this->buttonId($b), 'title' => $b['text']]],
                $buttons,
            );

            return ['type' => 'interactive', 'interactive' => [
                'type' => 'button',
                'body' => ['text' => $bodyText],
                'action' => ['buttons' => $replies],
            ]];
        }

        $rows = array_map(
            fn (array $b) => ['id' => $this->buttonId($b), 'title' => $b['text']],
            $buttons,
        );

        return ['type' => 'interactive', 'interactive' => [
            'type' => 'list',
            'body' => ['text' => $bodyText],
            'action' => ['button' => 'Меню', 'sections' => [['rows' => $rows]]],
        ]];
    }

    /** Сплющить двумерный массив рядов кнопок в плоский список.
     * @param array $rows Ряды кнопок
     * @return array Плоский список кнопок
     */
    private function flattenButtons(array $rows): array
    {
        $flat = [];
        foreach ($rows as $row) {
            foreach ((array) $row as $btn) {
                $flat[] = $btn;
            }
        }

        return $flat;
    }

    /** Идентификатор кнопки для WhatsApp (action или текст).
     * @param array $btn Кнопка (Button->toArray())
     * @return string Идентификатор
     */
    private function buttonId(array $btn): string
    {
        return (string) ($btn['action'] ?? $btn['text'] ?? '');
    }

    /** Вызвать Graph API (Bearer JSON для POST, query для GET).
     * @param string $path Путь относительно версии
     * @param array $payload Тело/параметры
     * @param string $method HTTP-метод
     * @return array Декодированный ответ
     * @throws \RuntimeException При ошибке API
     */
    private function apiCall(string $path, array $payload, string $method = 'POST'): array
    {
        $options = [
            'headers' => ['Authorization' => "Bearer {$this->accessToken}"],
            'http_errors' => false,
        ];
        if ($method === 'POST') {
            $options['json'] = $payload;
        } elseif (! empty($payload)) {
            $options['query'] = $payload;
        }

        $response = $this->client->request($method, $this->url($path), $options);

        return $this->assertOk($path, $response->getBody()->getContents());
    }

    /** Построить полный URL Graph API.
     * @param string $path Путь относительно версии
     * @return string Полный URL
     */
    private function url(string $path): string
    {
        return self::BASE_URL . $this->apiVersion . '/' . ltrim($path, '/');
    }

    /** Проверить ответ Graph API.
     * @param string $path Путь (для текста ошибки)
     * @param string $body Сырое тело ответа
     * @return array Декодированный ответ
     * @throws \RuntimeException Если тело не парсится или есть error
     */
    private function assertOk(string $path, string $body): array
    {
        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            throw new \RuntimeException("WhatsApp {$path}: invalid response body — {$body}");
        }

        if (isset($decoded['error'])) {
            $code = $decoded['error']['code'] ?? 0;
            $msg = $decoded['error']['message'] ?? 'unknown error';

            throw new \RuntimeException("WhatsApp {$path} failed [{$code}]: {$msg}");
        }

        return $decoded;
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