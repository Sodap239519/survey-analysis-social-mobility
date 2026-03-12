<?php

namespace App\Console\Commands;

use App\Models\Household;
use Illuminate\Console\Command;

/**
 * PopulateBaselineScores
 *
 * Extracts baseline capital scores from Household.raw_data (stored during XLSX import)
 * and writes them into the dedicated baseline_score_* columns.
 *
 * This is needed for households that were imported before the baseline_score_* columns
 * existed, or when firstOrCreate skipped the update for already-existing records.
 *
 * Column mapping in the "ข้อมูลพื้นฐาน" Excel sheet (0-based indices):
 *   Index 14 → Column O → ทุนมนุษย์   (human capital)
 *   Index 15 → Column P → ทุนกายภาพ  (physical capital)
 *   Index 16 → Column Q → ทุนการเงิน  (financial capital)
 *   Index 17 → Column R → ทุนธรรมชาติ (natural capital)
 *   Index 18 → Column S → ทุนทางสังคม (social capital)
 *
 * Values are on the X scale [1.0–4.0].
 *
 * Usage: php artisan baseline:populate [--force]
 */
class PopulateBaselineScores extends Command
{
    protected $signature = 'baseline:populate
                            {--force : Update all households, even those with existing baseline scores}';

    protected $description = 'Populate baseline_score_* columns in the households table from raw_data';

    public function handle(): int
    {
        $query = Household::whereNotNull('raw_data');

        if (! $this->option('force')) {
            $query->where(function ($q) {
                $q->whereNull('baseline_score_human')
                  ->orWhereNull('baseline_score_physical')
                  ->orWhereNull('baseline_score_financial')
                  ->orWhereNull('baseline_score_natural')
                  ->orWhereNull('baseline_score_social');
            });
        }

        $total    = $query->count();
        $updated  = 0;
        $skipped  = 0;
        $noData   = 0;

        $this->info("Found {$total} household(s) to process.");

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunkById(200, function ($households) use (&$updated, &$skipped, &$noData, $bar) {
            foreach ($households as $household) {
                $baseline = $this->extractBaselineScores($household->raw_data);

                if ($baseline === null) {
                    $noData++;
                    $bar->advance();
                    continue;
                }

                $allNull = $baseline['human'] === null
                    && $baseline['physical'] === null
                    && $baseline['financial'] === null
                    && $baseline['natural'] === null
                    && $baseline['social'] === null;

                if ($allNull) {
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                $household->update([
                    'baseline_score_human'    => $baseline['human'],
                    'baseline_score_physical' => $baseline['physical'],
                    'baseline_score_financial'=> $baseline['financial'],
                    'baseline_score_natural'  => $baseline['natural'],
                    'baseline_score_social'   => $baseline['social'],
                ]);

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();

        $this->info("Done. Updated: {$updated}, Skipped (all null): {$skipped}, No raw_data: {$noData}.");

        return self::SUCCESS;
    }

    /**
     * Extract baseline capital scores (X scale 1.0–4.0) from a raw_data row.
     *
     * The raw_data array stores the full Excel row from the "ข้อมูลพื้นฐาน" sheet.
     * Capital score columns are at 0-based indices 14–18 (columns O–S).
     *
     * @param  array|null $rawData
     * @return array{human: float|null, physical: float|null, financial: float|null,
     *               natural: float|null, social: float|null}|null
     */
    private function extractBaselineScores(?array $rawData): ?array
    {
        if (empty($rawData) || ! is_array($rawData)) {
            return null;
        }

        return [
            'human'    => $this->toFloat($rawData[14] ?? null), // Column O
            'physical' => $this->toFloat($rawData[15] ?? null), // Column P
            'financial'=> $this->toFloat($rawData[16] ?? null), // Column Q
            'natural'  => $this->toFloat($rawData[17] ?? null), // Column R
            'social'   => $this->toFloat($rawData[18] ?? null), // Column S
        ];
    }

    private function toFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        $f = filter_var($value, FILTER_VALIDATE_FLOAT);
        return $f !== false ? $f : null;
    }
}
