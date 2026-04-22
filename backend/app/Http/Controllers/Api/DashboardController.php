<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\Household;
use App\Models\Person;
use App\Models\Question;
use App\Models\SurveyResponse;
use App\Services\CompareHouseholdSurveyLogic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DashboardController
 *
 * Returns metrics for the Bento-style dashboard:
 * - จำนวนรหัสบ้าน (distinct house_code from households that have survey responses only)
 * - จำนวนผู้ตอบ (distinct respondents from survey responses)
 * - counts by poverty level (4) for each of 5 capitals
 * - mobility counts improved/same/decreased
 * - counts by district/subdistrict (responses only, excludes import-only data)
 */
class DashboardController extends Controller
{
    /**
     * Threshold used for ดีขึ้น / คงที่ / แย่ลง classification in mobility metrics.
     * avg_diff > avg_before * TREND_THRESHOLD_PCT  => ดีขึ้น
     * avg_diff < -avg_before * TREND_THRESHOLD_PCT => แย่ลง
     * otherwise => คงที่
     */
    private const TREND_THRESHOLD_PCT = 0.05;

    public function index(Request $request): JsonResponse
    {
        $district    = $request->query('district');
        $subdistrict = $request->query('subdistrict');
        $period      = $request->query('period', 'after');
        $surveyYear  = $request->query('survey_year') ? (int) $request->query('survey_year') : null;
        $modelName   = $request->query('model_name');

        // จำนวนรหัสบ้านที่มีการสำรวจ (DISTINCT house_code from responses only)
        $totalHouseCodes = SurveyResponse::query()
            ->join('households', 'survey_responses.household_id', '=', 'households.id')
            ->distinct('households.house_code')
            ->count('households.house_code');

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

        if ($modelName) {
            $responseQuery->where('model_name', $modelName);
        }

        // จำนวนผู้ตอบ (distinct person_id, or household_id if no person)
        $totalRespondents = (clone $responseQuery)
            ->whereNotNull('person_id')
            ->distinct('person_id')
            ->count('person_id');

        $totalResponses = (clone $responseQuery)->count();

        // จำนวนโมเดลที่ไม่ซ้ำกัน (distinct model_name from filtered responses)
        $totalModels = (clone $responseQuery)
            ->whereNotNull('model_name')
            ->distinct('model_name')
            ->count('model_name');

        // Poverty level distribution per capital
        $povertyByCapital = $this->getPovertyByCapital(clone $responseQuery);

        // Mobility counts (before vs after) – household-based
        $mobility = $this->getMobilityCounts($district, $subdistrict, $surveyYear, $modelName);

        // Mobility counts – people-based (respondents per mobility category)
        $mobilityPeople = $this->getMobilityPeopleCounts($district, $subdistrict, $surveyYear, $modelName);

        // Per-capital mobility counts (before vs after for each capital)
        $mobilityByCapital = $this->getMobilityByCapital($district, $subdistrict, $surveyYear, $modelName);
        $mobilityByCapitalByLevel = $this->getMobilityByCapitalByLevel($district, $subdistrict, $surveyYear, $modelName);

        // District/subdistrict breakdown
        $byDistrict = $this->getByDistrict($period, $district, $subdistrict, $surveyYear, $modelName);

        // Model breakdown (by_model: per model, mobility by capital level)
        $byModel = $this->getByModel($period, $district, $subdistrict, $surveyYear, $modelName);

        // Summary poverty levels across all responses
        $overallPoverty = $this->getOverallPovertyLevels(clone $responseQuery);

        // Geographic totals (districts, subdistricts, villages, households)
        $geoTotals = $this->getGeographicTotals($period, $district, $subdistrict, $surveyYear, $modelName);

        // Average scores per capital (for Radar Chart)
        $capitalAverages = $this->getCapitalAverages(clone $responseQuery);

        // Before/After comparison summary (for paired households)
        $comparisonSummary = $this->getComparisonSummary($district, $subdistrict, $surveyYear, $modelName);

        // Income averages: baseline (from Person) and survey (from Answer Q4/04)
        $incomeAverages = $this->getIncomeAverages(clone $responseQuery);

        // Income breakdown per model
        $incomeByModel = $this->getIncomeByModel(clone $responseQuery);

        // Overview insights: top multi-select choices for 4 key questions
        $overviewInsights = $this->getOverviewInsights(clone $responseQuery);

        return response()->json([
            'total_house_codes'    => $totalHouseCodes,
            'total_models'         => $totalModels,
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
            'mobility_people'      => $mobilityPeople,
            'mobility_by_capital'          => $mobilityByCapital,
            'mobility_by_capital_by_level' => $mobilityByCapitalByLevel,
            'comparison_summary'           => $comparisonSummary,
            'by_district'                  => $byDistrict,
            'by_model'                     => $byModel,
            'income_baseline_avg'          => $incomeAverages['baseline_avg'],
            'income_survey_avg'            => $incomeAverages['survey_avg'],
            'income_baseline_count'        => $incomeAverages['baseline_count'],
            'income_survey_count'          => $incomeAverages['survey_count'],
            'income_by_model'              => $incomeByModel,
            'overview_insights'            => $overviewInsights,
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

    /**
     * Compute average baseline income (from Person) and average survey income
     * (from Answer question_key Q4 or 04) for the filtered response set.
     * Also returns counts of records with valid income data.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Filtered SurveyResponse query
     * @return array{baseline_avg: float|null, survey_avg: float|null, baseline_count: int, survey_count: int}
     */
    private function getIncomeAverages($query): array
    {
        // Baseline income: average + count from persons.baseline_income_monthly
        $personIds = (clone $query)->whereNotNull('person_id')->pluck('person_id');

        $baselineAvg   = null;
        $baselineCount = 0;
        if ($personIds->isNotEmpty()) {
            $row = Person::whereIn('id', $personIds)
                ->whereNotNull('baseline_income_monthly')
                ->selectRaw('AVG(baseline_income_monthly) AS avg_val, COUNT(*) AS cnt')
                ->first();
            if ($row) {
                $baselineAvg   = $row->avg_val !== null ? (float) $row->avg_val : null;
                $baselineCount = (int) $row->cnt;
            }
        }

        // Survey income: average + count from answers.value_numeric for question_key Q4/04
        $surveyResponseIds = (clone $query)->pluck('id');

        $surveyAvg   = null;
        $surveyCount = 0;
        if ($surveyResponseIds->isNotEmpty()) {
            $questionIds = Question::whereIn('question_key', ['Q4', '04'])->pluck('id');
            if ($questionIds->isNotEmpty()) {
                $row = Answer::whereIn('survey_response_id', $surveyResponseIds)
                    ->whereNotNull('value_numeric')
                    ->whereIn('question_id', $questionIds)
                    ->selectRaw('AVG(value_numeric) AS avg_val, COUNT(*) AS cnt')
                    ->first();
                if ($row) {
                    $surveyAvg   = $row->avg_val !== null ? (float) $row->avg_val : null;
                    $surveyCount = (int) $row->cnt;
                }
            }
        }

        return [
            'baseline_avg'   => $baselineAvg   !== null ? round($baselineAvg, 2) : null,
            'survey_avg'     => $surveyAvg      !== null ? round($surveyAvg,   2) : null,
            'baseline_count' => $baselineCount,
            'survey_count'   => $surveyCount,
        ];
    }

    /**
     * Compute income averages and counts broken down by model_name.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query  Filtered SurveyResponse query
     */
    private function getIncomeByModel($query): array
    {
        $modelNames = (clone $query)
            ->whereNotNull('model_name')
            ->distinct()
            ->orderBy('model_name')
            ->pluck('model_name');

        $result = [];
        foreach ($modelNames as $mName) {
            $modelQuery   = (clone $query)->where('model_name', $mName);
            $incomeData   = $this->getIncomeAverages($modelQuery);
            $result[] = [
                'model_name'     => $mName,
                'baseline_avg'   => $incomeData['baseline_avg'],
                'baseline_count' => $incomeData['baseline_count'],
                'survey_avg'     => $incomeData['survey_avg'],
                'survey_count'   => $incomeData['survey_count'],
            ];
        }

        return $result;
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

    private function getMobilityCounts(?string $district, ?string $subdistrict, ?int $surveyYear = null, ?string $modelName = null): array
    {
        $compareLogic = new CompareHouseholdSurveyLogic();

        $query = Household::query();

        if ($district) {
            $query->where('district_name', 'like', "%{$district}%");
        }

        if ($subdistrict) {
            $query->where('subdistrict_name', 'like', "%{$subdistrict}%");
        }

        if ($surveyYear) {
            $query->where('survey_year', $surveyYear);
        }

        if ($modelName) {
            $query->whereHas('surveyResponses', function ($q) use ($modelName) {
                $q->where('model_name', $modelName);
            });
        }

        $improved   = 0;
        $same       = 0;
        $decreased  = 0;
        $noBaseline = 0;

        $query->chunk(100, function ($households) use ($compareLogic, $surveyYear, &$improved, &$same, &$decreased, &$noBaseline) {
            foreach ($households as $household) {
                $result  = $compareLogic->compare($household, $surveyYear, null);
                $summary = $result['summary'];

                if ($summary['avg_before'] === null && $summary['avg_after'] !== null) {
                    $noBaseline++;
                } elseif ($summary['avg_before'] !== null && $summary['avg_after'] !== null) {
                    $threshold = $summary['avg_before'] * self::TREND_THRESHOLD_PCT;
                    if ($summary['avg_diff'] > $threshold) {
                        $improved++;
                    } elseif ($summary['avg_diff'] < -$threshold) {
                        $decreased++;
                    } else {
                        $same++;
                    }
                }
            }
        });

        return [
            'improved'    => $improved,
            'same'        => $same,
            'decreased'   => $decreased,
            'no_baseline' => $noBaseline,
            'total'       => $improved + $same + $decreased + $noBaseline,
        ];
    }

    private function getMobilityByCapital(?string $district, ?string $subdistrict, ?int $surveyYear = null, ?string $modelName = null): array
    {
        $capitals     = ['human', 'physical', 'financial', 'natural', 'social'];
        $compareLogic = new CompareHouseholdSurveyLogic();
        $result       = [];

        foreach ($capitals as $capital) {
            $improved   = 0;
            $same       = 0;
            $decreased  = 0;
            $noBaseline = 0;

            $query = Household::query();

            if ($district) {
                $query->where('district_name', 'like', "%{$district}%");
            }

            if ($subdistrict) {
                $query->where('subdistrict_name', 'like', "%{$subdistrict}%");
            }

            if ($surveyYear) {
                $query->where('survey_year', $surveyYear);
            }

            if ($modelName) {
                $query->whereHas('surveyResponses', function ($q) use ($modelName) {
                    $q->where('model_name', $modelName);
                });
            }

            $query->chunk(100, function ($households) use ($compareLogic, $surveyYear, $capital, &$improved, &$same, &$decreased, &$noBaseline) {
                foreach ($households as $household) {
                    $compareResult = $compareLogic->compare($household, $surveyYear, null);
                    $capitalData   = $compareResult['capitals'][$capital];

                    if ($capitalData['before'] === null && $capitalData['after'] !== null) {
                        $noBaseline++;
                    } elseif ($capitalData['before'] !== null && $capitalData['after'] !== null) {
                        $threshold = $capitalData['before'] * self::TREND_THRESHOLD_PCT;
                        if ($capitalData['diff'] > $threshold) {
                            $improved++;
                        } elseif ($capitalData['diff'] < -$threshold) {
                            $decreased++;
                        } else {
                            $same++;
                        }
                    }
                }
            });

            $result[$capital] = [
                'improved'    => $improved,
                'same'        => $same,
                'decreased'   => $decreased,
                'no_baseline' => $noBaseline,
                'total'       => $improved + $same + $decreased + $noBaseline,
            ];
        }

        return $result;
    }

    private function getMobilityByCapitalByLevel(?string $district, ?string $subdistrict, ?int $surveyYear, ?string $modelName = null): array
    {
        $capitals = ['human', 'physical', 'financial', 'natural', 'social'];
        $compareLogic = new CompareHouseholdSurveyLogic();
        $result = [];
        
        foreach ($capitals as $capital) {
            // Initialize levels
            $levels = [
                1 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                2 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                3 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                4 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
            ];

            $query = Household::query();

            if ($district) {
                $query->where('district_name', 'like', "%{$district}%");
            }

            if ($subdistrict) {
                $query->where('subdistrict_name', 'like', "%{$subdistrict}%");
            }

            if ($surveyYear) {
                $query->where('survey_year', $surveyYear);
            }

            if ($modelName) {
                $query->whereHas('surveyResponses', function ($q) use ($modelName) {
                    $q->where('model_name', $modelName);
                });
            }

            $query->chunk(100, function ($households) use ($compareLogic, $surveyYear, $capital, &$levels) {
                foreach ($households as $household) {
                    $compareResult = $compareLogic->compare($household, $surveyYear, null);
                    $capitalData = $compareResult['capitals'][$capital];

                    // ตรวจสอบว่ามีข้อมูล both before and after
                    if ($capitalData['before'] !== null && $capitalData['after'] !== null) {
                        // แปลง after score (0-100) เป็น X scale (1-4) เพื่อหาระดับความยากจน
                        $afterNormalized = $capitalData['after']; // already normalized 0-100 from CompareLogic
                        $afterX = 1.0 + ($afterNormalized / 100.0) * 3.0;
                        $level = $this->povertyLevel($afterX);

                        // คำนวณ mobility
                        $threshold = $capitalData['before'] * self::TREND_THRESHOLD_PCT;
                        if ($capitalData['diff'] > $threshold) {
                            $levels[$level]['improved']++;
                        } elseif ($capitalData['diff'] < -$threshold) {
                            $levels[$level]['decreased']++;
                        } else {
                            $levels[$level]['same']++;
                        }
                    }
                }
            });

            $result[$capital] = $levels;
        }

        return $result;
    }

    private function getByDistrict(string $period, ?string $district, ?string $subdistrict, ?int $surveyYear = null, ?string $modelName = null): array
    {
        // Only count households that have survey responses (exclude import-only households)
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

        if ($modelName) {
            $responseQuery->where('model_name', $modelName);
        }

        $householdIds = (clone $responseQuery)->distinct()->pluck('household_id');

        $query = Household::query()
            ->whereIn('id', $householdIds)
            ->selectRaw('
                district_name,
                district_code,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
            ')
            ->groupBy('district_name', 'district_code')
            ->orderBy('district_name');

        $results = $query->get()->toArray();

        // 🆕 เพิ่มการนับจำนวนผู้ตอบแต่ละอำเภอ
        foreach ($results as &$result) {
            $responseCount = (clone $responseQuery)
                ->whereHas('household', function ($q) use ($result) {
                    $q->where('district_name', $result['district_name']);
                })
                ->distinct('person_id')
                ->count('person_id');
            
            $result['respondent_count'] = $responseCount;
        }

        return $results;
    }

    private function povertyLevel(float $x): int
    {
        if ($x < 1.75) return 1;
        if ($x < 2.50) return 2;
        if ($x < 3.25) return 3;
        return 4;
    }

    private function getGeographicTotals(string $period, ?string $district, ?string $subdistrict, ?int $surveyYear, ?string $modelName = null): array
    {
        // Only count households that have survey responses (exclude import-only households)
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

        if ($modelName) {
            $responseQuery->where('model_name', $modelName);
        }

        $householdIds = (clone $responseQuery)->distinct()->pluck('household_id');

        $row = Household::query()
            ->whereIn('id', $householdIds)
            ->selectRaw('
                COUNT(DISTINCT district_name)    AS district_count,
                COUNT(DISTINCT subdistrict_name) AS subdistrict_count,
                COUNT(DISTINCT village_name)     AS village_count,
                COUNT(DISTINCT house_code)       AS household_count
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

    /**
     * Get before/after comparison summary for paired households.
     * Returns average scores for both periods and the diff.
     */
    private function getComparisonSummary(?string $district, ?string $subdistrict, ?int $surveyYear, ?string $modelName = null): array
    {
        $capitals = [
            'human'     => 'score_human',
            'physical'  => 'score_physical',
            'financial' => 'score_financial',
            'natural'   => 'score_natural',
            'social'    => 'score_social',
        ];

        $beforeQuery = SurveyResponse::query()->where('period', 'before')->whereNotNull('score_aggregate');
        $afterQuery  = SurveyResponse::query()->where('period', 'after')->whereNotNull('score_aggregate');

        if ($surveyYear) {
            $beforeQuery->where('survey_year', $surveyYear);
            $afterQuery->where('survey_year', $surveyYear);
        }

        if ($district) {
            foreach ([$beforeQuery, $afterQuery] as $q) {
                $q->whereHas('household', function ($hq) use ($district) {
                    $hq->where('district_name', 'like', "%{$district}%");
                });
            }
        }

        if ($subdistrict) {
            foreach ([$beforeQuery, $afterQuery] as $q) {
                $q->whereHas('household', function ($hq) use ($subdistrict) {
                    $hq->where('subdistrict_name', 'like', "%{$subdistrict}%");
                });
            }
        }

        if ($modelName) {
            $beforeQuery->where('model_name', $modelName);
            $afterQuery->where('model_name', $modelName);
        }

        // Collect paired scores for both periods
        $beforeRows = $beforeQuery->select(['household_id', 'score_aggregate', 'score_human', 'score_physical', 'score_financial', 'score_natural', 'score_social'])->get()->keyBy('household_id');
        $afterRows  = $afterQuery->select(['household_id', 'score_aggregate', 'score_human', 'score_physical', 'score_financial', 'score_natural', 'score_social'])->get()->keyBy('household_id');

        $pairedIds = array_intersect(array_keys($beforeRows->toArray()), array_keys($afterRows->toArray()));
        $pairedCount = count($pairedIds);

        // Compute averages over paired households only
        $beforeAvg = ['aggregate' => 0, 'human' => 0, 'physical' => 0, 'financial' => 0, 'natural' => 0, 'social' => 0];
        $afterAvg  = ['aggregate' => 0, 'human' => 0, 'physical' => 0, 'financial' => 0, 'natural' => 0, 'social' => 0];

        if ($pairedCount > 0) {
            foreach ($pairedIds as $id) {
                $b = $beforeRows[$id];
                $a = $afterRows[$id];
                $beforeAvg['aggregate'] += (float) $b->score_aggregate;
                $afterAvg['aggregate']  += (float) $a->score_aggregate;
                foreach ($capitals as $slug => $col) {
                    $beforeAvg[$slug] += (float) ($b->{$col} ?? 0);
                    $afterAvg[$slug]  += (float) ($a->{$col} ?? 0);
                }
            }
            foreach (array_keys($beforeAvg) as $key) {
                $beforeAvg[$key] = round($beforeAvg[$key] / $pairedCount, 1);
                $afterAvg[$key]  = round($afterAvg[$key]  / $pairedCount, 1);
            }
        }

        $diff = [];
        foreach (array_keys($beforeAvg) as $key) {
            $diff[$key] = round($afterAvg[$key] - $beforeAvg[$key], 1);
        }

        return [
            'paired_count' => $pairedCount,
            'before_avg'   => $beforeAvg,
            'after_avg'    => $afterAvg,
            'diff'         => $diff,
        ];
    }

    /**
     * People-based mobility counts: count distinct respondents per mobility category.
     * For each household that improved/same/decreased, count its after-period respondents.
     */
    private function getMobilityPeopleCounts(?string $district, ?string $subdistrict, ?int $surveyYear = null, ?string $modelName = null): array
    {
        $compareLogic = new CompareHouseholdSurveyLogic();

        $query = Household::query();

        if ($district) {
            $query->where('district_name', 'like', "%{$district}%");
        }

        if ($subdistrict) {
            $query->where('subdistrict_name', 'like', "%{$subdistrict}%");
        }

        if ($surveyYear) {
            $query->where('survey_year', $surveyYear);
        }

        if ($modelName) {
            $query->whereHas('surveyResponses', function ($q) use ($modelName) {
                $q->where('model_name', $modelName);
            });
        }

        $improved   = 0;
        $same       = 0;
        $decreased  = 0;
        $noBaseline = 0;

        $query->with(['surveyResponses' => function ($q) use ($surveyYear) {
            // Load after-period respondents to count people per household.
            // We use only 'after' responses because mobility classification
            // (improved/same/decreased) is determined by the after-period score,
            // and we want to count the people whose situation we are describing.
            $q->where('period', 'after')->whereNotNull('person_id');
            if ($surveyYear) {
                $q->where('survey_year', $surveyYear);
            }
        }])->chunk(100, function ($households) use ($compareLogic, $surveyYear, &$improved, &$same, &$decreased, &$noBaseline) {
            foreach ($households as $household) {
                $result  = $compareLogic->compare($household, $surveyYear, null);
                $summary = $result['summary'];

                // Count distinct respondents in the after period; default to 1
                // for households that have no linked person records, so that
                // every household contributes at least one count.
                $personCount = max(1, $household->surveyResponses->unique('person_id')->count());

                if ($summary['avg_before'] === null && $summary['avg_after'] !== null) {
                    $noBaseline += $personCount;
                } elseif ($summary['avg_before'] !== null && $summary['avg_after'] !== null) {
                    $threshold = $summary['avg_before'] * self::TREND_THRESHOLD_PCT;
                    if ($summary['avg_diff'] > $threshold) {
                        $improved += $personCount;
                    } elseif ($summary['avg_diff'] < -$threshold) {
                        $decreased += $personCount;
                    } else {
                        $same += $personCount;
                    }
                }
            }
        });

        return [
            'improved'    => $improved,
            'same'        => $same,
            'decreased'   => $decreased,
            'no_baseline' => $noBaseline,
            'total'       => $improved + $same + $decreased + $noBaseline,
        ];
    }

    /**
     * Breakdown by model_name: for each model, show mobility by capital level
     * using the same header structure as the summary table.
     */
    private function getByModel(string $period, ?string $district, ?string $subdistrict, ?int $surveyYear = null, ?string $modelName = null): array
    {
        $compareLogic = new CompareHouseholdSurveyLogic();
        $capitals     = ['human', 'physical', 'financial', 'natural', 'social'];

        // Get distinct model names from after responses
        $modelQuery = SurveyResponse::query()->where('period', $period)->whereNotNull('model_name');

        if ($surveyYear) {
            $modelQuery->where('survey_year', $surveyYear);
        }

        if ($district) {
            $modelQuery->whereHas('household', function ($q) use ($district) {
                $q->where('district_name', 'like', "%{$district}%");
            });
        }

        if ($subdistrict) {
            $modelQuery->whereHas('household', function ($q) use ($subdistrict) {
                $q->where('subdistrict_name', 'like', "%{$subdistrict}%");
            });
        }

        if ($modelName) {
            $modelQuery->where('model_name', $modelName);
        }

        $modelNames = $modelQuery->distinct()->pluck('model_name')->sort()->values()->toArray();

        $result = [];

        foreach ($modelNames as $mName) {
            // Mobility by capital by level for this model
            $byLevel = [];
            foreach ($capitals as $capital) {
                $levels = [
                    1 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                    2 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                    3 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                    4 => ['improved' => 0, 'same' => 0, 'decreased' => 0],
                ];

                $householdIds = SurveyResponse::query()
                    ->where('model_name', $mName)
                    ->distinct()
                    ->pluck('household_id');

                $hQuery = Household::query()->whereIn('id', $householdIds);

                if ($district) {
                    $hQuery->where('district_name', 'like', "%{$district}%");
                }

                if ($subdistrict) {
                    $hQuery->where('subdistrict_name', 'like', "%{$subdistrict}%");
                }

                if ($surveyYear) {
                    $hQuery->where('survey_year', $surveyYear);
                }

                $hQuery->chunk(100, function ($households) use ($compareLogic, $surveyYear, $capital, &$levels) {
                    foreach ($households as $household) {
                        $compareResult = $compareLogic->compare($household, $surveyYear, null);
                        $capitalData   = $compareResult['capitals'][$capital];

                        if ($capitalData['before'] !== null && $capitalData['after'] !== null) {
                            $afterX  = 1.0 + ($capitalData['after'] / 100.0) * 3.0;
                            $level   = $this->povertyLevel($afterX);
                            $threshold = $capitalData['before'] * self::TREND_THRESHOLD_PCT;
                            if ($capitalData['diff'] > $threshold) {
                                $levels[$level]['improved']++;
                            } elseif ($capitalData['diff'] < -$threshold) {
                                $levels[$level]['decreased']++;
                            } else {
                                $levels[$level]['same']++;
                            }
                        }
                    }
                });

                $byLevel[$capital] = $levels;
            }

            $result[] = [
                'model_name' => $mName,
                'by_capital' => $byLevel,
            ];
        }

        return $result;
    }

    /**
     * Compute concise "Survey Insights" for 4 multi-select questions.
     * Returns an array of 4 items, each with title, headline, and top (up to 3 choice strings).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $responseQuery  Filtered SurveyResponse query
     */
    private function getOverviewInsights($responseQuery): array
    {
        $insightQuestions = [
            ['id' => 4,  'title' => 'กิจกรรมด้านการเงิน'],
            ['id' => 8,  'title' => 'การนำความรู้ด้านการเงินไปใช้ในชีวิตประจำวัน'],
            ['id' => 19, 'title' => 'การดำเนินการเรื่องหนี้หลังเข้าร่วมโครงการ'],
            ['id' => 3,  'title' => 'การเปลี่ยนแปลงทักษะ/ความสามารถหลังเข้าร่วมโครงการ'],
        ];

        $questionIds = array_column($insightQuestions, 'id');

        // Get filtered survey response IDs
        $surveyResponseIds = (clone $responseQuery)->pluck('id');

        $emptyResult = array_map(fn ($q) => [
            'title'    => $q['title'],
            'headline' => 'ยังไม่มีข้อมูลเพียงพอ',
            'top'      => [],
        ], $insightQuestions);

        if ($surveyResponseIds->isEmpty()) {
            return $emptyResult;
        }

        // Pre-load choices for these questions (keyed by question_id => [choice_id => Choice])
        $choicesByQuestion = Choice::whereIn('question_id', $questionIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('question_id')
            ->map(fn ($choices) => $choices->keyBy('id'));

        // Load answers in one query
        $allAnswers = Answer::whereIn('survey_response_id', $surveyResponseIds)
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('selected_choice_ids')
            ->get(['question_id', 'selected_choice_ids']);

        // Group answers by question_id
        $answersByQuestion = $allAnswers->groupBy('question_id');

        $result = [];

        foreach ($insightQuestions as $q) {
            $questionId = $q['id'];
            $answers    = $answersByQuestion->get($questionId, collect());
            $choices    = $choicesByQuestion->get($questionId, collect());

            // Count frequency of each selected choice_id
            $freq = [];
            foreach ($answers as $answer) {
                $choiceIds = $answer->selected_choice_ids;
                if (!is_array($choiceIds)) {
                    continue;
                }
                foreach ($choiceIds as $cid) {
                    $cid = (int) $cid;
                    $freq[$cid] = ($freq[$cid] ?? 0) + 1;
                }
            }

            if (empty($freq)) {
                $result[] = [
                    'title'    => $q['title'],
                    'headline' => 'ยังไม่มีข้อมูลเพียงพอ',
                    'top'      => [],
                ];
                continue;
            }

            // Sort by frequency descending
            arsort($freq);

            // Build sorted items array: [{text, count, is_exclusive}]
            $sortedItems = [];
            foreach ($freq as $cid => $count) {
                $choice = $choices->get($cid);
                if ($choice) {
                    $sortedItems[] = [
                        'text'         => $choice->text_th,
                        'count'        => $count,
                        'is_exclusive' => (bool) $choice->is_exclusive,
                    ];
                }
            }

            // Top 3 choice texts
            $top = array_slice(array_column($sortedItems, 'text'), 0, 3);

            $headline = $this->computeInsightHeadline($sortedItems);

            $result[] = [
                'title'    => $q['title'],
                'headline' => $headline,
                'top'      => $top,
            ];
        }

        return $result;
    }

    /**
     * Generate a concise Thai headline from a sorted list of top choices.
     *
     * Rules:
     * - If the top choice text contains "ไม่เคย" or "ยังไม่มีการเปลี่ยนแปลง", begin with "ส่วนใหญ่" and reflect it.
     * - Else if the top-2 choices are close in count (second >= 80% of first), mention both.
     * - Otherwise mention the top choice with "รองลงมาคือ" for the second.
     *
     * @param  array  $sortedItems  [{text, count, is_exclusive}, ...]  sorted desc by count
     */
    private function computeInsightHeadline(array $sortedItems): string
    {
        if (empty($sortedItems)) {
            return 'ยังไม่มีข้อมูลเพียงพอ';
        }

        $top = $sortedItems[0];

        // Check for negative/no-change keywords in top choice
        $noChangeKeywords = ['ไม่เคย', 'ยังไม่มีการเปลี่ยนแปลง'];
        foreach ($noChangeKeywords as $kw) {
            if (str_contains($top['text'], $kw)) {
                return "ส่วนใหญ่{$top['text']}";
            }
        }

        if (count($sortedItems) >= 2) {
            $second = $sortedItems[1];
            $ratio  = $top['count'] > 0 ? $second['count'] / $top['count'] : 0;
            if ($ratio >= 0.8) {
                return "ส่วนใหญ่เลือก \"{$top['text']}\" และ \"{$second['text']}\" ใกล้เคียงกัน";
            }
            return "ส่วนใหญ่เลือก \"{$top['text']}\" รองลงมาคือ \"{$second['text']}\"";
        }

        return "ส่วนใหญ่เลือก \"{$top['text']}\"";
    }
}
