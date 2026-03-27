<?php

namespace Govorun\Messaging;

use Govorun\Messaging\Dto\UserDto;
use Govorun\Messaging\Dto\MediaDto;
use Govorun\Messaging\Dto\ContactDto;
use Govorun\Messaging\Dto\LocationDto;

/** Входящее сообщение от пользователя.
 * Содержит все данные, полученные из мессенджера: текст, медиа, геолокацию, контакт и т.д.
 */
class IncomingMessage
{
    /** Создать экземпляр входящего сообщения.
     * @param string           $id           Уникальный идентификатор сообщения.
     * @param string           $chatId       Идентификатор чата.
     * @param string           $driverName   Имя драйвера мессенджера.
     * @param string|null      $text         Текст сообщения.
     * @param UserDto          $user         Данные пользователя-отправителя.
     * @param ContentType      $type         Тип контента сообщения.
     * @param string|null      $action       Действие (callback-данные).
     * @param array|null       $actionParams Параметры действия.
     * @param string|null      $event        Событие мессенджера.
     * @param MediaDto|null    $media        Медиаконтент сообщения.
     * @param LocationDto|null $location     Геолокация.
     * @param ContactDto|null  $contact      Контактные данные.
     * @param string|null      $referral     Реферальная ссылка.
     * @param array            $raw          Сырые данные из мессенджера.
     */
    public function __construct(
        public string $id,
        public string $chatId,
        public string $driverName,
        public ?string $text,
        public UserDto $user,
        public ContentType $type,
        public ?string $action = null,
        public ?array $actionParams = null,
        public ?string $event = null,
        public ?MediaDto $media = null,
        public ?LocationDto $location = null,
        public ?ContactDto $contact = null,
        public ?string $referral = null,
        public array $raw = [],
    ) {}
}
