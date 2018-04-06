<?php declare(strict_types=1);

namespace Mpociot\Couchbase\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne as EloquentHasOne;
use Illuminate\Support\Arr;

class HasOne extends EloquentHasOne
{

    /**
     * Set the base constraints on the relation query.
     *
     * @return void
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            if($this->foreignKey === '_id') {
                $this->query->useKeys(is_array($this->getParentKey()) ? $this->getParentKey() : [$this->getParentKey()]);
            } else {
                $this->query->where($this->foreignKey, '=', $this->getParentKey());
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        if($this->foreignKey === '_id') {
            $this->query->useKeys(Arr::flatten($this->getKeys($models, $this->localKey)));
        } else {
            $this->query->whereIn(
                $this->foreignKey, $this->getKeys($models, $this->localKey)
            );
        }
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getHasCompareKey()
    {
        return $this->getForeignKeyName();
    }

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  \Illuminate\Database\Eloquent\Builder $parent
     * @param  array|mixed $columns
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getRelationQuery(Builder $query, Builder $parent, $columns = ['*'])
    {
        $query->select($columns);

        $key = $this->wrap($this->getQualifiedParentKeyName());

        return $query->whereNotNull($this->getHasCompareKey());
    }

    /**
     * @inheritdoc
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $foreignKey = $this->getForeignKeyName();

        return $query->select($foreignKey);
    }

    /**
     * Get the key for comparing against the parent key in "has" query.
     *
     * @return string
     */
    public function getForeignKeyName()
    {
        return $this->foreignKey;
    }

    /**
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        return $this->getForeignKey();
    }
}
