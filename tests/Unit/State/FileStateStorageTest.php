<?php

namespace Govorun\Tests\Unit\State;

use Govorun\State\FileStateStorage;
use Govorun\Tests\TestCase;

class FileStateStorageTest extends TestCase
{
    private string $storagePath;
    private FileStateStorage $storage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->storagePath = sys_get_temp_dir() . '/govorun_state_test_' . uniqid();
        mkdir($this->storagePath, 0777, true);
        $this->storage = new FileStateStorage($this->storagePath);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->storagePath . '/*.json') as $file) {
            unlink($file);
        }
        if (is_dir($this->storagePath)) {
            rmdir($this->storagePath);
        }
        parent::tearDown();
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
        $this->storage->set('100', 'telegram', ['flow_class' => 'Test']);
        $this->storage->delete('100', 'telegram');
        $this->assertNull($this->storage->get('100', 'telegram'));
    }

    public function test_different_sessions_are_independent(): void
    {
        $this->storage->set('100', 'telegram', ['flow_class' => 'A']);
        $this->storage->set('100', 'viber', ['flow_class' => 'B']);

        $this->assertSame('A', $this->storage->get('100', 'telegram')['flow_class']);
        $this->assertSame('B', $this->storage->get('100', 'viber')['flow_class']);
    }

    public function test_set_overwrites_existing_state(): void
    {
        $this->storage->set('100', 'telegram', ['flow_class' => 'Old']);
        $this->storage->set('100', 'telegram', ['flow_class' => 'New']);
        $this->assertSame('New', $this->storage->get('100', 'telegram')['flow_class']);
    }

    public function test_creates_directory_if_not_exists(): void
    {
        $path = sys_get_temp_dir() . '/govorun_state_new_' . uniqid();
        $storage = new FileStateStorage($path);

        $storage->set('100', 'telegram', ['test' => true]);
        $this->assertSame(['test' => true], $storage->get('100', 'telegram'));

        foreach (glob($path . '/*.json') as $file) { unlink($file); }
        rmdir($path);
    }
}
