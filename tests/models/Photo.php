<?php declare(strict_types=1);

use ORT\Interactive\Couchbase\Eloquent\Model as Eloquent;

class Photo extends Eloquent
{
    protected $table = 'photos';
    protected static $unguarded = true;

    public function imageable()
    {
        return $this->morphTo();
    }
}
