<?php

use Illuminate\Support\Facades\DB;
use Mpociot\Couchbase\Events\QueryFired;

class InlineParametersTest extends TestCase
{
    public static function setUpBeforeClass()
    {
        putenv('CB_INLINE_PARAMETERS=false');
        putenv('CB_INLINE_PARAMETERS_DEFAULT_BUCKET=true');
        parent::setUpBeforeClass();
    }

    /**
     * @group InlineParametersTest
     */
    public function testInlineParameters()
    {
        /** @var \Mpociot\Couchbase\Query\Builder $query */
        $query = DB::table('table6')->select();

        $this->assertEquals(false, config('database.connections.couchbase-not-default.inline_parameters'));
        $this->assertEquals(false, DB::connection('couchbase-not-default')->hasInlineParameters());

        $this->assertEquals(true, config('database.connections.couchbase-default.inline_parameters'));
        $this->assertEquals(true, DB::hasInlineParameters());

        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = "table6"');
    }

    /**
     * @group InlineParametersTest
     */
    public function testInlineParametersSwitchToOff()
    {
        /** @var \Mpociot\Couchbase\Query\Builder $query */
        $query = DB::table('table6')->select();

        $this->assertEquals(true, DB::hasInlineParameters());

        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = "table6"',
            []);

        DB::setInlineParameters(false);
        $this->assertEquals(false, DB::hasInlineParameters());
        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = ?',
            ['table6']);
    }

    /**
     * @group InlineParametersTest
     */
    public function testInlineParametersSwitchToOn()
    {
        DB::setInlineParameters(false);

        /** @var \Mpociot\Couchbase\Query\Builder $query */
        $query = DB::table('table6')->select();

        $this->assertEquals(false, DB::hasInlineParameters());
        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = ?',
            ['table6']);

        DB::setInlineParameters(true);
        $this->assertEquals(true, DB::hasInlineParameters());
        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = "table6"',
            []);
    }

    /**
     * @group InlineParametersTest
     */
    public function testInlineParametersWhereRaw()
    {
        DB::setInlineParameters(true);

        /** @var \Mpociot\Couchbase\Query\Builder $query */
        $query = DB::table('table6')->whereRaw('`foo` = ?', ['bar']);

        $this->assertEquals(true, DB::hasInlineParameters());
        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = "table6" and `foo` = "bar"',
            []);


        /** @var \Mpociot\Couchbase\Query\Builder $query */
        $query = DB::table('table6')->whereRaw('`foo?` = ? or `abc` = ?', ['bar', 'def']);

        $this->assertEquals(true, DB::hasInlineParameters());
        $this->assertSelectSqlEquals($query,
            'select `' . $query->from . '`.*, meta(`' . $query->from . '`).`id` as `_id`'
            . ' from `' . $query->from . '` where `eloquent_type` = "table6" and `foo?` = "bar" or `abc` = "def"',
            []);
    }

    /**
     * @param string $sql
     * @return int
     */
    private function getActualBindingsCount(string $sql)
    {
        $this->assertTrue(mb_strpos($sql, '$') === false,
            'Assert query does not contain \'$\', this would change the result.');

        $rows = DB::select('explain ' . $sql);
        $this->assertArrayHasKey(0, $rows);
        $explain = $rows[0];
        $this->assertArrayHasKey('plan', $explain);
        $this->assertArrayHasKey('text', $explain);
        $this->assertEquals($sql, $explain['text']);
        $count = 0;
        $countRecursive = function ($value) use (&$count, &$countRecursive) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $countRecursive($item);
                }
            } else {
                if (preg_match('/\\$([0-9]+)/', $value, $match)) {
                    $count = max($count, (int)$match[1]);
                }
            }
        };
        $countRecursive($explain['plan']);
        return $count;
    }

    /**
     * @group InlineParametersTest
     */
    public function testApplyBindingsBasic()
    {
        $this->assertEquals(0, $this->getActualBindingsCount('select 1'));
        $this->assertEquals(1, $this->getActualBindingsCount('select ?'));
        $this->assertEquals(3, $this->getActualBindingsCount('select ?, ?, ?'));

        $this->assertEquals('"a"', DB::getQueryGrammar()->applyBindings('?', ['a']));

        $this->assertEquals('"a" "b" "c"', DB::getQueryGrammar()->applyBindings('? ? ?', ['a', 'b', 'c']));

        $this->assertEquals('"a" "b" "c" ? ?', DB::getQueryGrammar()->applyBindings('? ? ? ? ?', ['a', 'b', 'c']));
    }

    /**
     * @group InlineParametersTest
     */
    public function testApplyBindingsIdentifier()
    {
        $this->assertEquals(0, $this->getActualBindingsCount('select 1 as `ident?ifier`'));
        $this->assertEquals(1, $this->getActualBindingsCount('select ? as `ident?ifier`'));
        $this->assertEquals(3,
            $this->getActualBindingsCount('select ? as `ident?ifier`, ? as `ident?ifier2`, ? as `ident?ifier3`'));

        $this->assertEquals('`ident?ifier` "b"', DB::getQueryGrammar()->applyBindings('`ident?ifier` ?', ['b']));
        $this->assertEquals('`ident?``?ifier2` "c"',
            DB::getQueryGrammar()->applyBindings('`ident?``?ifier2` ?', ['c']));
        $this->assertEquals('`ident?\'?ifier3` "d"',
            DB::getQueryGrammar()->applyBindings('`ident?\'?ifier3` ?', ['d']));
        $this->assertEquals('`ident?"?ifier4` "e"', DB::getQueryGrammar()->applyBindings('`ident?"?ifier4` ?', ['e']));
        $this->assertEquals('`ident?"\'``?ifier5` "f"',
            DB::getQueryGrammar()->applyBindings('`ident?"\'``?ifier5` ?', ['f']));
    }

    /**
     * @group InlineParametersTest
     */
    public function testApplyBindingsLiteralSinglequote()
    {
        $this->assertEquals(0, $this->getActualBindingsCount('select \'?\''));
        $this->assertEquals(1, $this->getActualBindingsCount('select \'?\', ?'));
        $this->assertEquals(3, $this->getActualBindingsCount('select \'?\', ?, \'?\', ?, \'?\', ?'));

        $this->assertEquals('\'string?\' "g"', DB::getQueryGrammar()->applyBindings('\'string?\' ?', ['g']));
        $this->assertEquals('\'string?`?\' "h"', DB::getQueryGrammar()->applyBindings('\'string?`?\' ?', ['h']));
        $this->assertEquals('\'string?\'\'?\' "i"', DB::getQueryGrammar()->applyBindings('\'string?\'\'?\' ?', ['i']));
        $this->assertEquals('\'string?"?\' "j"', DB::getQueryGrammar()->applyBindings('\'string?"?\' ?', ['j']));
        $this->assertEquals('\'string?"\'\'`?\' "k"',
            DB::getQueryGrammar()->applyBindings('\'string?"\'\'`?\' ?', ['k']));
    }

    /**
     * @group InlineParametersTest
     */
    public function testApplyBindingsLiteralJsonString()
    {
        $this->assertEquals(0, $this->getActualBindingsCount('select "?"'));
        $this->assertEquals(1, $this->getActualBindingsCount('select "?", ?'));
        $this->assertEquals(3, $this->getActualBindingsCount('select "?", ?, "?", ?, "?", ?'));

        $this->assertEquals(json_encode('?') . ' "?l" "l2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?') . ' ? ? ?', ['?l', 'l2']));
        $this->assertEquals(json_encode('?`?') . ' "?m" "m2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?`?') . ' ? ? ?', ['?m', 'm2']));
        $this->assertEquals(json_encode('?\'?') . ' "?n" "n2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?\'?') . ' ? ? ?', ['?n', 'n2']));
        $this->assertEquals(json_encode('?"?') . ' "?o" "o2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?"?') . ' ? ? ?', ['?o', 'o2']));
        $this->assertEquals(json_encode('?"\'`?') . ' "?p" "p2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?"\'`?') . ' ? ? ?', ['?p', 'p2']));
        $this->assertEquals(json_encode('?\"?') . '"?q" "" "q2" ? ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?\"?') . '? "" ? ? ?', ['?q', 'q2']));
        $this->assertEquals(json_encode('?\\') . '"?r" "" "r2" ? ?',
            DB::getQueryGrammar()->applyBindings(json_encode('?\\') . '? "" ? ? ?', ['?r', 'r2']));
    }

    /**
     * @group InlineParametersTest
     */
    public function testApplyBindingsLiteralJson()
    {
        $this->assertEquals(0, $this->getActualBindingsCount('select '.json_encode(['?' => '?']).''));
        $this->assertEquals(1, $this->getActualBindingsCount('select '.json_encode(['?' => '?']).', ?'));
        $this->assertEquals(3, $this->getActualBindingsCount('select '.json_encode(['?' => '?']).', ?, '.json_encode(['?' => '?']).', ?, '.json_encode(['?' => '?']).', ?'));

        $this->assertEquals(json_encode(['?' => '?']) . ' "?l" "l2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?']) . ' ? ? ?', ['?l', 'l2']));
        $this->assertEquals(json_encode(['?' => '?`?']) . ' "?m" "m2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?`?']) . ' ? ? ?', ['?m', 'm2']));
        $this->assertEquals(json_encode(['?' => '?\'?']) . ' "?n" "n2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?\'?']) . ' ? ? ?', ['?n', 'n2']));
        $this->assertEquals(json_encode(['?' => '?"?']) . ' "?o" "o2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?"?']) . ' ? ? ?', ['?o', 'o2']));
        $this->assertEquals(json_encode(['?' => '?"\'`?']) . ' "?p" "p2" ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?"\'`?']) . ' ? ? ?', ['?p', 'p2']));
        $this->assertEquals(json_encode(['?' => '?\"?']) . '"?q" "" "q2" ? ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?\"?']) . '? "" ? ? ?', ['?q', 'q2']));
        $this->assertEquals(json_encode(['?' => '?\\']) . '"?r" "" "r2" ? ?',
            DB::getQueryGrammar()->applyBindings(json_encode(['?' => '?\\']) . '? "" ? ? ?', ['?r', 'r2']));
    }

    /**
     * @group InlineParametersTest
     */
    public function testStatement()
    {
        DB::setInlineParameters(false);
        $this->assertEquals(false, DB::hasInlineParameters());
        $this->assertQueryFiredEquals('select * from `'.DB::getBucketName().'` where eloquent_type = ? LIMIT 1', ['test'], function(){
            DB::statement('select * from `'.DB::getBucketName().'` where eloquent_type = ? LIMIT 1', ['test']);
        });

        DB::setInlineParameters(true);
        $this->assertEquals(true, DB::hasInlineParameters());
        $this->assertQueryFiredEquals('select * from `'.DB::getBucketName().'` where eloquent_type = "test" LIMIT 1', [], function(){
            DB::statement('select * from `'.DB::getBucketName().'` where eloquent_type = ? LIMIT 1', ['test']);
        });
    }
}
