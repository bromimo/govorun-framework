<?php

namespace Govorun\Messaging;

/** Кнопка клавиатуры для исходящего сообщения.
 * Создаётся через Button::make() + fluent-сеттеры.
 */
final class Button
{
    /** Создать экземпляр кнопки.
     * Конструктор приватный — используйте Button::make() и fluent-сеттеры.
     */
    private function __construct(
        public string $text,
        public ?string $action = null,
        public ?array $param = null,
        public ?string $url = null,
        public bool $requestContact = false,
        public bool $requestLocation = false,
    ) {}

    /** Создать кнопку с указанным текстом.
     * @param string $text Текст кнопки
     * @return self
     */
    public static function make(string $text): self
    {
        return new self($text);
    }

    /** Задать action (и опционально param).
     * @param string $action Имя действия
     * @param array<string, mixed>|null $param Параметры действия
     * @return self
     */
    public function action(string $action, ?array $param = null): self
    {
        $this->action = $action;
        $this->param = $param;

        return $this;
    }

    /** Задать URL-ссылку кнопки.
     * @param string $url URL-адрес
     * @return self
     */
    public function url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /** Запросить у пользователя контакт по нажатию кнопки.
     * @param bool $request Флаг запроса контакта
     * @return self
     */
    public function requestContact(bool $request = true): self
    {
        $this->requestContact = $request;

        return $this;
    }

    /** Запросить у пользователя геолокацию по нажатию кнопки.
     * @param bool $request Флаг запроса геолокации
     * @return self
     */
    public function requestLocation(bool $request = true): self
    {
        $this->requestLocation = $request;

        return $this;
    }

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
