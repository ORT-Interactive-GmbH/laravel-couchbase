<?php declare(strict_types=1);

use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Item extends Eloquent
{
    protected $table = 'items';
    protected static $unguarded = true;

    public function user()
    {
        return $this->belongsTo('User');
    }

    public function scopeSharp($query)
    {
        return $query->where('type', 'sharp');
    }
}
