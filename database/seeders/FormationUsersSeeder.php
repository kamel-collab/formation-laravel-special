<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class FormationUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('Ce seeder est réservé à la formation locale.');
        }

        User::firstOrCreate(
            ['email' => 'amina@example.test'],
            ['name' => 'Amina', 'password' => 'Formation123!'],
        );

        User::firstOrCreate(
            ['email' => 'karim@example.test'],
            ['name' => 'Karim', 'password' => 'Formation123!'],
        );
    }
}
