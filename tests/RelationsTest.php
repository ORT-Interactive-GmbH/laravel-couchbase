<?php declare(strict_types=1);

class RelationsTest extends TestCase
{
    /**
     * @group RelationsTest
     */
    public function tearDown(): void
    {
        Mockery::close();

        User::truncate();
        Client::truncate();
        Address::truncate();
        Book::truncate();
        Item::truncate();
        Role::truncate();
        Client::truncate();
        Group::truncate();
        Photo::truncate();
    }

    /**
     * @group RelationsTest
     */
    public function testHasMany()
    {
        $author = User::create(['name' => 'George R. R. Martin']);
        Book::create(['title' => 'A Game of Thrones', 'author_id' => $author->_id]);
        Book::create(['title' => 'A Clash of Kings', 'author_id' => $author->_id]);

        $books = $author->books;
        $this->assertEquals(2, count($books));

        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user_id' => $user->_id]);
        Item::create(['type' => 'shield', 'user_id' => $user->_id]);
        Item::create(['type' => 'sword', 'user_id' => $user->_id]);
        Item::create(['type' => 'bag', 'user_id' => null]);

        $items = $user->items;
        $this->assertEquals(3, count($items));
    }

    /**
     * @group RelationsTest
     * @group testBelongsTo
     */
    public function testBelongsTo()
    {
        $user = User::create(['name' => 'George R. R. Martin']);
        Book::create(['title' => 'A Game of Thrones', 'author_id' => $user->_id]);
        $book = Book::create(['title' => 'A Clash of Kings', 'author_id' => $user->_id]);

        $author = $book->author;
        $this->assertEquals('George R. R. Martin', $author->name);

        $user = User::create(['name' => 'John Doe']);
        $item = Item::create(['type' => 'sword', 'user_id' => $user->_id]);

        $owner = $item->user;
        $this->assertEquals('John Doe', $owner->name);

        $book = Book::create(['title' => 'A Clash of Kings']);

        $this->assertEquals(null, $book->author);
    }

    /**
     * @group RelationsTest
     */
    public function testHasOne()
    {
        $user = User::create(['name' => 'John Doe']);
        Role::create(['type' => 'admin', 'user_id' => $user->_id]);

        $role = $user->role;
        $this->assertEquals('admin', $role->type);
        $this->assertEquals($user->_id, $role->user_id);

        $user = User::create(['name' => 'Jane Doe']);
        $role = new Role(['type' => 'user']);
        $user->role()->save($role);

        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_id, $role->user_id);

        $user = User::where('name', 'Jane Doe')->first();
        $role = $user->role;
        $this->assertEquals('user', $role->type);
        $this->assertEquals($user->_id, $role->user_id);
    }

    /**
     * @group RelationsTest
     */
    public function testWithBelongsTo()
    {
        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user_id' => $user->_id]);
        Item::create(['type' => 'shield', 'user_id' => $user->_id]);
        Item::create(['type' => 'sword', 'user_id' => $user->_id]);
        Item::create(['type' => 'bag', 'user_id' => null]);

        $items = Item::with('user')->orderBy('user_id', 'desc')->get();

        $user = $items[0]->getRelation('user');
        $this->assertInstanceOf('User', $user);
        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(1, count($items[0]->getRelations()));
        $this->assertEquals(null, $items[3]->getRelation('user'));
    }

    /**
     * @group RelationsTest
     */
    public function testWithHasMany()
    {
        $user = User::create(['name' => 'John Doe']);
        Item::create(['type' => 'knife', 'user_id' => $user->_id]);
        Item::create(['type' => 'shield', 'user_id' => $user->_id]);
        Item::create(['type' => 'sword', 'user_id' => $user->_id]);
        Item::create(['type' => 'bag', 'user_id' => null]);

        $user = User::with('items')->find($user->_id);

        $items = $user->getRelation('items');
        $this->assertEquals(3, count($items));
        $this->assertInstanceOf('Item', $items[0]);
    }

    /**
     * @group RelationsTest
     */
    public function testWithHasOne()
    {
        $user = User::create(['name' => 'John Doe']);
        Role::create(['type' => 'admin', 'user_id' => $user->_id]);
        Role::create(['type' => 'guest', 'user_id' => $user->_id]);

        $user = User::with('role')->find($user->_id);

        $role = $user->getRelation('role');
        $this->assertInstanceOf('Role', $role);
        $this->assertEquals('admin', $role->type);
    }

    /**
     * @group RelationsTest
     */
    public function testEasyRelation()
    {
        // Has Many
        $user = User::create(['name' => 'John Doe']);
        $item = Item::create(['type' => 'knife']);
        $user->items()->save($item);

        $user = User::find($user->_id);
        $items = $user->items;
        $this->assertEquals(1, count($items));
        $this->assertInstanceOf('Item', $items[0]);
        $this->assertEquals($user->_id, $items[0]->user_id);

        // Has one
        $user = User::create(['name' => 'John Doe']);
        $role = Role::create(['type' => 'admin']);
        $user->role()->save($role);

        $user = User::find($user->_id);
        $role = $user->role;
        $this->assertInstanceOf('Role', $role);
        $this->assertEquals('admin', $role->type);
        $this->assertEquals($user->_id, $role->user_id);
    }

    /**
     * @group RelationsTest
     * @group testBelongsToMany
     */
    public function testBelongsToMany()
    {
        $user = User::create(['name' => 'John Doe']);

        // Add 2 clients
        $user->clients()->save(new Client(['name' => 'Pork Pies Ltd.']));
        $user->clients()->create(['name' => 'Buffet Bar Inc.']);

        // Refetch
        $user = User::find($user->_id);
        $client = Client::first();

        // Check for relation attributes
        $this->assertTrue(array_key_exists('user_ids', $client->getAttributes()),
            'Asserting client has attribute user_ids');
        $this->assertTrue(array_key_exists('client_ids', $user->getAttributes()),
            'Asserting user has attribute client_ids');

        $clients = $user->clients;
        $users = $client->users;

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
        $this->assertInstanceOf('Client', $clients[0]);
        $this->assertInstanceOf('User', $users[0]);
        $this->assertCount(2, $user->clients);
        $this->assertCount(1, $client->users);

        // Now create a new user to an existing client
        $user = $client->users()->create(['name' => 'Jane Doe']);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->clients);
        $this->assertInstanceOf('Client', $user->clients->first());
        $this->assertCount(1, $user->clients);

        // Get user and unattached client
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Check the models are what they should be
        $this->assertInstanceOf('Client', $client);
        $this->assertInstanceOf('User', $user);

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids),
            'Asserting not to find client::_id in user::client_ids');
        $this->assertFalse(in_array($user->_id, $client->user_ids),
            'Asserting not to find user::_id in client::user_ids');
        $this->assertCount(1, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user
        $user->clients()->attach($client);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertTrue(in_array($client->_id, $user->client_ids),
            'Asserting to find client::_id in user::client_ids');
        $this->assertTrue(in_array($user->_id, $client->user_ids), 'Asserting to find user::_id in client::user_ids');
        $this->assertCount(2, $user->clients);
        $this->assertCount(2, $client->users);

        // Detach clients from user
        $user->clients()->sync([]);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids),
            'Asserting not to find client::_id in user::client_ids (second time)');
        $this->assertFalse(in_array($user->_id, $client->user_ids),
            'Asserting not to find user::_id in client::user_ids (second time)');
        $this->assertCount(0, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user via sync
        $user->clients()->sync([$client->getKey()]);

        // Get the new user model
        $user = User::where('name', '=', 'Jane Doe')->first();
        $client = Client::Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertTrue(in_array($client->_id, $user->client_ids),
            'Asserting to find client::_id in user::client_ids (second time)');
        $this->assertTrue(in_array($user->_id, $client->user_ids),
            'Asserting to find user::_id in client::user_ids (second time)');
        $this->assertCount(1, $user->clients);
        $this->assertCount(2, $client->users);
    }

    /**
     * @group RelationsTest
     * @group _
     */
    public function testWithBelongsToMany()
    {
        $user = User::create(['name' => 'John Doe']);

        // Add 2 clients
        $user->clients()->save(new Client(['name' => 'Pork Pies Ltd.']));
        $user->clients()->create(['name' => 'Buffet Bar Inc.']);

        // Refetch
        $user = User::with('clients')->find($user->_id);
        $client = Client::with('users')->first();

        // Check for relation attributes
        $this->assertTrue(array_key_exists('user_ids', $client->getAttributes()),
            'Asserting client has attribute user_ids');
        $this->assertTrue(array_key_exists('client_ids', $user->getAttributes()),
            'Asserting user has attribute client_ids');

        // Check for relation attributes
        $this->assertTrue(array_key_exists('user_ids', $user->clients->first()->getAttributes()),
            'Asserting client has attribute user_ids');
        $this->assertTrue(array_key_exists('client_ids', $client->users->first()->getAttributes()),
            'Asserting user has attribute client_ids');

        $clients = $user->getRelation('clients');
        $users = $client->getRelation('users');

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $users);
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $clients);
        $this->assertInstanceOf('Client', $clients[0]);
        $this->assertInstanceOf('User', $users[0]);
        $this->assertCount(2, $user->clients);
        $this->assertCount(1, $client->users);

        // Now create a new user to an existing client
        $user = $client->users()->create(['name' => 'Jane Doe']);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $user->clients);
        $this->assertInstanceOf('Client', $user->clients->first());
        $this->assertCount(1, $user->clients);

        // Get user and unattached client
        $user = User::with('clients')->where('name', '=', 'Jane Doe')->first();
        $client = Client::with('users')->Where('name', '=', 'Buffet Bar Inc.')->first();

        // Check the models are what they should be
        $this->assertInstanceOf('Client', $client);
        $this->assertInstanceOf('User', $user);

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids),
            'Asserting not to find client::_id in user::client_ids');
        $this->assertFalse(in_array($user->_id, $client->user_ids),
            'Asserting not to find user::_id in client::user_ids');
        $this->assertCount(1, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user
        $user->clients()->attach($client);

        // Get the new user model
        $user = User::with('clients')->where('name', '=', 'Jane Doe')->first();
        $client = Client::with('users')->Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertTrue(in_array($client->_id, $user->client_ids),
            'Asserting to find client::_id in user::client_ids');
        $this->assertTrue(in_array($user->_id, $client->user_ids), 'Asserting to find user::_id in client::user_ids');
        $this->assertCount(2, $user->clients);
        $this->assertCount(2, $client->users);

        // Detach clients from user
        $user->clients()->sync([]);

        // Get the new user model
        $user = User::with('clients')->where('name', '=', 'Jane Doe')->first();
        $client = Client::with('users')->Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are not attached
        $this->assertFalse(in_array($client->_id, $user->client_ids),
            'Asserting not to find client::_id in user::client_ids (second time)');
        $this->assertFalse(in_array($user->_id, $client->user_ids),
            'Asserting not to find user::_id in client::user_ids (second time)');
        $this->assertCount(0, $user->clients);
        $this->assertCount(1, $client->users);

        // Attach the client to the user via sync
        $user->clients()->sync([$client->getKey()]);

        // Get the new user model
        $user = User::with('clients')->where('name', '=', 'Jane Doe')->first();
        $client = Client::with('users')->Where('name', '=', 'Buffet Bar Inc.')->first();

        // Assert they are attached
        $this->assertTrue(in_array($client->_id, $user->client_ids),
            'Asserting to find client::_id in user::client_ids (second time)');
        $this->assertTrue(in_array($user->_id, $client->user_ids),
            'Asserting to find user::_id in client::user_ids (second time)');
        $this->assertCount(1, $user->clients);
        $this->assertCount(2, $client->users);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachesExistingModels()
    {
        $client = Client::create(['name' => 'Foo Bar Ltd.', 'user_ids' => []]);
        $user = User::create(['name' => 'John Doe', 'client_ids' => [$client->_id,]]);

        $clients = [
            Client::create(['name' => 'Pork Pies Ltd.'])->_id,
            Client::create(['name' => 'Buffet Bar Inc.'])->_id,
        ];

        $moreClients = [
            Client::create(['name' => 'synced Boloni Ltd.'])->_id,
            Client::create(['name' => 'synced Meatballs Inc.'])->_id,
        ];

        // Sync multiple records
        $user->clients()->sync($clients);

        $user = User::with('clients')->find($user->_id);

        $this->assertFalse(in_array($client->_id, $user->client_ids),
            'Assert non attached ID\'s are detached succesfully');

        $this->assertCount(2, $user->clients, 'Assert there are two client objects in the relationship');

        // Add more clients
        $user->clients()->sync($moreClients);

        // Refetch
        $user = User::with('clients')->find($user->_id);

        $this->assertCount(2, $user->clients, 'Assert there are now still 2 client objects in the relationship');

        // Assert that the new relationships name start with synced
        $this->assertStringStartsWith('synced', $user->clients[0]->name);
        $this->assertStringStartsWith('synced', $user->clients[1]->name);
    }

    /**
     * @group RelationsTest
     */
    public function testDetachNonExistingModel()
    {
        $client = Client::create(['name' => 'Foo Bar Ltd.']);
        $user = User::create(['name' => 'John Doe', 'client_ids' => [$client->_id,]]);
        $user2 = User::create(['name' => 'John Doe2', 'client_ids' => [$client->_id,]]);

        // Sync multiple records
        $this->assertErrorException(function () use ($user) {
            $user->clients()->sync([]);
        }, E_USER_WARNING, '/^Tying to pull a value from non existing column/');
        $this->assertErrorException(function () use ($user2, $client) {
            $user2->clients()->detach([$client->_id]);
        }, E_USER_WARNING, '/^Tying to pull a value from non existing column/');
    }

    /**
     * @group RelationsTest
     */
    public function testDetachModelsWithMissingColumn()
    {
        $user = User::create(['name' => 'John Doe', 'client_ids' => ['non_existing_client_id']]);

        // Sync multiple records
        $this->assertErrorException(function () use ($user) {
            $user->clients()->sync([]);
        }, E_USER_WARNING, '/^Tying to pull a value from non existing document/');
        $this->assertErrorException(function () use ($user) {
            $user->clients()->detach(['non_existing_client_id2']);
        }, E_USER_WARNING, '/^Tying to pull a value from non existing document/');
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManySync()
    {
        // create test instances
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Pork Pies Ltd.'])->_id;
        $client2 = Client::create(['name' => 'Buffet Bar Inc.'])->_id;
        $client3 = Client::create(['name' => 'Meatballs Inc.'])->_id;

        // Sync multiple
        $user->clients()->sync([$client1, $client2]);
        $this->assertCount(2, $user->clients);

        // Refresh user
        $user = User::where('name', '=', 'John Doe')->first();

        // Sync
        $user->clients()->sync([$client1, $client3]);

        // Expect two clients
        $this->assertCount(2, $user->clients);

        // Expect client1 and client3, but not client2
        $this->assertContains('Pork Pies Ltd.', [$user->clients[0]->name, $user->clients[1]->name]);
        $this->assertContains('Meatballs Inc.', [$user->clients[0]->name, $user->clients[1]->name]);
        $this->assertNotContains('Buffet Bar Inc.', [$user->clients[0]->name, $user->clients[1]->name]);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachArray()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1'])->_id;
        $client2 = Client::create(['name' => 'Test 2'])->_id;

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->attach([$client1, $client2]);
        $this->assertCount(2, $user->clients);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyAttachEloquentCollection()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1']);
        $client2 = Client::create(['name' => 'Test 2']);
        $collection = new \Illuminate\Database\Eloquent\Collection([$client1, $client2]);

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->attach($collection);
        $this->assertCount(2, $user->clients);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManySyncAlreadyPresent()
    {
        $user = User::create(['name' => 'John Doe']);
        $client1 = Client::create(['name' => 'Test 1'])->_id;
        $client2 = Client::create(['name' => 'Test 2'])->_id;

        $user->clients()->sync([$client1, $client2]);
        $this->assertCount(2, $user->clients);

        $user = User::where('name', '=', 'John Doe')->first();
        $user->clients()->sync([$client1]);
        $this->assertCount(1, $user->clients);

        $user = User::where('name', '=', 'John Doe')->first()->toArray();
        $this->assertCount(1, $user['client_ids']);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyCustom()
    {
        $user = User::create(['name' => 'John Doe']);
        $group = $user->groups()->create(['name' => 'Admins']);

        // Refetch
        $user = User::find($user->_id);
        $group = Group::find($group->_id);

        // Check for custom relation attributes
        $this->assertTrue(array_key_exists('users', $group->getAttributes()));
        $this->assertTrue(array_key_exists('groups', $user->getAttributes()));

        // Assert they are attached
        $this->assertTrue(in_array($group->_id, $user->groups->pluck('_id')->toArray()));
        $this->assertTrue(in_array($user->_id, $group->users->pluck('_id')->toArray()));
        $this->assertEquals($group->_id, $user->groups()->first()->_id);
        $this->assertEquals($user->_id, $group->users()->first()->_id);
    }

    /**
     * @group RelationsTest
     */
    public function testBelongsToManyBindings()
    {
        $user = User::create(['name' => 'John Doe']);
        $groupsRelation = $user->groups();

        $this->assertTrue($groupsRelation instanceof \ORT\Interactive\Couchbase\Relations\BelongsToMany,
            'Assert that User->groups is a BelongsToManyRelation');
        $this->assertTrue(is_array($groupsRelation->getBindings()), 'Assert that bindings are an array');
        $this->assertTrue(is_array($groupsRelation->getRawBindings()), 'Assert that raw bindings are an array');
    }

    /**
     * @group RelationsTest
     */
    public function testMorph()
    {
        $user = User::create(['name' => 'John Doe']);
        $client = Client::create(['name' => 'Jane Doe']);

        $photo = Photo::create(['url' => 'http://graph.facebook.com/john.doe/picture']);
        $photo = $user->photos()->save($photo);

        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $user = User::find($user->_id);
        $this->assertEquals(1, $user->photos->count());
        $this->assertEquals($photo->id, $user->photos->first()->id);

        $photo = Photo::create(['url' => 'http://graph.facebook.com/jane.doe/picture']);
        $client->photo()->save($photo);

        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $client = Client::find($client->_id);
        $this->assertNotNull($client->photo);
        $this->assertEquals($photo->id, $client->photo->id);

        $photo = Photo::first();
        $this->assertEquals($photo->imageable->name, $user->name);

        $user = User::with('photos')->find($user->_id);
        $relations = $user->getRelations();
        $this->assertTrue(array_key_exists('photos', $relations));
        $this->assertEquals(1, $relations['photos']->count());

        $photos = Photo::with('imageable')->get();
        $relations = $photos[0]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('User', $photos[0]->imageable);

        $relations = $photos[1]->getRelations();
        $this->assertTrue(array_key_exists('imageable', $relations));
        $this->assertInstanceOf('Client', $photos[1]->imageable);
    }

    /**
     * @group RelationsTest
     */
    public function testHasManyHas()
    {
        $author1 = User::create(['name' => 'George R. R. Martin']);
        $author1->books()->create(['title' => 'A Game of Thrones', 'rating' => 5]);
        $author1->books()->create(['title' => 'A Clash of Kings', 'rating' => 5]);
        $author2 = User::create(['name' => 'John Doe']);
        $author2->books()->create(['title' => 'My book', 'rating' => 2]);
        User::create(['name' => 'Anonymous author']);
        Book::create(['title' => 'Anonymous book', 'rating' => 1]);

        $authors = User::has('books')->orderBy('name')->get();
        $this->assertCount(2, $authors);
        $this->assertEquals('George R. R. Martin', $authors[0]->name);
        $this->assertEquals('John Doe', $authors[1]->name);

        $authors = User::whereHas('books', function ($query) {
            $query->where('rating', 5);
        })->get();
        $this->assertCount(1, $authors);

        $authors = User::whereHas('books', function ($query) {
            $query->where('rating', '<', 5);
        })->get();
        $this->assertCount(1, $authors);
    }

    /**
     * @group RelationsTest
     */
    public function testHasOneHas()
    {
        $user1 = User::create(['name' => 'John Doe']);
        $user1->role()->create(['title' => 'admin']);
        $user2 = User::create(['name' => 'Jane Doe']);
        $user2->role()->create(['title' => 'reseller']);
        User::create(['name' => 'Mark Moe']);
        Role::create(['title' => 'Customer']);

        $users = User::has('role')->orderBy('name', 'DESC')->get();
        $this->assertCount(2, $users);
        $this->assertEquals('John Doe', $users[0]->name);
        $this->assertEquals('Jane Doe', $users[1]->name);
    }

    /**
     * @group RelationsTest
     */
    public function testNestedKeys()
    {
        $client = Client::create([
            'data' => [
                'client_id' => 35298,
                'name' => 'John Doe',
            ],
        ]);

        $address = $client->addresses()->create([
            'data' => [
                'address_id' => 1432,
                'city' => 'Paris',
            ],
        ]);

        $client = Client::where('data.client_id', 35298)->first();
        $this->assertEquals(1, $client->addresses->count());

        $address = $client->addresses->first();
        $this->assertEquals('Paris', $address->data['city']);

        $client = Client::with('addresses')->first();
        $this->assertEquals('Paris', $client->addresses->first()->data['city']);
    }

    /**
     * @group RelationsTest
     */
    public function testDoubleSaveOneToMany()
    {
        $author = User::create(['name' => 'George R. R. Martin']);
        $book = Book::create(['title' => 'A Game of Thrones']);

        $author->books()->save($book);
        $author->books()->save($book);
        $author->save();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_id, $book->author_id);

        $author = User::where('name', 'George R. R. Martin')->first();
        $book = Book::where('title', 'A Game of Thrones')->first();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_id, $book->author_id);

        $author->books()->save($book);
        $author->books()->save($book);
        $author->save();
        $this->assertEquals(1, $author->books()->count());
        $this->assertEquals($author->_id, $book->author_id);
    }

    /**
     * @group RelationsTest
     */
    public function testDoubleSaveManyToMany()
    {
        $user = User::create(['name' => 'John Doe']);
        $client = Client::create(['name' => 'Admins']);

        $user->clients()->save($client);
        $user->clients()->save($client);
        $user->save();

        $this->assertEquals(1, $user->clients()->count());
        $this->assertEquals([$user->_id], $client->user_ids);
        $this->assertEquals([$client->_id], $user->client_ids);

        $user = User::where('name', 'John Doe')->first();
        $client = Client::where('name', 'Admins')->first();
        $this->assertEquals(1, $user->clients()->count());
        $this->assertEquals([$user->_id], $client->user_ids);
        $this->assertEquals([$client->_id], $user->client_ids);

        $user->clients()->save($client);
        $user->clients()->save($client);
        $user->save();
        $this->assertEquals(1, $user->clients()->count());
        $this->assertEquals([$user->_id], $client->user_ids);
        $this->assertEquals([$client->_id], $user->client_ids);
    }
}
