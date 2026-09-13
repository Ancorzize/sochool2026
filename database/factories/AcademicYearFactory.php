<?php

namespace Database\Factories;

use App\Domain\Academic\Models\AcademicYear;
use App\Domain\Tenant\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'name' => '2026',
            'start_date' => '2026-01-15',
            'end_date' => '2026-11-30',
            'status' => 'ACTIVE',
            'is_current' => true,
        ];
    }
}
