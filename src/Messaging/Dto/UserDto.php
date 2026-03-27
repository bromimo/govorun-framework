<?php

namespace Govorun\Messaging\Dto;

/** DTO пользователя мессенджера. */
class UserDto
{
    /** Создать экземпляр DTO пользователя.
     * @param string      $id        Уникальный идентификатор пользователя.
     * @param string|null $firstName Имя пользователя.
     * @param string|null $lastName  Фамилия пользователя.
     * @param string|null $username  Никнейм пользователя.
     * @param string|null $phone     Номер телефона.
     * @param string|null $locale    Локаль пользователя.
     * @param array       $raw       Сырые данные из мессенджера.
     */
    public function __construct(
        public string $id,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $username = null,
        public ?string $phone = null,
        public ?string $locale = null,
        public array $raw = [],
    ) {}
}
