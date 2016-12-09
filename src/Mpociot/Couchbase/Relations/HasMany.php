<?php namespace Mpociot\Couchbase\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany as EloquentHasMany;

class HasMany extends EloquentHasMany
{

    /**
     * Add the constraints for a relationship query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  \Illuminate\Database\Eloquent\Builder  $parent
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
     * Get the plain foreign key.
     *
     * @return string
     */
    public function getPlainForeignKey()
    {
        return $this->getForeignKey();
    }
}
