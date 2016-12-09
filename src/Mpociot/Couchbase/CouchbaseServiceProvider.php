<?php namespace Mpociot\Couchbase;

use Illuminate\Support\ServiceProvider;
use Mpociot\Couchbase\Eloquent\Model;

class CouchbaseServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     */
    public function boot()
    {
        Model::setConnectionResolver($this->app['db']);

        Model::setEventDispatcher($this->app['events']);
    }

    /**
     * Register the service provider.
     */
    public function register()
    {
        // Add database driver.
        $this->app->resolving('db', function ($db) {
            $db->extend('couchbase', function ($config) {
                return new Connection($config);
            });
        });
    }
}
