<?php

namespace Database\Factories;

use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Academic\Models\Grade;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradeFactory extends Factory
{
    protected $model = Grade::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'educational_level_id' => function (array $attributes) {
                return EducationalLevel::factory()->create(['school_id' => $attributes['school_id']])->id;
            },
            'name' => 'Grado 6°',
            'code' => 'G-6',
            'grade_order' => 6,
        ];
    }
}
