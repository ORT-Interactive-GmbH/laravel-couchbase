<?php declare(strict_types=1);

class SeederTest extends TestCase
{
    public function tearDown(): void
    {
        User::truncate();
    }

    public function testSeed()
    {
        $seeder = new UserTableSeeder;
        $seeder->run();

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }

    public function testArtisan()
    {
        Artisan::call('db:seed');

        $user = User::where('name', 'John Doe')->first();
        $this->assertTrue($user->seed);
    }
}
