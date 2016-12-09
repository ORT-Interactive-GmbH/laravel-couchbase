<?php namespace Mpociot\Couchbase\Schema;

use Closure;
use Illuminate\Database\Connection;

class Blueprint extends \Illuminate\Database\Schema\Blueprint
{
    /**
     * Fluent columns.
     *
     * @var array
     */
    protected $columns = [];

    /**
     * Specify an index for the collection.
     *
     * @param  string|array  $columns
     * @param  array         $options
     * @param  string  $name
     * @param  string|null  $algorithm
     * @return Blueprint
     */
    public function index($columns = null, $name = null, $algorithm = null, $options = [])
    {
        return $this;
    }

    /**
     * Specify the primary key(s) for the table.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @param  array         $options
     * @return \Illuminate\Support\Fluent
     */
    public function primary($columns = null, $name = null, $algorithm = null, $options = [])
    {
        return $this;
    }

    /**
     * Indicate that the given index should be dropped.
     *
     * @param  string|array  $columns
     * @return Blueprint
     */
    public function dropIndex($columns = null)
    {
        return $this;
    }

    /**
     * Specify a unique index for the collection.
     *
     * @param  string|array  $columns
     * @param  string  $name
     * @param  string|null  $algorithm
     * @param  array         $options
     * @return Blueprint
     */
    public function unique($columns = null, $name = null, $algorithm = null, $options = [])
    {
        return $this;
    }

    /**
     * Indicate that the table needs to be created.
     *
     * @return bool
     */
    public function create()
    {
        return true;
    }

    /**
     * Indicate that the collection should be dropped.
     *
     * @return bool
     */
    public function drop()
    {
        return true;
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param  string  $type
     * @param  string  $name
     * @param  array   $parameters
     * @return Blueprint
     */
    public function addColumn($type, $name, array $parameters = [])
    {
        return $this;
    }

    /**
     * Allows the use of unsupported schema methods.
     *
     * @return Blueprint
     */
    public function __call($method, $args)
    {
        // Dummy.
        return $this;
    }
}
