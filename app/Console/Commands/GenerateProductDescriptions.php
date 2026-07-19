<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class GenerateProductDescriptions extends Command
{
    protected $signature = 'products:generate-descriptions {--dry-run : Preview without saving} {--limit=0 : Limit number of products}';
    protected $description = 'Generate SEO descriptions for products that have none';

    public function handle(): int
    {
        $query = Product::whereNull('description')
            ->orWhere('description', '')
            ->with(['category', 'brand']);

        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $total = (clone $query)->count();
        $this->info("Found {$total} products without descriptions.");

        if ($total === 0) {
            return 0;
        }

        $dryRun = $this->option('dry-run');
        $bar = $this->output->createProgressBar($limit > 0 ? min($limit, $total) : $total);
        $updated = 0;

        $query->chunk(500, function ($products) use ($dryRun, $bar, &$updated) {
            foreach ($products as $product) {
                $desc = $this->generateDescription($product);
                $short = $this->generateShortDescription($product);

                if ($dryRun) {
                    if ($updated < 3) {
                        $this->newLine();
                        $this->line("  <fg=cyan>{$product->name}</>");
                        $this->line("  Short: {$short}");
                        $this->line("  Desc:  " . \Illuminate\Support\Str::limit($desc, 120));
                    }
                } else {
                    $product->update([
                        'description' => $desc,
                        'short_description' => $short,
                    ]);
                }

                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        if ($dryRun) {
            $this->info("Dry run complete. {$updated} products would be updated.");
        } else {
            $this->info("Done! Updated {$updated} product descriptions.");
        }

        return 0;
    }

    private function generateDescription(Product $product): string
    {
        $name = $product->name;
        $category = $product->category?->name ?? 'Fine Jewellery';
        $brand = $product->brand?->name ?? 'Jwellers';
        $price = number_format($product->price ?? 0);
        $mrp = number_format($product->mrp ?? 0);

        // Detect product type from name
        $type = $this->detectType($name);
        $ageGroup = $this->detectAgeGroup($name);
        $gender = $this->detectGender($name, $category);

        $templates = [
            "Shop the {$name} from {$brand} — an elegant and finely crafted {$type} designed for {$gender}. Made with premium metals and skilled craftsmanship, this {$type} is perfect for everyday wear, gifting, or special occasions. Available at just ₹{$price}" . ($product->mrp > $product->price ? " (MRP ₹{$mrp})" : '') . ". Part of our {$category} collection at Jwellers — timeless elegance in every piece.",

            "Adorn yourself in style with the {$name} by {$brand}. This {$category} piece combines beauty and craftsmanship, finished to perfection for {$ageGroup}. Whether it's a wedding, a family celebration, or a festive occasion, this {$type} completes your look. Priced at ₹{$price}" . ($product->mrp > $product->price ? " (save " . round((($product->mrp - $product->price) / $product->mrp) * 100) . "%!)" : '') . ". Shop now at Jwellers for free shipping on orders above ₹500.",

            "Introducing the {$name} from {$brand}'s {$category} range. Designed with {$gender} in mind, this stunning {$type} offers the perfect blend of elegance, craftsmanship, and durability. The premium finish ensures a lasting shine while maintaining a graceful, timeless look. Available at ₹{$price}" . ($product->mrp > $product->price ? " — that's " . round((($product->mrp - $product->price) / $product->mrp) * 100) . "% off the retail price!" : '') . " Browse more {$category} at Jwellers.",
        ];

        return $templates[crc32($name) % count($templates)];
    }

    private function generateShortDescription(Product $product): string
    {
        $name = $product->name;
        $brand = $product->brand?->name ?? 'Jwellers';
        $type = $this->detectType($name);
        $gender = $this->detectGender($name, $product->category?->name ?? '');

        $templates = [
            "Elegant {$type} for {$gender} by {$brand}. Finely crafted with a lasting shine, perfect for everyday wear.",
            "Stunning {$type} from {$brand} — designed for elegance and craftsmanship. Ideal for {$gender}.",
            "Premium quality {$type} by {$brand}. Beautifully finished and perfect for gifting or special occasions.",
        ];

        return $templates[crc32($name) % count($templates)];
    }

    private function detectType(string $name): string
    {
        $n = strtolower($name);
        if (preg_match('/\bbridal\b|\bset\b|combo/', $n)) return 'jewellery set';
        if (preg_match('/\bnecklace\b|neckpiece|choker/', $n)) return 'necklace';
        if (preg_match('/\bearring|jhumka|stud/', $n)) return 'pair of earrings';
        if (preg_match('/\bring\b/', $n)) return 'ring';
        if (preg_match('/\bbangle|bracelet|kada/', $n)) return 'bangle';
        if (preg_match('/\bmangalsutra\b/', $n)) return 'mangalsutra';
        if (preg_match('/\bpendant|locket/', $n)) return 'pendant';
        if (preg_match('/\bchain\b/', $n)) return 'chain';
        if (preg_match('/\bnose|nath/', $n)) return 'nose pin';
        if (preg_match('/\banklet|payal/', $n)) return 'anklet';
        return 'jewellery piece';
    }

    private function detectAgeGroup(string $name): string
    {
        $n = strtolower($name);
        if (preg_match('/\bbridal\b|wedding/', $n)) return 'brides and special occasions';
        if (preg_match('/\bdaily\b|casual|office/', $n)) return 'everyday elegance';
        if (preg_match('/\bfestive\b|party/', $n)) return 'festive celebrations';
        return 'every occasion';
    }

    private function detectGender(string $name, string $category): string
    {
        $n = strtolower($name . ' ' . $category);
        if (preg_match('/\bwomen|woman|ladies|her\b/', $n)) return 'women';
        if (preg_match('/\bmen|man|him\b/', $n)) return 'men';
        return 'everyone';
    }
}
