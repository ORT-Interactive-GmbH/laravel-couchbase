<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
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
     * @param \Illuminate\Database\Query\Expression|string $value
     * @param bool                                         $prefixAlias
     * @param bool                                         $identifier
     * @return string
     */
    public function wrap($value, $prefixAlias = false, $identifier = false)
    {
        if ($this->isExpression($value)) {
            return $this->getValue($value);
        }

        if (!is_scalar($value)) {
            return $value;
        }

        $escaped = $value[0] === '`' || $value[0] === '"' || $value[0] === '\'';
        if ($escaped) {
            if ($value[0] === substr($value, -1)) {
                return $value;
            }
        } else if (preg_match('/^[a-zA-Z_]+\(/S', $value)) {
            return $value;
        }

        if (false !== strpos($value, '.')) {
            return $this->wrapIdentifierSegments(explode('.', $value));
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedIdentifier($value, $prefixAlias);
        }

        if (!$identifier) {
            return parent::wrap($value, $prefixAlias);
        }

        return $this->wrapIdentifierSegments(explode('.', $value));
    }

    /**
     * @param array $values
     * @param bool  $identifiers
     * @return array
     */
    public function wrapArray(array $values, $identifiers = false)
    {
        $ret = [];
        foreach($values as $value) {
            $ret[] = $this->wrap($value, false, $identifiers);
        }
        return $ret;
    }

    /**
     * @param \Illuminate\Database\Query\Expression|string $table
     * @return string
     */
    public function wrapTable($table)
    {
        if (!$this->isExpression($table)) {
            return $this->wrap($this->tablePrefix . $table, true, true);
        }

        return $this->getValue($table);
    }

    /**
     * @param string $value
     * @return string
     */
    public function wrapIdentifier($value)
    {
        if ($value === '*' || '`' === $value[0] && '`' === substr($value, -1) || preg_match('/^[a-zA-Z_]+\(/S', $value)) {
            return $value;
        }

        return '`' . str_replace('`', '``', $value) . '`';
    }

    /**
     * @param string $value
     * @param bool   $prefixAlias
     * @return string
     */
    protected function wrapAliasedIdentifier($value, $prefixAlias = false)
    {
        $segments = preg_split('/\s+as\s+/i', $value);

        // If we are wrapping a table we need to prefix the alias with the table prefix
        // as well in order to generate proper syntax. If this is a column of course
        // no prefix is necessary. The condition will be true when from wrapTable.
        if ($prefixAlias) {
            $segments[1] = $this->tablePrefix . $segments[1];
        }

        return $this->wrap($segments[0], false, true) . ' as ' . $this->wrap($segments[1], false, true);
    }

    /**
     * @param array $segments
     * @return string
     */
    protected function wrapIdentifierSegments($segments)
    {
        return implode('.', array_map([$this, 'wrapIdentifier'], $segments));
    }

    /**
     * @param \Mpociot\Couchbase\Query\Builder $query
     * @return string
     */
    protected function compileReturning(Builder $query) {
        $p = [];
        foreach($query->returning as $t) {
            $p[] = $this->wrap($t, false, true);
        }
        return implode(', ', $p);
    }

    /**
     * Compile a "where null" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereNull(BaseBuilder $query, $where)
    {
        return '(' .
            $this->wrap($where['column'], false, true) .
            ' is null OR ' .
            $this->wrap($where['column'], false, true) .
            ' is MISSING )';
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if (trim($where['column'], "`") === '_id') {
            return 'meta(' .
                $this->wrap($query->getConnection()->getBucketName(), false, true) .
                ').`id`' .
                ' in [' .
                $values .
                ']';
        } else {
            return $this->wrap($where['column'], false, true) . ' in [' . $values . ']';
            $colIdentifier = str_random(5);
            return 'ANY ' .
                $this->wrap($colIdentifier, false, true) .
                ' IN ' .
                $this->wrap($where['column'], false, true) .
                ' SATISFIES ' .
                $this->wrap($colIdentifier, false, true) .
                ' IN ["' .
                $values .
                '"]';
        }
    }

    /**
     * Compile a "where not in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereNotIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        if (trim($where['column'], "`") === '_id') {
            return 'meta(' .
                $this->wrap($query->getConnection()->getBucketName(), false, true) .
                ').`id`' .
                ' not in [' .
                $values .
                ']';
        } else {
            return $this->wrap($where['column'], false, true) . ' not in [' . $values . ']';
            $colIdentifier = str_random(5);
            return 'ANY ' .
                $this->wrap($colIdentifier, false, true) .
                ' IN ' .
                $this->wrap($where['column'], false, true) .
                ' SATISFIES ' .
                $this->wrap($colIdentifier, false, true) .
                ' IN ["' .
                $values .
                '"]';
        }
    }

    /**
     * Compile a "where in" clause.
     *
     * @param  \Illuminate\Database\Query\Builder $query
     * @param  array                              $where
     * @return string
     */
    protected function whereAnyIn(BaseBuilder $query, $where)
    {
        if (empty($where['values'])) {
            return '0 = 1';
        }

        $values = $this->parameterize($where['values']);

        $colIdentifier = str_random(5);
        return 'ANY ' .
            $this->wrap($colIdentifier, false, true) .
            ' IN ' .
            $this->wrap($where['column'], false, true) .
            ' SATISFIES ' .
            $this->wrap($colIdentifier, false, true) .
            ' IN ["' .
            $values .
            '"]';
    }

    /**
     * @param \Mpociot\Couchbase\Query\Builder $query
     * @param array                            $values
     * @return string
     */
    public function compileUnset(Builder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = $this->compileReturning($query);

        $columns = [];

        foreach ($values as $key) {
            $columns[] = $this->wrap($key, false, true);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);
        return trim("update {$table} {$keyClause} unset {$columns} {$where} RETURNING {$returning}");
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

        return "insert into {$table} {$keyValue} values {$parameters} RETURNING {$returning}";
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
        $returning = $this->compileReturning($query);

        $columns = [];
        $unsetColumns = [];

        foreach ($values as $key => $value) {
            if ($value instanceof MissingValue) {
                $unsetColumns[] = $this->wrap($key, false, true);
            } else {
                $columns[] = $this->wrap($key, false, true) . ' = ' . $this->parameter($value);
            }
        }

        $columns = implode(', ', $columns);
        $unsetColumns = implode(', ', $unsetColumns);

        $where = $this->compileWheres($query);

        $forIns = [];
        foreach ($query->forIns as $forIn) {
            foreach ($forIn['values'] as $key => $value) {
                $forIns[] = $this->wrap($key, false, true) .
                    ' = ' .
                    $this->wrap($value) .
                    ' FOR ' .
                    $this->wrap(str_singular($forIn['alias']), false, true) .
                    ' IN ' .
                    $this->wrap($forIn['alias'], false, true) .
                    ' WHEN ' .
                    $this->wrap($forIn['column'], false, true) .
                    '= ' . $this->wrap($forIn['value']) .
                    ' END';
            }
        }
        $forIns = implode(', ', $forIns);

        return trim("update {$table} $keyClause set $columns " .
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
        // use-keys-clause:
        $keyClause = is_null($query->keys) ? null : $this->compileKeys($query);
        // returning-clause
        $returning = $this->compileReturning($query);
        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$table} {$keyClause} {$where} RETURNING {$returning}");
    }

    /**
     * @param BaseBuilder $query
     * @return string
     */
    public function compileKeys(BaseBuilder $query)
    {
        if (is_array($query->keys)) {
            if (0 === count($query->keys)) {
                return 'USE KEYS []';
            }
            return 'USE KEYS ["' . implode('","', $query->keys) . '"]';
        }
        return "USE KEYS \"{$query->keys}\"";
    }
}
