<?php

namespace Database\Factories;

use App\Models\Field;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FieldFactory extends Factory
{
    protected $model = Field::class;

    public function definition(): array
    {
        return [
            'field_uid' => Str::random(20),
            'page' => 1,
            'type' => fake()->randomElement(['text', 'date', 'sign', 'initials']),
            'pos_x' => fake()->randomFloat(2, 50, 500),
            'pos_y' => fake()->randomFloat(2, 50, 700),
            'width' => fake()->randomFloat(2, 80, 200),
            'height' => fake()->randomFloat(2, 20, 60),
            'label' => fake()->word(),
            'name' => fake()->unique()->regexify('[a-z][a-z0-9_]{3,15}'),
            'required' => fake()->boolean(70),
            'sort_order' => 0,
        ];
    }

    public function signature(): static
    {
        return $this->state(['type' => 'sign', 'width' => 200, 'height' => 60]);
    }
}
