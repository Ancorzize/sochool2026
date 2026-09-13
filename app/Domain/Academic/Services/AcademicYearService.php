<?php

namespace App\Domain\Academic\Services;

use Illuminate\Support\Facades\DB;

class AcademicYearService
{
    public function listYears(int $schoolId)
    {
        return DB::table('academic_years')
            ->where('school_id', $schoolId)
            ->orderBy('start_date', 'desc')
            ->get();
    }

    public function createYear(int $schoolId, array $data)
    {
        $data['school_id'] = $schoolId;
        $data['status'] = $data['status'] ?? 'UPCOMING';

        if ($data['status'] === 'ACTIVE') {
            DB::table('academic_years')
                ->where('school_id', $schoolId)
                ->update(['status' => 'CLOSED']);
        }

        $id = DB::table('academic_years')->insertGetId([
            'school_id' => $schoolId,
            'name' => $data['name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => $data['status'],
        ]);

        return DB::table('academic_years')->where('id', $id)->first();
    }

    public function activateYear(int $schoolId, int $yearId)
    {
        DB::table('academic_years')
            ->where('school_id', $schoolId)
            ->update(['status' => 'CLOSED']);

        DB::table('academic_years')
            ->where('school_id', $schoolId)
            ->where('id', $yearId)
            ->update(['status' => 'ACTIVE']);

        return DB::table('academic_years')->where('id', $yearId)->first();
    }
}
