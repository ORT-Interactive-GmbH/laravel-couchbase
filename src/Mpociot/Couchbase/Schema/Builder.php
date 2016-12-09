<?php namespace Mpociot\Couchbase\Schema;

use Closure;
use Mpociot\Couchbase\Connection;

class Builder extends \Illuminate\Database\Schema\Builder
{
    /**
     * Create a new database Schema manager.
     *
     * @param  Connection  $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Determine if the given table has a given column.
     *
     * @param  string  $table
     * @param  string  $column
     * @return bool
     */
    public function hasColumn($table, $column)
    {
        return true;
    }

    /**
     * Determine if the given table has given columns.
     *
     * @param  string  $table
     * @param  array   $columns
     * @return bool
     */
    public function hasColumns($table, array $columns)
    {
        return true;
    }

    /**
     * Determine if the given collection exists.
     *
     * @param  string  $collection
     * @return bool
     */
    public function hasTable($collection)
    {
        return true;
    }

    /**
     * Modify a collection on the schema.
     *
     * @param  string   $collection
     * @param  Closure  $callback
     * @return bool
     */
    public function table($collection, Closure $callback)
    {
        return true;
    }

    /**
     * Create a new collection on the schema.
     *
     * @param  string   $collection
     * @param  Closure  $callback
     * @return bool
     */
    public function create($collection, Closure $callback = null)
    {
        return true;
    }

    /**
     * Drop a collection from the schema.
     *
     * @param  string  $collection
     * @return bool
     */
    public function drop($collection)
    {
        return true;
    }

    /**
     * Create a new Blueprint.
     *
     * @param  string   $collection
     * @return Blueprint
     */
    protected function createBlueprint($collection, Closure $callback = null)
    {
        return new Blueprint($this->connection, $collection);
    }
}
