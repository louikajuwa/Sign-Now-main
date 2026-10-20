<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $uuid = Str::uuid()->toString();

        return [
            'uuid' => $uuid,
            'user_id' => User::factory(),
            'original_filename' => fake()->word() . '.pdf',
            'title' => fake()->sentence(3),
            'file_path' => "docsign/{$uuid}/original.pdf",
            'page_count' => fake()->numberBetween(1, 5),
            'render_scale' => 1.50,
            'status' => 'draft',
        ];
    }

    public function ready(): static
    {
        return $this->state(['status' => 'ready']);
    }

    public function filled(): static
    {
        return $this->state(['status' => 'filled']);
    }

    public function completed(): static
    {
        return $this->state(['status' => 'completed']);
    }

    public function template(): static
    {
        return $this->state(['is_template' => true, 'status' => 'ready']);
    }
}
