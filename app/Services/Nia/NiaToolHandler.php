<?php

namespace App\Services\Nia;

use App\Models\Lead;

/**
 * Executes Claude tool calls during a tool-use loop. Each method returns a
 * JSON-serialisable array that is sent back to Claude as the tool result.
 *
 * Centralising this here keeps ClaudeService focused on the API conversation
 * and lets us unit-test tool behaviour without hitting Anthropic.
 */
class NiaToolHandler
{
    public function __construct(
        private readonly ProductLookupService $products,
    ) {
    }

    /**
     * @return array{tools: array, schemas: array}
     */
    public static function toolDefinitions(): array
    {
        return [
            [
                'name'        => 'lookup_products',
                'description' => 'Search the live ForeverKids product catalog. Use this BEFORE recommending any product. Never invent SKUs, prices, or stock.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'query'             => ['type' => 'string', 'description' => 'Free-text search, e.g. "red frock"'],
                        'gender'            => ['type' => 'string', 'enum' => ['girl', 'boy', 'unisex']],
                        'age_band'          => ['type' => 'string', 'description' => 'e.g. 0-3m, 1-3y, 3-6y'],
                        'occasion'          => ['type' => 'string', 'description' => 'e.g. party, casual, festive, wedding'],
                        'color'             => ['type' => 'string'],
                        'max_price'         => ['type' => 'number'],
                        'category_keywords' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'e.g. ["frock"], ["tshirt", "kurta"]',
                        ],
                        'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                    ],
                    'required' => [],
                ],
            ],
            [
                'name'         => 'get_size_chart_url',
                'description'  => 'Return the public URL of the size guide. Use when customer asks about sizing.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [],
                ],
            ],
            [
                'name'        => 'create_order_link',
                'description' => 'Return a deep link to a product detail page. Optionally pre-select a size variant.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'product_slug' => ['type' => 'string'],
                        'size'         => ['type' => 'string'],
                    ],
                    'required' => ['product_slug'],
                ],
            ],
        ];
    }

    /**
     * Dispatch a single tool call by name. Unknown tools return an error
     * payload (Claude is told to retry differently).
     */
    public function execute(string $name, array $input, Lead $lead): array
    {
        return match ($name) {
            'lookup_products'    => $this->lookupProducts($input),
            'get_size_chart_url' => $this->sizeChartUrl(),
            'create_order_link'  => $this->orderLink($input),
            default              => ['error' => "Unknown tool: {$name}"],
        };
    }

    private function lookupProducts(array $input): array
    {
        $matches = $this->products->search($input);
        return [
            'count'    => count($matches),
            'products' => array_map(fn ($p) => $p->toArray(), $matches),
        ];
    }

    private function sizeChartUrl(): array
    {
        return ['url' => url('/size-guide')];
    }

    private function orderLink(array $input): array
    {
        $slug = (string) ($input['product_slug'] ?? '');
        if ($slug === '') {
            return ['error' => 'product_slug is required'];
        }
        $url = url('/products/' . $slug);
        if (!empty($input['size'])) {
            $url .= '?size=' . urlencode((string) $input['size']);
        }
        return ['url' => $url];
    }
}
