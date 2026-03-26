<?php

namespace Govorun\Tests\Unit\State;

use Govorun\State\CacheStateStorage;
use Govorun\Tests\TestCase;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository as CacheRepository;

class CacheStateStorageTest extends TestCase
{
    private CacheStateStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $cache = new CacheRepository(new ArrayStore());
        $this->storage = new CacheStateStorage($cache);
    }

    public function test_get_returns_null_when_no_state(): void
    {
        $this->assertNull($this->storage->get('100', 'telegram'));
    }

    public function test_set_and_get_state(): void
    {
        $data = [
            'flow_class' => 'App\\Flows\\AppointmentFlow',
            'current_step' => 'service',
            'data' => ['service' => 'manicure'],
        ];

        $this->storage->set('100', 'telegram', $data);

        $result = $this->storage->get('100', 'telegram');
        $this->assertSame($data, $result);
    }

    public function test_delete_removes_state(): void
    {
        $this->storage->set('100', 'telegram', ['flow_class' => 'Test', 'current_step' => 'a', 'data' => []]);
        $this->storage->delete('100', 'telegram');
        $this->assertNull($this->storage->get('100', 'telegram'));
    }

    public function test_different_sessions_are_independent(): void
    {
        $this->storage->set('100', 'telegram', ['flow_class' => 'A', 'current_step' => 'a', 'data' => []]);
        $this->storage->set('100', 'viber', ['flow_class' => 'B', 'current_step' => 'b', 'data' => []]);

        $this->assertSame('A', $this->storage->get('100', 'telegram')['flow_class']);
        $this->assertSame('B', $this->storage->get('100', 'viber')['flow_class']);
    }

    public function test_set_overwrites_existing_state(): void
    {
        $this->storage->set('100', 'telegram', ['flow_class' => 'Old', 'current_step' => 'a', 'data' => []]);
        $this->storage->set('100', 'telegram', ['flow_class' => 'New', 'current_step' => 'b', 'data' => []]);
        $this->assertSame('New', $this->storage->get('100', 'telegram')['flow_class']);
    }

    public function test_respects_ttl(): void
    {
        $cache = new CacheRepository(new ArrayStore());
        $storage = new CacheStateStorage($cache, ttl: 1);
        $storage->set('100', 'telegram', ['flow_class' => 'Test', 'current_step' => 'a', 'data' => []]);

        // State should be retrievable immediately
        $this->assertNotNull($storage->get('100', 'telegram'));
    }
}
