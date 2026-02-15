<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'content'     => fake()->sentence(8),
            'ip_hash'     => hash('sha256', fake()->ipv4()),
            'category'    => fake()->randomElement(['advice', 'confession', 'fun', null]),
            'likes_count' => fake()->numberBetween(0, 50),
            'is_deleted'  => false,
            'expires_at'  => now()->addHours(24),
        ];
    }
}
