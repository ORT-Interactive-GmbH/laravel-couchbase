<?php declare(strict_types=1);

namespace ORT\Interactive\Couchbase\Relations;

use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;

class MorphTo extends EloquentMorphTo
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
            $this->query->where($this->ownerKey, '=', $this->parent->{$this->foreignKey});
        }
    }

    /**
     * Get all of the relation results for a type.
     *
     * @param  string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getResultsByType($type)
    {
        $instance = $this->createModelByType($type);

        $key = $instance->getKeyName();

        $query = $instance->newQuery();

        return $query->whereIn($key, $this->gatherKeysByType($type))->get();
    }
}
