<?php

use ORT\Interactive\Couchbase\Query\Builder as Query;
use ORT\Interactive\Couchbase\Query\Grammar;

class ArgsParametersTest extends TestCase
{
    public static function setUpBeforeClass(): void
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
