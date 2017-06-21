<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Mpociot\Couchbase\Connection;
use Mpociot\Couchbase\Helper;

class Builder extends BaseBuilder
{

    /**
     * The column projections.
     *
     * @var array
     */
    public $projections;

    public $forIns = [];

    /**
     * @var string
     */
    public $type;

    /**
     * The cursor timeout value.
     *
     * @var int
     */
    public $timeout;

    /**
     * The cursor hint value.
     *
     * @var int
     */
    public $hint;

    /**
     * Indicate if we are executing a pagination query.
     *
     * @var bool
     */
    public $paginating = false;

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    public $operators = [
        '=', '<', '>', '<=', '>=', '<>', '!=',
        'like', 'not like', 'between', 'ilike',
        '&', '|', '^', '<<', '>>',
        'rlike', 'regexp', 'not regexp',
        'exists', 'type', 'mod', 'where', 'all', 'size', 'regex', 'text', 'slice', 'elemmatch',
        'geowithin', 'geointersects', 'near', 'nearsphere', 'geometry',
        'maxdistance', 'center', 'centersphere', 'box', 'polygon', 'uniquedocs',
    ];

    /**
     * Operator conversion.
     *
     * @var array
     */
    protected $conversion = [
        '='  => '=',
        '!=' => '$ne',
        '<>' => '$ne',
        '<'  => '$lt',
        '<=' => '$lte',
        '>'  => '$gt',
        '>=' => '$gte',
    ];

    /**
     * Check if we need to return Collections instead of plain arrays (laravel >= 5.3 )
     *
     * @var boolean
     */
    protected $useCollections;

    /**
     * Keys used via 'USE KEYS'
     * @var null|array|string
     */
    public $keys = null;

    /** @var string[]  returning-clause */
    public $returning = ['*'];

    /**
     * Create a new query builder instance.
     *
     * @param Connection $connection
     * @param Processor  $processor
     */
    public function __construct(Connection $connection, Processor $processor)
    {
        $this->grammar = new Grammar;
        $this->connection = $connection;
        $this->processor = $processor;
        $this->useCollections = $this->shouldUseCollections();
        $this->returning([$this->connection->getBucketName().'.*']);
    }

    /**
     * @param $keys
     *
     * @return $this
     */
    public function useKeys($keys)
    {
        if(is_null($keys)) {
            $keys = [];
        }
        $this->keys = $keys;

        return $this;
    }

    /**
     * @param array $column
     *
     * @return $this
     */
    public function returning(array $column = ['*'])
    {
        $this->returning = $column;

        return $this;
    }

    /**
     * Returns true if Laravel or Lumen >= 5.3
     *
     * @return bool
     */
    protected function shouldUseCollections()
    {
        if (function_exists('app')) {
            $version = app()->version();
            $version = filter_var(explode(')', $version)[0], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION); // lumen
            return version_compare($version, '5.3', '>=');
        }
    }

    /**
     * Set the table which the query is targeting.
     *
     * @param  string  $type
     * @return $this
     */
    public function from($type)
    {
        $this->from = $this->connection->getBucketName();
        $this->type = $type;

        if(!is_null($type)) {
            $this->where( Helper::TYPE_NAME, $type );
        }
        return $this;
    }

    /**
     * Create a new query instance for nested where condition.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function forNestedWhere()
    {
        // ->from($this->from) is wrong, and ->from($this->type) is redundant in nested where
        return $this->newQuery()->from(null);
    }

    /**
     * Set the projections.
     *
     * @param  array  $columns
     * @return $this
     */
    public function project($columns)
    {
        $this->projections = is_array($columns) ? $columns : func_get_args();

        return $this;
    }
    
    /**
     * Execute the query as a "select" statement.
     *
     * @param  array  $columns
     * @return \Illuminate\Support\Collection
     */
    public function getWithMeta($columns = ['*'])
    {
        return $this->connection->selectWithMeta($this->toSql(), $this->getBindings());
    }

    /**
     * Set the cursor timeout in seconds.
     *
     * @param  int $seconds
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->timeout = $seconds;

        return $this;
    }

    /**
     * Set the cursor hint.
     *
     * @param  mixed $index
     * @return $this
     */
    public function hint($index)
    {
        $this->hint = $index;

        return $this;
    }

    /**
     * Execute a query for a single record by ID.
     *
     * @param  mixed  $id
     * @param  array  $columns
     * @return mixed|static
     */
    public function find($id, $columns = ['*'])
    {
        if (is_array($id) === true) {
            return $this->useKeys($id);
        }
        return $this->useKeys($id)->first();
    }

    /**
     * Generate the unique cache key for the current query.
     *
     * @return string
     */
    public function generateCacheKey()
    {
        $key = [
            'bucket'     => $this->from,
            'type'       => $this->type,
            'wheres'     => $this->wheres,
            'columns'    => $this->columns,
            'groups'     => $this->groups,
            'orders'     => $this->orders,
            'offset'     => $this->offset,
            'limit'      => $this->limit,
            'aggregate'  => $this->aggregate,
        ];

        return md5(serialize(array_values($key)));
    }

    /**
     * Execute an aggregate function on the database.
     *
     * @param  string  $function
     * @param  array   $columns
     * @return mixed
     */
    public function aggregate($function, $columns = [])
    {
        $this->aggregate = compact('function', 'columns');

        $results = $this->get($columns);

        // Once we have executed the query, we will reset the aggregate property so
        // that more select queries can be executed against the database without
        // the aggregate value getting in the way when the grammar builds it.
        $this->columns = null;
        $this->aggregate = null;

        if (isset($results[0])) {
            $result = (array) $results[0];

            return $result['aggregate'];
        }
    }

    /**
     * Determine if any rows exist for the current query.
     *
     * @return bool
     */
    public function exists()
    {
        return ! is_null($this->first());
    }

    /**
     * Force the query to only return distinct results.
     *
     * @return Builder
     */
    public function distinct($column = false)
    {
        $this->distinct = true;

        if ($column) {
            $this->columns = [$column];
        }

        return $this;
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return Builder
     */
    public function whereBetween($column, array $values, $boolean = 'and', $not = false)
    {
        $type = 'between';

        $this->wheres[] = compact('column', 'type', 'boolean', 'values', 'not');

        $this->addBinding($values, 'where');

        return $this;
    }

    /**
     * Set the limit and offset for a given page.
     *
     * @param  int  $page
     * @param  int  $perPage
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function forPage($page, $perPage = 15)
    {
        $this->paginating = true;

        return $this->skip(($page - 1) * $perPage)->take($perPage);
    }

    /**
     * Insert a new record into the database.
     *
     * @param array $values
     *
     * @return bool
     */
    public function insert(array $values)
    {
        // Since every insert gets treated like a batch insert, we will have to detect
        // if the user is inserting a single document or an array of documents.
        $batch = true;
        foreach ($values as $key => $value) {
            // As soon as we find a value that is not an array we assume the user is
            // inserting a single document.
            if (! is_array($value) || is_string($key)) {
                $batch = false;
                break;
            }
        }

        if (is_null($this->keys)) {
            $this->useKeys(Helper::getUniqueId($this->type));
        }

        if ($batch){
            foreach ($values as &$value) {
                $value[Helper::TYPE_NAME] = $this->type;
                $key = Helper::getUniqueId($this->type);
                $result = $this->connection->getCouchbaseBucket()->upsert($key, $value);
            }
        } else {
            $values[Helper::TYPE_NAME] = $this->type;
            $result = $this->connection->getCouchbaseBucket()->upsert($this->keys, $values);
        }

        return $result;
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array   $values
     * @param  string  $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = NULL)
    {
        if (!is_null($sequence) && isset($values[$sequence])){
            $this->useKeys((string)$values[ $sequence]);
        } elseif (isset($values['_id'])) {
            $this->useKeys((string)$values[ '_id']);
        }
        $this->insert($values);
        return $this->keys;
    }

    /**
     * Get an array with the values of a given column.
     *
     * @param  string  $column
     * @param  string|null  $key
     * @return array
     */
    public function pluck($column, $key = null)
    {
        $results = $this->get(is_null($key) ? [$column] : [$column, $key]);

        // Convert ObjectID's to strings
        if ($key == '_id') {
            $results = $results->map(function ($item) {
                $item['_id'] = (string) $item['_id'];
                return $item;
            });
        }

        $p = Arr::pluck($results, $column, $key);
        return $this->useCollections ? new Collection($p) : $p;
    }

    /**
     * Run a truncate statement on the table.
     */
    public function truncate()
    {
        return $this->delete();
    }

    /**
     * Get an array with the values of a given column.
     *
     * @deprecated
     * @param  string  $column
     * @param  string  $key
     * @return array
     */
    public function lists($column, $key = null)
    {
        return $this->pluck($column, $key);
    }

    /**
     * Append one or more values to an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function push($column, $value = null, $unique = false)
    {
        $obj = $this->connection->getCouchbaseBucket()->get($this->keys);
        if (!isset($obj->value->{$column})) {
            $obj->value->{$column} = [];
        }
        if (is_array($value) && count($value) === 1) {
            $obj->value->{$column}[] = reset($value);
        } else {
            $obj->value->{$column}[] = $value;
        }

        $array = array_map('json_encode', $obj->value->{$column});
        $array = array_unique($array);
        $obj->value->{$column} = array_map('json_decode', $array);

        return $this->connection->getCouchbaseBucket()->upsert($this->keys, $obj->value);
    }

    /**
     * Remove one or more values from an array.
     *
     * @param  mixed   $column
     * @param  mixed   $value
     * @return int
     */
    public function pull($column, $value = null)
    {
        try {
            $obj = $this->connection->getCouchbaseBucket()->get($this->keys);
            if (!is_array($value)) {
                $value = [$value];
            }
            $filtered = collect($obj->value->{$column})->reject(function ($val, $key) use ($value){
                $match = false;
                foreach (array_keys($value) AS $matchKey) {
                    if (is_object($val)) {
                        if ($val->{$matchKey} === $value[$matchKey]) {
                            $match = true;
                        }
                    } else {
                        $match = $val === $value[$matchKey];
                    }

                }
                return $match;
            });
            $obj->value->{$column} = $filtered->flatten()->toArray();

            return $this->connection->getCouchbaseBucket()->upsert($this->keys, $obj->value);
        } catch (\Exception $e) {

        }
    }

    /**
     * Remove one or more fields.
     *
     * @param  mixed $columns
     * @return int
     */
    public function drop($columns)
    {
        if (! is_array($columns)) {
            $columns = [$columns];
        }

        $query = $this->getGrammar()->compileUnset($this, $columns);
        return $this->connection->update($query, $this->getBindings());
    }

    /**
     * Get a new instance of the query builder.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return new Builder($this->connection, $this->processor);
    }

    /**
     * Run the query as a "select" statement against the connection.
     *
     * @return array
     */
    protected function runSelect()
    {
        if ($this->columns === ['*']) {
            $this->columns = [$this->connection->getBucketName().'.*'];
        }
        if ($this->columns === [$this->connection->getBucketName().'.*'] || in_array('_id', $this->columns)) {
            $this->columns[] = 'meta('.$this->connection->getBucketName().').id as _id';
            $this->columns = array_diff($this->columns, ['_id']);
        }
        for($i=0; $i<count($this->wheres); $i++) {
            if (array_key_exists('column', $this->wheres[$i]) === true && $this->wheres[$i]['column'][0] !== '`' && $this->wheres[$i]['column'] == 'build') {
                $this->wheres[$i]['column'] = '`' . $this->wheres[$i]['column'] . '`';
            }
        }
        return parent::runSelect();
    }

    /**
     * Convert a key to ObjectID if needed.
     *
     * @param  mixed $id
     * @return mixed
     */
    public function convertKey($id)
    {
        return $id;
    }

    /**
     * Add a FOR ... IN query
     *
     * @param  string  $column
     * @param  mixed   $value
     * @param  string  $alias
     * @param  array   $values
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function forIn($column, $value, $alias, $values)
    {
        $this->forIns[] = compact('column', 'value', 'alias', 'values');

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     *
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column === '_id') {
            //$column = 'meta('.$this->connection->getBucketName().').id';
            $value = func_num_args() == 2 ? $operator : $value;
            $this->useKeys($value);
            return $this;
        }
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Set custom options for the query.
     *
     * @param  array  $options
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = $options;

        return $this;
    }

    /**
     * @param string|int|array $values
     *
     * @return array
     */
    protected function detectValues($values)
    {
        foreach ($values as &$value) {
            $value[Helper::TYPE_NAME] = $this->type;
        }
        return [$values];
    }

    /**
     * Handle dynamic method calls into the method.
     *
     * @param  string  $method
     * @param  array   $parameters
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if ($method == 'unset') {
            return call_user_func_array([$this, 'drop'], $parameters);
        }

        return parent::__call($method, $parameters);
    }
}
