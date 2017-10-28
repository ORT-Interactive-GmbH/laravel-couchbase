<?php
return [
    'connections' => [
        'couchbase' => [
            'name'       => 'couchbase',
            'driver'     => 'couchbase',
            'port'       => 8091,
            'host'       => '127.0.0.1',
            'bucket'     => false !== ($v = getenv('CB_BUCKET')) ? $v : 'test-ing',
            'user'       => false !== ($v = getenv('CB_USER')) ? $v : '',
            'password'   => false !== ($v = getenv('CB_PASSWORD')) ? $v : '',
            'n1ql_hosts' => ['http://127.0.0.18093'],
        ],
        'mysql'     => [
            'name'      => 'mysql',
            'driver'    => 'mysql',
            'host'      => '127.0.0.1',
            'database'  => 'testing',
            'username'  => false !== ($v = getenv('MYSQL_USER')) ? $v : 'root',
            'password'  => false !== ($v = getenv('MYSQL_PASSWORD')) ? $v : '',
            'charset'   => 'utf8',
            'collation' => 'utf8_unicode_ci',
            'prefix'    => '',
        ],
    ],
];
