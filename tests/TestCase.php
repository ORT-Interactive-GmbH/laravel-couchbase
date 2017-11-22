<?php

class TestCase extends Orchestra\Testbench\TestCase
{

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            Mpociot\Couchbase\CouchbaseServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  Illuminate\Foundation\Application    $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        $config = require __DIR__ . '/config/database.php';

        /** @var \Illuminate\Config\Repository $appConfig */
        $appConfig = $app->make('config');

        $appConfig->set('app.key', 'ZsZewWyUJ5FsKp9lMwv4tYbNlegQilM7');

        $appConfig->set('database.default', 'couchbase');
        $appConfig->set('database.connections.mysql', $config['connections']['mysql']);
        $appConfig->set('database.connections.couchbase', $config['connections']['couchbase']);

        $appConfig->set('auth.model', 'User');
        $appConfig->set('auth.providers.users.model', 'User');
        $appConfig->set('cache.driver', 'array');

        \DB::listen(function (\Illuminate\Database\Events\QueryExecuted $sql) use (&$fh) {
            file_put_contents(__DIR__ . '/../sql-log.sql', $sql->sql . ";\n", FILE_APPEND);
            file_put_contents(__DIR__.'/../sql-log.sql', '-- '.json_encode($sql->bindings)."\n\n", FILE_APPEND);
        });
    }
}
