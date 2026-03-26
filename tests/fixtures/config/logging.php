<?php

return [
    'default' => 'single',

    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => sys_get_temp_dir() . '/govorun-test.log',
            'level' => 'debug',
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => sys_get_temp_dir() . '/govorun-test.log',
            'level' => 'debug',
            'days' => 7,
        ],
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
    ],
];
