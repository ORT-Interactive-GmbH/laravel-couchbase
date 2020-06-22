<?php declare(strict_types=1);

namespace ORT\Interactive\Couchbase;

class Helper
{

    const TYPE_NAME = 'eloquent_type';

    public static function getUniqueId($prefix = null)
    {
        return (($prefix !== null) ? $prefix . '::' : '') . uniqid();
    }

}
