<?php declare(strict_types=1);

class TestCase extends Orchestra\Testbench\TestCase
{

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application $app
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
     * @param  Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // reset base path to point to our package's src directory
        //$app['path.base'] = __DIR__ . '/../src';

        try {
            (new Dotenv\Dotenv(__DIR__ . '/../', '.env'))->load();
        } catch (Exception $e) {
            // ignore
        }

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
            file_put_contents(__DIR__ . '/../sql-log.sql', '-- ' . json_encode($sql->bindings) . "\n\n", FILE_APPEND);
        });
    }

    /**
     * @param callable $callback
     * @param string $expectedExceptionClass
     */
    public function assertException($callback, $expectedExceptionClass)
    {
        $thrownExceptionClass = null;
        try {
            $callback();
        } catch (Exception $e) {
            $thrownExceptionClass = get_class($e);
        }
        $this->assertEquals($expectedExceptionClass, $thrownExceptionClass,
            'Failed to assert that ' . json_encode($thrownExceptionClass) . ' matches expected exception ' . json_encode($expectedExceptionClass) . '.');
    }

    /**
     * @param callable $callback
     * @param int $severity
     * @param null $messageRegex
     */
    public function assertErrorException($callback, $severity, $messageRegex = null)
    {
        $thrownExceptionClass = null;
        try {
            $callback();
        } catch (Exception $e) {
            $thrownExceptionClass = get_class($e);
        }
        $this->assertEquals(ErrorException::class, $thrownExceptionClass,
            'Failed to assert that ' . json_encode($thrownExceptionClass) . ' is a ErrorException.');
        /** @var ErrorException $e */
        if ($messageRegex !== null) {
            $this->assertTrue(preg_match($messageRegex, $e->getMessage()) !== false,
                'Failed to assert that message ' . json_encode($e->getMessage()) . ' matches regex ' . json_encode($messageRegex) . '.');
        }
        $this->assertEquals($severity, $e->getSeverity(), 'Failed to assert that severity matches.');
    }
}
