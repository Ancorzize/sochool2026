<?php

namespace Database\Factories;

use App\Domain\Student\Models\Student;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'school_id' => School::factory(),
            'student_code' => 'EST-' . $this->faker->unique()->numerify('#####'),
            'document_type' => 'TI',
            'document_number' => $this->faker->unique()->numerify('100#######'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'gender' => $this->faker->randomElement(['M', 'F']),
            'birth_date' => $this->faker->date('Y-m-d', '2015-01-01'),
            'nationality' => 'Colombiana',
            'city' => 'Bogotá',
            'academic_status' => 'ACTIVE',
        ];
    }
}
