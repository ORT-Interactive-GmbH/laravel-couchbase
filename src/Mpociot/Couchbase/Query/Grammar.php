<?php declare(strict_types=1);

namespace Mpociot\Couchbase\Query;

use Exception;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Mpociot\Couchbase\Helper;

class Grammar extends BaseGrammar
{
    const INDEX_TYPE_VIEW = 'VIEW';
    const INDEX_TYPE_GSI = 'GSI';

    protected $reservedWords = [
        'ALL',
        'ALTER',
        'ANALYZE',
        'AND',
        'ANY',
        'ARRAY',
        'AS',
        'ASC',
        'BEGIN',
        'BETWEEN',
        'BINARY',
        'BOOLEAN',
        'BREAK',
        'BUCKET',
        'BUILD',
        'BY',
        'CALL',
        'CASE',
        'CAST',
        'CLUSTER',
        'COLLATE',
        'COLLECTION',
        'COMMIT',
        'CONNECT',
        'CONTINUE',
        'CORRELATE',
        'COVER',
        'CREATE',
        'DATABASE',
        'DATASET',
        'DATASTORE',
        'DECLARE',
        'DECREMENT',
        'DELETE',
        'DERIVED',
        'DESC',
        'DESCRIBE',
        'DISTINCT',
        'DO',
        'DROP',
        'EACH',
        'ELEMENT',
        'ELSE',
        'END',
        'EVERY',
        'EXCEPT',
        'EXCLUDE',
        'EXECUTE',
        'EXISTS',
        'EXPLAIN',
        'FALSE',
        'FETCH',
        'FIRST',
        'FLATTEN',
        'FOR',
        'FORCE',
        'FROM',
        'FUNCTION',
        'GRANT',
        'GROUP',
        'GSI',
        'HAVING',
        'IF',
        'IGNORE',
        'ILIKE',
        'IN',
        'INCLUDE',
        'INCREMENT',
        'INDEX',
        'INFER',
        'INLINE',
        'INNER',
        'INSERT',
        'INTERSECT',
        'INTO',
        'IS',
        'JOIN',
        'KEY',
        'KEYS',
        'KEYSPACE',
        'KNOWN',
        'LAST',
        'LEFT',
        'LET',
        'LETTING',
        'LIKE',
        'LIMIT',
        'LSM',
        'MAP',
        'MAPPING',
        'MATCHED',
        'MATERIALIZED',
        'MERGE',
        'MINUS',
        'MISSING',
        'NAMESPACE',
        'NEST',
        'NOT',
        'NULL',
        'NUMBER',
        'OBJECT',
        'OFFSET',
        'ON',
        'OPTION',
        'OR',
        'ORDER',
        'OUTER',
        'OVER',
        'PARSE',
        'PARTITION',
        'PASSWORD',
        'PATH',
        'POOL',
        'PREPARE',
        'PRIMARY',
        'PRIVATE',
        'PRIVILEGE',
        'PROCEDURE',
        'PUBLIC',
        'RAW',
        'REALM',
        'REDUCE',
        'RENAME',
        'RETURN',
        'RETURNING',
        'REVOKE',
        'RIGHT',
        'ROLE',
        'ROLLBACK',
        'SATISFIES',
        'SCHEMA',
        'SELECT',
        'SELF',
        'SEMI',
        'SET',
        'SHOW',
        'SOME',
        'START',
        'STATISTICS',
        'STRING',
        'SYSTEM',
        'THEN',
        'TO',
        'TRANSACTION',
        'TRIGGER',
        'TRUE',
        'TRUNCATE',
        'UNDER',
        'UNION',
        'UNIQUE',
        'UNKNOWN',
        'UNNEST',
        'UNSET',
        'UPDATE',
        'UPSERT',
        'USE',
        'USER',
        'USING',
        'VALIDATE',
        'VALUE',
        'VALUED',
        'VALUES',
        'VIA',
        'VIEW',
        'WHEN',
        'WHERE',
        'WHILE',
        'WITH',
        'WITHIN',
        'WORK',
        'XOR',
    ];

    /**
     * The components that make up a select clause.
     *
     * Note: We added "key"
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'use',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'unions',
        'lock',
    ];

    /**
     * @param \Illuminate\Database\Query\Expression|string $value
     * @param bool $prefixAlias
     * @return string
     */
    public function wrap($value, $prefixAlias = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (!is_scalar($value)) {
            return $value;
        }

        if ($value[0] === '`' && $value[0] === substr($value, -1)) {
            return $this->wrapValue($value);
        } elseif ($value === '*') {
            return $this->wrapValue($value);
        }

        if (false !== strpos($value, '.')) {
            return $this->wrapSegments(explode('.', $value));
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $this->wrapValue($value);
    }

    /**
     * @param string $value
     * @return string
     */
    public function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        if ('`' === $value[0] && '`' === substr($value, -1)) {
            if (substr_count($value, '`') % 2 === 0) {
                return $value;
            }
            return $this->wrapValue(substr($value, 1, -1));
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * @param array $segments
     * @return string
     */
    protected function wrapSegments($segments)
    {
        return implode('.', array_map([$this, 'wrapValue'], $segments));
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function wrapData($value)
    {
        $data = json_encode($value);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \InvalidArgumentException(
                'json_decode error: ' . json_last_error_msg());
        }
        return $data;
    }

    /**
     * Compile a select query into SQL.
     *
     * @param  BaseBuilder $query
     * @return string
     */
    public function compileSelect(BaseBuilder $query)
    {
        // If the query does not have any columns set, we'll set the columns to the
        // * character to just get all of the columns from the database. Then we
        // can build the query and concatenate all the pieces together as one.
        $original = $query->columns;

        if (is_null($query->columns) || $query->columns === ['*']) {
            $query->columns = [$this->wrapTable($query->connection->getBucketName()) . '.*'];
        }
        if ($query->columns === [$this->wrapTable($query->connection->getBucketName()) . '.*'] || in_array('_id',
                $query->columns)) {
            $query->columns[] = $this->getMetaIdExpression($query, true);
            $query->columns = array_diff($query->columns, ['_id']);
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = parent::compileSelect($query);

        $query->columns = $original;

        return $sql;
    }

    /**
     * @param \Mpociot\Couchbase\Query\Builder $query
     * @return string
     */
    protected function compileReturning(Builder $query)
    {
        return implode(', ', $this->wrapArray($query->returning));
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $where
     * @return string
     */
    protected function whereNull(BaseBuilder $query, $where)
    {
        return '(' .
            $this->wrap($where['column']) .
            ' is null OR ' .
            $this->wrap($where['column']) .
            ' is MISSING )';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $where
     * @return string
     */
    protected function whereIn(BaseBuilder $query, $where)
    {
        $values = $this->parameterize($where['values'] ?? []);

        if (trim($where['column'], "`") === '_id') {
            $where['column'] = $this->getMetaIdExpression($query);
        }

        return $this->wrap($where['column']) . ' in [' . $values . ']';
    }

    /**
     * @param BaseBuilder $query
     * @param bool $withAsUnderscoreId
     * @return Expression
     */
    public function getMetaIdExpression(BaseBuilder $query, $withAsUnderscoreId = false)
    {
        return new Expression('meta(' . $this->wrapTable($query->getConnection()->getBucketName()) . ').`id`' . ($withAsUnderscoreId ? ' as `_id`' : ''));
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array $where
     * @return string
     */
    protected function whereNotIn(BaseBuilder $query, $where)
    {
        $values = $this->parameterize($where['values'] ?? []);

        if (trim($where['column'], "`") === '_id') {
            $where['column'] = $this->getMetaIdExpression($query);
        }

        return $this->wrap($where['column']) . ' not in [' . $values . ']';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  Builder $query
     * @param  array $where
     * @return string
     */
    protected function whereAnyIn(Builder $query, $where)
    {
        $values = $this->parameterize($where['values'] ?? []);

        $colIdentifier = str_random(32);
        return 'ANY ' .
            $this->wrapValue($colIdentifier) .
            ' IN ' .
            $this->wrap($where['column']) .
            ' SATISFIES ' .
            $this->wrapValue($colIdentifier) .
            ' IN [' .
            $values .
            '] END';
    }

    /**
     * @param \Mpociot\Couchbase\Query\Builder $query
     * @param array $values
     * @return string
     */
    public function compileUnset(Builder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use keys/index clause:
        $useClause = $this->compileUse($query);
        // returning-clause
        $returning = $this->compileReturning($query);

        $columns = $this->wrapArray($values);

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);
        return trim("update {$table} {$useClause} unset {$columns} {$where} RETURNING {$returning}");
    }

    /**
     * {@inheritdoc}
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        if (is_null($query->keys)) {
            $query->useKeys(Helper::getUniqueId($values[Helper::TYPE_NAME]));
        }
        // use keys/index clause:
        $useClause = $this->compileUse($query);
        // returning-clause
        $returning = $this->compileReturning($query);

        if (!is_array(reset($values))) {
            $values = [$values];
        }
        $parameters = [];

        foreach ($values as $record) {
            $parameters[] = '(' . $this->parameterize($record) . ')';
        }
        $parameters = collect($parameters)->transform(function ($parameter) use ($keyClause) {
            return "({$keyClause}, ?)";
        });
        $parameters = implode(', ', array_fill(0, count($parameters), '?'));
        $keyValue = '(KEY, VALUE)';

        return "insert into {$table} {$useClause} values {$parameters} RETURNING {$returning}";
    }

    /**
     * {@inheritdoc}
     *
     * notice: supported set query only
     */
    public function compileUpdate(BaseBuilder $query, $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use keys/index clause:
        $useClause = $this->compileUse($query);
        // returning-clause
        $returning = $this->compileReturning($query);

        $columns = [];
        $unsetColumns = [];

        foreach ($values as $key => $value) {
            if ($value instanceof MissingValue) {
                $unsetColumns[] = $this->wrap($key);
            } else {
                $columns[] = $this->wrap($key) . ' = ' . $this->parameter($value);
            }
        }

        $columns = implode(', ', $columns);
        $unsetColumns = implode(', ', $unsetColumns);

        $where = $this->compileWheres($query);

        $forIns = [];
        foreach ($query->forIns as $forIn) {
            foreach ($forIn['values'] as $key => $value) {
                $forIns[] = $this->wrap($key) .
                    ' = ' .
                    $this->wrapData($value) .
                    ' FOR ' .
                    $this->wrap(str_singular($forIn['alias'])) .
                    ' IN ' .
                    $this->wrap($forIn['alias']) .
                    ' WHEN ' .
                    $this->wrap($forIn['column']) .
                    '= ' . $this->wrapData($forIn['value']) .
                    ' END';
            }
        }
        $forIns = implode(', ', $forIns);

        return trim("update {$table} $useClause set $columns " .
            ($unsetColumns ? "unset " . $unsetColumns : "") .
            "{$forIns} {$where} RETURNING {$returning}");
    }

    /**
     * {@inheritdoc}
     *
     * @see http://developer.couchbase.com/documentation/server/4.1/n1ql/n1ql-language-reference/delete.html
     */
    public function compileDelete(BaseBuilder $query)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use keys/index clause:
        $useClause = $this->compileUse($query);
        // returning-clause
        $returning = $this->compileReturning($query);
        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$table} {$useClause} {$where} RETURNING {$returning}");
    }

    /**
     * @param Builder $query
     * @return string
     * @throws Exception
     */
    public function compileUse(Builder $query)
    {
        if($query->keys !== null && !empty($query->indexes)) {
            throw new Exception('Only one of useKeys or useIndex can be used, not both.');
        }

        if($query->keys !== null) {
            return 'USE KEYS '.$this->wrapData($query->keys);
        }

        if(!empty($query->indexes)) {
            return 'USE INDEX ('.implode(', ', array_map(function($index){
                if(!in_array($index['type'], [self::INDEX_TYPE_VIEW, self::INDEX_TYPE_GSI])) {
                    throw new Exception('Unsupported index type '.json_encode($index['type']).'.');
                }
                return $this->wrapIdentifier($index['name']).' USING '.$index['type'];
            }, $query->indexes)).')';
        }

        return '';
    }

    /**
     * @param array $values
     * @return mixed
     */
    public function parameterize(array $values)
    {
        /**
         * Quick fix to allow:
         * objectA.relation_ids = [1,2,3,4]
         * to reference objectB.id
         */
        if (isset($values[0]) && is_array($values[0])) {
            $values = collect($values)->flatten()->toArray();
        }
        return parent::parameterize($values);
    }

}
