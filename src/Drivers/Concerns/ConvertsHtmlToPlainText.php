<?php

namespace Govorun\Drivers\Concerns;

/** Преобразование HTML-текста в обычный текст для платформ без HTML-разметки. */
trait ConvertsHtmlToPlainText
{
    /** Преобразовать HTML-текст в обычный текст.
     * Заменяет теги <a> на «текст (url)», удаляет остальные теги и декодирует HTML-сущности.
     * @param string $html HTML-строка
     * @return string Обычный текст
     */
    protected function htmlToPlainText(string $html): string
    {
        $withLinks = preg_replace_callback(
            '#<a\s+[^>]*href=["\']([^"\']+)["\'][^>]*>(.*?)</a>#is',
            fn ($m) => "{$m[2]} ({$m[1]})",
            $html,
        );

        $stripped = strip_tags($withLinks);

        return html_entity_decode($stripped, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}