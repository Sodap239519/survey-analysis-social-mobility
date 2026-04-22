<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * ImportProdSqlite
 *
 * Copies core data tables from a Production SQLite file (sqlite_prod connection)
 * into the default MySQL connection.
 *
 * Usage:
 *   php artisan app:import-prod-sqlite
 *   php artisan app:import-prod-sqlite --truncate
 *
 * Prerequisites:
 *   1. Set DB_PROD_SQLITE_PATH in .env to the path of the downloaded prod-database.sqlite.
 *   2. Run php artisan migrate to ensure MySQL schema is up to date.
 */
class ImportProdSqlite extends Command
{
    protected $signature = 'app:import-prod-sqlite
                            {--truncate : Truncate destination tables before importing}
                            {--chunk=500 : Number of rows per insert batch}';

    protected $description = 'Import core data tables from Production SQLite into local MySQL';

    /**
     * Tables imported in FK-safe order.
     * Ephemeral/system tables are intentionally excluded.
     */
    private const CORE_TABLES = [
        'users',
        'capitals',
        'households',
        'persons',
        'questions',
        'choices',
        'survey_responses',
        'answers',
        'detailed_answers',
    ];

    /**
     * Tables that exist in Production but should be skipped.
     */
    private const SKIP_TABLES = [
        'cache',
        'cache_locks',
        'jobs',
        'failed_jobs',
        'job_batches',
        'sessions',
        'password_reset_tokens',
        'personal_access_tokens',
        'migrations',
    ];

    public function handle(): int
    {
        $srcPath = config('database.connections.sqlite_prod.database');

        if (! file_exists($srcPath)) {
            $this->error("SQLite file not found: {$srcPath}");
            $this->line('Set DB_PROD_SQLITE_PATH in your .env to the path of the downloaded prod-database.sqlite.');
            return self::FAILURE;
        }

        $this->info("Source SQLite : {$srcPath}");
        $this->info('Destination   : ' . config('database.default') . ' / ' . config('database.connections.' . config('database.default') . '.database'));
        $this->newLine();

        $truncate = $this->option('truncate');
        $chunk    = (int) $this->option('chunk');

        $tables = self::CORE_TABLES;

        $this->line('Tables to import: ' . implode(', ', $tables));
        if ($truncate) {
            $this->warn('--truncate is set: destination tables will be CLEARED before import.');
        }
        $this->newLine();

        if (! $this->confirm('Proceed with import?', false)) {
            $this->info('Import cancelled.');
            return self::SUCCESS;
        }

        // Disable FK checks on MySQL/MariaDB so we can insert in any order.
        $driver = DB::getDriverName();
        $mysqlFamily = in_array($driver, ['mysql', 'mariadb'], true);
        if ($mysqlFamily) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        }

        try {
            foreach ($tables as $table) {
                $this->importTable($table, $truncate, $chunk);
            }
        } finally {
            if ($mysqlFamily) {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            }
        }

        $this->newLine();
        $this->info('Import completed successfully.');
        $this->newLine();
        $this->line('Post-import verification:');
        foreach ($tables as $table) {
            $count = DB::table($table)->count();
            $this->line("  {$table}: {$count} rows");
        }

        return self::SUCCESS;
    }

    private function importTable(string $table, bool $truncate, int $chunk): void
    {
        $src = DB::connection('sqlite_prod');

        // Check the table exists in SQLite source.
        $exists = $src->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
            [$table]
        );

        if (empty($exists)) {
            $this->warn("  [{$table}] not found in source — skipping.");
            return;
        }

        $total = $src->table($table)->count();

        if ($total === 0) {
            $this->line("  [{$table}] 0 rows — skipping.");
            return;
        }

        if ($truncate) {
            DB::table($table)->truncate();
        }

        $this->line("  [{$table}] importing {$total} rows…");
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $orderColumn = $this->resolveSqliteOrderColumn($src, $table);

        $imported = 0;
        $src->table($table)
            ->orderBy($orderColumn)
            ->chunk($chunk, function ($rows) use ($table, $bar, &$imported) {
                $data = collect($rows)->map(fn ($row) => (array) $row)->toArray();
                DB::table($table)->insert($data);
                $imported += count($data);
                $bar->advance(count($data));
            });

        $bar->finish();
        $this->newLine();
        $this->info("  [{$table}] {$imported} rows imported.");
    }

    private function resolveSqliteOrderColumn($src, string $table): string
    {
        // Prefer common PK/timestamp columns if present; fallback to SQLite rowid.
        $cols = $src->select("PRAGMA table_info($table)");
        $names = collect($cols)->pluck('name')->all();

        if (in_array('id', $names, true)) {
            return 'id';
        }
        if (in_array('created_at', $names, true)) {
            return 'created_at';
        }

        // rowid is available for most SQLite tables unless created WITHOUT ROWID
        return 'rowid';
    }
}
