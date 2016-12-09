<?php

use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Role extends Eloquent
{
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
