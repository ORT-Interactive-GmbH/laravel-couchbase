<?php declare(strict_types=1);
/**
 * Created by PhpStorm.
 * User: lukas.quast
 * Date: 28.08.17
 * Time: 11:48
 */

namespace Mpociot\Couchbase\Query;


final class MissingValue
{
    /**
     * MissingValue constructor.
     * @deprecated Please use getMissingValue() instead
     */
    public function __construct()
    {

    }

    public static function getMissingValue() {
        static $missingValue;
        if(!isset($missingValue)) {
            $missingValue = new self();
        }
        return $missingValue;
    }
}