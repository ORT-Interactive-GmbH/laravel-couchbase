<?php namespace Mpociot\Couchbase\Query;

use Illuminate\Database\Query\Grammars\Grammar as BaseGrammar;
use Illuminate\Database\Query\Builder as BaseBuilder;

class Grammar extends BaseGrammar
{
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

        return '`' . str_replace('"', '""', $value) . '`';
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
        // returning-clause
        $returning = implode(', ', $query->returning);

        $columns = [];

        foreach ($values as $key) {
            $columns[] = $this->wrap($key);
        }

        $columns = implode(', ', $columns);

        $where = $this->compileWheres($query);
        return trim("update {$table} unset $columns $where RETURNING {$returning}");
    }

    /**
     * {@inheritdoc}
     */
    public function compileInsert(BaseBuilder $query, array $values)
    {
        // keyspace-ref:
        $table = $this->wrapTable($query->from);
        // use-keys-clause:
        if (is_null($query->key)) {
            $query->key(uniqid());
        }
        $keyClause = $this->wrapKey($query->key);
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

        return trim("update {$table} set $columns $forIns $where RETURNING {$returning}");
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
        $keyClause = null;
        if ($query->key) {
            $key = $this->wrapKey($query->key);
            $keyClause = "USE KEYS {$key}";
        }
        // returning-clause
        $returning = implode(', ', $query->returning);
        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        return trim("delete from {$table} {$keyClause} {$where} RETURNING {$returning}");
    }
}
