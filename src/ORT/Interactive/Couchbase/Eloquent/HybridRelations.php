<?php declare(strict_types=1);

namespace ORT\Interactive\Couchbase\Eloquent;

use Illuminate\Support\Str;
use ORT\Interactive\Couchbase\Relations\BelongsTo;
use ORT\Interactive\Couchbase\Relations\BelongsToMany;
use ORT\Interactive\Couchbase\Relations\HasMany;
use ORT\Interactive\Couchbase\Relations\HasOne;
use ORT\Interactive\Couchbase\Relations\MorphTo;
use ORT\Interactive\Couchbase\Relations\MorphOne;
use ORT\Interactive\Couchbase\Relations\MorphMany;

trait HybridRelations
{
    /**
     * Define a one-to-one relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function hasOne($related, $foreignKey = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::hasOne($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasOne($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a polymorphic one-to-one relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne|MorphOne
     */
    public function morphOne($related, $name, $type = null, $id = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::morphOne($related, $name, $type, $id, $localKey);
        }

        $instance = new $related;

        list($type, $id) = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphOne($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * Define a one-to-many relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function hasMany($related, $foreignKey = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::hasMany($related, $foreignKey, $localKey);
        }

        $foreignKey = $foreignKey ?: $this->getForeignKey();

        $instance = new $related;

        $localKey = $localKey ?: $this->getKeyName();

        return new HasMany($instance->newQuery(), $this, $foreignKey, $localKey);
    }

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany|MorphMany
     */
    public function morphMany($related, $name, $type = null, $id = null, $localKey = null)
    {
        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::morphMany($related, $name, $type, $id, $localKey);
        }

        $instance = $this->newRelatedInstance($related);

        // Here we will gather up the morph type and ID for the relationship so that we
        // can properly query the intermediate table of a relation. Finally, we will
        // get the table and create the relationship instances for the developers.
        [$type, $id] = $this->getMorphs($name, $type, $id);

        $localKey = $localKey ?: $this->getKeyName();

        return new MorphMany($instance->newQuery(), $this, $type, $id, $localKey);
    }

    /**
     * Define an inverse one-to-one or many relationship.
     *
     * @param string $related
     * @param string $foreignKey
     * @param string $otherKey
     * @param string $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function belongsTo($related, $foreignKey = null, $otherKey = null, $relation = null)
    {
        // If no relation name was given, we will use this debug backtrace to extract
        // the calling method's name and use that as the relationship name as most
        // of the time this will be what we desire to use for the relationships.
        if (is_null($relation)) {
            list($current, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $relation = $caller['function'];
        }

        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::belongsTo($related, $foreignKey, $otherKey, $relation);
        }

        // If no foreign key was supplied, we can use a backtrace to guess the proper
        // foreign key name by using the name of the relationship function, which
        // when combined with an "_id" should conventionally match the columns.
        if (is_null($foreignKey)) {
            $foreignKey = Str::snake($relation) . '_id';
        }

        $instance = new $related;

        // Once we have the foreign key names, we'll just create a new Eloquent query
        // for the related models and returns the relationship instance which will
        // actually be responsible for retrieving and hydrating every relations.
        $query = $instance->newQuery();

        $otherKey = $otherKey ?: $instance->getKeyName();

        return new BelongsTo($query, $this, $foreignKey, $otherKey, $relation);
    }

    /**
     * Define a polymorphic, inverse one-to-one or many relationship.
     *
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $ownerKey
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function morphTo($name = null, $type = null, $id = null, $ownerKey = NULL)
    {
        // If no name is provided, we will use the backtrace to get the function name
        // since that is most likely the name of the polymorphic interface. We can
        // use that to get both the class and foreign key that will be utilized.
        if (is_null($name)) {
            list($current, $caller) = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

            $name = Str::snake($caller['function']);
        }

        list($type, $id) = $this->getMorphs($name, $type, $id);

        // If the type value is null it is probably safe to assume we're eager loading
        // the relationship. When that is the case we will pass in a dummy query as
        // there are multiple types in the morph and we can't use single queries.
        if (is_null($class = $this->$type)) {
            return new MorphTo(
                $this->newQuery(), $this, $id, null, $type, $name
            );
        }

        // If we are not eager loading the relationship we will essentially treat this
        // as a belongs-to style relationship since morph-to extends that class and
        // we will pass in the appropriate values so that it behaves as expected.
        else {
            $class = $this->getActualClassNameForMorph($class);

            $instance = new $class;

            return new MorphTo(
                $instance->newQuery(), $this, $id, $instance->getKeyName(), $type, $name
            );
        }
    }

    /**
     * Define a many-to-many relationship.
     *
     * @param string $related
     * @param string $table
     * @param string $foreignPivotKey
     * @param string $relatedPivotKey
     * @param string $parentKey
     * @param string $relatedKey
     * @param string $relation
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function belongsToMany($related, $table = null, $foreignPivotKey = null,
                                  $relatedPivotKey = null, $parentKey = null,
                                  $relatedKey = null, $relation = null)
    {
        // If no relationship name was passed, we will pull backtraces to get the
        // name of the calling function. We will use that function name as the
        // title of this relation since that is a great convention to apply.
        if (is_null($relation)) {
            $relation = $this->guessBelongsToManyRelation();
        }

        // Check if it is a relation with an original model.
        if (!is_subclass_of($related, Model::class)) {
            return parent::belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relation);
        }

        // First, we'll need to determine the foreign key and "other key" for the
        // relationship. Once we have determined the keys we'll make the query
        // instances as well as the relationship instances we need for this.
        $instance = $this->newRelatedInstance($related);

        $foreignPivotKey = $foreignPivotKey ?: $this->getForeignKey() . 's';

        $relatedPivotKey = $relatedPivotKey ?: $instance->getForeignKey() . 's';

        // If no table name was provided, we can guess it by concatenating the two
        // models using underscores in alphabetical order. The two model names
        // are transformed to snake case from their default CamelCase also.
        if (is_null($table)) {
            $table = $this->joiningTable($related);
        }

        // Now we're ready to create a new query builder for the related model and
        // the relationship instances for the relation. The relations will set
        // appropriate query constraint and entirely manages the hydrations.
        $query = $instance->newQuery();

        return new BelongsToMany(
            $query,
            $this,
            $table,
            $foreignPivotKey,
            $relatedPivotKey,
            $parentKey ?: $this->getKeyName(),
            $relatedKey ?: $instance->getKeyName(),
            $relation
        );
    }

}
