<?php

use App\Provider;
use Illuminate\Database\Seeder;

class ProviderTableSeeder extends Seeder
{
    public function run()
    {
        // Let's truncate our existing records to start from scratch.
        Provider::truncate();

        $faker = \Faker\Factory::create();

        // And now, let's create a few articles in our database:
        for ($i = 0; $i < 5; $i++) {
            Provider::create([
                'name' => $faker->sentence,
                'description' => $faker->paragraph,
                'address' => $faker->paragraph,
                ]);
        }
    }
}
