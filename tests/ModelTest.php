<?php declare(strict_types=1);

class ModelTest extends TestCase
{
    public function tearDown()
    {
        User::truncate();
        Soft::truncate();
        Book::truncate();
        Item::truncate();
    }

    public function testNewModel()
    {
        $user = new User;
        $this->assertInstanceOf('ORT\Interactive\Couchbase\Eloquent\Model', $user);
        $this->assertInstanceOf('ORT\Interactive\Couchbase\Connection', $user->getConnection());
        $this->assertEquals(false, $user->exists);
        $this->assertEquals('users', $user->getTable());
        $this->assertEquals('_id', $user->getKeyName());
    }

    public function testInsert()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;

        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $this->assertTrue(isset($user->_id));
        $this->assertTrue(is_string($user->_id));
        $this->assertNotEquals('', (string)$user->_id);
        $this->assertNotEquals(0, strlen((string)$user->_id));
        $this->assertInstanceOf('Carbon\Carbon', $user->created_at);

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(35, $user->age);
    }

    public function testUpdate()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $check = User::find($user->_id);

        $check->age = 36;
        $check->save();

        $this->assertEquals(true, $check->exists);
        $this->assertInstanceOf('Carbon\Carbon', $check->created_at);
        $this->assertInstanceOf('Carbon\Carbon', $check->updated_at);
        $this->assertEquals(1, User::count());

        $this->assertEquals('John Doe', $check->name);
        $this->assertEquals(36, $check->age);

        $user->update(['age' => 20]);

        $check = User::find($user->_id);
        $this->assertEquals(20, $check->age);
    }

    public function testManualStringId()
    {
        $user = new User;
        $user->_id = '4af9f23d8ead0e1d32000000';
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals('4af9f23d8ead0e1d32000000', $user->_id);

        $raw = $user->getAttributes();

        $user = new User;
        $user->_id = 'customId';
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals('customId', $user->_id);

        $raw = $user->getAttributes();
        $this->assertInternalType('string', $raw['_id']);
    }

    public function testManualIntId()
    {
        $user = new User;
        $user->_id = 1;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals('1', $user->_id);

        $raw = $user->getAttributes();
        $this->assertInternalType('string', $raw['_id']);
    }

    public function testDelete()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $this->assertEquals(true, $user->exists);
        $this->assertEquals(1, User::count());

        $user->delete();

        $this->assertEquals(0, User::count());
    }

    public function testAll()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $user = new User;
        $user->name = 'Jane Doe';
        $user->title = 'user';
        $user->age = 32;
        $user->save();

        $all = User::all();

        $this->assertEquals(2, count($all));
        $this->assertContains('John Doe', $all->pluck('name'));
        $this->assertContains('Jane Doe', $all->pluck('name'));
    }

    public function testFind()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $check = User::find($user->_id);

        $this->assertInstanceOf('ORT\Interactive\Couchbase\Eloquent\Model', $check);
        $this->assertEquals(true, $check->exists);
        $this->assertEquals($user->_id, $check->_id);

        $this->assertEquals('John Doe', $check->name);
        $this->assertEquals(35, $check->age);
    }

    public function testGet()
    {
        User::insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $users = User::get();
        $this->assertEquals(2, count($users));
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('ORT\Interactive\Couchbase\Eloquent\Model', $users[0]);
    }

    public function testFirst()
    {
        User::insert([
            ['name' => 'John Doe'],
            ['name' => 'Jane Doe'],
        ]);

        $user = User::first();
        $this->assertInstanceOf('ORT\Interactive\Couchbase\Eloquent\Model', $user);
        $this->assertEquals('John Doe', $user->name);
    }

    public function testNoDocument()
    {
        $items = Item::where('name', 'nothing')->get();
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $items);
        $this->assertEquals(0, $items->count());

        $item = Item::where('name', 'nothing')->first();
        $this->assertEquals(null, $item);

        $item = Item::find('51c33d8981fec6813e00000a');
        $this->assertEquals(null, $item);
    }

    public function testFindOrfail()
    {
        $this->setExpectedException('Illuminate\Database\Eloquent\ModelNotFoundException');
        User::findOrfail('51c33d8981fec6813e00000a');
    }

    public function testCreate()
    {
        $user = User::create(['name' => 'Jane Poe']);

        $this->assertInstanceOf('ORT\Interactive\Couchbase\Eloquent\Model', $user);
        $this->assertEquals(true, $user->exists);
        $this->assertEquals('Jane Poe', $user->name);

        $check = User::where('name', 'Jane Poe')->first();
        $this->assertEquals($user->_id, $check->_id);
    }

    public function testDestroy()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        User::destroy((string)$user->_id);

        $this->assertEquals(0, User::count());
    }

    public function testTouch()
    {
        $user = new User;
        $user->name = 'John Doe';
        $user->title = 'admin';
        $user->age = 35;
        $user->save();

        $old = $user->updated_at;

        sleep(1);
        $user->touch();
        $check = User::find($user->_id);

        $this->assertNotEquals($old, $check->updated_at);
    }

    public function testSoftDelete()
    {
        Soft::create(['name' => 'John Doe']);
        Soft::create(['name' => 'Jane Doe']);

        $this->assertEquals(2, Soft::count());

        $user = Soft::where('name', 'John Doe')->first();
        $this->assertEquals(true, $user->exists);
        $this->assertEquals(false, $user->trashed());
        $this->assertNull($user->deleted_at);

        $user->delete();
        $this->assertEquals(true, $user->trashed());
        $this->assertNotNull($user->deleted_at);

        $user = Soft::where('name', 'John Doe')->first();
        $this->assertNull($user);

        $this->assertEquals(1, Soft::count());
        $this->assertEquals(2, Soft::withTrashed()->count());

        $user = Soft::withTrashed()->where('name', 'John Doe')->first();
        $this->assertNotNull($user);
        $this->assertInstanceOf('Carbon\Carbon', $user->deleted_at);
        $this->assertEquals(true, $user->trashed());

        $user->restore();
        $this->assertEquals(2, Soft::count());
    }

    /**
     * @group testPrimaryKey
     */
    public function testPrimaryKeyUsingFind()
    {
        $user = new User;
        $this->assertEquals('_id', $user->getKeyName());

        $book = new Book;
        $this->assertEquals('title', $book->getKeyName());

        $book->title = 'A Game of Thrones';
        $book->author = 'George R. R. Martin';
        $book->save();

        $this->assertEquals('A Game of Thrones', $book->getKey());

        $check = Book::find('A Game of Thrones');
        $this->assertEquals('title', $check->getKeyName());
        $this->assertEquals('A Game of Thrones', $check->getKey());
        $this->assertEquals('A Game of Thrones', $check->title);
    }

    /**
     * @group testPrimaryKeyUsingWhere
     */
    public function testPrimaryKeyUsingWhere()
    {
        $book = new Book;
        $book->title = 'False Book';
        $book->save();

        $book = new Book;
        $this->assertEquals('title', $book->getKeyName());

        $book->title = 'A Game of Thrones Where';
        $book->author = 'George R. R. Martin Where';
        $book->save();

        $this->assertEquals('A Game of Thrones Where', $book->getKey());

        $check = Book::where('_id', 'A Game of Thrones Where')->first();
        $this->assertEquals('title', $check->getKeyName());
        $this->assertEquals('A Game of Thrones Where', $check->getKey());
        $this->assertEquals('A Game of Thrones Where', $check->title);

        $check = Book::where('title', 'A Game of Thrones Where')->first();
        $this->assertEquals('title', $check->getKeyName());
        $this->assertEquals('A Game of Thrones Where', $check->getKey());
        $this->assertEquals('A Game of Thrones Where', $check->title);
    }

    /**
     * @group testPrimaryKeyUsingWhere
     */
    public function testPrimaryKeyUsingUseKeys()
    {
        $book = new Book;
        $book->title = 'False Book';
        $book->save();

        $book = new Book;
        $this->assertEquals('title', $book->getKeyName());

        $book->title = 'A Game of Thrones Where';
        $book->author = 'George R. R. Martin Where';
        $book->save();

        $this->assertEquals('A Game of Thrones Where', $book->getKey());

        $check = Book::useKeys('A Game of Thrones Where')->first();
        $this->assertEquals('title', $check->getKeyName());
        $this->assertEquals('A Game of Thrones Where', $check->getKey());
        $this->assertEquals('A Game of Thrones Where', $check->title);

        $check = Book::where('title', 'A Game of Thrones Where')->first();
        $this->assertEquals('title', $check->getKeyName());
        $this->assertEquals('A Game of Thrones Where', $check->getKey());
        $this->assertEquals('A Game of Thrones Where', $check->title);
    }

    public function testScope()
    {
        Item::insert([
            ['name' => 'knife', 'type' => 'sharp'],
            ['name' => 'spoon', 'type' => 'round'],
        ]);

        $sharp = Item::sharp()->get();
        $this->assertEquals(1, $sharp->count());
    }

    public function testToArray()
    {
        $item = Item::create(['name' => 'fork', 'type' => 'sharp']);

        $array = $item->toArray();
        $keys = array_keys($array);
        sort($keys);
        $this->assertEquals(['_id', 'created_at', 'name', 'type', 'updated_at'], $keys);
        $this->assertTrue(is_string($array['created_at']));
        $this->assertTrue(is_string($array['updated_at']));
        $this->assertTrue(is_string($array['_id']));
    }

    /**
     * @group testUnset
     */
    public function testUnset()
    {
        $user1 = User::create(['name' => 'John Doe', 'note1' => 'ABC', 'note2' => 'DEF']);
        $user2 = User::create(['name' => 'Jane Doe', 'note1' => 'ABC', 'note2' => 'DEF']);

        $user1->unset('note1');

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertTrue(isset($user2->note1));
        $this->assertTrue(isset($user2->note2));

        // Re-fetch to be sure
        $user1 = User::find($user1->getKey());
        $user2 = User::find($user2->getKey());

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertTrue(isset($user2->note1));
        $this->assertTrue(isset($user2->note2));

        $user2->unset(['note1', 'note2']);

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertFalse(isset($user2->note1));
        $this->assertFalse(isset($user2->note2));

        // Re-fetch to be sure
        $user1 = User::find($user1->getKey());
        $user2 = User::find($user2->getKey());

        $this->assertFalse(isset($user1->note1));
        $this->assertTrue(isset($user1->note2));
        $this->assertFalse(isset($user2->note1));
        $this->assertFalse(isset($user2->note2));
    }

    public function testDotNotation()
    {
        $user = User::create([
            'name' => 'John Doe',
            'address' => [
                'city' => 'Paris',
                'country' => 'France',
            ],
        ]);

        $this->assertEquals('Paris', $user->getAttribute('address.city'));
        $this->assertEquals('Paris', $user['address.city']);
        $this->assertEquals('Paris', $user->{'address.city'});

        // Fill
        $user->fill([
            'address.city' => 'Strasbourg',
        ]);

        $this->assertEquals('Strasbourg', $user['address.city']);
    }

    public function testModelExists()
    {
        $user = new User();
        $this->assertFalse($user->exists);
        $user->save();
        $this->assertTrue($user->exists);
        $user->delete();
        $this->assertFalse($user->exists);
    }

    public function testModelExistsWithOtherExisting()
    {
        User::create([
            'name' => 'John Doe'
        ]);

        $user = new User();
        $this->assertFalse($user->exists);
        $user->save();
        $this->assertTrue($user->exists);
        $user->delete();
        $this->assertFalse($user->exists);
    }

}
