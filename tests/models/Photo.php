<?php

use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Photo extends Eloquent
{
    protected $table = 'photos';
    protected static $unguarded = true;

    public function imageable()
    {
        return $this->morphTo();
    }
}
