<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        Admin::updateOrCreate(
            ['email' => 'daniel@gmail.com'],
            [
                'name'     => 'Daniel',
                'password' => 'habukodaw', // auto-hashed via model cast
            ]
        );
    }
}
