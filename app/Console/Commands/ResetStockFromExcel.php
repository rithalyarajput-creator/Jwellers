<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ResetStockFromExcel extends Command
{
    protected $signature = 'products:reset-stock
                            {file : Path to the Excel file}
                            {--dry-run : Preview changes without writing}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset products.stock_quantity to match Excel stock report (matches by SKU)';

    private const COL_CODE = 0;
    private const COL_NAME = 1;
    private const COL_STOCK = 3;

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $dryRun   = (bool) $this->option('dry-run');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info('Reading Excel...');
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $this->info("Sheet: {$sheet->getTitle()} | Rows: {$highestRow}");

        // Collect Excel rows first
        $rows = [];
        for ($row = 5; $row <= $highestRow; $row++) {
            $code = trim((string) $sheet->getCellByColumnAndRow(self::COL_CODE + 1, $row)->getValue());
            $name = trim((string) $sheet->getCellByColumnAndRow(self::COL_NAME + 1, $row)->getValue());

            if (empty($code) || empty($name) || ! preg_match('/[A-Za-z0-9]/', $code)) {
                continue;
            }

            $sku   = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $code);
            $stock = (int) ($sheet->getCellByColumnAndRow(self::COL_STOCK + 1, $row)->getValue() ?: 0);
            // Clamp negatives to 0 — DB column is UNSIGNED, and negative stock is meaningless.
            $rows[$sku] = max(0, $stock);
        }

        $this->info('Excel rows parsed: ' . count($rows));

        if (! $dryRun && ! $this->option('force')) {
            if (! $this->confirm('This will overwrite stock_quantity on production for matched SKUs. Continue?')) {
                $this->warn('Aborted.');
                return self::SUCCESS;
            }
        }

        $updated  = 0;
        $unchanged = 0;
        $missing  = 0;
        $totalDelta = 0;

        $bar = $this->output->createProgressBar(count($rows));
        $bar->start();

        foreach ($rows as $sku => $excelStock) {
            $bar->advance();

            $product = Product::where('sku', $sku)->first();
            if (! $product) {
                $missing++;
                continue;
            }

            $current = (int) $product->stock_quantity;
            if ($current === $excelStock) {
                $unchanged++;
                continue;
            }

            $totalDelta += ($excelStock - $current);

            if (! $dryRun) {
                DB::table('products')
                    ->where('id', $product->id)
                    ->update(['stock_quantity' => $excelStock, 'updated_at' => now()]);
            }
            $updated++;
        }

        $bar->finish();
        $this->newLine(2);

        $this->line('<fg=green>=== SUMMARY ===</>');
        $this->line('Mode: ' . ($dryRun ? '<fg=yellow>DRY-RUN (no writes)</>' : '<fg=red>WRITE</>'));
        $this->line("Updated:    <fg=green>{$updated}</>");
        $this->line("Unchanged:  {$unchanged}");
        $this->line("Not in DB:  <fg=red>{$missing}</>");
        $this->line('Net stock delta: ' . ($totalDelta >= 0 ? '+' : '') . $totalDelta);

        return self::SUCCESS;
    }
}
