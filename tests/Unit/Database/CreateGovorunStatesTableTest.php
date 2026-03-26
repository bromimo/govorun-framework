<?php

namespace Govorun\Tests\Unit\Database;

use Govorun\Database\Migrations\CreateGovorunStatesTable;
use Govorun\Tests\TestCase;
use Illuminate\Database\Capsule\Manager as Capsule;

class CreateGovorunStatesTableTest extends TestCase
{
    private Capsule $capsule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }

    public function test_up_creates_govorun_states_table(): void
    {
        $migration = new CreateGovorunStatesTable();
        $migration->up($this->capsule->getConnection()->getSchemaBuilder());

        $this->assertTrue(
            $this->capsule->getConnection()->getSchemaBuilder()->hasTable('govorun_states')
        );
    }

    public function test_table_has_required_columns(): void
    {
        $migration = new CreateGovorunStatesTable();
        $migration->up($this->capsule->getConnection()->getSchemaBuilder());

        $schema = $this->capsule->getConnection()->getSchemaBuilder();
        $this->assertTrue($schema->hasColumn('govorun_states', 'chat_id'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'driver'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'flow_class'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'current_step'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'data'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'expires_at'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'created_at'));
        $this->assertTrue($schema->hasColumn('govorun_states', 'updated_at'));
    }

    public function test_down_drops_table(): void
    {
        $migration = new CreateGovorunStatesTable();
        $schema = $this->capsule->getConnection()->getSchemaBuilder();

        $migration->up($schema);
        $this->assertTrue($schema->hasTable('govorun_states'));

        $migration->down($schema);
        $this->assertFalse($schema->hasTable('govorun_states'));
    }
}
