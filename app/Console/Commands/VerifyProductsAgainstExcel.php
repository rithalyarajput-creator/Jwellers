<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VerifyProductsAgainstExcel extends Command
{
    protected $signature = 'products:verify-excel
                            {file : Path to the Excel file}
                            {--threshold=0.01 : Minimum decimal difference to flag a price drift}';

    protected $description = 'Cross-verify products table against ForeverKids Excel stock report (read-only)';

    private const COL_CODE = 0;
    private const COL_NAME = 1;
    private const COL_STOCK = 3;
    private const COL_COST_PRICE = 6;
    private const COL_MRP = 8;
    private const COL_SALES_PRICE = 10;
    private const COL_BARCODE = 16;

    public function handle(): int
    {
        $filePath = $this->argument('file');
        $threshold = (float) $this->option('threshold');

        if (! file_exists($filePath)) {
            $this->error("File not found: {$filePath}");
            return self::FAILURE;
        }

        $this->info('Reading Excel file...');
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestRow();

        $this->info("Sheet: {$sheet->getTitle()} | Total rows: {$highestRow}");
        $this->newLine();

        $reportPath = storage_path('app/verification-report-' . now()->format('Ymd-His') . '.csv');
        $out = fopen($reportPath, 'w');
        fputcsv($out, ['Status', 'SKU', 'Field', 'DB Value', 'Excel Value', 'Product Name']);

        $stats = [
            'MATCH' => 0,
            'DRIFT' => 0,
            'MISSING_IN_DB' => 0,
            'MISSING_IN_EXCEL' => 0,
            'drift_details' => [],
        ];

        // Track SKUs seen in Excel for later "missing in Excel" check
        $excelSkus = [];

        $bar = $this->output->createProgressBar($highestRow - 4);
        $bar->start();

        for ($row = 5; $row <= $highestRow; $row++) {
            $bar->advance();

            $code = trim((string) $sheet->getCellByColumnAndRow(self::COL_CODE + 1, $row)->getValue());
            $name = trim((string) $sheet->getCellByColumnAndRow(self::COL_NAME + 1, $row)->getValue());

            if (empty($code) || empty($name) || !preg_match('/[A-Za-z0-9]/', $code)) {
                continue;
            }

            // Sanitise SKU the same way import command does
            $sku = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $code);
            $excelSkus[$sku] = true;

            $excelStock = (int) ($sheet->getCellByColumnAndRow(self::COL_STOCK + 1, $row)->getValue() ?: 0);
            $excelCost = (float) ($sheet->getCellByColumnAndRow(self::COL_COST_PRICE + 1, $row)->getValue() ?: 0);
            $excelMrp = (float) ($sheet->getCellByColumnAndRow(self::COL_MRP + 1, $row)->getValue() ?: 0);
            $excelPrice = (float) ($sheet->getCellByColumnAndRow(self::COL_SALES_PRICE + 1, $row)->getValue() ?: 0);
            if ($excelPrice <= 0) {
                $excelPrice = $excelMrp;
            }
            $excelBarcode = trim((string) $sheet->getCellByColumnAndRow(self::COL_BARCODE + 1, $row)->getValue());

            $product = Product::where('sku', $sku)->first();

            if (! $product) {
                fputcsv($out, ['MISSING_IN_DB', $sku, '-', '-', '-', $name]);
                $stats['MISSING_IN_DB']++;
                continue;
            }

            $fieldsChecked = [
                ['stock_quantity', (int) $product->stock_quantity, $excelStock, 0],
                ['price', (float) $product->price, $excelPrice, $threshold],
                ['mrp', (float) $product->mrp, $excelMrp, $threshold],
                ['cost_price', (float) $product->cost_price, $excelCost, $threshold],
                ['barcode', (string) $product->barcode, $excelBarcode, 0],
            ];

            $drifted = false;
            foreach ($fieldsChecked as [$field, $dbVal, $xlsVal, $tol]) {
                if (is_numeric($dbVal) && is_numeric($xlsVal)) {
                    if (abs($dbVal - $xlsVal) > $tol) {
                        fputcsv($out, ['DRIFT', $sku, $field, $dbVal, $xlsVal, $name]);
                        $stats['DRIFT']++;
                        $stats['drift_details'][$field] = ($stats['drift_details'][$field] ?? 0) + 1;
                        $drifted = true;
                    }
                } else {
                    if ((string) $dbVal !== (string) $xlsVal) {
                        fputcsv($out, ['DRIFT', $sku, $field, $dbVal, $xlsVal, $name]);
                        $stats['DRIFT']++;
                        $stats['drift_details'][$field] = ($stats['drift_details'][$field] ?? 0) + 1;
                        $drifted = true;
                    }
                }
            }

            if (! $drifted) {
                $stats['MATCH']++;
            }
        }

        $bar->finish();
        $this->newLine(2);

        // Find products in DB but not in Excel
        $this->info('Checking for products in DB that are missing from Excel...');
        Product::select('sku', 'name')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->chunk(500, function ($products) use (&$excelSkus, $out, &$stats) {
                foreach ($products as $product) {
                    if (! isset($excelSkus[$product->sku])) {
                        fputcsv($out, ['MISSING_IN_EXCEL', $product->sku, '-', '-', '-', $product->name]);
                        $stats['MISSING_IN_EXCEL']++;
                    }
                }
            });

        fclose($out);

        // Summary
        $this->newLine();
        $this->line('<fg=green>=== VERIFICATION SUMMARY ===</>');
        $this->line("Products fully matching: <fg=green>{$stats['MATCH']}</>");
        $this->line("Drift entries (field mismatches): <fg=yellow>{$stats['DRIFT']}</>");
        $this->line("In Excel but not in DB: <fg=red>{$stats['MISSING_IN_DB']}</>");
        $this->line("In DB but not in Excel: <fg=red>{$stats['MISSING_IN_EXCEL']}</>");

        if (! empty($stats['drift_details'])) {
            $this->newLine();
            $this->line('<fg=yellow>Drift by field:</>');
            foreach ($stats['drift_details'] as $field => $count) {
                $this->line("  {$field}: {$count}");
            }
        }

        $this->newLine();
        $this->info("Report saved to: {$reportPath}");

        return self::SUCCESS;
    }
}
