<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\HouseholdImport;
use App\Models\Household;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ImportController extends Controller
{
    public function importHouseholds(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,csv,xls',
        ]);

        $import = new HouseholdImport();
        Excel::import($import, $request->file('file'));

        return response()->json([
            'message'  => 'Import completed',
            'imported' => $import->imported,
            'skipped'  => $import->skipped,
        ]);
    }

    public function stats(): JsonResponse
    {
        $row = Household::query()
            ->selectRaw('
                COUNT(DISTINCT district_name)    AS district_count,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
            ')->first();

        $byDistrict = Household::query()
            ->selectRaw('
                district_name,
                district_code,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
            ')
            ->groupBy('district_name', 'district_code')
            ->orderBy('district_name')
            ->get()
            ->toArray();

        return response()->json([
            'total_districts'    => $row ? (int) $row->district_count : 0,
            'total_subdistricts' => $row ? (int) $row->subdistrict_count : 0,
            'total_villages'     => $row ? (int) $row->village_count : 0,
            'total_households'   => $row ? (int) $row->household_count : 0,
            'by_district'        => $byDistrict,
        ]);
    }
}
