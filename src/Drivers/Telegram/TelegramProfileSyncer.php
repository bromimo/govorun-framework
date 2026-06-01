<?php

namespace Govorun\Drivers\Telegram;

use GuzzleHttp\Client;
use Govorun\Contracts\ProfileSyncer;

/** Синхронизатор профиля Telegram-бота с Bot API (name/short_description/description/commands/photo). */
class TelegramProfileSyncer implements ProfileSyncer
{
    private const SECTIONS = ['name', 'short_description', 'description', 'commands', 'photo'];

    /** Создать синкер.
     * @param TelegramDriver $driver Драйвер Telegram
     * @param array $profile Содержимое config/bot_profile.php
     * @param string $token Токен бота
     */
    public function __construct(
        private TelegramDriver $driver,
        private array $profile,
        private string $token,
    ) {}

    /** Собрать синкер из конфигурации приложения.
     * @return static Экземпляр
     */
    public static function make(): static
    {
        $config = app('config');
        $driver = app()->bound('driver.telegram')
            ? app()->make('driver.telegram')
            : new TelegramDriver(config: $config->get('messenger.telegram', []), client: new Client());

        return new static(
            driver: $driver,
            profile: $config->get('bot_profile') ?? [],
            token: (string) ($config->get('messenger.telegram.token') ?? ''),
        );
    }

    /** @return string Имя мессенджера */
    public function messenger(): string
    {
        return 'telegram';
    }

    /** @return array<int, string> Секции профиля */
    public function sections(): array
    {
        return self::SECTIONS;
    }

    /** @return bool Готов ли синк */
    public function isConfigured(): bool
    {
        return $this->profile !== [] && $this->token !== '';
    }

    /** Синхронизировать секцию.
     * @param string $section Секция
     * @return bool Пушнули ли
     * @throws \RuntimeException При неизвестной секции
     */
    public function sync(string $section): bool
    {
        return match ($section) {
            'name'              => $this->syncName(),
            'short_description' => $this->syncShortDescription(),
            'description'       => $this->syncDescription(),
            'commands'          => $this->syncCommands(),
            'photo'             => $this->syncPhoto(),
            default             => throw new \RuntimeException("Unknown telegram profile section: {$section}"),
        };
    }

    /** Идемпотентно установить имя бота: пропустить если совпадает.
     * @return bool true если пушили, false если пропустили.
     */
    private function syncName(): bool
    {
        $desired = (string) ($this->profile['name'] ?? '');

        if ($this->driver->getMyName() === $desired) {
            return false;
        }

        $this->driver->setMyName($desired);

        return true;
    }

    /** Идемпотентно установить short_description.
     * @return bool true если пушили, false если пропустили.
     */
    private function syncShortDescription(): bool
    {
        $desired = (string) ($this->profile['short_description'] ?? '');

        if ($this->driver->getMyShortDescription() === $desired) {
            return false;
        }

        $this->driver->setMyShortDescription($desired);

        return true;
    }

    /** Идемпотентно установить description.
     * @return bool true если пушили, false если пропустили.
     */
    private function syncDescription(): bool
    {
        $desired = (string) ($this->profile['description'] ?? '');

        if ($this->driver->getMyDescription() === $desired) {
            return false;
        }

        $this->driver->setMyDescription($desired);

        return true;
    }

    /** Идемпотентно установить список команд.
     * @return bool true если пушили, false если пропустили.
     */
    private function syncCommands(): bool
    {
        $desired = $this->profile['commands'] ?? [];
        $current = $this->driver->getMyCommands();
        $desiredNormalized = array_map(
            fn (array $c) => ['command' => (string) ($c['command'] ?? ''), 'description' => (string) ($c['description'] ?? '')],
            $desired,
        );

        if ($current === $desiredNormalized) {
            return false;
        }

        $this->driver->setMyCommands($desired);

        return true;
    }

    /** Синхронизировать фото: статичное → animated → отсутствие → removeMyProfilePhoto.
     * Идемпотентность для фото не применяется (Telegram не отдаёт отпечаток текущего файла).
     * @return bool Всегда true — пушим каждый раз.
     */
    private function syncPhoto(): bool
    {
        $jpgPath = storage_path('app/bot-profile.jpg');
        if (is_file($jpgPath)) {
            $this->driver->setMyProfilePhoto($jpgPath, 'static');

            return true;
        }

        $mp4Path = storage_path('app/bot-profile.mp4');
        if (is_file($mp4Path)) {
            $this->driver->setMyProfilePhoto($mp4Path, 'animated');

            return true;
        }

        $this->driver->removeMyProfilePhoto();

        return true;
    }
}