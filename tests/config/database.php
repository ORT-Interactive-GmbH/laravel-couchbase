<?php
return [
    'connections' => [
        'couchbase' => strpos(env('CB_VERSION', '4') , '4') === 0 ? [
            'name'       => 'couchbase',
            'driver'     => 'couchbase',
            'port'       => env('CB_PORT', 8091),
            'host'       => env('CB_HOST', '127.0.0.1'),
            'bucket'     => env('CB_DATABASE', env('CB_BUCKET', 'test-ing')),
            'user'       => env('CB_USER', env('CB_USERNAME')),
            'password'   => env('CB_PASSWORD'),
            'n1ql_hosts' => ['http://127.0.0.18093'],
        ] : [
            'name'       => 'couchbase',
            'driver'     => 'couchbase',
            'port'     => env('CB_PORT', 8093),
            'host'     => env('CB_HOST', '127.0.0.1'),
            'bucket'   => env('CB_DATABASE', env('CB_BUCKET', 'test-ing')),
            'username' => env('CB_USER', env('CB_USERNAME', 'dbuser_backend')),
            'password' => env('CB_PASSWORD', 'password_backend'),
            'auth_type' => env('CB_AUTH_TYPE', \Mpociot\Couchbase\Connection::AUTH_TYPE_USER_PASSWORD),
            'admin_username' => env('CB_ADMIN_USERNAME',env('CB_USER',env('CB_USERNAME','Administrator'))),
            'admin_password' => env('CB_ADMIN_PASSWORD',env('CB_PASSWORD','password')),
        ],
        'mysql'     => [
            'name'      => 'mysql',
            'driver'    => 'mysql',
            'host'      => env('MYSQL_HOST', '127.0.0.1'),
            'database'  => 'testing',
            'username'  => env('MYSQL_USER', 'root'),
            'password'  => env('MYSQL_PASSWORD', env('MYSQL_ROOT_PASSWORD')),
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],
];
