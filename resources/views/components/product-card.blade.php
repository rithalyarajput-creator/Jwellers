@props(['product', 'showQuickView' => true, 'compact' => false])

@php
    $discount = $product->discount_percentage ?? 0;
    $hasDiscount = $product->price < $product->mrp;
    $rating = $product->rating ?? 0;
    $reviewCount = $product->review_count ?? 0;
    $outOfStock = !$product->isInStock();

    // Read hover action settings (cached for 1hr by Setting::get)
    $showWishlist = \App\Models\Setting::get('product_card_wishlist', true);
    $showAddToCart = \App\Models\Setting::get('product_card_add_to_cart', true);
    $showQuickViewBtn = $showQuickView && \App\Models\Setting::get('product_card_quick_view', true);
    $hasHoverActions = $showWishlist || $showQuickViewBtn;

    // Category-aware placeholder image
    $rootCatId = null;
    if ($product->category) {
        $rootCatId = $product->category->parent_id ?? $product->category->id;
    }
    $placeholderImage = match($rootCatId) {
        1 => asset('images/placeholder-girls.svg'),
        8 => asset('images/placeholder-boys.svg'),
        15 => asset('images/placeholder-baby.svg'),
        default => asset('images/placeholder-boys.svg'),
    };

    // ── Inline variant pickers ──
    // Build size + colour lists and a lookup map keyed by [size][color] → variant info,
    // so the card's Alpine state can resolve the right variant_id at "Add to Bag" time.
    $colorHexMap = [
        'red'=>'#dc2626','pink'=>'#ec4899','orange'=>'#f97316','yellow'=>'#eab308',
        'green'=>'#16a34a','olive'=>'#65a30d','teal'=>'#14b8a6','cyan'=>'#06b6d4',
        'blue'=>'#2563eb','navy'=>'#1e3a8a','purple'=>'#9333ea','magenta'=>'#d946ef',
        'maroon'=>'#7f1d1d','brown'=>'#92400e','beige'=>'#d6c7a3','cream'=>'#f5f0e1',
        'white'=>'#ffffff','off-white'=>'#fafaf6','ivory'=>'#fffff0',
        'grey'=>'#9ca3af','gray'=>'#9ca3af','silver'=>'#d1d5db','black'=>'#1a1a1a',
        'gold'=>'#d4af37',
        // Foreverkids / MARG-import shorthand colour names
        'lavende'=>'#c084fc','lavender'=>'#c084fc',
        'biscuit'=>'#d4a373','peach'=>'#fbb6ce',
        'mustard'=>'#f59e0b','wine'=>'#7f1d1d','rust'=>'#b45309',
        'l.pink'=>'#fbcfe8','d.pink'=>'#be185d','o.pink'=>'#f9a8d4',
        'l.blue'=>'#93c5fd','d.blue'=>'#1e40af','n.blue'=>'#1e3a8a',
        'l.green'=>'#86efac','d.green'=>'#166534',
        'l.grey'=>'#d4d4d4','d.grey'=>'#404040',
        'sky'=>'#7dd3fc','aqua'=>'#67e8f9','coral'=>'#fb7185',
        'multi'=>'linear-gradient(45deg,#dc2626,#eab308,#16a34a,#2563eb)',
    ];

    $cardSizes = [];
    $cardColors = [];
    $variantLookup = [];
    if ($product->relationLoaded('variants') && $product->variants->isNotEmpty()) {
        foreach ($product->variants as $v) {
            if (isset($v->is_active) && !$v->is_active) continue;
            $attrs = is_array($v->attributes)
                ? $v->attributes
                : (is_string($v->attributes) ? (json_decode($v->attributes, true) ?: []) : []);
            $size = isset($attrs['size']) && $attrs['size'] !== '' ? (string) $attrs['size'] : null;
            $color = isset($attrs['color']) && $attrs['color'] !== '' ? ucwords(strtolower((string) $attrs['color'])) : null;
            if ($size === null && $color === null) continue;
            if ($size !== null && !in_array($size, $cardSizes, true)) $cardSizes[] = $size;
            if ($color !== null && !in_array($color, $cardColors, true)) $cardColors[] = $color;
            $sizeKey = $size ?? '';
            $colorKey = $color ?? '';
            $variantLookup[$sizeKey][$colorKey] = [
                'id' => (int) $v->id,
                'stock' => (int) ($v->stock_quantity ?? 0),
                'price' => (float) ($v->price ?: $product->price),
            ];
        }
        usort($cardSizes, function ($a, $b) {
            $na = is_numeric($a); $nb = is_numeric($b);
            if ($na && $nb) return (float) $a <=> (float) $b;
            if ($na && !$nb) return -1;
            if (!$na && $nb) return 1;
            return strcmp((string) $a, (string) $b);
        });
        sort($cardColors);
    }
    $hasSizeOptions = !empty($cardSizes);
    $hasColorOptions = !empty($cardColors);
    $hasVariantOptions = $hasSizeOptions || $hasColorOptions;
    $maxInline = $compact ? 4 : 5;

    // If a product has variants but every one is zero-stock, treat the whole
    // product as out-of-stock so the card shows Notify Me without forcing the
    // user to pick options just to find out it's sold out.
    if ($hasVariantOptions) {
        $totalVariantStock = $product->variants->sum(fn ($v) => max(0, (int) ($v->stock_quantity ?? 0)));
        if ($totalVariantStock === 0) {
            $outOfStock = true;
        }
    }
@endphp

@if($compact)
    {{-- Compact card for horizontal scrollable rows --}}
    <div {{ $attributes->merge(['class' => 'group shrink-0 w-full']) }}
         x-data='{
             selectedSize: null,
             selectedColor: null,
             sizes: @json($cardSizes),
             colors: @json($cardColors),
             lookup: @json($variantLookup),
             requireSize: {{ $hasSizeOptions ? 'true' : 'false' }},
             requireColor: {{ $hasColorOptions ? 'true' : 'false' }},
             get matchedVariant() {
                 const sk = this.selectedSize ?? "";
                 const ck = this.selectedColor ?? "";
                 const bySize = this.lookup[sk];
                 if (!bySize) return null;
                 return bySize[ck] ?? null;
             },
             get isPickerComplete() {
                 if (this.requireSize && !this.selectedSize) return false;
                 if (this.requireColor && !this.selectedColor) return false;
                 return true;
             },
             get canAdd() {
                 if (!this.isPickerComplete) return false;
                 const m = this.matchedVariant;
                 return !!(m && m.stock > 0);
             },
             get isNotifyMode() {
                 if (!this.isPickerComplete) return false;
                 const m = this.matchedVariant;
                 return !m || m.stock <= 0;
             },
             addToCart() {
                 if (!this.requireSize && !this.requireColor) {
                     Alpine.store("cart").add({{ (int) $product->id }});
                     return;
                 }
                 if (this.requireSize && !this.selectedSize) {
                     Alpine.store("toast").error("Please pick a size first");
                     return;
                 }
                 if (this.requireColor && !this.selectedColor) {
                     Alpine.store("toast").error("Please pick a colour first");
                     return;
                 }
                 const m = this.matchedVariant;
                 if (!m || m.stock <= 0) {
                     // Out of stock — fire the notify-stock dispatch, same as the
                     // hard-out-of-stock fallback further down the card.
                     this.$dispatch("notify-stock", { productId: {{ (int) $product->id }} });
                     return;
                 }
                 Alpine.store("cart").add({{ (int) $product->id }}, 1, m.id);
             }
         }'>
        <a href="{{ route('product.show', $product) }}" class="block relative">
            <div class="aspect-square bg-neutral-50 rounded-[20px] overflow-hidden mb-2">
                <img src="{{ $product->primary_image_url }}"
                     alt="{{ $product->name }}"
                     class="w-full h-full object-contain transition-transform duration-300"
                     style="will-change:transform;"
                     onmouseenter="this.style.transform='scale(1.05)'"
                     onmouseleave="this.style.transform='scale(1)'"
                     loading="lazy"
                     onerror="this.src='{{ $placeholderImage }}'">
            </div>
            @if($hasDiscount)
                <span class="absolute top-2 left-2 bg-[#F8931D] text-white font-bold rounded-full text-[8px] w-8 h-8 flex items-center justify-center sm:w-auto sm:h-auto sm:text-[10px] sm:px-2 sm:py-0.5 sm:rounded-md">{{ round($discount) }}%<span class="hidden sm:inline">&nbsp;Off</span></span>
            @endif
        </a>

        <a href="{{ route('product.show', $product) }}" class="block px-1">
            @if($product->brand)
                <p class="text-[10px] text-neutral-600 uppercase tracking-wide mb-0.5">{{ $product->brand->name }}</p>
            @endif
            <h3 class="text-xs text-[#222] line-clamp-1 mb-1 group-hover:text-[#6F9CA2] leading-snug font-medium">
                {{ $product->name }}
            </h3>
        </a>

        @if($rating > 0)
            <div class="flex items-center gap-1 mb-1 px-1">
                <span class="inline-flex items-center gap-0.5 bg-[#C1539C] text-white text-[10px] font-bold px-1 py-0.5 rounded-sm">
                    {{ number_format($rating, 1) }}
                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                </span>
                <span class="text-[10px] text-neutral-600">({{ $reviewCount }})</span>
            </div>
        @endif

        <div class="flex items-baseline gap-1 flex-wrap px-1">
            <span class="text-sm font-bold text-[#222]">@price($product->price)</span>
            @if($hasDiscount)
                <span class="text-[10px] text-neutral-600 line-through">@price($product->mrp)</span>
            @endif
        </div>

        {{-- Variant pickers (compact) --}}
        @if($hasVariantOptions)
            <div class="px-1 mt-1.5 space-y-1">
                @if($hasSizeOptions)
                    <div class="flex items-center gap-1 flex-wrap">
                        <span class="text-[9px] text-neutral-500 uppercase tracking-wide">Size</span>
                        @foreach(array_slice($cardSizes, 0, $maxInline) as $sz)
                            <button type="button"
                                    @click.stop.prevent="selectedSize = @js($sz)"
                                    :class="selectedSize === @js($sz) ? 'border-[#6F9CA2] bg-[#6F9CA2] text-white' : 'border-neutral-300 text-neutral-700 hover:border-neutral-500'"
                                    class="text-[10px] px-1.5 py-0.5 border rounded leading-none transition-colors min-w-[22px] text-center">
                                {{ $sz }}
                            </button>
                        @endforeach
                        @if(count($cardSizes) > $maxInline)
                            <a href="{{ route('product.show', $product) }}" class="text-[10px] text-neutral-500 hover:text-[#6F9CA2]">+{{ count($cardSizes) - $maxInline }}</a>
                        @endif
                    </div>
                @endif
                @if($hasColorOptions)
                    <div class="flex items-center gap-1 flex-wrap">
                        <span class="text-[9px] text-neutral-500 uppercase tracking-wide">Color</span>
                        @foreach(array_slice($cardColors, 0, $maxInline) as $clr)
                            @php $hex = $colorHexMap[strtolower($clr)] ?? '#cbd5e1'; @endphp
                            <button type="button"
                                    title="{{ $clr }}"
                                    @click.stop.prevent="selectedColor = @js($clr)"
                                    :class="selectedColor === @js($clr) ? 'ring-2 ring-[#6F9CA2] ring-offset-1' : ''"
                                    class="w-4 h-4 rounded-full transition-all border-0"
                                    style="background:{{ $hex }};"></button>
                        @endforeach
                        @if(count($cardColors) > $maxInline)
                            <a href="{{ route('product.show', $product) }}" class="text-[10px] text-neutral-500 hover:text-[#6F9CA2]">+{{ count($cardColors) - $maxInline }}</a>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- Add to Cart --}}
        @if($showAddToCart)
            <div class="mt-2 px-1">
                @unless($outOfStock)
                    @if($hasVariantOptions)
                        <button @click.stop.prevent="addToCart()"
                                :class="isNotifyMode ? 'border border-neutral-200 text-neutral-600 bg-white hover:bg-neutral-50' : 'text-white'"
                                :style="isNotifyMode ? '' : (canAdd ? 'background:#6F9CA2;' : 'background:#9CA3AF;')"
                                class="w-full py-2.5 text-[12px] font-semibold rounded-md transition-colors duration-200">
                            <span x-text="isNotifyMode ? 'Notify Me' : (canAdd ? 'Add to Bag' : 'Select options')">Add to Bag</span>
                        </button>
                    @else
                        <button @click="$store.cart.add({{ $product->id }})"
                                class="w-full py-2.5 text-[12px] font-semibold text-white rounded-md transition-colors duration-200"
                                style="background:#6F9CA2;"
                                @mouseenter="$el.style.background='#5B878D'"
                                @mouseleave="$el.style.background='#6F9CA2'">
                            Add to Bag
                        </button>
                    @endif
                @else
                    <button @click="$dispatch('notify-stock', { productId: {{ $product->id }} })"
                            class="w-full py-2.5 text-[12px] font-medium text-neutral-600 border border-neutral-200 rounded-md hover:bg-neutral-50 transition-colors">
                        Notify Me
                    </button>
                @endunless
            </div>
        @endif
    </div>
@else
    {{-- Full product card - MudKid style --}}
    <div {{ $attributes->merge(['class' => 'group card-product flex flex-col bg-white rounded-[20px] overflow-hidden']) }}
         x-data='{
             selectedSize: null,
             selectedColor: null,
             sizes: @json($cardSizes),
             colors: @json($cardColors),
             lookup: @json($variantLookup),
             requireSize: {{ $hasSizeOptions ? 'true' : 'false' }},
             requireColor: {{ $hasColorOptions ? 'true' : 'false' }},
             get matchedVariant() {
                 const sk = this.selectedSize ?? "";
                 const ck = this.selectedColor ?? "";
                 const bySize = this.lookup[sk];
                 if (!bySize) return null;
                 return bySize[ck] ?? null;
             },
             get isPickerComplete() {
                 if (this.requireSize && !this.selectedSize) return false;
                 if (this.requireColor && !this.selectedColor) return false;
                 return true;
             },
             get canAdd() {
                 if (!this.isPickerComplete) return false;
                 const m = this.matchedVariant;
                 return !!(m && m.stock > 0);
             },
             get isNotifyMode() {
                 if (!this.isPickerComplete) return false;
                 const m = this.matchedVariant;
                 return !m || m.stock <= 0;
             },
             addToCart() {
                 if (!this.requireSize && !this.requireColor) {
                     Alpine.store("cart").add({{ (int) $product->id }});
                     return;
                 }
                 if (this.requireSize && !this.selectedSize) {
                     Alpine.store("toast").error("Please pick a size first");
                     return;
                 }
                 if (this.requireColor && !this.selectedColor) {
                     Alpine.store("toast").error("Please pick a colour first");
                     return;
                 }
                 const m = this.matchedVariant;
                 if (!m || m.stock <= 0) {
                     // Out of stock — fire the notify-stock dispatch, same as the
                     // hard-out-of-stock fallback further down the card.
                     this.$dispatch("notify-stock", { productId: {{ (int) $product->id }} });
                     return;
                 }
                 Alpine.store("cart").add({{ (int) $product->id }}, 1, m.id);
             }
         }'>
        {{-- Image Section --}}
        <div class="relative aspect-square overflow-hidden bg-neutral-50">
            <a href="{{ route('product.show', $product) }}">
                <img src="{{ $product->primary_image_url }}"
                     alt="{{ $product->name }}"
                     class="w-full h-full object-contain transition-transform duration-300"
                     style="will-change:transform;"
                     onmouseenter="this.style.transform='scale(1.05)'"
                     onmouseleave="this.style.transform='scale(1)'"
                     loading="lazy"
                     onerror="this.src='{{ $placeholderImage }}'">
            </a>

            {{-- Top-left badges --}}
            <div class="absolute top-2 left-2 sm:top-3 sm:left-3 flex flex-col gap-1">
                @if($hasDiscount)
                    <span class="bg-[#F8931D] text-white font-bold rounded-full text-[8px] w-8 h-8 flex items-center justify-center sm:w-auto sm:h-auto sm:text-[10px] sm:px-2 sm:py-0.5 sm:rounded-md">{{ round($discount) }}%<span class="hidden sm:inline">&nbsp;Off</span></span>
                @endif
            </div>

            {{-- Top-right hover actions (Wishlist + Quick View) --}}
            @if($hasHoverActions)
                <div class="absolute top-3 right-3 flex flex-col gap-1.5 sm:opacity-0 sm:group-hover:opacity-100 focus-within:opacity-100 transition-opacity duration-200">
                    @if($showWishlist)
                        <button @click="$store.wishlist.toggle({{ $product->id }})"
                                class="w-10 h-10 bg-white rounded-full shadow-sm flex items-center justify-center transition-colors focus:outline-none focus:ring-2 focus:ring-[#6F9CA2] focus:ring-offset-1"
                                :style="$store.wishlist.has({{ $product->id }}) ? 'color: #ef4444;' : 'color: #737373;'"
                                aria-label="Toggle wishlist">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                            </svg>
                        </button>
                    @endif
                    @if($showQuickViewBtn)
                        <button @click="$dispatch('quick-view', { productId: {{ $product->id }} })"
                                class="w-10 h-10 bg-white rounded-full shadow-sm flex items-center justify-center text-neutral-600 hover:text-[#6F9CA2] transition-colors focus:outline-none focus:ring-2 focus:ring-[#6F9CA2] focus:ring-offset-1"
                                aria-label="Quick view">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                            </svg>
                        </button>
                    @endif
                </div>
            @endif

            {{-- Out of stock overlay --}}
            @if($outOfStock)
                <div class="absolute inset-0 bg-white/70 flex items-center justify-center">
                    <span class="text-xs font-semibold text-neutral-600 bg-white px-3 py-1 rounded-full shadow-sm">Out of Stock</span>
                </div>
            @endif
        </div>

        {{-- Content Section --}}
        <div class="p-3 flex flex-col flex-1">
            {{-- Brand --}}
            @if($product->brand)
                <p class="text-[10px] text-neutral-600 uppercase tracking-wider mb-0.5">{{ $product->brand->name }}</p>
            @elseif($product->category)
                <a href="{{ route('category.show', $product->category) }}" class="text-[10px] text-neutral-600 uppercase tracking-wider mb-0.5 block hover:text-[#6F9CA2]">
                    {{ $product->category->name }}
                </a>
            @endif

            {{-- Product Name --}}
            <h3 class="text-[13px] font-medium text-[#222] mb-1.5 leading-snug min-h-9">
                <a href="{{ route('product.show', $product) }}" class="line-clamp-2 hover:text-[#6F9CA2] transition-colors">
                    {{ $product->name }}
                </a>
            </h3>

            {{-- Rating Badge --}}
            @if($rating > 0)
                <div class="flex items-center gap-1 mb-1.5">
                    <span class="inline-flex items-center gap-0.5 bg-[#C1539C] text-white text-[10px] font-bold px-1.5 py-0.5 rounded-sm">
                        {{ number_format($rating, 1) }}
                        <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </span>
                    <span class="text-[10px] text-neutral-600">({{ $reviewCount }})</span>
                </div>
            @endif

            {{-- Price Row --}}
            <div class="flex items-baseline gap-1.5 mb-2">
                <span class="text-sm font-bold text-[#222]">@price($product->price)</span>
                @if($hasDiscount)
                    <span class="text-[11px] text-neutral-600 line-through">@price($product->mrp)</span>
                    <span class="text-[11px] font-semibold text-[#B06D0F]">{{ round($discount) }}% off</span>
                @endif
            </div>

            {{-- Variant pickers --}}
            @if($hasVariantOptions)
                <div class="space-y-1.5 mb-2.5">
                    @if($hasSizeOptions)
                        <div class="flex items-center gap-1 flex-wrap">
                            <span class="text-[10px] text-neutral-500 uppercase tracking-wide mr-0.5">Size</span>
                            @foreach(array_slice($cardSizes, 0, $maxInline) as $sz)
                                <button type="button"
                                        @click.stop.prevent="selectedSize = @js($sz)"
                                        :class="selectedSize === @js($sz) ? 'border-[#6F9CA2] bg-[#6F9CA2] text-white' : 'border-neutral-300 text-neutral-700 hover:border-neutral-500'"
                                        class="text-[11px] px-1.5 py-0.5 border rounded leading-none transition-colors min-w-[24px] text-center">
                                    {{ $sz }}
                                </button>
                            @endforeach
                            @if(count($cardSizes) > $maxInline)
                                <a href="{{ route('product.show', $product) }}" class="text-[10px] text-neutral-500 hover:text-[#6F9CA2]">+{{ count($cardSizes) - $maxInline }} more</a>
                            @endif
                        </div>
                    @endif
                    @if($hasColorOptions)
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="text-[10px] text-neutral-500 uppercase tracking-wide mr-0.5">Color</span>
                            @foreach(array_slice($cardColors, 0, $maxInline) as $clr)
                                @php $hex = $colorHexMap[strtolower($clr)] ?? '#cbd5e1'; @endphp
                                <button type="button"
                                        title="{{ $clr }}"
                                        @click.stop.prevent="selectedColor = @js($clr)"
                                        :class="selectedColor === @js($clr) ? 'ring-2 ring-[#6F9CA2] ring-offset-1' : ''"
                                        class="w-5 h-5 rounded-full transition-all border-0"
                                        style="background:{{ $hex }};"></button>
                            @endforeach
                            @if(count($cardColors) > $maxInline)
                                <a href="{{ route('product.show', $product) }}" class="text-[10px] text-neutral-500 hover:text-[#6F9CA2]">+{{ count($cardColors) - $maxInline }} more</a>
                            @endif
                        </div>
                    @endif
                </div>
            @endif

            {{-- Add to Cart / Notify --}}
            @if($showAddToCart)
                <div class="mt-auto pt-1">
                    @unless($outOfStock)
                        @if($hasVariantOptions)
                            <button @click.stop.prevent="addToCart()"
                                    :class="isNotifyMode ? 'border border-neutral-200 text-neutral-600 bg-white hover:bg-neutral-50' : 'text-white'"
                                    :style="isNotifyMode ? '' : (canAdd ? 'background:#6F9CA2;' : 'background:#9CA3AF;')"
                                    class="w-full py-2.5 text-[13px] font-semibold rounded-md transition-colors duration-200">
                                <span x-text="isNotifyMode ? 'Notify Me' : (canAdd ? 'Add to Bag' : 'Select options')">Add to Bag</span>
                            </button>
                        @else
                            <button @click="$store.cart.add({{ $product->id }})"
                                    class="w-full py-2.5 text-[13px] font-semibold text-white rounded-md transition-colors duration-200"
                                    style="background:#6F9CA2;"
                                    @mouseenter="$el.style.background='#5B878D'"
                                    @mouseleave="$el.style.background='#6F9CA2'">
                                Add to Bag
                            </button>
                        @endif
                    @else
                        <button @click="$dispatch('notify-stock', { productId: {{ $product->id }} })"
                                class="w-full py-2.5 text-[13px] font-medium text-neutral-600 border border-neutral-200 rounded-md hover:bg-neutral-50 transition-colors">
                            Notify Me
                        </button>
                    @endunless
                </div>
            @endif
        </div>
    </div>
@endif
