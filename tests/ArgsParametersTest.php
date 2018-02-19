<?php

use Mpociot\Couchbase\Query\Builder as Query;
use Mpociot\Couchbase\Query\Grammar;

class ArgsParametersTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        putenv('CB_INLINE_PARAMETERS=false');
        parent::setUpBeforeClass();
    }

    /**
     * @group ArgsParametersTest
     * @group ParametersTest
     */
    public function testParameters()
    {
        $query = DB::table('table6')->select();

        $this->assertEquals(false, config('database.connections.couchbase.inline_parameters'));
        $this->assertEquals(false, DB::hasInlineParameters());
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = ?',
            $query->toSql());
    }
}
