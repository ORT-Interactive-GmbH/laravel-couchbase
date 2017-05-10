<?php
    
    class ConnectionTest extends TestCase
    {
        public function testConnection()
        {
            $connection = DB::connection('couchbase');
            $this->assertInstanceOf('Mpociot\Couchbase\Connection', $connection);
        }
    
        /**
         * @group testReconnect
         */
        public function testReconnect()
        {
            /** @var \Mpociot\Couchbase\Connection $c1 */
            /** @var \Mpociot\Couchbase\Connection $c2 */
            $c1 = DB::connection('couchbase');
            $hash1 = spl_object_hash($c1->getCouchbaseCluster());
            $c2 = DB::connection('couchbase');
            $hash2 = spl_object_hash($c2->getCouchbaseCluster());
            $this->assertEquals($hash1, $hash2);
    
            $c1 = DB::connection('couchbase');
            $hash1 = spl_object_hash($c1->getCouchbaseCluster());
            $c1->reconnect();
            $c2 = DB::connection('couchbase');
            $hash2 = spl_object_hash($c2->getCouchbaseCluster());
            $this->assertNotEquals($hash1, $hash2);
        }
    
        public function testDb()
        {
            $connection = DB::connection('couchbase');
            $this->assertInstanceOf('CouchbaseBucket', $connection->getCouchbaseBucket());
    
            $connection = DB::connection('couchbase');
            $this->assertInstanceOf('CouchbaseCluster', $connection->getCouchbaseCluster());
        }
    
        public function testBucketWithTypes()
        {
            $collection = DB::connection('couchbase')->builder('unittests');
            $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $collection);
    
            $collection = DB::connection('couchbase')->table('unittests');
            $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $collection);
    
            $collection = DB::connection('couchbase')->type('unittests');
            $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $collection);
        }
    
        public function testQueryLog()
        {
            DB::enableQueryLog();
    
            $this->assertEquals(0, count(DB::getQueryLog()));
    
            DB::type('items')->get();
            $this->assertEquals(1, count(DB::getQueryLog()));
    
            DB::type('items')->count();
            $this->assertEquals(2, count(DB::getQueryLog()));
    
            DB::type('items')->where('name', 'test')->update(['name' => 'test']);
            $this->assertEquals(3, count(DB::getQueryLog()));
    
            DB::type('items')->where('name', 'test')->delete();
            $this->assertEquals(4, count(DB::getQueryLog()));
    
            DB::type('items')->insert(['name' => 'test']);
            //$this->assertEquals(5, count(DB::getQueryLog()));
    
        }
    
        public function testDriverName()
        {
            $driver = DB::connection('couchbase')->getDriverName();
            $this->assertEquals('couchbase', $driver);
        }
    }
