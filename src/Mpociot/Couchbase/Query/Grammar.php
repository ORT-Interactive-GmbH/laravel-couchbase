<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Mpociot\Couchbase\Helper;

class Grammar extends BaseGrammar
{
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
        'XOR'
    ];

    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'keys',
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
     * {@inheritdoc}
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        return $value;
    }

    /**
     * @param $value
     *
     * @return string
     */
    protected function wrapKey($value)
    {
        if (is_null($value)) {
            return;
        }

        if(in_array(strtoupper($value), $this->reservedWords) || preg_match('/\s/', $value)) {
            return '`' . str_replace('"', '""', $value) . '`';
        }

        return $value;
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNull(BaseBuilder $query, $where)
    {
        return '('.$this->wrap($where['column']).' is null OR '.$this->wrap($where['column']).' is MISSING )';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if ($where['column'] === '_id') {
            $column = 'meta('.$query->getConnection()->getBucketName().').id';
            return $column.' in ['.$values.']';
        } else {
            return $where['column'].' in ['.$values.']';
            $colIdentifier = str_random(5);
            return 'ANY '.$colIdentifier.' IN '.$this->wrap($where['column']).' SATISFIES '.$colIdentifier.' IN ["'.$values.'"]';
        }
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereNotIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if ($where['column'] === '_id') {
            $column = 'meta('.$query->getConnection()->getBucketName().').id';
            return $column.' not in ['.$values.']';
        } else {
            return $where['column'].' not in ['.$values.']';
            $colIdentifier = str_random(5);
            return 'ANY '.$colIdentifier.' IN '.$this->wrap($where['column']).' SATISFIES '.$colIdentifier.' IN ["'.$values.'"]';
        }
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array  $where
     * @return string
     */
    protected function whereAnyIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        $colIdentifier = str_random(5);
        return 'ANY '.$colIdentifier.' IN '.$this->wrap($where['column']).' SATISFIES '.$colIdentifier.' IN ["'.$values.'"]';
    }

    public function compileUnset(BaseBuilder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);

        $columns = [];

        foreach ($values as $key) {
            $columns[] = $this->wrap($key);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);
        return trim("update {$table} {$keyClause} unset $columns $where RETURNING {$returning}");
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
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);

        if (!is_array(reset($values))) {
            $values = [$values];
        }
        $parameters = [];

        foreach ($values as $record) {
            $parameters[] = '(' . $this->parameterize($record) . ')';
        }
        $parameters = collect($parameters)->transform(function ($parameter) use($keyClause) {
            return "({$keyClause}, ?)";
        });
        $parameters = implode(', ', $parameters->toArray());
        $keyValue = '(KEY, VALUE)';

        return "insert into {$table} {$keyValue} values $parameters RETURNING {$returning}";
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
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);

        $columns = [];

        foreach ($values as $key => $value) {
            $columns[] = $this->wrapKey($key) . ' = ' . $this->parameter($value);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);

        $forIns = [];
        foreach ($query->forIns as $forIn) {
            foreach ($forIn['values'] as $key => $value) {
                $forIns[] = $this->wrap($key) . ' = "' . $value.'" FOR '.str_singular($forIn['alias']).' IN '.$forIn['alias'].' WHEN '.$forIn['column'].'="'.$this->wrapValue($forIn['value']).'" END';
            }
        }
        $forIns = implode(', ', $forIns);

        return trim("update {$table} $keyClause set $columns $forIns $where RETURNING {$returning}");
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
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = implode(', ', $query->returning);
        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$table} {$keyClause} {$where} RETURNING {$returning}");
    }

    /**
     * @param BaseBuilder $query
     * @return string
     */
    public function compileKeys(BaseBuilder $query)
    {
        if(is_array($query->keys)) {
            if(empty($query->keys)) {
                return 'USE KEYS []';
            }
            return 'USE KEYS [\''.implode('\', \'', $query->keys).'\']';
        }
        return 'USE KEYS \''.$query->keys.'\'';
    }
}
