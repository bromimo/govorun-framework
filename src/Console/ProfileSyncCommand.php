<?php

namespace Govorun\Console;

use Illuminate\Console\Command;
use Govorun\Contracts\ProfileSyncer;

/** Команда синхронизации профилей ботов с API мессенджеров.
 * Оркестрирует per-driver синкеры (ProfileSyncer), резолвимые по конвенции
 * Govorun\Drivers\{Name}\{Name}ProfileSyncer. Continue-and-report.
 */
class ProfileSyncCommand extends Command
{
    protected $signature = 'bot:profile-sync
        {--messenger=* : Какие мессенджеры синкать (telegram|whatsapp|...); по умолчанию все включённые}
        {--only=* : Какие секции синкать}
        {--skip=* : Какие секции пропустить}';

    protected $description = 'Push bot profiles (Telegram/WhatsApp/...) to messenger APIs';

    /** Запустить синхронизацию.
     * @return int 0 при успехе, 1 если была ошибка или нет сконфигурированных профилей
     */
    public function handle(): int
    {
        $requested = $this->option('messenger');
        $messengers = ! empty($requested)
            ? $requested
            : app('config')->get('messenger.drivers', []);

        $syncers = [];
        foreach ($messengers as $messenger) {
            $syncer = $this->resolveSyncer($messenger);
            if ($syncer === null) {
                $this->warn("∘ {$messenger}: синхронизация профиля не поддерживается, пропуск.");
                continue;
            }
            if (! $syncer->isConfigured()) {
                $this->warn("∘ {$messenger}: профиль не сконфигурирован, пропуск.");
                continue;
            }
            $syncers[] = $syncer;
        }

        if (empty($syncers)) {
            $this->error('Нет сконфигурированных профилей для синхронизации.');

            return self::FAILURE;
        }

        $only = $this->option('only');
        $skip = $this->option('skip');
        $errors = [];

        foreach ($syncers as $syncer) {
            $messenger = $syncer->messenger();
            $this->line("[{$messenger}]");

            foreach ($this->resolveSections($syncer, $only, $skip) as $section) {
                try {
                    if ($syncer->sync($section)) {
                        $this->info("  ✓ {$section}");
                    } else {
                        $this->line("  ∘ {$section} (без изменений)");
                    }
                } catch (\Throwable $e) {
                    $errors["{$messenger}.{$section}"] = $e->getMessage();
                    $this->error("  ✗ {$section}: {$e->getMessage()}");
                }
            }
        }

        if (! empty($errors)) {
            $this->newLine();
            $this->error('Ошибки в ' . count($errors) . ' секциях:');
            foreach ($errors as $key => $msg) {
                $this->line("  - {$key}: {$msg}");
            }

            return self::FAILURE;
        }

        $this->info('Профиль синхронизирован.');

        return self::SUCCESS;
    }

    /** Резолвить синкер по конвенции именования.
     * @param string $messenger Имя мессенджера
     * @return ?ProfileSyncer Синкер или null если не поддержан
     */
    private function resolveSyncer(string $messenger): ?ProfileSyncer
    {
        $class = 'Govorun\\Drivers\\' . ucfirst($messenger) . '\\' . ucfirst($messenger) . 'ProfileSyncer';

        if (! class_exists($class) || ! is_subclass_of($class, ProfileSyncer::class)) {
            return null;
        }

        return $class::make();
    }

    /** Применить --only/--skip к секциям синкера.
     * @param ProfileSyncer $syncer Синкер
     * @param array $only Фильтр only
     * @param array $skip Фильтр skip
     * @return array<int, string> Итоговый список секций
     */
    private function resolveSections(ProfileSyncer $syncer, array $only, array $skip): array
    {
        $sections = $syncer->sections();

        if (! empty($only)) {
            $sections = array_values(array_intersect($sections, $only));
        }

        if (! empty($skip)) {
            $sections = array_values(array_diff($sections, $skip));
        }

        return $sections;
    }
}