<?php

namespace Govorun\Foundation;

/** Базовый сервис-провайдер фреймворка.
 * Все провайдеры должны наследовать этот класс и реализовывать
 * методы register() и boot() для регистрации сервисов в контейнере.
 */
abstract class ServiceProvider
{
    /** Создать экземпляр сервис-провайдера.
     * @param Application $app Экземпляр приложения
     */
    public function __construct(protected Application $app)
    {
    }

    /** Зарегистрировать сервисы в контейнере.
     * @return void
     */
    public function register(): void
    {
    }

    /** Выполнить действия после регистрации всех провайдеров.
     * @return void
     */
    public function boot(): void
    {
    }
}
