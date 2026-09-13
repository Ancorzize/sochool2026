<?php

namespace Database\Factories;

use App\Domain\Academic\Models\EducationalLevel;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class EducationalLevelFactory extends Factory
{
    protected $model = EducationalLevel::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => 'Secundaria',
            'code' => 'SEC',
            'level_order' => 2,
        ];
    }
}
