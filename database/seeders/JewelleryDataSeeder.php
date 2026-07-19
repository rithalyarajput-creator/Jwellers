<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Wipes the demo (non-jewellery) catalog and seeds a clean jewellery catalog:
 * jewellery categories, brands, and demo products with free Unsplash imagery.
 *
 * Safe to re-run: it truncates the catalog tables each time before seeding.
 */
class JewelleryDataSeeder extends Seeder
{
    public function run(): void
    {
        // ---- Wipe existing catalog data ---------------------------------
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('product_images')->truncate();
        DB::table('products')->truncate();
        DB::table('categories')->truncate();
        DB::table('brands')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        // ---- Brands -----------------------------------------------------
        $brandNames = ['Jwellers Signature', 'Aurelia', 'Nakshatra', 'Heritage Gold', 'Rivaah', 'Silverline'];
        $brands = [];
        foreach ($brandNames as $i => $name) {
            $brands[] = Brand::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'is_active' => true,
                'position' => $i,
            ]);
        }

        // ---- Categories -------------------------------------------------
        // key => [name, icon, unsplash image]
        $categoryDefs = [
            'necklaces'   => ['Necklaces',   'https://images.unsplash.com/photo-1599643478518-a784e5dc4c8f?w=600&q=80'],
            'earrings'    => ['Earrings',    'https://images.unsplash.com/photo-1535632066927-ab7c9ab60908?w=600&q=80'],
            'rings'       => ['Rings',       'https://images.unsplash.com/photo-1605100804763-247f67b3557e?w=600&q=80'],
            'bangles'     => ['Bangles & Bracelets', 'https://images.unsplash.com/photo-1611591437281-460bfbe1220a?w=600&q=80'],
            'mangalsutra' => ['Mangalsutra', 'https://images.unsplash.com/photo-1617038220319-276d3cfab638?w=600&q=80'],
            'pendants'    => ['Pendants',    'https://images.unsplash.com/photo-1602752250015-52934bc45613?w=600&q=80'],
            'chains'      => ['Chains',      'https://images.unsplash.com/photo-1620656798932-902cbb1df6f9?w=600&q=80'],
            'bridal'      => ['Bridal Sets', 'https://images.unsplash.com/photo-1601121141461-9d6647bca1ed?w=600&q=80'],
        ];

        $categories = [];
        $pos = 0;
        foreach ($categoryDefs as $key => [$name, $img]) {
            $categories[$key] = Category::create([
                'name' => $name,
                'slug' => Str::slug($name),
                'description' => "Explore our exquisite collection of {$name} crafted with timeless elegance.",
                'image_url' => $img,
                'position' => $pos++,
                'level' => 0,
                'is_active' => true,
                'is_featured' => $pos <= 6,
            ]);
        }

        // ---- Products ---------------------------------------------------
        // [name, category key, mrp, price, images[]]
        $img = fn ($id) => "https://images.unsplash.com/photo-{$id}?w=800&q=80";

        $products = [
            ['Kundan Bridal Necklace Set', 'bridal', 45999, 32999, ['1601121141461-9d6647bca1ed', '1602173574767-37531caea7d0']],
            ['Temple Gold Choker', 'necklaces', 38999, 27999, ['1599643478518-a784e5dc4c8f', '1620656798932-902cbb1df6f9']],
            ['Polki Diamond Necklace', 'necklaces', 62999, 49999, ['1611652022419-a9419f74343d', '1602173574767-37531caea7d0']],
            ['Emerald Drop Earrings', 'earrings', 15999, 11499, ['1535632066927-ab7c9ab60908', '1596944924616-7b38e7cfac36']],
            ['Jhumka Pearl Earrings', 'earrings', 8999, 5999, ['1596944924616-7b38e7cfac36', '1535632066927-ab7c9ab60908']],
            ['Rose Gold Stud Earrings', 'earrings', 6999, 4499, ['1629224316810-9d8805b95e76', '1535632066927-ab7c9ab60908']],
            ['Solitaire Diamond Ring', 'rings', 54999, 42999, ['1605100804763-247f67b3557e', '1603561591411-07134e71a2a9']],
            ['Ruby Cocktail Ring', 'rings', 18999, 13999, ['1603561591411-07134e71a2a9', '1605100804763-247f67b3557e']],
            ['Gold Band Ring', 'rings', 24999, 19999, ['1610694955371-d4a3e0ce4b52', '1605100804763-247f67b3557e']],
            ['Antique Gold Bangles (Set of 4)', 'bangles', 34999, 26999, ['1611591437281-460bfbe1220a', '1608042314453-ae338d80c427']],
            ['Diamond Tennis Bracelet', 'bangles', 42999, 33999, ['1608042314453-ae338d80c427', '1611591437281-460bfbe1220a']],
            ['Silver Charm Bracelet', 'bangles', 4999, 3299, ['1611652022419-a9419f74343d', '1611591437281-460bfbe1220a']],
            ['Traditional Long Mangalsutra', 'mangalsutra', 21999, 16999, ['1617038220319-276d3cfab638', '1599643478518-a784e5dc4c8f']],
            ['Diamond Mangalsutra Pendant', 'mangalsutra', 27999, 21999, ['1617038260826-a7d2d5c15b7e', '1617038220319-276d3cfab638']],
            ['Heart Solitaire Pendant', 'pendants', 12999, 8999, ['1602752250015-52934bc45613', '1611652022419-a9419f74343d']],
            ['Om Gold Pendant', 'pendants', 9999, 6999, ['1611652022419-a9419f74343d', '1602752250015-52934bc45613']],
            ['22K Gold Rope Chain', 'chains', 32999, 27999, ['1620656798932-902cbb1df6f9', '1599643478518-a784e5dc4c8f']],
            ['Sterling Silver Box Chain', 'chains', 3999, 2499, ['1620656798932-902cbb1df6f9', '1602752250015-52934bc45613']],
        ];

        $sellerId = DB::table('sellers')->value('id');

        foreach ($products as $i => [$name, $catKey, $mrp, $price, $imgs]) {
            $product = Product::create([
                'uuid' => (string) Str::uuid(),
                'seller_id' => $sellerId,
                'brand_id' => $brands[array_rand($brands)]->id,
                'category_id' => $categories[$catKey]->id,
                'name' => $name,
                'slug' => Str::slug($name) . '-' . ($i + 1),
                'short_description' => "Exquisite {$name} crafted with timeless elegance — a perfect addition to your collection.",
                'description' => "The {$name} from Jwellers is meticulously handcrafted by skilled artisans using genuine hallmarked materials. Designed for those who appreciate fine craftsmanship, it makes a stunning statement for weddings, festivals, and special occasions. Comes with an authenticity certificate and elegant gift packaging.",
                'sku' => 'JWL-' . str_pad((string) ($i + 1), 5, '0', STR_PAD_LEFT),
                'mrp' => $mrp,
                'price' => $price,
                'cost_price' => round($price * 0.7, 2),
                'stock_quantity' => rand(5, 40),
                'stock_status' => 'in_stock',
                'is_active' => true,
                'is_featured' => $i < 8,
                'tax_rate' => 3.00, // GST on jewellery
                'rating' => round(rand(40, 50) / 10, 1),
                'review_count' => rand(5, 120),
                'sales_count' => rand(10, 300),
                'status' => 'approved',
                'published_at' => now(),
            ]);

            foreach ($imgs as $pos => $id) {
                ProductImage::create([
                    'product_id' => $product->id,
                    'url' => $img($id),
                    'alt_text' => $name,
                    'position' => $pos,
                    'is_primary' => $pos === 0,
                ]);
            }
        }

        $this->command->info('Seeded ' . count($categories) . ' jewellery categories, ' . count($brands) . ' brands, and ' . count($products) . ' products.');
    }
}
