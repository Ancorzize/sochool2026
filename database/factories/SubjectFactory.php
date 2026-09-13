<?php

namespace Database\Factories;

use App\Domain\Academic\Models\Subject;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => $this->faker->word(),
            'code' => $this->faker->lexify('SUB-???'),
            'color' => '#3B82F6',
        ];
    }
}
