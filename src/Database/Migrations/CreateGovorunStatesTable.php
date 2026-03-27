<?php

namespace Govorun\Database\Migrations;

use Illuminate\Database\Schema\Builder as SchemaBuilder;

/** Миграция для создания таблицы состояний govorun_states.
 * Создаёт таблицу для хранения пользовательских сессий (состояний потоков)
 * с уникальным индексом по паре chat_id + driver.
 */
class CreateGovorunStatesTable
{
    /** Выполнить миграцию — создать таблицу govorun_states.
     * @param SchemaBuilder $schema Построитель схемы базы данных
     * @return void
     */
    public function up(SchemaBuilder $schema): void
    {
        $schema->create('govorun_states', function ($table) {
            $table->id();
            $table->string('chat_id', 255);
            $table->string('driver', 50);
            $table->string('flow_class', 255);
            $table->string('current_step', 100);
            $table->json('data')->default('{}');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->unique(['chat_id', 'driver'], 'unique_session');
            $table->index('expires_at', 'idx_expires');
        });
    }

    /** Откатить миграцию — удалить таблицу govorun_states.
     * @param SchemaBuilder $schema Построитель схемы базы данных
     * @return void
     */
    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('govorun_states');
    }
}
