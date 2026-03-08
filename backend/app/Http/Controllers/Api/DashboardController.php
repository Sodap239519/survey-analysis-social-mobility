<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\SurveyResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController
 *
 * Returns metrics for the Bento-style dashboard:
 * - จำนวนรหัสบ้าน (distinct house_code from all imported households)
 * - จำนวนผู้ตอบ (distinct respondents)
 * - counts by poverty level (4) for each of 5 capitals
 * - mobility counts improved/same/decreased
 * - counts by district/subdistrict
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $district    = $request->query('district');
        $subdistrict = $request->query('subdistrict');
        $period      = $request->query('period', 'after');
        $surveyYear  = $request->query('survey_year') ? (int) $request->query('survey_year') : null;

        // จำนวนรหัสบ้านทั้งหมด (DISTINCT house_code from imported households)
        $totalHouseCodes = Household::distinct('house_code')->count('house_code');

        // Filter survey responses
        $responseQuery = SurveyResponse::query()->where('period', $period);

        if ($surveyYear) {
            $responseQuery->where('survey_year', $surveyYear);
        }

        if ($district) {
            $responseQuery->whereHas('household', function ($q) use ($district) {
                $q->where('district_name', 'like', "%{$district}%")
                  ->orWhere('district_code', $district);
            });
        }

        if ($subdistrict) {
            $responseQuery->whereHas('household', function ($q) use ($subdistrict) {
                $q->where('subdistrict_name', 'like', "%{$subdistrict}%")
                  ->orWhere('subdistrict_code', $subdistrict);
            });
        }

        // จำนวนผู้ตอบ (distinct person_id, or household_id if no person)
        $totalRespondents = (clone $responseQuery)
            ->whereNotNull('person_id')
            ->distinct('person_id')
            ->count('person_id');

        $totalResponses = (clone $responseQuery)->count();

        // Poverty level distribution per capital
        $povertyByCapital = $this->getPovertyByCapital(clone $responseQuery);

        // Mobility counts (before vs after)
        $mobility = $this->getMobilityCounts($district, $subdistrict, $surveyYear);

        // Per-capital mobility counts (before vs after for each capital)
        $mobilityByCapital = $this->getMobilityByCapital($district, $subdistrict, $surveyYear);
        $mobilityByCapitalByLevel = $this->getMobilityByCapitalByLevel($district, $subdistrict, $surveyYear);

        // District/subdistrict breakdown
        $byDistrict = $this->getByDistrict($period, $district, $subdistrict, $surveyYear);

        // Summary poverty levels across all responses
        $overallPoverty = $this->getOverallPovertyLevels(clone $responseQuery);

        // Geographic totals (districts, subdistricts, villages, households)
        $geoTotals = $this->getGeographicTotals($district, $subdistrict, $surveyYear);

        // Average scores per capital (for Radar Chart)
        $capitalAverages = $this->getCapitalAverages(clone $responseQuery);

        return response()->json([
            'total_house_codes'    => $totalHouseCodes,
            'total_respondents'    => $totalRespondents,
            'total_responses'      => $totalResponses,
            'total_districts'      => $geoTotals['districts'],
            'total_subdistricts'   => $geoTotals['subdistricts'],
            'total_villages'       => $geoTotals['villages'],
            'total_households'     => $geoTotals['households'],
            'capital_averages'     => $capitalAverages,
            'poverty_by_capital'   => $povertyByCapital,
            'overall_poverty'      => $overallPoverty,
            'mobility'             => $mobility,
            'mobility_by_capital'          => $mobilityByCapital,
            'mobility_by_capital_by_level' => $mobilityByCapitalByLevel,
            'by_district'                  => $byDistrict,
        ]);
    }

    public function years(): JsonResponse
    {
        $years = SurveyResponse::query()
            ->whereNotNull('survey_year')
            ->distinct()
            ->orderBy('survey_year', 'desc')
            ->pluck('survey_year')
            ->map(fn ($y) => (int) $y)
            ->values();

        return response()->json($years);
    }

    private function getPovertyByCapital($query): array
    {
        $capitals = [
            'human'     => 'score_human',
            'physical'  => 'score_physical',
            'financial' => 'score_financial',
            'natural'   => 'score_natural',
            'social'    => 'score_social',
        ];

        $result = [];

        foreach ($capitals as $slug => $scoreCol) {
            $rows = (clone $query)->whereNotNull($scoreCol)->get([$scoreCol]);

            $levels = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

            foreach ($rows as $row) {
                $normalized = (float) $row->{$scoreCol}; // already 0-100
                // Convert to [1,4] scale for individual capital
                $x = 1.0 + ($normalized / 100.0) * 3.0;
                $level = $this->povertyLevel($x);
                $levels[$level]++;
            }

            $result[$slug] = $levels;
        }

        return $result;
    }

    private function getOverallPovertyLevels($query): array
    {
        $levels = [1 => 0, 2 => 0, 3 => 0, 4 => 0];

        $rows = (clone $query)->whereNotNull('poverty_level')
            ->selectRaw('poverty_level, COUNT(*) as cnt')
            ->groupBy('poverty_level')
            ->get();

        foreach ($rows as $row) {
            $level = (int) $row->poverty_level;
            if (isset($levels[$level])) {
                $levels[$level] = (int) $row->cnt;
            }
        }

        return $levels;
    }

    private function getMobilityCounts(?string $district, ?string $subdistrict, ?int $surveyYear = null): array
    {
        // Compare before vs after for households that have both periods
        $beforeQuery = SurveyResponse::query()->where('period', 'before')
            ->whereNotNull('score_aggregate');

        $afterQuery = SurveyResponse::query()->where('period', 'after')
            ->whereNotNull('score_aggregate');

        if ($surveyYear) {
            $beforeQuery->where('survey_year', $surveyYear);
            $afterQuery->where('survey_year', $surveyYear);
        }

        if ($district) {
            $filter = function ($q) use ($district) {
                $q->whereHas('household', function ($hq) use ($district) {
                    $hq->where('district_name', 'like', "%{$district}%");
                });
            };
            $beforeQuery->where($filter);
            $afterQuery->where($filter);
        }

        $beforeMap = $beforeQuery->pluck('score_aggregate', 'household_id')->toArray();
        $afterMap  = $afterQuery->pluck('score_aggregate', 'household_id')->toArray();

        $improved  = 0;
        $same      = 0;
        $decreased = 0;

        foreach ($afterMap as $householdId => $afterScore) {
            if (!isset($beforeMap[$householdId])) {
                continue;
            }

            $diff = $afterScore - $beforeMap[$householdId];

            if ($diff > 0.01) {
                $improved++;
            } elseif ($diff < -0.01) {
                $decreased++;
            } else {
                $same++;
            }
        }

        return [
            'improved'  => $improved,
            'same'      => $same,
            'decreased' => $decreased,
        ];
    }

    private function getMobilityByCapital(?string $district, ?string $subdistrict, ?int $surveyYear = null): array
    {
        $capitals = [
            'human'     => 'score_human',
            'physical'  => 'score_physical',
            'financial' => 'score_financial',
            'natural'   => 'score_natural',
            'social'    => 'score_social',
        ];

        $result = [];

        foreach ($capitals as $slug => $scoreCol) {
            $beforeQuery = SurveyResponse::query()->where('period', 'before')->whereNotNull($scoreCol);
            $afterQuery  = SurveyResponse::query()->where('period', 'after')->whereNotNull($scoreCol);

            if ($surveyYear) {
                $beforeQuery->where('survey_year', $surveyYear);
                $afterQuery->where('survey_year', $surveyYear);
            }

            if ($district) {
                $beforeQuery->whereHas('household', function ($q) use ($district) {
                    $q->where('district_name', 'like', "%{$district}%");
                });
                $afterQuery->whereHas('household', function ($q) use ($district) {
                    $q->where('district_name', 'like', "%{$district}%");
                });
            }

            if ($subdistrict) {
                $beforeQuery->whereHas('household', function ($q) use ($subdistrict) {
                    $q->where('subdistrict_name', 'like', "%{$subdistrict}%");
                });
                $afterQuery->whereHas('household', function ($q) use ($subdistrict) {
                    $q->where('subdistrict_name', 'like', "%{$subdistrict}%");
                });
            }

            $beforeMap = $beforeQuery->pluck($scoreCol, 'household_id')->toArray();
            $afterMap  = $afterQuery->pluck($scoreCol, 'household_id')->toArray();

            $improved  = 0;
            $same      = 0;
            $decreased = 0;

            foreach ($afterMap as $householdId => $afterScore) {
                if (!isset($beforeMap[$householdId])) {
                    continue;
                }

                $diff = (float) $afterScore - (float) $beforeMap[$householdId];

                if ($diff > 0.01) {
                    $improved++;
                } elseif ($diff < -0.01) {
                    $decreased++;
                } else {
                    $same++;
                }
            }

            $result[$slug] = [
                'improved'  => $improved,
                'same'      => $same,
                'decreased' => $decreased,
            ];
        }

        return $result;
    }

    private function getMobilityByCapitalByLevel(?string $district, ?string $subdistrict, ?int $surveyYear): array
    {
        $capitals = [
            'human'     => 'score_human',
            'physical'  => 'score_physical',
            'financial' => 'score_financial',
            'natural'   => 'score_natural',
            'social'    => 'score_social',
        ];

        $result = [];

        foreach ($capitals as $slug => $scoreCol) {
            $beforeQuery = SurveyResponse::query()->where('period', 'before')->whereNotNull($scoreCol);
            $afterQuery  = SurveyResponse::query()->where('period', 'after')->whereNotNull($scoreCol);

            if ($surveyYear) {
                $beforeQuery->where('survey_year', $surveyYear);
                $afterQuery->where('survey_year', $surveyYear);
            }

            if ($district) {
                $beforeQuery->whereHas('household', function ($q) use ($district) {
                    $q->where('district_name', 'like', "%{$district}%");
                });
                $afterQuery->whereHas('household', function ($q) use ($district) {
                    $q->where('district_name', 'like', "%{$district}%");
                });
            }

            if ($subdistrict) {
                $beforeQuery->whereHas('household', function ($q) use ($subdistrict) {
                    $q->where('subdistrict_name', 'like', "%{$subdistrict}%");
                });
                $afterQuery->whereHas('household', function ($q) use ($subdistrict) {
                    $q->where('subdistrict_name', 'like', "%{$subdistrict}%");
                });
            }

            $beforeMap = $beforeQuery->pluck($scoreCol, 'household_id')->toArray();
            $afterMap  = $afterQuery->pluck($scoreCol, 'household_id')->toArray();

            $levels = [
                1 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                2 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                3 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                4 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
            ];

            foreach ($afterMap as $householdId => $afterScore) {
                if (!isset($beforeMap[$householdId])) {
                    continue;
                }

                // Map 0-100 score to 1-4 scale; thresholds at 1.75, 2.50, 3.25
                $x = 1.0 + ((float) $afterScore / 100.0) * 3.0;
                $level = $this->povertyLevel($x);

                $diff = (float) $afterScore - (float) $beforeMap[$householdId];

                // Use a small epsilon (0.01) to absorb floating-point rounding differences;
                // scores that fall within this margin are considered unchanged ("same").
                if ($diff > 0.01) {
                    $levels[$level]['improved']++;
                } elseif ($diff < -0.01) {
                    $levels[$level]['decreased']++;
                } else {
                    $levels[$level]['same']++;
                }
            }

            $result[$slug] = $levels;
        }

        return $result;
    }

    private function getByDistrict(string $period, ?string $district, ?string $subdistrict, ?int $surveyYear = null): array
    {
        $query = Household::query()
            ->selectRaw('district_name, district_code, COUNT(DISTINCT house_code) as house_count')
            ->groupBy('district_name', 'district_code')
            ->orderBy('district_name');

        if ($surveyYear) {
            $query->where('survey_year', $surveyYear);
        }

        if ($district) {
            $query->where(function ($q) use ($district) {
                $q->where('district_name', 'like', "%{$district}%")
                  ->orWhere('district_code', $district);
            });
        }

        return $query->get()->toArray();
    }

    private function povertyLevel(float $x): int
    {
        if ($x < 1.75) return 1;
        if ($x < 2.50) return 2;
        if ($x < 3.25) return 3;
        return 4;
    }

    private function getGeographicTotals(?string $district, ?string $subdistrict, ?int $surveyYear): array
    {
        $query = Household::query();

        if ($surveyYear) {
            $query->where('survey_year', $surveyYear);
        }

        if ($district) {
            $query->where(function ($q) use ($district) {
                $q->where('district_name', 'like', "%{$district}%")
                  ->orWhere('district_code', $district);
            });
        }

        if ($subdistrict) {
            $query->where(function ($q) use ($subdistrict) {
                $q->where('subdistrict_name', 'like', "%{$subdistrict}%")
                  ->orWhere('subdistrict_code', $subdistrict);
            });
        }

        $row = $query->selectRaw('
            COUNT(DISTINCT district_name)   AS district_count,
            COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
            COUNT(DISTINCT village_name)    AS village_count,
            COUNT(DISTINCT house_code)      AS household_count
        ')->first();

        return [
            'districts'   => $row ? (int) $row->district_count : 0,
            'subdistricts'=> $row ? (int) $row->subdistrict_count : 0,
            'villages'    => $row ? (int) $row->village_count : 0,
            'households'  => $row ? (int) $row->household_count : 0,
        ];
    }

    private function getCapitalAverages($query): array
    {
        $row = (clone $query)->selectRaw('
            AVG(score_human)     as avg_human,
            AVG(score_physical)  as avg_physical,
            AVG(score_financial) as avg_financial,
            AVG(score_natural)   as avg_natural,
            AVG(score_social)    as avg_social
        ')->first();

        return [
            'human'     => $row ? round((float) $row->avg_human, 1) : 0,
            'physical'  => $row ? round((float) $row->avg_physical, 1) : 0,
            'financial' => $row ? round((float) $row->avg_financial, 1) : 0,
            'natural'   => $row ? round((float) $row->avg_natural, 1) : 0,
            'social'    => $row ? round((float) $row->avg_social, 1) : 0,
        ];
    }
}
