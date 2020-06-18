<?php declare(strict_types=1);

use ORT\Interactive\Couchbase\Eloquent\Model as Eloquent;

class Group extends Eloquent
{
    protected $table = 'groups';
    protected static $unguarded = true;

    public function users()
    {
        return $this->belongsToMany('User', null, 'groups', 'users');
    }
}
