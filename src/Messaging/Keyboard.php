<?php

namespace Govorun\Messaging;

/** Клавиатура с кнопками для исходящего сообщения.
 * Поддерживает inline- и reply-режимы, а также удаление клавиатуры.
 * Ряды кнопок задаются через Keyboard::make()->buttons([[Button, …], [Button, …]]).
 */
class Keyboard
{
    /** @var array<int, array<int, Button>> */
    private array $rows = [];
    private string $type = 'inline';
    private bool $remove = false;
    private bool $resize = false;
    private bool $oneTime = false;

    /** Создать новую inline-клавиатуру.
     * @return static
     */
    public static function make(): static
    {
        return new static;
    }

    /** Создать reply-клавиатуру.
     * @return static
     */
    public static function reply(): static
    {
        $kb = new static;
        $kb->type = 'reply';

        return $kb;
    }

    /** Создать клавиатуру для удаления текущей клавиатуры у пользователя.
     * @return static
     */
    public static function remove(): static
    {
        $kb = new static;
        $kb->remove = true;

        return $kb;
    }

    /** Задать матрицу кнопок: внешний массив — ряды, внутренние — кнопки в ряду.
     * @param array<int, array<int, Button>> $rows Ряды с кнопками
     * @return static
     */
    public function buttons(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    /** Подогнать высоту кнопок под содержимое (только reply).
     * @param bool $resize Флаг подгонки размера
     * @return static
     */
    public function resize(bool $resize = true): static
    {
        $this->resize = $resize;

        return $this;
    }

    /** Скрыть клавиатуру после нажатия кнопки (только reply).
     * @param bool $oneTime Флаг одноразовой клавиатуры
     * @return static
     */
    public function oneTime(bool $oneTime = true): static
    {
        $this->oneTime = $oneTime;

        return $this;
    }

    /** Преобразовать клавиатуру в массив.
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'remove' => $this->remove,
            'resize' => $this->resize,
            'oneTime' => $this->oneTime,
            'rows' => array_map(
                fn (array $row) => array_map(
                    fn (Button $btn) => $btn->toArray(),
                    $row,
                ),
                $this->rows,
            ),
        ];
    }
}
