<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Choice;
use App\Models\DetailedAnswer;
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

        // Financial summary cards: expenses (Q8), debt (Q10), savings (Q9)
        $financialSummaryCards = $this->getFinancialSummaryCards(clone $responseQuery);

        // Financial averages by model: income, expenses, debt, savings
        $financialByModel = $this->getFinancialByModel(clone $responseQuery);

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
            'financial_summary_cards'      => $financialSummaryCards,
            'financial_by_model'           => $financialByModel,
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
     * Returns an array of 4 items, each with title, denominator, and top (up to 3)
     * items each containing { label, count, percent }.
     * Percent denominator = total distinct respondents in the current filter scope.
     * Since multi-select, totals may exceed 100%.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $responseQuery  Filtered SurveyResponse query
     */
    private function getOverviewInsights($responseQuery): array
    {
        $insightQuestions = [
            ['id' => 4,  'title' => 'การเข้าร่วมกิจกรรมด้านการเงินจากโครงการ'],
            ['id' => 8,  'title' => 'การนำความรู้ด้านการเงินไปใช้ในชีวิตประจำวัน'],
            ['id' => 19, 'title' => 'การดำเนินการเรื่องหนี้หลังเข้าร่วมโครงการ'],
            ['id' => 3,  'title' => 'การเปลี่ยนแปลงทักษะ/ความสามารถหลังเข้าร่วมโครงการ'],
        ];

        $questionIds = array_column($insightQuestions, 'id');

        // Get filtered survey response IDs
        $surveyResponseIds = (clone $responseQuery)->pluck('id');
        $denominator       = $surveyResponseIds->count();

        $emptyResult = array_map(fn ($q) => [
            'title'       => $q['title'],
            'denominator' => 0,
            'top'         => [],
        ], $insightQuestions);

        if ($denominator === 0) {
            return $emptyResult;
        }

        // Pre-load choices for these questions (keyed by question_id => [choice_id => Choice])
        $choicesByQuestion = Choice::whereIn('question_id', $questionIds)
            ->orderBy('sort_order')
            ->get()
            ->groupBy('question_id')
            ->map(fn ($choices) => $choices->keyBy('id'));

        // Load answers in one query — include survey_response_id for distinct counting
        $allAnswers = Answer::whereIn('survey_response_id', $surveyResponseIds)
            ->whereIn('question_id', $questionIds)
            ->whereNotNull('selected_choice_ids')
            ->get(['survey_response_id', 'question_id', 'selected_choice_ids']);

        // Group answers by question_id
        $answersByQuestion = $allAnswers->groupBy('question_id');

        $result = [];

        foreach ($insightQuestions as $q) {
            $questionId = $q['id'];
            $answers    = $answersByQuestion->get($questionId, collect());
            $choices    = $choicesByQuestion->get($questionId, collect());

            // Count distinct respondents who selected each choice_id
            $freq = [];
            foreach ($answers as $answer) {
                $choiceIds = $answer->selected_choice_ids;
                if (!is_array($choiceIds)) {
                    continue;
                }
                $respondentChoices = [];
                foreach ($choiceIds as $cid) {
                    $cid = (int) $cid;
                    if (!isset($respondentChoices[$cid])) {
                        $respondentChoices[$cid]  = true;
                        $freq[$cid] = ($freq[$cid] ?? 0) + 1;
                    }
                }
            }

            if (empty($freq)) {
                $result[] = [
                    'title'       => $q['title'],
                    'denominator' => $denominator,
                    'top'         => [],
                ];
                continue;
            }

            // Sort by frequency descending
            arsort($freq);

            // Build top-3 items with label, count, percent
            $top = [];
            foreach (array_slice($freq, 0, 3, true) as $cid => $count) {
                $choice = $choices->get($cid);
                if ($choice) {
                    $top[] = [
                        'label'   => $choice->text_th,
                        'count'   => $count,
                        'percent' => $denominator > 0
                            ? round($count / $denominator * 100, 1)
                            : 0.0,
                    ];
                }
            }

            $result[] = [
                'title'       => $q['title'],
                'denominator' => $denominator,
                'top'         => $top,
            ];
        }

        return $result;
    }

    /**
     * Expense category label mapping (Q8 keys → Thai labels).
     * Keys match Q8_EXPENSE_ITEMS in the frontend form.
     */
    private static function expenseLabelMap(): array
    {
        return [
            '10.1'  => 'ค่าใช้จ่ายเพื่อการบริโภค (อาหาร เครื่องดื่ม)',
            '10.2'  => 'ค่าใช้จ่ายเพื่อการอุปโภค (ของใช้ในครัวเรือน เดินทาง พลังงาน)',
            '10.3'  => 'ค่าน้ำ ไฟ โทรศัพท์ อินเทอร์เน็ต',
            '10.4'  => 'ค่าใช้จ่ายเพื่อการศึกษา',
            '10.5'  => 'ค่ารักษาพยาบาล',
            '10.6'  => 'ค่าประกันภัยต่างๆ',
            '10.7'  => 'ค่าใช้จ่ายด้านสังคม (งานบวช งานแต่ง งานศพ) ศาสนา บริจาค',
            '10.8'  => 'ค่าใช้จ่ายเพื่อความบันเทิง ท่องเที่ยว',
            '10.9'  => 'ค่าใช้จ่ายเสี่ยงโชค (ลอตเตอรี่ หวย)',
            '10.10' => 'ค่าเครื่องดื่มแอลกอฮอล์ บุหรี่ ยาสูบ',
            '10.11' => 'อื่นๆ',
        ];
    }

    /**
     * Debt type label mapping (Q10 keys → Thai labels).
     * Keys match Q10_DEBT_SOURCES in the frontend form.
     */
    private static function debtLabelMap(): array
    {
        return [
            '1.1'  => 'ญาติ/เพื่อน/เพื่อนบ้าน (ไม่มีค่าตอบแทนอื่น)',
            '1.2'  => 'ญาติ/เพื่อน/เพื่อนบ้าน (ดอกเบี้ย < 15%/ปี)',
            '1.3'  => 'กองทุนการเงินของชุมชน (สหกรณ์ กลุ่มออมทรัพย์)',
            '1.4'  => 'กองทุนการเงินที่รัฐสนับสนุน (กองทุนหมู่บ้าน/กขคจ.)',
            '1.5'  => 'ธนาคารเพื่อการเกษตรและสหกรณ์ (ธกส.)',
            '1.6'  => 'ธนาคารออมสิน',
            '1.7'  => 'ธนาคารพาณิชย์อื่นๆ',
            '1.8'  => 'สถาบันการเงินเอกชน (ไฟแนนซ์ บัตรกดเงินสด)',
            '1.9'  => 'ร้านค้าอุปโภค บริโภค ปัจจัยการผลิต',
            '1.10' => 'เงินกู้นอกระบบ (ดอกเบี้ย > 15%/ปี)',
            '1.11' => 'กองทุนเงินให้กู้ยืมเพื่อการศึกษา (กยศ./กอร.)',
            '1.12' => 'แหล่งอื่นๆ',
        ];
    }

    /**
     * Savings type label mapping (Q9_savings keys → Thai labels).
     * Keys match Q9_SAVINGS_ITEMS in the frontend form.
     */
    private static function savingsLabelMap(): array
    {
        return [
            '1.1' => 'เงินสด และทรัพย์สิน (ทอง เพชร พลอย พระเครื่อง ของสะสมมีมูลค่า)',
            '1.2' => 'เงินฝากกับสถาบันการเงิน (ธนาคาร หน่วยประกันชีวิต)',
            '1.3' => 'เงินฝากกับสหกรณ์ กลุ่มออมทรัพย์ กองทุนชุมชน กลุ่มสัจจะ กองทุนหมู่บ้าน',
            '1.4' => 'พันธบัตร/สลากออมทรัพย์ (ออมสิน ธกส. ฯลฯ)',
            '1.5' => 'กองทุนการออมแห่งชาติ (กอช.)',
            '1.6' => 'การออมอื่นๆ',
        ];
    }

    /**
     * Compute financial summary cards for expenses (Q8), debt (Q10), and savings (Q9).
     * Returns { expenses, debt, savings } each with { title, denominator, sum_amount, avg_amount, top, note }.
     * Each top item contains { label, total_amount } sorted by total_amount descending.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $responseQuery  Filtered SurveyResponse query
     */
    private function getFinancialSummaryCards($responseQuery): array
    {
        $surveyResponseIds = (clone $responseQuery)->pluck('id');
        $denominator       = $surveyResponseIds->count();

        $emptyCard = fn (string $title) => [
            'title'       => $title,
            'denominator' => 0,
            'sum_amount'  => 0,
            'avg_amount'  => 0,
            'top'         => [],
            'note'        => 'หมายเหตุ: 1 ครัวเรือนอาจมีหลายหมวด ทำให้ผลรวมเกิน 100%',
        ];

        if ($denominator === 0) {
            return [
                'expenses' => $emptyCard('รายจ่ายครัวเรือนปัจจุบัน'),
                'debt'     => $emptyCard('หนี้สินปัจจุบัน'),
                'savings'  => $emptyCard('การออมปัจจุบัน'),
            ];
        }

        // ── 1. Expenses card (Q8, question_id=9, value_text is JSON) ─────────────
        $expenseLabelMap = self::expenseLabelMap();

        $q8Id = Question::where('question_key', 'Q8')->value('id');
        $expenseKeyTotals = [];
        $expenseTotal     = 0.0;
        if ($q8Id) {
            $expenseAnswers = Answer::whereIn('survey_response_id', $surveyResponseIds)
                ->where('question_id', $q8Id)
                ->whereNotNull('value_text')
                ->get(['survey_response_id', 'value_text']);

            foreach ($expenseAnswers as $row) {
                $decoded = json_decode($row->value_text, true);
                if (!is_array($decoded)) {
                    continue;
                }
                foreach ($decoded as $key => $value) {
                    if (is_numeric($value) && (float) $value > 0) {
                        $expenseKeyTotals[$key] = ($expenseKeyTotals[$key] ?? 0.0) + (float) $value;
                        $expenseTotal += (float) $value;
                    }
                }
            }
        }
        $expensesSum = (int) round($expenseTotal);
        $expensesAvg = $denominator > 0 ? (int) round($expenseTotal / $denominator) : 0;
        arsort($expenseKeyTotals);
        $expenseTop = [];
        foreach (array_slice($expenseKeyTotals, 0, 3, true) as $key => $keyTotal) {
            $expenseTop[] = [
                'label'        => $expenseLabelMap[$key] ?? $key,
                'total_amount' => (int) round($keyTotal),
            ];
        }

        // ── 2. Debt card (Q10, question_code='Q10_debt', sub_answers JSON) ────────
        $debtLabelMap = self::debtLabelMap();
        $debtKeyTotals = [];
        $debtTotal     = 0.0;

        $debtRows = DetailedAnswer::whereIn('survey_response_id', $surveyResponseIds)
            ->where('question_code', 'Q10_debt')
            ->whereNotNull('sub_answers')
            ->get(['survey_response_id', 'sub_answers']);

        foreach ($debtRows as $row) {
            $subAnswers = $row->sub_answers;
            if (!is_array($subAnswers)) {
                continue;
            }
            foreach ($subAnswers as $key => $info) {
                if (is_array($info) && isset($info['amount']) && is_numeric($info['amount']) && (float) $info['amount'] > 0) {
                    $debtKeyTotals[$key] = ($debtKeyTotals[$key] ?? 0.0) + (float) $info['amount'];
                    $debtTotal += (float) $info['amount'];
                } elseif (is_numeric($info) && (float) $info > 0) {
                    $debtKeyTotals[$key] = ($debtKeyTotals[$key] ?? 0.0) + (float) $info;
                    $debtTotal += (float) $info;
                }
            }
        }
        $debtSum = (int) round($debtTotal);
        $debtAvg = $denominator > 0 ? (int) round($debtTotal / $denominator) : 0;
        arsort($debtKeyTotals);
        $debtTop = [];
        foreach (array_slice($debtKeyTotals, 0, 3, true) as $key => $keyTotal) {
            $debtTop[] = [
                'label'        => $debtLabelMap[$key] ?? $key,
                'total_amount' => (int) round($keyTotal),
            ];
        }

        // ── 3. Savings card (Q9_savings detailed_answers, savings types by amount total) ──
        $savingsLabelMap = self::savingsLabelMap();
        $savingsKeyTotals = [];
        $savingsTotal     = 0.0;

        $savingsDetailRows = DetailedAnswer::whereIn('survey_response_id', $surveyResponseIds)
            ->where('question_code', 'Q9_savings')
            ->whereNotNull('sub_answers')
            ->get(['survey_response_id', 'sub_answers']);

        foreach ($savingsDetailRows as $row) {
            $subAnswers = $row->sub_answers;
            if (!is_array($subAnswers)) {
                continue;
            }
            foreach ($subAnswers as $key => $value) {
                if (is_numeric($value) && (float) $value > 0) {
                    $savingsKeyTotals[$key] = ($savingsKeyTotals[$key] ?? 0.0) + (float) $value;
                    $savingsTotal += (float) $value;
                }
            }
        }
        $savingsSum = (int) round($savingsTotal);
        $savingsAvg = $denominator > 0 ? (int) round($savingsTotal / $denominator) : 0;
        arsort($savingsKeyTotals);
        $savingsTop = [];
        foreach (array_slice($savingsKeyTotals, 0, 3, true) as $key => $keyTotal) {
            $savingsTop[] = [
                'label'        => $savingsLabelMap[$key] ?? $key,
                'total_amount' => (int) round($keyTotal),
            ];
        }

        return [
            'expenses' => [
                'title'       => 'รายจ่ายครัวเรือนปัจจุบัน',
                'denominator' => $denominator,
                'sum_amount'  => $expensesSum,
                'avg_amount'  => $expensesAvg,
                'top'         => $expenseTop,
                'note'        => 'หมายเหตุ: 1 ครัวเรือนอาจมีหลายหมวด ทำให้ผลรวมเกิน 100%',
            ],
            'debt'     => [
                'title'       => 'หนี้สินปัจจุบัน',
                'denominator' => $denominator,
                'sum_amount'  => $debtSum,
                'avg_amount'  => $debtAvg,
                'top'         => $debtTop,
                'note'        => 'หมายเหตุ: 1 ครัวเรือนอาจมีหลายหมวด ทำให้ผลรวมเกิน 100%',
            ],
            'savings'  => [
                'title'       => 'การออมปัจจุบัน',
                'denominator' => $denominator,
                'sum_amount'  => $savingsSum,
                'avg_amount'  => $savingsAvg,
                'top'         => $savingsTop,
                'note'        => null,
            ],
        ];
    }

    /**
     * Compute financial averages per model: income, expenses, debt, savings.
     * Returns array of { model_name, income_avg, expense_avg, debt_avg, savings_avg }.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $responseQuery  Filtered SurveyResponse query
     */
    private function getFinancialByModel($responseQuery): array
    {
        $modelNames = (clone $responseQuery)
            ->whereNotNull('model_name')
            ->distinct()
            ->orderBy('model_name')
            ->pluck('model_name');

        $result = [];

        // Q4/04 for income (survey), Q8 for expenses, Q10_debt for debt, Q9_savings for savings
        $q8Id = Question::where('question_key', 'Q8')->value('id');
        $q4Ids = Question::whereIn('question_key', ['Q4', '04'])->pluck('id');

        foreach ($modelNames as $mName) {
            $modelQuery        = (clone $responseQuery)->where('model_name', $mName);
            $modelResponseIds  = (clone $modelQuery)->pluck('id');
            $modelCount        = $modelResponseIds->count();

            if ($modelCount === 0) {
                $result[] = [
                    'model_name'  => $mName,
                    'income_avg'  => null,
                    'expense_avg' => null,
                    'debt_avg'    => null,
                    'savings_avg' => null,
                ];
                continue;
            }

            // Income avg (survey Q4)
            $incomeAvg = null;
            if ($q4Ids->isNotEmpty()) {
                $row = Answer::whereIn('survey_response_id', $modelResponseIds)
                    ->whereIn('question_id', $q4Ids)
                    ->whereNotNull('value_numeric')
                    ->selectRaw('AVG(value_numeric) AS avg_val')
                    ->first();
                $incomeAvg = $row && $row->avg_val !== null ? round((float) $row->avg_val, 2) : null;
            }

            // Expenses avg (Q8 value_text JSON, sum per respondent)
            $expenseAvg = null;
            if ($q8Id) {
                $expenseRows = Answer::whereIn('survey_response_id', $modelResponseIds)
                    ->where('question_id', $q8Id)
                    ->whereNotNull('value_text')
                    ->get(['value_text']);

                $totalExpense = 0.0;
                $expenseCount = 0;
                foreach ($expenseRows as $row) {
                    $decoded = json_decode($row->value_text, true);
                    if (is_array($decoded)) {
                        $sum = 0.0;
                        foreach ($decoded as $v) {
                            if (is_numeric($v)) {
                                $sum += (float) $v;
                            }
                        }
                        $totalExpense += $sum;
                        $expenseCount++;
                    }
                }
                $expenseAvg = $expenseCount > 0 ? round($totalExpense / $expenseCount, 2) : null;
            }

            // Debt avg (Q10_debt sub_answers, sum of amounts per respondent)
            $debtAvg = null;
            $debtRows = DetailedAnswer::whereIn('survey_response_id', $modelResponseIds)
                ->where('question_code', 'Q10_debt')
                ->whereNotNull('sub_answers')
                ->get(['sub_answers']);

            $totalDebt = 0.0;
            $debtCount = 0;
            foreach ($debtRows as $row) {
                $subAnswers = $row->sub_answers;
                if (is_array($subAnswers)) {
                    $sum = 0.0;
                    foreach ($subAnswers as $info) {
                        if (is_array($info) && isset($info['amount']) && is_numeric($info['amount'])) {
                            $sum += (float) $info['amount'];
                        } elseif (is_numeric($info)) {
                            $sum += (float) $info;
                        }
                    }
                    $totalDebt += $sum;
                    $debtCount++;
                }
            }
            $debtAvg = $debtCount > 0 ? round($totalDebt / $debtCount, 2) : null;

            // Savings avg (Q9_savings sub_answers, sum of amounts per respondent)
            $savingsAvg = null;
            $savingsRows = DetailedAnswer::whereIn('survey_response_id', $modelResponseIds)
                ->where('question_code', 'Q9_savings')
                ->whereNotNull('sub_answers')
                ->get(['sub_answers']);

            $totalSavings = 0.0;
            $savingsCount = 0;
            foreach ($savingsRows as $row) {
                $subAnswers = $row->sub_answers;
                if (is_array($subAnswers)) {
                    $sum = 0.0;
                    foreach ($subAnswers as $key => $value) {
                        // Skip '_name' keys (text fields)
                        if (str_ends_with((string) $key, '_name')) {
                            continue;
                        }
                        if (is_numeric($value)) {
                            $sum += (float) $value;
                        }
                    }
                    $totalSavings += $sum;
                    $savingsCount++;
                }
            }
            $savingsAvg = $savingsCount > 0 ? round($totalSavings / $savingsCount, 2) : null;

            $result[] = [
                'model_name'  => $mName,
                'income_avg'  => $incomeAvg,
                'expense_avg' => $expenseAvg,
                'debt_avg'    => $debtAvg,
                'savings_avg' => $savingsAvg,
            ];
        }

        return $result;
    }
}
