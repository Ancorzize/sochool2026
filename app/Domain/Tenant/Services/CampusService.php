<?php

namespace App\Domain\Tenant\Services;

use App\Domain\Tenant\Models\Campus;
use Illuminate\Support\Facades\DB;

class CampusService
{
    public function listCampuses(int $schoolId)
    {
        return Campus::where('school_id', $schoolId)->orderBy('id')->get();
    }

    public function createCampus(int $schoolId, array $data): Campus
    {
        $data['school_id'] = $schoolId;

        // If marked as main campus, clear previous main campus first
        if (!empty($data['is_main'])) {
            DB::table('campuses')
                ->where('school_id', $schoolId)
                ->update(['is_main' => false]);
        } else {
            // If no campus exists yet for this school, make this the main campus
            $count = DB::table('campuses')->where('school_id', $schoolId)->count();
            if ($count === 0) {
                $data['is_main'] = true;
            }
        }

        return Campus::create($data);
    }

    public function setMainCampus(int $schoolId, int $campusId): Campus
    {
        DB::table('campuses')
            ->where('school_id', $schoolId)
            ->update(['is_main' => false]);

        $campus = Campus::where('school_id', $schoolId)->where('id', $campusId)->firstOrFail();
        $campus->update(['is_main' => true]);

        return $campus;
    }
}
