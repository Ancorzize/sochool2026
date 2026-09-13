<?php

namespace Database\Factories;

use App\Domain\Teacher\Models\Teacher;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'school_id' => School::factory(),
            'teacher_code' => 'DOC-' . $this->faker->unique()->numerify('###'),
            'document_type' => 'CC',
            'document_number' => $this->faker->unique()->numerify('10########'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'specialty' => $this->faker->randomElement(['Matemáticas', 'Español', 'Inglés', 'Ciencias', 'Sociales']),
            'status' => 'ACTIVE',
        ];
    }
}
