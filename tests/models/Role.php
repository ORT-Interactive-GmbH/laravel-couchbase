<?php declare(strict_types=1);

use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Role extends Eloquent
{
    protected $connection = 'couchbase-not-default';
    protected $table = 'roles';
    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo('User');
    }

    public function mysqlUser()
    {
        return $this->belongsTo('MysqlUser');
    }
}
