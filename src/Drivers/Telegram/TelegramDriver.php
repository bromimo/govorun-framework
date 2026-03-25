<?php

namespace Govorun\Drivers\Telegram;

use Govorun\Contracts\MessengerDriver;
use Govorun\Http\Request;
use Govorun\Messaging\ContentType;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\Dto\LocationDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\IncomingMessage;
use Govorun\Messaging\OutgoingMessage;
use GuzzleHttp\ClientInterface;

class TelegramDriver implements MessengerDriver
{
    private string $token;
    private ?string $secret;

    public function __construct(
        private array $config,
        private ClientInterface $client,
    ) {
        $this->token = $config['token'];
        $this->secret = $config['secret'] ?? null;
    }

    public function verifyWebhook(Request $request): bool
    {
        if ($this->secret === null) {
            return true;
        }

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        return $header !== null && hash_equals($this->secret, $header);
    }

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

    /**
     * @return array{string, array<string, string>}
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

    public function delete(string $messageId, string $chatId): void
    {
        $this->apiCall('deleteMessage', [
            'chat_id' => $chatId,
            'message_id' => (int) $messageId,
        ]);
    }

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

    private function buildCallbackData(string $action, array $params): string
    {
        $parts = ['act:' . $action];
        foreach ($params as $key => $value) {
            $parts[] = $key . ':' . $value;
        }
        return implode(';', $parts);
    }

    private function apiCall(string $method, array $payload): array
    {
        $response = $this->client->request('POST', $this->apiUrl($method), [
            'json' => $payload,
        ]);

        return json_decode($response->getBody()->getContents(), true) ?? [];
    }

    public function installWebhook(string $url): bool
    {
        // Implemented in Task 3
        return false;
    }

    public function removeWebhook(): bool
    {
        // Implemented in Task 3
        return false;
    }

    public function getUser(string $id): UserDto
    {
        // Implemented in Task 3
        return new UserDto(id: $id);
    }

    protected function apiUrl(string $method): string
    {
        return "https://api.telegram.org/bot{$this->token}/{$method}";
    }
}
