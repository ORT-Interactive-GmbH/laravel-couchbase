<?php
    
    use Mpociot\Couchbase\Query\Builder as Query;
    
    class QueryBuilderTest extends TestCase
    {
        /**
         * @group QueryBuilderTest
         */
        public function tearDown()
        {
            DB::table('users')->truncate();
            DB::table('items')->truncate();
        }
        
        /**
         * @group QueryBuilderTest
         */
        public function testCollection()
        {
            $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', DB::table('users'));
        }
        
        /**
         * @group QueryBuilderTest
         */
        public function testGet()
        {
            $users = DB::table('users')->get();
            $this->assertEquals(0, count($users));
    
            DB::table('users')->useKeys('users::john_doe')->insert(['name' => 'John Doe']);
    
            $users = DB::table('users')->get();
            $this->assertEquals(1, count($users));
        }
        
        /**
         * @group QueryBuilderTest
         */
        public function testNoDocument()
        {
            $items = DB::table('items')->where('name', 'nothing')->get()->toArray();
            $this->assertEquals([], $items);
    
            $item = DB::table('items')->where('name', 'nothing')->first();
            $this->assertEquals(null, $item);
    
            $item = DB::table('items')->where('_id', '51c33d8981fec6813e00000a')->first();
            $this->assertEquals(null, $item);
        }
        
        /**
         * @group QueryBuilderTest
         */
        public function testInsert()
        {
            DB::table('users')->useKeys('users::tags')->insert([
                'tags' => ['tag1', 'tag2'],
                'name' => 'John Doe',
            ]);
    
            $users = DB::table('users')->get();
            $this->assertEquals(1, count($users));
    
            $user = $users[0];
            $this->assertEquals('John Doe', $user['name']);
            $this->assertTrue(is_array($user['tags']));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testInsertGetId()
        {
            $id = DB::table('users')->insertGetId(['name' => 'John Doe']);
            $this->assertTrue(is_string($id));
            $id = DB::table('users')->useKeys('foobar')->insertGetId(['name' => 'John Doe']);
            $this->assertSame('foobar', $id);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testBatchInsert()
        {
            DB::table('users')->useKeys('batch')->insert([
                [
                    'tags' => ['tag1', 'tag2'],
                    'name' => 'Jane Doe',
                ],
                [
                    'tags' => ['tag3'],
                    'name' => 'John Doe',
                ],
            ]);
    
            $users = DB::table('users')->get();
    
            $this->assertEquals(2, count($users));
            $this->assertTrue(is_array($users[0]['tags']));
        }
    
        /**
         * @group QueryBuilderTest
         * @group testFind
         */
        public function testFind()
        {
            $id = 'my_id';
            DB::table('users')->useKeys($id)->insert(['name' => 'John Doe']);
    
            $user = DB::table('users')->find($id);
            $this->assertEquals('John Doe', $user['name']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testFindNull()
        {
            $user = DB::table('users')->find(null);
            $this->assertEquals(null, $user);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testCount()
        {
            DB::table('users')->useKeys('users.1')->insert(['name' => 'Jane Doe']);
            DB::table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe']);
    
            $this->assertEquals(2, DB::table('users')->count());
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testUpdate()
        {
    
            DB::table('users')->useKeys('users.1')->insert(['name' => 'John Doe', 'age' => 30]);
            DB::table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe', 'age' => 20]);
    
            DB::table('users')->where('name', 'John Doe')->update(['age' => 100]);
            $users = DB::table('users')->get();
    
            $john = DB::table('users')->where('name', 'John Doe')->first();
            $jane = DB::table('users')->where('name', 'Jane Doe')->first();
            $this->assertEquals(100, $john['age']);
            $this->assertEquals(20, $jane['age']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testDelete()
        {
    
            DB::table('users')->useKeys('users.1')->insert(['name' => 'John Doe', 'age' => 25]);
            DB::table('users')->useKeys('users.2')->insert(['name' => 'Jane Doe', 'age' => 20]);
    
            DB::table('users')->where('age', '<', 10)->delete();
            $this->assertEquals(2, DB::table('users')->count());
    
            DB::table('users')->where('age', '<', 25)->delete();
            $this->assertEquals(1, DB::table('users')->count());
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testTruncate()
        {
            DB::table('users')->useKeys('john')->insert(['name' => 'John Doe']);
            DB::table('users')->truncate();
            $this->assertEquals(0, DB::table('users')->count());
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testSubKey()
        {
            DB::table('users')->useKeys('users.1')->insert([
                'name'    => 'John Doe',
                'address' => ['country' => 'Belgium', 'city' => 'Ghent'],
            ]);
            DB::table('users')->useKeys('users.2')->insert([
                'name'    => 'Jane Doe',
                'address' => ['country' => 'France', 'city' => 'Paris'],
            ]);
    
            $users = DB::table('users')->where('address.country', 'Belgium')->get();
            $this->assertEquals(1, count($users));
            $this->assertEquals('John Doe', $users[0]['name']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testInArray()
        {
            DB::table('items')->useKeys('items.1')->insert([
                'tags' => ['tag1', 'tag2', 'tag3', 'tag4'],
            ]);
            DB::table('items')->useKeys('items.2')->insert([
                'tags' => ['tag2'],
            ]);
    
            $items = DB::table('items')->whereRaw('ARRAY_CONTAINS(tags, "tag2")')->get();
            $this->assertEquals(2, count($items));
    
            $items = DB::table('items')->whereRaw('ARRAY_CONTAINS(tags, "tag1")')->get();
            $this->assertEquals(1, count($items));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testDistinct()
        {
            DB::table('items')->useKeys('item:1')->insert(['name' => 'knife', 'type' => 'sharp']);
            DB::table('items')->useKeys('item:2')->insert(['name' => 'fork',  'type' => 'sharp']);
            DB::table('items')->useKeys('item:3')->insert(['name' => 'spoon', 'type' => 'round']);
            DB::table('items')->useKeys('item:4')->insert(['name' => 'spoon', 'type' => 'round']);
    
            $items = DB::table('items')->distinct('name')->get()->toArray();
            sort($items);
            $this->assertEquals(3, count($items));
            $this->assertEquals([['name' => 'fork'], ['name' => 'knife'], ['name' => 'spoon']], $items);
    
            $types = DB::table('items')->distinct('type')->get()->toArray();
            sort($types);
            $this->assertEquals(2, count($types));
            $this->assertEquals([['type' => 'round'], ['type' => 'sharp']], $types);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testTake()
        {
    
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);
    
            $items = DB::table('items')->orderBy('name')->take(2)->get();
            $this->assertEquals(2, count($items));
            $this->assertEquals('fork', $items[0]['name']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testSkip()
        {
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);
    
            $items = DB::table('items')->orderBy('name')->skip(2)->get();
            $this->assertEquals(2, count($items));
            $this->assertEquals('spoon', $items[0]['name']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testPluck()
        {
            DB::table('users')->useKeys('user.1')->insert(['name' => 'Jane Doe', 'age' => 20]);
            DB::table('users')->useKeys('user.2')->insert(['name' => 'John Doe', 'age' => 25]);
    
            $age = DB::table('users')->where('name', 'John Doe')->pluck('age')->toArray();
            $this->assertEquals([25], $age);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testList()
        {
    
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);
    
            $list = DB::table('items')->pluck('name')->toArray();
            sort($list);
            $this->assertEquals(4, count($list));
            $this->assertEquals(['fork', 'knife', 'spoon', 'spoon'], $list);
    
            $list = DB::table('items')->pluck('type', 'name')->toArray();
            $this->assertEquals(3, count($list));
            $this->assertEquals(['knife' => 'sharp', 'fork' => 'sharp', 'spoon' => 'round'], $list);
    
            $list = DB::table('items')->pluck('name', '_id')->toArray();
            $this->assertEquals(4, count($list));
            $this->assertEquals(18, strlen(key($list)));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testAggregate()
        {
    
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'knife', 'type' => 'sharp', 'amount' => 34]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'fork',  'type' => 'sharp', 'amount' => 20]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 3]);
            DB::table('items')->useKeys('item.'.uniqid())->insert(['name' => 'spoon', 'type' => 'round', 'amount' => 14]);
    
            $this->assertEquals(71, DB::table('items')->sum('amount'));
            $this->assertEquals(4, DB::table('items')->count('amount'));
            $this->assertEquals(3, DB::table('items')->min('amount'));
            $this->assertEquals(34, DB::table('items')->max('amount'));
            $this->assertEquals(17.75, DB::table('items')->avg('amount'));
    
            $this->assertEquals(2, DB::table('items')->where('name', 'spoon')->count('amount'));
            $this->assertEquals(14, DB::table('items')->where('name', 'spoon')->max('amount'));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testSubdocumentAggregate()
        {
            DB::table('items')->insert([
                ['name' => 'knife', 'amount' => ['hidden' => 10, 'found' => 3]],
                ['name' => 'fork',  'amount' => ['hidden' => 35, 'found' => 12]],
                ['name' => 'spoon', 'amount' => ['hidden' => 14, 'found' => 21]],
                ['name' => 'spoon', 'amount' => ['hidden' => 6, 'found' => 4]],
            ]);
    
            $this->assertEquals(65, DB::table('items')->sum('amount.hidden'));
            $this->assertEquals(4, DB::table('items')->count('amount.hidden'));
            $this->assertEquals(6, DB::table('items')->min('amount.hidden'));
            $this->assertEquals(35, DB::table('items')->max('amount.hidden'));
            $this->assertEquals(16.25, DB::table('items')->avg('amount.hidden'));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testUnset()
        {
            $id1 = DB::table('users')->insertGetId(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
            $id2 = DB::table('users')->insertGetId(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
    
            DB::table('users')->where('name', 'John Doe')->unset('note1');
    
            $user1 = DB::table('users')->find($id1);
            $user2 = DB::table('users')->find($id2);
    
            $this->assertFalse(isset($user1['note1']));
            $this->assertTrue(isset($user1['note2']));
            $this->assertTrue(isset($user2['note1']));
            $this->assertTrue(isset($user2['note2']));
    
            DB::table('users')->where('name', 'Jane Doe')->unset(['note1', 'note2']);
    
            $user2 = DB::table('users')->find($id2);
            $this->assertFalse(isset($user2['note1']));
            $this->assertFalse(isset($user2['note2']));
        }
        
        /**
         * @group QueryBuilderTest
         * @group testUpdateSubdocument
         */
        public function testUpdateSubdocument()
        {
            $id = DB::table('users')->insertGetId(['name' => 'John Doe', 'address' => ['country' => 'Belgium']]);
    
            //dd($id, get_class(DB::table('users')), DB::table('users')->where('_id', $id)->toSql(), DB::table('users')->useKeys($id)->toSql());
            DB::table('users')->where('_id', $id)->update(['address.country' => 'England']);
    
            $check = DB::table('users')->find($id);
            $this->assertEquals('England', $check['address']['country']);
        }
        /**
         * @group QueryBuilderTest
         */
        public function testIncrement()
        {
            DB::table('users')->insert([
                ['name' => 'John Doe', 'age' => 30, 'note' => 'adult'],
                ['name' => 'Jane Doe', 'age' => 10, 'note' => 'minor'],
                ['name' => 'Robert Roe', 'age' => null],
                ['name' => 'Mark Moe'],
            ]);
    
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(30, $user['age']);
    
            DB::table('users')->where('name', 'John Doe')->increment('age');
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(31, $user['age']);
    
            DB::table('users')->where('name', 'John Doe')->decrement('age');
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(30, $user['age']);
    
            DB::table('users')->where('name', 'John Doe')->increment('age', 5);
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(35, $user['age']);
    
            DB::table('users')->where('name', 'John Doe')->decrement('age', 5);
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(30, $user['age']);
    
            DB::table('users')->where('name', 'Jane Doe')->increment('age', 10, ['note' => 'adult']);
            $user = DB::table('users')->where('name', 'Jane Doe')->first();
            $this->assertEquals(20, $user['age']);
            $this->assertEquals('adult', $user['note']);
    
            DB::table('users')->where('name', 'John Doe')->decrement('age', 20, ['note' => 'minor']);
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(10, $user['age']);
            $this->assertEquals('minor', $user['note']);
    
            DB::table('users')->increment('age');
            $user = DB::table('users')->where('name', 'John Doe')->first();
            $this->assertEquals(11, $user['age']);
            $user = DB::table('users')->where('name', 'Jane Doe')->first();
            $this->assertEquals(21, $user['age']);
            $user = DB::table('users')->where('name', 'Robert Roe')->first();
            $this->assertEquals(null, $user['age']);
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testWhere()
        {
            /** @var Query $query */
            $query = DB::table('table1')->where('a', '=', 'b');
            $this->assertEquals('select * from `'.$query->from.'` where `eloquent_type` = table1 and `a` = b', $this->queryToSql($query));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testWhereWithTwoParameters()
        {
            /** @var Query $query */
            $query = DB::table('table2')->where('a', 'b');
            $this->assertEquals('select * from `'.$query->from.'` where `eloquent_type` = table2 and `a` = b', $this->queryToSql($query));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testNestedWhere()
        {
            /** @var Query $query */
            $query = DB::table('table3')->where(function(Query $query){
                $query->where('a', 'b');
            });
            $this->assertEquals('select * from `'.$query->from.'` where `eloquent_type` = table3 and (`a` = b)', $this->queryToSql($query));
        }
    
        /**
         * @group QueryBuilderTest
         */
        public function testDictWhere()
        {
            /** @var Query $query */
            $query = DB::table('table4')->where(['a' => 'b']);
            $this->assertEquals('select * from `'.$query->from.'` where `eloquent_type` = table4 and (`a` = b)', $this->queryToSql($query));
            $query = DB::table('table5')->where(['a' => 'b', 'c' => 'd']);
            $this->assertEquals('select * from `'.$query->from.'` where `eloquent_type` = table5 and (`a` = b and `c` = d)', $this->queryToSql($query));
        }
    
        private function queryToSql(Query $query) {
            return str_replace_array('?', $query->getBindings(), $query->toSql());
        }
    }
