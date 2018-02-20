<?php declare(strict_types=1);

class QueryTest extends TestCase
{
    protected static $started = false;

    /**
     * @group QueryTest
     */
    public function setUp()
    {
        parent::setUp();
        User::create(['name' => 'John Doe', 'age' => 35, 'title' => 'admin']);
        User::create(['name' => 'Jane Doe', 'age' => 33, 'title' => 'admin']);
        User::create(['name' => 'Harry Hoe', 'age' => 13, 'title' => 'user']);
        User::create(['name' => 'Robert Roe', 'age' => 37, 'title' => 'user']);
        User::create(['name' => 'Mark Moe', 'age' => 23, 'title' => 'user']);
        User::create(['name' => 'Brett Boe', 'age' => 35, 'title' => 'user']);
        User::create(['name' => 'Tommy Toe', 'age' => 33, 'title' => 'user']);
        User::create(['name' => 'Yvonne Yoe', 'age' => 35, 'title' => 'admin']);
        User::create(['name' => 'Error', 'age' => null, 'title' => null]);
    }

    /**
     * @group QueryTest
     */
    public function tearDown()
    {
        User::truncate();
        parent::tearDown();
    }

    /**
     * @group QueryTest
     */
    public function testWhere()
    {
        $users = User::where('age', 35)->get();
        $this->assertEquals(3, count($users));

        $users = User::where('age', '=', 35)->get();
        $this->assertEquals(3, count($users));

        $users = User::where('age', '>', 34)->get();
        $this->assertEquals(4, count($users));

        $users = User::where('age', '>=', 35)->get();
        $this->assertEquals(4, count($users));

        $users = User::where('age', '<=', 18)->get();
        $this->assertEquals(1, count($users));

        $users = User::where('age', '!=', 35)->get();
        $this->assertEquals(5, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testAndWhere()
    {
        $users = User::where('age', 35)->where('title', 'admin')->get();
        $this->assertEquals(2, count($users));

        $users = User::where('age', '>=', 35)->where('title', 'user')->get();
        $this->assertEquals(2, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testWhereIn()
    {
        $users = User::whereIn('age', [35, 33])->get();
        $this->assertEquals(5, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testWhereNotIn()
    {
        $users = User::whereNotIn('age', [13, 23, 37, 33])->get();
        $this->assertEquals(3, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testBetween()
    {
        $users = User::whereBetween('age', [0, 25])->get();
        $this->assertEquals(2, count($users));
        $users = User::whereBetween('age', [13, 23])->get();
        $this->assertEquals(2, count($users));
        // testing whereNotBetween for version 4.1
        $users = User::whereBetween('age', [0, 25], 'and', true)->get();
        $this->assertEquals(6, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testAggregations()
    {
        $this->assertEquals(9, User::count());

        $this->assertEquals(37, User::max('age'));

        $this->assertEquals(13, User::min('age'));

        $this->assertEquals(30.5, User::avg('age'));

        $this->assertEquals(244, User::sum('age'));
    }

    /**
     * @group QueryTest
     */
    public function testLike()
    {
        $users = User::where('name', 'like', '%Doe')->get();
        $this->assertEquals(2, count($users));

        $users = User::where('name', 'like', '%Y%')->get();
        $this->assertEquals(1, count($users));

        $users = User::where('name', 'LIKE', '%y%')->get();
        $this->assertEquals(2, count($users));

        $users = User::where('name', 'like', 'T%')->get();
        $this->assertEquals(1, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testSelect()
    {
        $user = User::where('name', 'John Doe')->select('name')->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(null, $user->age);
        $this->assertEquals(null, $user->title);

        $user = User::where('name', 'John Doe')->select('name', 'title')->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('admin', $user->title);
        $this->assertEquals(null, $user->age);

        $user = User::where('name', 'John Doe')->select(['name', 'title'])->get()->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals('admin', $user->title);
        $this->assertEquals(null, $user->age);

        $user = User::where('name', 'John Doe')->get(['name'])->first();

        $this->assertEquals('John Doe', $user->name);
        $this->assertEquals(null, $user->age);
    }

    /**
     * @group QueryTest
     */
    public function testOrWhere()
    {
        $users = User::where('age', 13)->orWhere('title', 'admin')->get();
        $this->assertEquals(4, count($users));

        $users = User::where('age', 13)->orWhere('age', 23)->get();
        $this->assertEquals(2, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testWhereNull()
    {
        $users = User::whereNull('age')->get();
        $this->assertEquals(1, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testWhereNotNull()
    {
        $users = User::whereNotNull('age')->get();
        $this->assertEquals(8, count($users));
    }

    /**
     * @group QueryTest
     */
    public function testOrder()
    {
        $user = User::whereNotNull('age')->orderBy('age', 'asc')->first();
        $this->assertEquals(13, $user->age);

        $user = User::whereNotNull('age')->orderBy('age', 'ASC')->first();
        $this->assertEquals(13, $user->age);

        $user = User::whereNotNull('age')->orderBy('age', 'desc')->first();
        $this->assertEquals(37, $user->age);

        $user = User::whereNotNull('age')->orderBy('natural', 'asc')->first();
        $this->assertEquals(35, $user->age);

        $user = User::whereNotNull('age')->orderBy('natural', 'ASC')->first();
        $this->assertEquals(35, $user->age);

        $user = User::whereNotNull('age')->orderBy('natural', 'desc')->first();
        $this->assertEquals(35, $user->age);
    }

    /**
     * @group QueryTest
     */
    public function testCount()
    {
        $count = User::where('age', '<>', 35)->count();
        $this->assertEquals(5, $count);
    }

    /**
     * @group QueryTest
     */
    public function testExists()
    {
        $this->assertFalse(User::where('age', '>', 37)->exists());
        $this->assertTrue(User::where('age', '<', 37)->exists());
    }

    /**
     * @group QueryTest
     */
    public function testMultipleOr()
    {
        $users = User::where(function ($query) {
            $query->where('age', 35)->orWhere('age', 33);
        })
            ->where(function ($query) {
                $query->where('name', 'John Doe')->orWhere('name', 'Jane Doe');
            })->get();

        $this->assertEquals(2, count($users));
    }


    /**
     * @group QueryTest
     */
    public function testSubquery()
    {
        $users = User::where('title', 'admin')->orWhere(function ($query) {
            $query->where('name', 'Tommy Toe')
                ->orWhere('name', 'Error');
        })->get();
        $this->assertEquals(5, count($users));

        $users = User::where('title', 'user')->where(function ($query) {
            $query->where('age', 35)
                ->orWhere('name', 'like', '%Harry%');
        })->get();
        $this->assertEquals(2, count($users));

        $users = User::where('age', 35)->orWhere(function ($query) {
            $query->where('title', 'admin')
                ->orWhere('name', 'Error');
        })->get();
        $this->assertEquals(5, count($users));

        $users = User::whereNull('deleted_at')
            ->where('title', 'admin')
            ->where(function ($query) {
                $query->where('age', '>', 15)
                    ->orWhere('name', 'Harry Hoe');
            })
            ->get();
        $this->assertEquals(3, $users->count());

        $users = User::whereNull('deleted_at')
            ->where(function ($query) {
                $query->where('name', 'Harry Hoe')
                    ->orWhere(function ($query) {
                        $query->where('age', '>', 15)
                            ->where('title', '<>', 'admin');
                    });
            })
            ->get();

        $this->assertEquals(5, $users->count());
    }

    /**
     * @group QueryTest
     */
    public function testPaginate()
    {
        $results = User::paginate(2);
        $this->assertEquals(2, $results->count());
        $this->assertNotNull($results->first()->title);
        $this->assertEquals(9, $results->total());

        $results = User::paginate(2, ['name', 'age']);
        $this->assertEquals(2, $results->count());
        $this->assertNull($results->first()->title);
        $this->assertEquals(9, $results->total());
        $this->assertEquals(1, $results->currentPage());
    }

    /**
     * @group QueryTest
     */
    public function testPluckIdId()
    {
        $result = User::query()->pluck('_id', '_id');
        $this->assertInstanceOf(Illuminate\Support\Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
        foreach ($result as $key => $value) {
            $this->assertEquals($key, $value);
        }
    }

}
