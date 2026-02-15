<?php

namespace Database\Factories;

use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected $model = Admin::class;

    public function definition(): array
    {
        return [
            'name'     => fake()->name(),
            'email'    => fake()->unique()->safeEmail(),
            'password' => 'password', // auto-hashed via model cast
        ];
    }
}
