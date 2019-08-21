<?php declare(strict_types=1);

namespace Mpociot\Couchbase\Relations;

use Illuminate\Support\Arr;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            if($this->ownerKey === '_id') {
                $this->query->useKeys([$this->parent->{$this->foreignKey}]);
            } else {
                $this->query->where($this->ownerKey, '=', $this->parent->{$this->foreignKey});
            }
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param  array $models
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->ownerKey;

        if($key === '_id') {
            $this->query->useKeys(Arr::flatten($this->getEagerModelKeys($models)));
        } else {
            $this->query->whereIn($key, $this->getEagerModelKeys($models));
        }
    }
}
