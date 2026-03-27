<?php

namespace Govorun\Messaging;

/** Клавиатура с кнопками для исходящего сообщения.
 * Поддерживает inline- и reply-режимы, а также удаление клавиатуры.
 */
class Keyboard
{
    private array $rows = [];
    private array $currentRow = [];
    private string $type = 'inline';
    private bool $remove = false;

    /** Создать новую inline-клавиатуру.
     * @return static
     */
    public static function make(): static
    {
        return new static();
    }

    /** Создать reply-клавиатуру.
     * @return static
     */
    public static function reply(): static
    {
        $kb = new static();
        $kb->type = 'reply';

        return $kb;
    }

    /** Создать клавиатуру для удаления текущей клавиатуры у пользователя.
     * @return static
     */
    public static function remove(): static
    {
        $kb = new static();
        $kb->remove = true;

        return $kb;
    }

    /** Добавить кнопку в текущий ряд.
     * @param string      $text            Текст кнопки.
     * @param string|null $action          Действие при нажатии.
     * @param array|null  $param           Параметры действия.
     * @param string|null $url             URL-ссылка кнопки.
     * @param bool        $requestContact  Запросить контакт пользователя.
     * @param bool        $requestLocation Запросить геолокацию пользователя.
     * @return static
     */
    public function button(
        string $text,
        ?string $action = null,
        ?array $param = null,
        ?string $url = null,
        bool $requestContact = false,
        bool $requestLocation = false,
    ): static {
        $this->currentRow[] = new Button($text, $action, $param, $url, $requestContact, $requestLocation);

        return $this;
    }

    /** Завершить текущий ряд и начать новый.
     * @return static
     */
    public function row(): static
    {
        if (! empty($this->currentRow)) {
            $this->rows[] = $this->currentRow;
            $this->currentRow = [];
        }

        return $this;
    }

    /** Преобразовать клавиатуру в массив.
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $rows = $this->rows;

        if (! empty($this->currentRow)) {
            $rows[] = $this->currentRow;
        }

        return [
            'type' => $this->type,
            'remove' => $this->remove,
            'rows' => array_map(
                fn (array $row) => array_map(
                    fn (Button $btn) => $btn->toArray(),
                    $row
                ),
                $rows
            ),
        ];
    }
}
