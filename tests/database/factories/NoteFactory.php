<?php

namespace LowerRockLabs\Lockable\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use LowerRockLabs\Lockable\Tests\Models\Note;

class NoteFactory extends Factory
{
    protected $model = Note::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
        ];
    }
}
