<?php declare(strict_types=1);

use ORT\Interactive\Couchbase\Eloquent\Model as Eloquent;

class Address extends Eloquent
{
    protected static $unguarded = true;

    public function addresses()
    {
        return $this->embedsMany('Address');
    }
}
