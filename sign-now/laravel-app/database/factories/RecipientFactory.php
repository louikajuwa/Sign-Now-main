<?php

namespace Database\Factories;

use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;

class RecipientFactory extends Factory
{
    protected $model = Recipient::class;

    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'name' => fake()->name(),
            'token' => Recipient::generateToken(),
            'status' => 'pending',
        ];
    }

    public function sent(): static
    {
        return $this->state(['status' => 'sent', 'sent_at' => now()]);
    }

    public function signed(): static
    {
        return $this->state([
            'status' => 'signed',
            'sent_at' => now()->subHours(2),
            'opened_at' => now()->subHour(),
            'signed_at' => now(),
        ]);
    }
}
