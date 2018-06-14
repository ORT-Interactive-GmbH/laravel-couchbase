<?php declare(strict_types=1);

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTableSeeder extends Seeder
{
    public function run()
    {
        DB::connection('couchbase-not-default')->table('users')->delete();

        DB::connection('couchbase-not-default')->table('users')->insert(['name' => 'John Doe', 'seed' => true]);
    }
}
