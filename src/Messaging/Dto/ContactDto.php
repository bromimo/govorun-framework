<?php

namespace Govorun\Messaging\Dto;

/** DTO контактных данных из входящего сообщения. */
class ContactDto
{
    /** Создать экземпляр DTO контакта.
     * @param string      $phone     Номер телефона.
     * @param string|null $firstName Имя контакта.
     * @param string|null $lastName  Фамилия контакта.
     * @param string|null $userId    Идентификатор пользователя.
     * @param array       $raw       Сырые данные из мессенджера.
     */
    public function __construct(
        public string $phone,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $userId = null,
        public array $raw = [],
    ) {}
}
