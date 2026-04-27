<?php

return [
    'default' => 'single',

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => getenv('GOVORUN_TEST_LOG_PATH') ?: sys_get_temp_dir() . '/govorun-test.log',
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => getenv('GOVORUN_TEST_LOG_PATH') ?: sys_get_temp_dir() . '/govorun-test.log',
            'level' => 'debug',
            'days' => 7,
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
    ],
];
