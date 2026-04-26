<?php

namespace Govorun\Console;

use Throwable;
use Illuminate\Console\Command;
use Govorun\Drivers\Telegram\TelegramDriver;

/** Команда синхронизации Telegram-профиля бота с Bot API.
 * Читает config/bot_profile.php и storage/app/bot-profile.{jpg|mp4},
 * пушит значения через setMyName / setMyShortDescription / setMyDescription /
 * setMyCommands / setMyProfilePhoto / removeMyProfilePhoto.
 *
 * Поведение при ошибках: continue-and-report (не останавливается на первой).
 */
class ProfileSyncCommand extends Command
{
    protected $signature = 'bot:profile-sync
        {--only=* : Какие секции синкать (name|short_description|description|commands|photo)}
        {--skip=* : Какие секции пропустить}';

    protected $description = 'Push Telegram bot profile (name/about/description/commands/photo) to Bot API';

    private const SECTIONS = ['name', 'short_description', 'description', 'commands', 'photo'];

    /** Запустить синхронизацию профиля.
     * @return int 0 при успехе, 1 если хоть одна секция упала или конфигурация невалидна.
     */
    public function handle(): int
    {
        $config = app('config');

        $profile = $config->get('bot_profile');
        if ($profile === null) {
            $this->error('Профиль не найден. Сгенерирован ли проект из фабрики с включённым Telegram?');
            return self::FAILURE;
        }

        $token = $config->get('messenger.telegram.token');
        if ($token === null || $token === '') {
            $this->error('Telegram-токен не задан в .env (MESSENGER_TELEGRAM_TOKEN).');
            return self::FAILURE;
        }

        $sections = $this->resolveSections();
        if ($sections === null) {
            return self::FAILURE;
        }

        $driver = $this->resolveDriver();
        $errors = [];

        foreach ($sections as $section) {
            try {
                $this->syncSection($driver, $section, $profile);
                $this->info("✓ {$section}");
            } catch (Throwable $e) {
                $errors[$section] = $e->getMessage();
                $this->error("✗ {$section}: {$e->getMessage()}");
            }
        }

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Ошибки в ' . count($errors) . ' секциях:');
            foreach ($errors as $section => $msg) {
                $this->line("  - {$section}: {$msg}");
            }
            return self::FAILURE;
        }

        $this->info('Профиль синхронизирован.');
        return self::SUCCESS;
    }

    /** Разрулить список секций по флагам --only и --skip.
     * @return array<int, string>|null Список секций или null при ошибке.
     */
    private function resolveSections(): ?array
    {
        $only = $this->option('only');
        $skip = $this->option('skip');

        $invalid = array_diff([...$only, ...$skip], self::SECTIONS);
        if (! empty($invalid)) {
            $this->error('Неизвестные секции: ' . implode(', ', $invalid));
            $this->line('Допустимые: ' . implode(', ', self::SECTIONS));
            return null;
        }

        $sections = empty($only) ? self::SECTIONS : array_values(array_intersect(self::SECTIONS, $only));
        $sections = array_values(array_diff($sections, $skip));

        return $sections;
    }

    /** Получить экземпляр TelegramDriver из контейнера.
     * @return TelegramDriver
     */
    private function resolveDriver(): TelegramDriver
    {
        $app = app();

        if ($app->bound('driver.telegram')) {
            return $app->make('driver.telegram');
        }

        $config = app('config')->get('messenger.telegram', []);

        return new TelegramDriver(config: $config, client: new \GuzzleHttp\Client());
    }

    /** Синхронизировать одну секцию.
     * @param TelegramDriver $driver
     * @param string $section
     * @param array<string, mixed> $profile
     * @return void
     */
    private function syncSection(TelegramDriver $driver, string $section, array $profile): void
    {
        match ($section) {
            'name'              => $driver->setMyName((string) ($profile['name'] ?? '')),
            'short_description' => $driver->setMyShortDescription((string) ($profile['short_description'] ?? '')),
            'description'       => $driver->setMyDescription((string) ($profile['description'] ?? '')),
            'commands'          => $driver->setMyCommands($profile['commands'] ?? []),
            'photo'             => $this->syncPhoto($driver),
        };
    }

    /** Синхронизировать фото: статичное → animated → отсутствие → removeMyProfilePhoto.
     * @param TelegramDriver $driver
     * @return void
     */
    private function syncPhoto(TelegramDriver $driver): void
    {
        $jpgPath = storage_path('app/bot-profile.jpg');
        if (is_file($jpgPath)) {
            $driver->setMyProfilePhoto($jpgPath, 'static');
            return;
        }

        $mp4Path = storage_path('app/bot-profile.mp4');
        if (is_file($mp4Path)) {
            $driver->setMyProfilePhoto($mp4Path, 'animated');
            return;
        }

        $driver->removeMyProfilePhoto();
    }
}
