<?php declare(strict_types=1);

class PaginationTest extends TestCase
{
    public function tearDown()
    {
        User::truncate();
    }

    /**
     * @group PaginationTest
     */
    public function testAll()
    {
        DB::table('users')->delete();

        DB::table('users')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(6, $pagination->total());
    }

    /**
     * @group PaginationTest
     */
    public function testWhere()
    {
        DB::table('users')->delete();

        DB::table('users')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
    }

    /**
     * @group PaginationTest
     */
    public function testOrderBy()
    {
        DB::table('users')->delete();

        DB::table('users')->insert(['name' => 'John Doe 1', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 2', 'abc' => 1]);
        DB::table('users')->insert(['name' => 'John Doe 3', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 4', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 5', 'abc' => 2]);
        DB::table('users')->insert(['name' => 'John Doe 6', 'abc' => 2]);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'ASC')->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 3', $pagination->get(0)->name);
        $this->assertEquals('John Doe 4', $pagination->get(1)->name);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'ASC')->paginate(2, ['*'], 'page', 2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 5', $pagination->get(0)->name);
        $this->assertEquals('John Doe 6', $pagination->get(1)->name);

        /** @var \Illuminate\Pagination\LengthAwarePaginator $pagination */
        $pagination = User::where('abc', 2)->orderBy('name', 'DESC')->paginate(2);
        $this->assertEquals(2, $pagination->count());
        $this->assertEquals(4, $pagination->total());
        $this->assertEquals('John Doe 6', $pagination->get(0)->name);
        $this->assertEquals('John Doe 5', $pagination->get(1)->name);
    }
}
