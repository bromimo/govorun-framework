<?php

namespace Govorun\Messaging;

/** Кнопка клавиатуры для исходящего сообщения. */
class Button
{
    /** Создать экземпляр кнопки.
     * @param string      $text            Текст кнопки.
     * @param string|null $action          Действие при нажатии.
     * @param array|null  $param           Параметры действия.
     * @param string|null $url             URL-ссылка кнопки.
     * @param bool        $requestContact  Запросить контакт пользователя.
     * @param bool        $requestLocation Запросить геолокацию пользователя.
     */
    public function __construct(
        public string $text,
        public ?string $action = null,
        public ?array $param = null,
        public ?string $url = null,
        public bool $requestContact = false,
        public bool $requestLocation = false,
    ) {}

    /** Преобразовать кнопку в массив.
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['text' => $this->text];

        if ($this->action !== null) {
            $data['action'] = $this->action;
        }
        if ($this->param !== null) {
            $data['param'] = $this->param;
        }
        if ($this->url !== null) {
            $data['url'] = $this->url;
        }
        if ($this->requestContact) {
            $data['requestContact'] = true;
        }
        if ($this->requestLocation) {
            $data['requestLocation'] = true;
        }

        return $data;
    }
}
