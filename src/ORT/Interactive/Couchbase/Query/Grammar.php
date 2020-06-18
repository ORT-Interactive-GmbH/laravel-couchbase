<?php declare(strict_types=1);

namespace ORT\Interactive\Couchbase\Query;

use Exception;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Builder as BaseBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;

class Grammar extends BaseGrammar
{
    const INDEX_TYPE_VIEW = 'VIEW';
    const INDEX_TYPE_GSI = 'GSI';
    const IDENTIFIER_ENCLOSURE_CHAR = '`';
    const VIRTUAL_META_ID_COLUMN = '_id';

    public static function removeMissingValue($values)
    {
        if (is_array($values) || $values instanceof Arrayable || $values instanceof \stdClass) {
            foreach ($values as $key => $value) {
                if ($value instanceof MissingValue) {
                    if ($values instanceof \stdClass) {
                        unset($values->{$key});
                    } else {
                        unset($values[$key]);
                    }
                } else {
                    $newValue = self::removeMissingValue($value);
                    if ($values instanceof \stdClass) {
                        $values->{$key} = $newValue;
                    } else {
                        $values[$key] = $newValue;
                    }
                }
            }
        }

        return $values;
    }

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

        if (false !== strpos($value, '.')) {
            return $this->wrapSegments(explode('.', $value));
        }

        if (strpos(strtolower($value), ' as ') !== false) {
            return $this->wrapAliasedValue($value, $prefixAlias);
        }

        return $this->wrapValue($value);
    }

    /**
     * Used to wrap identifiers.
     *
     * @param string $value
     * @return string
     */
    public function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }

        if (self::IDENTIFIER_ENCLOSURE_CHAR === mb_substr($value, 0,
                1) && self::IDENTIFIER_ENCLOSURE_CHAR === mb_substr($value, -1)) {
            if (mb_substr_count($value, self::IDENTIFIER_ENCLOSURE_CHAR) % 2 === 0) {
                return $value;
            }
            return $this->wrapValue(mb_substr($value, 1, -1));
        }

        return self::IDENTIFIER_ENCLOSURE_CHAR . str_replace(self::IDENTIFIER_ENCLOSURE_CHAR,
                self::IDENTIFIER_ENCLOSURE_CHAR . self::IDENTIFIER_ENCLOSURE_CHAR,
                $value) . self::IDENTIFIER_ENCLOSURE_CHAR;
    }

    /**
     * @param array $segments
     * @return string
     */
    public function wrapSegments($segments)
    {
        return implode('.', array_map([$this, 'wrapValue'], $segments));
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function wrapData($value)
    {
        $value = self::removeMissingValue($value);
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
        if ($query->columns === [$this->wrapTable($query->connection->getBucketName()) . '.*']) {
            $query->columns[] = self::VIRTUAL_META_ID_COLUMN;
        }

        $query->columns = array_unique($query->columns);

        foreach ($query->columns as $i => $column) {
            $query->columns[$i] = $this->replaceColumnIfMetaId($column, $query, true);
        }

        // To compile the query, we'll spin through each component of the query and
        // see if that component exists. If it does we'll just call the compiler
        // function for the component which is responsible for making the SQL.
        $sql = parent::compileSelect($query);

        $query->columns = $original;

        return $sql;
    }

    /**
     * @param \ORT\Interactive\Couchbase\Query\Builder $query
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
     * Compile a "between" where clause.
     *
     * @param  BaseBuilder $query
     * @param  array $where
     * @return string
     */
    protected function whereBetween(BaseBuilder $query, $where)
    {
        $between = $where['not'] ? 'not between' : 'between';

        return $this->wrap($where['column']) . ' ' . $between . ' ' . $this->parameter($where['values'][0]) . ' and ' . $this->parameter($where['values'][1]);
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

        $where['column'] = $this->replaceColumnIfMetaId($where['column'], $query);

        return $this->wrap($where['column']) . ' in [' . $values . ']';
    }

    /**
     * Compile a raw where clause.
     *
     * @param  BaseBuilder $query
     * @param  array $where
     * @return string
     */
    protected function whereRaw(BaseBuilder $query, $where)
    {
        return $where['sql'];
    }

    /**
     * @param BaseBuilder $query
     * @param bool $withAs
     * @return Expression
     */
    public function getMetaIdExpression(BaseBuilder $query, $withAs = false)
    {
        return new Expression('meta(' . $this->wrapTable($query->getConnection()->getBucketName()) . ').' . $this->wrapValue('id') . ($withAs ? ' as ' . $this->wrapValue(self::VIRTUAL_META_ID_COLUMN) : ''));
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

        $where['column'] = $this->replaceColumnIfMetaId($where['column'], $query);

        return $this->wrap($where['column']) . ' not in [' . $values . ']';
    }

    /**
     * @param string $column
     * @param BaseBuilder $query
     * @param bool $withAs
     * @return Expression|string
     */
    private function replaceColumnIfMetaId($column, BaseBuilder $query, $withAs = false)
    {
        if (is_string($column) && trim($column, self::IDENTIFIER_ENCLOSURE_CHAR) === self::VIRTUAL_META_ID_COLUMN) {
            $column = $this->getMetaIdExpression($query, $withAs);
        }
        return $column;
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
     * @param \ORT\Interactive\Couchbase\Query\Builder $query
     * @param array $values
     * @return string
     */
    public function compileUnset(Builder $query, array $values)
    {
        $newValues = [];
        foreach ($values as $value) {
            $newValues[$value] = MissingValue::getMissingValue();
        }
        return $this->compileUpdate($query, $newValues);
    }

    /**
     * {@inheritdoc}
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        throw new \Exception('Inserts are done via CouchbaseBucket->upsert(), this method should not be used.');
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

        $setColumns = implode(', ', $columns + $forIns);
        $unsetColumns = implode(', ', $unsetColumns);

        return trim('update ' . $table
            . ' ' . $useClause
            . ($setColumns ? (' set ' . $setColumns) : '')
            . ($unsetColumns ? (' unset ' . $unsetColumns) : '')
            . ' ' . $where
            . ' RETURNING ' . $returning);
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
        if ($query->keys !== null && !empty($query->indexes)) {
            throw new Exception('Only one of useKeys or useIndex can be used, not both.');
        }

        if ($query->keys !== null) {
            return 'USE KEYS ' . $this->wrapData($query->keys);
        }

        if (!empty($query->indexes)) {
            return 'USE INDEX (' . implode(', ', array_map(function ($index) {
                    if (!in_array($index['type'], [self::INDEX_TYPE_VIEW, self::INDEX_TYPE_GSI])) {
                        throw new Exception('Unsupported index type ' . json_encode($index['type']) . '.');
                    }
                    return $this->wrapValue($index['name']) . ' USING ' . $index['type'];
                }, $query->indexes)) . ')';
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

    /**
     * @param string $sql
     * @param array $bindings
     * @return string
     */
    public function applyBindings(string $sql, array $bindings)
    {
        $shiftIdentifier = function (string $sql) {
            preg_match('/^`(``|[^`])*`/u', $sql, $match);
            $length = isset($match[0]) ? mb_strlen($match[0]) : mb_strlen($sql);
            return [mb_substr($sql, 0, $length), mb_substr($sql, $length)];
        };
        $shiftLiteralSinglequote = function (string $sql) {
            preg_match('/^\'(\'\'|[^\'])*\'/u', $sql, $match);
            $length = isset($match[0]) ? mb_strlen($match[0]) : mb_strlen($sql);
            return [mb_substr($sql, 0, $length), mb_substr($sql, $length)];
        };
        $shiftLiteralDoublequote = function (string $sql) use ($bindings) {
            preg_match('/^"(\\\\\\\\|\\\\[^\\\\]|[^\\\\"])*"/u', $sql, $match);
            $length = isset($match[0]) ? mb_strlen($match[0]) : mb_strlen($sql);
            return [mb_substr($sql, 0, $length), mb_substr($sql, $length)];
        };

        $tokens = [];
        while (!empty($sql)) {
            $identifierPos = mb_strpos($sql, '`');
            $literalSinglequotePos = mb_strpos($sql, '\'');
            $literalDoublequotePos = mb_strpos($sql, '"');
            $firstPos = $identifierPos !== false ? $identifierPos : null;
            $firstPos = $literalSinglequotePos !== false ? ($firstPos === null ? $literalSinglequotePos : min($literalSinglequotePos,
                $firstPos)) : $firstPos;
            $firstPos = $literalDoublequotePos !== false ? ($firstPos === null ? $literalDoublequotePos : min($literalDoublequotePos,
                $firstPos)) : $firstPos;

            if ($firstPos !== null) {
                $tokens[] = [
                    'context' => 'raw',
                    'sql' => mb_substr($sql, 0, $firstPos)
                ];
                $sql = mb_substr($sql, $firstPos);
            }

            if ($identifierPos === $firstPos) {
                list($identifier, $sql) = $shiftIdentifier($sql);
                $tokens[] = [
                    'context' => 'identifier',
                    'sql' => $identifier
                ];
            } elseif ($literalSinglequotePos === $firstPos) {
                list($literal, $sql) = $shiftLiteralSinglequote($sql);
                $tokens[] = [
                    'context' => 'literal',
                    'sql' => $literal
                ];
            } elseif ($literalDoublequotePos === $firstPos) {
                list($literal, $sql) = $shiftLiteralDoublequote($sql);
                $tokens[] = [
                    'context' => 'literal',
                    'sql' => $literal
                ];
            } else {
                $tokens[] = [
                    'context' => 'raw',
                    'sql' => $sql
                ];
                $sql = '';
            }
        }

        foreach ($tokens as $key => $token) {
            if ($token['context'] === 'raw') {
                $tokenSqls = explode('?', $token['sql']);
                $tokenSql = $tokenSqls[0];
                for($i = 1; $i < count($tokenSqls); $i++) {
                    $data = empty($bindings) ? '?' : self::wrapData(array_shift($bindings));
                    $tokenSql .= $data.$tokenSqls[$i];
                }
                $tokens[$key]['sql'] = $tokenSql;
            }
        }

        $sql = implode('', array_map(function ($token) {
            return $token['sql'];
        }, $tokens));

        return $sql;
    }

}
