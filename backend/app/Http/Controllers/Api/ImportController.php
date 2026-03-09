<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\HouseholdImport;
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
            'rows'     => $import->rows,
        ]);
    }
}
