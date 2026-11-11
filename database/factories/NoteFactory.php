<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class NoteFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'content' => fake()->paragraph()];
    }
}
