<?php

namespace Govorun\Drivers\Whatsapp;

use GuzzleHttp\Client;
use Govorun\Contracts\ProfileSyncer;

/** Синхронизатор бизнес-профиля WhatsApp с Cloud API. */
class WhatsappProfileSyncer implements ProfileSyncer
{
    private const TEXT_SECTIONS = ['about', 'description', 'address', 'email', 'websites', 'vertical'];

    /** Создать синкер.
     * @param WhatsappDriver $driver Драйвер WhatsApp
     * @param array $profile Содержимое config/whatsapp_profile.php
     * @param string $token Access-токен
     */
    public function __construct(
        private WhatsappDriver $driver,
        private array $profile,
        private string $token,
    ) {}

    /** Собрать синкер из конфигурации приложения.
     * @return static Экземпляр
     */
    public static function make(): static
    {
        $config = app('config');
        $driver = app()->bound('driver.whatsapp')
            ? app()->make('driver.whatsapp')
            : new WhatsappDriver(config: $config->get('messenger.whatsapp', []), client: new Client());

        return new static(
            driver: $driver,
            profile: $config->get('whatsapp_profile') ?? [],
            token: (string) ($config->get('messenger.whatsapp.access_token') ?? ''),
        );
    }

    /** @return string Имя мессенджера */
    public function messenger(): string
    {
        return 'whatsapp';
    }

    /** @return array<int, string> Секции профиля */
    public function sections(): array
    {
        return [...self::TEXT_SECTIONS, 'photo'];
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
        if ($section === 'photo') {
            return $this->syncPhoto();
        }

        if (in_array($section, self::TEXT_SECTIONS, true)) {
            return $this->syncTextField($section);
        }

        throw new \RuntimeException("Unknown whatsapp profile section: {$section}");
    }

    /** Идемпотентно установить текстовое поле профиля.
     * @param string $field Имя поля
     * @return bool Пушнули ли
     */
    private function syncTextField(string $field): bool
    {
        if (! array_key_exists($field, $this->profile)) {
            return false;
        }

        $desired = $this->profile[$field];
        $current = $this->driver->getBusinessProfile()[$field] ?? null;

        if ($current === $desired) {
            return false;
        }

        $this->driver->setBusinessProfile([$field => $desired]);

        return true;
    }

    /** Загрузить и установить фото профиля (пушим всегда при наличии файла).
     * @return bool true если фото было, иначе false
     */
    private function syncPhoto(): bool
    {
        foreach (['jpg', 'png'] as $ext) {
            $path = storage_path("app/whatsapp-profile.{$ext}");
            if (is_file($path)) {
                $handle = $this->driver->uploadProfilePhoto($path);
                $this->driver->setBusinessProfile(['profile_picture_handle' => $handle]);

                return true;
            }
        }

        return false;
    }
}