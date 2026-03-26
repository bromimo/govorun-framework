<?php

namespace Govorun\Database\Migrations;

use Illuminate\Database\Schema\Builder as SchemaBuilder;

class CreateGovorunStatesTable
{
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

    public function down(SchemaBuilder $schema): void
    {
        $schema->dropIfExists('govorun_states');
    }
}
