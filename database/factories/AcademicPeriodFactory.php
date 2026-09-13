<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicPeriod;
use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicPeriodFactory extends Factory
{
    protected $model = AcademicPeriod::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'academic_year_id' => function (array $attributes) {
                return AcademicYear::factory()->create(['school_id' => $attributes['school_id']])->id;
            },
            'name' => 'Periodo 1',
            'period_order' => 1,
            'weight_percentage' => 25.00,
            'start_date' => '2026-01-15',
            'end_date' => '2026-04-10',
            'status' => 'OPEN',
        ];
    }
}
