<?php

namespace Govorun\Contracts;

/** Контракт синхронизатора профиля мессенджера с его API. */
interface ProfileSyncer
{
    /** Имя мессенджера (telegram|whatsapp|...).
     * @return string Имя
     */
    public function messenger(): string;

    /** Поддерживаемые секции профиля.
     * @return array<int, string> Список секций
     */
    public function sections(): array;

    /** Готов ли синк (есть конфиг профиля и токен).
     * @return bool Готовность
     */
    public function isConfigured(): bool;

    /** Синхронизировать одну секцию.
     * @param string $section Имя секции
     * @return bool true — пушнули, false — без изменений
     * @throws \Throwable При ошибке API
     */
    public function sync(string $section): bool;
}