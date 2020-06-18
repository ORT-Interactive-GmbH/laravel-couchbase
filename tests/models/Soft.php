<?php declare(strict_types=1);

use ORT\Interactive\Couchbase\Eloquent\Model as Eloquent;
use ORT\Interactive\Couchbase\Eloquent\SoftDeletes;

class Soft extends Eloquent
{
    use SoftDeletes;

    protected $table = 'soft';
    protected static $unguarded = true;
    protected $dates = ['deleted_at'];
}
