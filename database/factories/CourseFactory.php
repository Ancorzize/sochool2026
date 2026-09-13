<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Academic\Models\Course;
use App\Domain\Academic\Models\Grade;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => function (array $attributes) {
                return AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id;
            },
            'grade_id' => function (array $attributes) {
                return Grade::factory()->create(['school_id' => $attributes['school_id']])->id;
            },
            'name' => $this->faker->bothify('?-#'),
            'shift' => 'MAÑANA',
        ];
    }
}
