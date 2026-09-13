<?php

namespace Database\Factories;

use App\Domain\Grading\Models\GradingScale;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class GradingScaleFactory extends Factory
{
    protected $model = GradingScale::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Escala Numérica (1.0 - 5.0)',
            'scale_type' => 'NUMERIC',
            'min_score' => 1.00,
            'max_score' => 5.00,
            'passing_score' => 3.00,
            'decimal_places' => 1,
            'rounding_rule' => 'HALF_UP',
            'is_default' => true,
        ];
    }
}
