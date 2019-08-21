<?php declare(strict_types=1);

class ConnectionTest extends TestCase
{
    public function testConnection()
    {
        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('Mpociot\Couchbase\Connection', $connection);
    }

    /**
     * @group testReconnect
     */
    public function testReconnect()
    {
        $this->markTestSkipped('Reconnect is currently not required.');

        /** @var \Mpociot\Couchbase\Connection $c1 */
        /** @var \Mpociot\Couchbase\Connection $c2 */
        $c1 = DB::connection('couchbase-default');
        $requiredReference = $c1->getCouchbaseCluster();
        // $requiredReference is required because of spl_object_hash(): Once the object is destroyed, its hash may be reused for other objects.
        $hash1 = spl_object_hash($c1->getCouchbaseCluster());
        $c2 = DB::connection('couchbase-default');
        $hash2 = spl_object_hash($c2->getCouchbaseCluster());
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertEquals($hash1, $hash2);

        $c1 = DB::connection('couchbase-default');
        $requiredReference = $c1->getCouchbaseCluster();
        // $requiredReference is required because of spl_object_hash(): Once the object is destroyed, its hash may be reused for other objects.
        $hash1 = spl_object_hash($c1->getCouchbaseCluster());
        DB::purge('couchbase-default');
        $c2 = DB::connection('couchbase-default');
        $hash2 = spl_object_hash($c2->getCouchbaseCluster());
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertNotEquals($hash1, $hash2);
    }

    /**
     * @group testReconnect
     */
    public function testConnectionSingleton()
    {
        /** @var \Mpociot\Couchbase\Connection $c1 */
        /** @var \Mpociot\Couchbase\Connection $c2 */
        $c1 = DB::connection();
        $c2 = DB::connection('couchbase-default');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));

        $c1 = DB::connection();
        $c2 = DB::connection('couchbase-not-default');
        $this->assertNotEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertNotEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));

        $c1 = DB::connection('couchbase-not-default');
        $c2 = DB::connection('couchbase-not-default');
        $this->assertEquals(spl_object_hash($c1), spl_object_hash($c2));
        $this->assertEquals(spl_object_hash($c1->getCouchbaseCluster()), spl_object_hash($c2->getCouchbaseCluster()));
    }

    public function testDb()
    {
        $connection = DB::connection();
        $this->assertInstanceOf('CouchbaseBucket', $connection->getCouchbaseBucket());
        $this->assertInstanceOf('CouchbaseCluster', $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('CouchbaseBucket', $connection->getCouchbaseBucket());
        $this->assertInstanceOf('CouchbaseCluster', $connection->getCouchbaseCluster());

        $connection = DB::connection('couchbase-not-default');
        $this->assertInstanceOf('CouchbaseBucket', $connection->getCouchbaseBucket());
        $this->assertInstanceOf('CouchbaseCluster', $connection->getCouchbaseCluster());
    }

    public function testBucketWithTypes()
    {
        $connection = DB::connection();
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->type('unittests'));

        $connection = DB::connection('couchbase-default');
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->type('unittests'));

        $connection = DB::connection('couchbase-not-default');
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->builder('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->table('unittests'));
        $this->assertInstanceOf('Mpociot\Couchbase\Query\Builder', $connection->type('unittests'));
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
        $this->assertEquals(4, count(DB::getQueryLog())); // insert does not use N1QL-queries

    }

    public function testDriverName()
    {
        $driver = DB::connection()->getDriverName();
        $this->assertEquals('couchbase', $driver);

        $driver = DB::connection('couchbase-default')->getDriverName();
        $this->assertEquals('couchbase', $driver);

        $driver = DB::connection('couchbase-not-default')->getDriverName();
        $this->assertEquals('couchbase', $driver);
    }
}
