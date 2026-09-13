<?php

namespace Database\Factories;

use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $name = 'Colegio ' . $this->faker->company();
        return [
            'uuid' => (string) Str::uuid(),
            'code' => 'SCH-' . Str::upper(Str::random(5)),
            'name' => $name,
            'legal_name' => $name . ' S.A.S.',
            'tax_identifier' => $this->faker->numerify('900.###.###-#'),
            'slug' => Str::slug($name) . '-' . Str::random(4),
            'email' => $this->faker->companyEmail(),
            'phone' => $this->faker->phoneNumber(),
            'city' => 'Bogotá',
            'state' => 'Cundinamarca',
            'country' => 'Colombia',
            'status' => 'ACTIVE',
        ];
    }
}
