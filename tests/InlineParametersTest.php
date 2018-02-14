<?php

use Mpociot\Couchbase\Query\Builder as Query;
use Mpociot\Couchbase\Query\Grammar;

class InlineParametersTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        putenv('CB_INLINE_PARAMETERS=true');
        parent::setUpBeforeClass();
    }

    /**
     * @group InlineParametersTest
     * @group ParametersTest
     */
    public function testInlineParameters()
    {
        $query = DB::table('table6')->select();

        $this->assertEquals(true, config('database.connections.couchbase.inline_parameters'));
        $this->assertEquals(true, DB::hasInlineParameters());
        $this->assertEquals('select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id` from `' . $query->from . '` where `eloquent_type` = "table6"',
            $query->toSql());
    }
}
