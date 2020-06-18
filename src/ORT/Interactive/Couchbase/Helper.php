<?php declare(strict_types=1);

namespace ORT\Interactive\Couchbase;

class Helper
{
    const TYPE_NAME = 'eloquent_type';

    public static function getUniqueId($praefix = null)
    {
        return (($praefix !== null) ? $praefix . '::' : '') . uniqid();
    }

}
