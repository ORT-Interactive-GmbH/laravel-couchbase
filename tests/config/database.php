<?php

return [

    'connections' => [

        'couchbase' => [
            'name'       => 'couchbase',
            'driver'     => 'couchbase',
            'port'       => '8091',
            'host'       => '127.0.0.1',
            'bucket'     => 'testing',
            'n1ql_hosts' => ['http://127.0.0.1:8093']
        ],

        'mysql' => [
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'testing',
            'username'  => 'root',
            'password'  => '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],

];
