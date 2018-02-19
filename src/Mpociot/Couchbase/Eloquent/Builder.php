<?php declare(strict_types=1);

namespace Mpociot\Couchbase\Eloquent;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Mpociot\Couchbase\Query\Builder as QueryBuilder;

class Builder extends EloquentBuilder
{
    /**
     * The methods that should be returned from query builder.
     *
     * @var array
     */
    protected $passthru = [
        'insert',
        'insertGetId',
        'getBindings',
        'getRawBindings',
        'toSql',
        'exists',
        'count',
        'min',
        'max',
        'avg',
        'sum',
        'getConnection',
        'pluck',
        'push',
        'pull',
    ];

    /**
     * Update a record in the database.
     *
     * @param  array $values
     * @param  array $options
     * @return int
     */
    public function update(array $values, array $options = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performUpdate($this->model, $values);

            return 1;
        }

        return $this->query->update($this->addUpdatedAtColumn($values), $options);
    }

    /**
     * Insert a new record into the database.
     *
     * @param  array $values
     * @return bool
     */
    public function insert(array $values)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return true;
        }

        return parent::insert($values);
    }

    /**
     * Paginate the given query.
     *
     * @param  int $perPage
     * @param  array $columns
     * @param  string $pageName
     * @param  int|null $page
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     *
     * @throws \InvalidArgumentException
     */
    public function paginate($perPage = null, $columns = ['*'], $pageName = 'page', $page = null)
    {
        $page = $page ?: Paginator::resolveCurrentPage($pageName);

        $perPage = $perPage ?: $this->model->getPerPage();

        /** @var Builder $builder */
        $builder = $this->forPage($page, $perPage);
        $builder = $builder->applyScopes();

        /** @var QueryBuilder $query */
        $query = $builder->getQuery();

        // first check if result is ordered, else `metrics.sortCount` is not set :/
        if (empty($query->orders)) {
            // should not go here...
            return parent::paginate($perPage, $columns, $pageName, $page);
        }

        $rawResult = $query->getWithMeta();
        if (isset($rawResult->metrics['sortCount'])) {
            $total = $rawResult->metrics['sortCount'];
        } else {
            if ($rawResult->metrics['resultCount'] === 0) {
                $total = 0;
            } else {
                // should not go here...
                $total = $this->getCountForPagination();
            }
        }
        // If we actually found models we will also eager load any relationships that
        // have been specified as needing to be eager loaded, which will solve the
        // n+1 query issue for the developers to avoid running a lot of queries.
        $models = $this->model->hydrate($rawResult->rows->all())->all();
        if (count($models) > 0) {
            $models = $builder->eagerLoadRelations($models);
        }

        $results = $total
            ? $builder->getModel()->newCollection($models)
            : $this->model->newCollection();

        return $this->paginator($results, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'pageName' => $pageName,
        ]);
    }

    /**
     * Insert a new record and get the value of the primary key.
     *
     * @param  array $values
     * @param  string $sequence
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performInsert($this->model, $values);

            return $this->model->getKey();
        }

        return parent::insertGetId($values, $sequence);
    }

    /**
     * Delete a record from the database.
     *
     * @return mixed
     */
    public function delete()
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $relation->performDelete($this->model);

            return $this->model->getKey();
        }

        return parent::delete();
    }

    /**
     * Increment a column's value by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @param  array $extra
     * @return int
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            $result = $this->model->update([$column => $value]);

            return $result;
        }

        return parent::increment($column, $amount, $extra);
    }

    /**
     * Decrement a column's value by a given amount.
     *
     * @param  string $column
     * @param  int $amount
     * @param  array $extra
     * @return int
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        // Intercept operations on embedded models and delegate logic
        // to the parent relation instance.
        if ($relation = $this->model->getParentRelation()) {
            $value = $this->model->{$column};

            // When doing increment and decrements, Eloquent will automatically
            // sync the original attributes. We need to change the attribute
            // temporary in order to trigger an update query.
            $this->model->{$column} = null;

            $this->model->syncOriginalAttribute($column);

            return $this->model->update([$column => $value]);
        }

        return parent::decrement($column, $amount, $extra);
    }

    /**
     * Add a where clause on the primary key to the query.
     *
     * @param  mixed $id
     * @return $this
     */
    public function whereKey($id)
    {
        $this->query->useKeys($id);

        return $this;
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string|\Closure $column
     * @param  string $operator
     * @param  mixed $value
     * @param  string $boolean
     * @return $this
     */
    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column === $this->model->getKeyName() || $column === '_id') {
            $value = func_num_args() == 2 ? $operator : $value;
            $this->whereKey($value);
            return $this;
        }
        return parent::where($column, $operator, $value, $boolean);
    }

    /**
     * Add the "has" condition where clause to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $hasQuery
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $relation
     * @param  string $operator
     * @param  int $count
     * @param  string $boolean
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addHasWhere(EloquentBuilder $hasQuery, Relation $relation, $operator, $count, $boolean)
    {
        $query = $hasQuery->getQuery();

        // Get the number of related objects for each possible parent.
        $relations = $query->pluck($relation->getHasCompareKey())->filter(function($value){
            return $value !== null && $value !== '';
        });
        $relationCount = array_count_values(array_map(function ($id) {
            return (string)$id; // Convert Back ObjectIds to Strings
        }, is_array($relations) ? $relations : $relations->toArray()));

        // Remove unwanted related objects based on the operator and count.
        $relationCount = array_filter($relationCount, function ($counted) use ($count, $operator) {
            // If we are comparing to 0, we always need all results.
            if ($count == 0) {
                return true;
            }

            switch ($operator) {
                case '>=':
                case '<':
                    return $counted >= $count;
                case '>':
                case '<=':
                    return $counted > $count;
                case '=':
                case '!=':
                    return $counted == $count;
            }
        });

        // If the operator is <, <= or !=, we will use whereNotIn.
        $not = in_array($operator, ['<', '<=', '!=']);

        // If we are comparing to 0, we need an additional $not flip.
        if ($count == 0) {
            $not = !$not;
        }

        // All related ids.
        $relatedIds = array_keys($relationCount);

        // Add whereIn to the query.
        return $this->whereIn($this->model->getKeyName(), $relatedIds, $boolean, $not);
    }

    /**
     * Create a raw database expression.
     *
     * @param  closure $expression
     * @return mixed
     */
    public function raw($expression = null)
    {
        // Get raw results from the query builder.
        $results = $this->query->raw($expression);

        // The result is a single object.
        if (is_array($results) and array_key_exists('_id', $results)) {
            return $this->model->newFromBuilder((array)$results);
        }

        return $results;
    }
}
