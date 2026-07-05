<x-pos.layout>
<div class="pos-container" x-data="posBilling()" @keydown.window="handleKeydown($event)" x-init="init()" x-on:keydown.escape.window="mobileCartOpen = false">

    {{-- ═══════ TOP BAR ═══════ --}}
    <div class="flex items-center justify-between px-3 sm:px-4 py-2 relative" style="background: var(--pos-sidebar); color: white; min-height: 52px;" role="banner">
        {{-- Left: Store + Terminal --}}
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full" style="background: var(--pos-success);" aria-hidden="true"></div>
                <span class="text-sm font-medium">{{ $store->name ?? 'Store' }}</span>
            </div>
            <span class="text-xs px-2 py-0.5 rounded hidden sm:inline" style="background: rgba(255,255,255,0.1);">{{ $register->name ?? 'Terminal' }}</span>
        </div>

        {{-- Mobile: Search toggle button --}}
        <button @click="mobileSearchOpen = !mobileSearchOpen" class="pos-mobile-search-btn p-2 rounded-lg" style="color: #CBD5E1;" aria-label="Toggle search">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        </button>

        {{-- Center: Search --}}
        <div class="flex-1 max-w-lg mx-4 sm:mx-6 relative pos-topbar-center" :class="{ 'pos-search-active': mobileSearchOpen }">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style="color: #CBD5E1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
            </svg>
            <input
                type="text"
                x-ref="searchInput"
                x-model="searchQuery"
                @input.debounce.300ms="searchProducts()"
                @keydown.enter.prevent="onSearchEnter()"
                @focus="showSearchResults = searchResults.length > 0"
                @click.outside="showSearchResults = false"
                placeholder="Search products by name, SKU, or barcode... (F2)"
                class="w-full pl-10 pr-10 py-2 rounded-lg text-sm focus:outline-none focus:ring-2"
                style="background: rgba(255,255,255,0.1); color: white; border: 1px solid rgba(255,255,255,0.15); --tw-ring-color: var(--pos-primary);"
            >
            <button x-show="searchQuery" @click="searchQuery = ''; searchResults = []; showSearchResults = false"
                    class="absolute right-10 top-1/2 -translate-y-1/2" aria-label="Clear search">
                <svg class="w-4 h-4" style="color: #CBD5E1;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
            {{-- Camera scan button — opens ZXing-js modal. Mirrors USB scan behaviour. --}}
            <button @click="openScanner()" class="absolute right-3 top-1/2 -translate-y-1/2 p-1 rounded transition-colors"
                    style="color: #CBD5E1;"
                    @mouseenter="$el.style.color='white'" @mouseleave="$el.style.color='#CBD5E1'"
                    aria-label="Scan with camera" title="Scan with camera">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </button>

            {{-- Search Results Dropdown — MARG-style tabular layout (D.No / Stock / Unit / MRP / Rate-A) --}}
            <div x-show="showSearchResults && searchResults.length > 0" x-transition
                 class="absolute top-full left-0 right-0 mt-1 rounded-lg shadow-2xl pos-scroll"
                 style="background: white; border: 1px solid var(--pos-border); max-height: 380px; z-index: 50;">
                {{-- Tabular header (MARG style) --}}
                <div class="grid grid-cols-12 gap-2 px-3 py-1.5 text-[10px] uppercase tracking-wider font-bold sticky top-0"
                     style="background: #1e293b; color: #cbd5e1; border-bottom: 2px solid #475569; font-family: ui-monospace, Consolas, monospace;">
                    <div class="col-span-5">Description</div>
                    <div class="col-span-2 text-right">Stock</div>
                    <div class="col-span-1 text-center">Unit</div>
                    <div class="col-span-2 text-right">M.R.P.</div>
                    <div class="col-span-2 text-right">Rate&nbsp;A</div>
                </div>
                <template x-for="(product, idx) in searchResults" :key="product.id">
                    <button @click="addSearchResult(product); showSearchResults = false; searchQuery = ''; searchResults = [];"
                            class="w-full grid grid-cols-12 gap-2 px-3 py-2 text-left transition-colors items-center marg-find-row"
                            style="border-bottom: 1px solid #F1F5F9; color: var(--pos-text); font-family: ui-monospace, Consolas, monospace;">
                        <div class="col-span-5 min-w-0">
                            <div class="text-sm font-semibold truncate">
                                <span x-text="product.article_no || product.name"></span>
                                <template x-if="product.matched_variant">
                                    <span class="ml-1 inline-block px-1.5 py-0.5 rounded text-[10px] font-bold align-middle"
                                          style="background:#6F9CA2; color:white;"
                                          x-text="(product.matched_variant.size || '') + (product.matched_variant.color ? ' · ' + product.matched_variant.color : '')"></span>
                                </template>
                            </div>
                            <div class="text-[11px]" style="color: var(--pos-text-muted);">
                                <span x-text="product.matched_variant ? product.matched_variant.sku : product.sku"></span>
                                <span x-show="product.barcode && !product.matched_variant"> · <span x-text="product.barcode"></span></span>
                            </div>
                        </div>
                        <div class="col-span-2 text-right text-sm font-bold"
                             :style="(product.stock > 0 ? 'color: #15803d' : 'color: #dc2626')"
                             x-text="product.stock"></div>
                        <div class="col-span-1 text-center text-xs" style="color: var(--pos-text-muted);" x-text="product.unit_code || 'PCS'"></div>
                        <div class="col-span-2 text-right text-sm" x-text="'₹' + (product.mrp || product.price).toFixed(2)"></div>
                        <div class="col-span-2 text-right text-sm font-bold" style="color: #1e40af;" x-text="'₹' + product.price.toFixed(2)"></div>
                    </button>
                </template>
            </div>
        </div>

        {{-- Right: Staff + Actions --}}
        <div class="flex items-center gap-2 sm:gap-3 shrink-0">
            <div class="text-right pos-topbar-staff">
                <div class="text-sm font-medium">{{ $staff->user->first_name ?? 'Staff' }}</div>
                <div class="text-xs" style="color: #CBD5E1;">{{ ucfirst($staff->role ?? 'cashier') }}</div>
            </div>
            <div class="w-px h-8 hidden sm:block" style="background: rgba(255,255,255,0.15);"></div>

            {{-- Hold Bill --}}
            <button @click="holdBill()" class="p-2 rounded-lg transition-colors" style="color: #CBD5E1;"
                    @mouseenter="$el.style.background='rgba(255,255,255,0.1)'" @mouseleave="$el.style.background='transparent'"
                    title="Hold Bill (F9)" aria-label="Hold Bill">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </button>

            {{-- Held Bills --}}
            <button @click="showHeldBills()" class="p-2 rounded-lg transition-colors relative" style="color: #CBD5E1;"
                    @mouseenter="$el.style.background='rgba(255,255,255,0.1)'" @mouseleave="$el.style.background='transparent'"
                    title="Held Bills (F10)" aria-label="Held Bills">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                </svg>
                <span x-show="heldBillsCount > 0" x-text="heldBillsCount"
                      class="absolute -top-1 -right-1 w-4 h-4 rounded-full text-xs flex items-center justify-center font-bold"
                      style="background: var(--pos-accent); color: white; font-size: 10px;"></span>
            </button>

            {{-- More Menu --}}
            <div class="relative" x-data="{ menuOpen: false }">
                <button @click="menuOpen = !menuOpen" class="p-2 rounded-lg transition-colors" style="color: #CBD5E1;"
                        @mouseenter="$el.style.background='rgba(255,255,255,0.1)'" @mouseleave="$el.style.background='transparent'"
                        aria-label="More options" :aria-expanded="menuOpen">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/>
                    </svg>
                </button>
                <div x-show="menuOpen" @click.outside="menuOpen = false" x-transition
                     class="absolute right-0 top-full mt-1 w-48 rounded-lg shadow-xl py-1"
                     style="background: white; border: 1px solid var(--pos-border); z-index: 50;">
                    <button @click="menuOpen = false; showReturnsModal = true; returnSuccess = null;" class="w-full px-4 py-2.5 text-left text-sm flex items-center gap-2" style="color: var(--pos-text);"
                            @mouseenter="$el.style.background='#F8FAFC'" @mouseleave="$el.style.background='white'">
                        <svg class="w-4 h-4" style="color: var(--pos-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                        Returns
                    </button>
                    <button @click="menuOpen = false; window.location.href = '{{ route('pos.shift.close') }}'" class="w-full px-4 py-2.5 text-left text-sm flex items-center gap-2" style="color: var(--pos-text);"
                            @mouseenter="$el.style.background='#F8FAFC'" @mouseleave="$el.style.background='white'">
                        <svg class="w-4 h-4" style="color: var(--pos-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Close Shift
                    </button>
                    @if(in_array($staff->role ?? '', ['manager', 'supervisor']))
                    <button @click="menuOpen = false; window.location.href = '{{ route('pos.reports') }}'" class="w-full px-4 py-2.5 text-left text-sm flex items-center gap-2" style="color: var(--pos-text);"
                            @mouseenter="$el.style.background='#F8FAFC'" @mouseleave="$el.style.background='white'">
                        <svg class="w-4 h-4" style="color: var(--pos-text-muted);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                        Reports
                    </button>
                    @endif
                    <div class="my-1" style="border-top: 1px solid #F1F5F9;"></div>
                    <button @click="menuOpen = false; doLogout()" class="w-full px-4 py-2.5 text-left text-sm flex items-center gap-2" style="color: var(--pos-danger);"
                            @mouseenter="$el.style.background='#FEF2F2'" @mouseleave="$el.style.background='white'">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════ MAIN CONTENT: Product Grid + Cart ═══════ --}}
    <div class="flex flex-1 overflow-hidden pos-main-flex" role="main">

        {{-- ═══════ LEFT: Product Grid (60% desktop, full on mobile) ═══════ --}}
        <div class="flex flex-col pos-products-panel" style="width: 60%; border-right: 1px solid var(--pos-border);" role="region" aria-label="Products">

            {{-- Category Tabs --}}
            <div class="flex items-center gap-1 px-4 py-2 overflow-x-auto" style="background: white; border-bottom: 1px solid var(--pos-border); min-height: 48px;"
                 x-ref="categoryTabs">
                <button @click="selectedCategory = null; loadProducts()"
                        class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors"
                        :style="!selectedCategory ? 'background: var(--pos-primary); color: white;' : 'color: var(--pos-text-muted); background: #F1F5F9;'">
                    All
                </button>
                <template x-for="cat in categories" :key="cat.id">
                    <button @click="selectedCategory = cat.id; loadProducts()"
                            class="px-4 py-1.5 rounded-full text-sm font-medium whitespace-nowrap transition-colors"
                            :style="selectedCategory === cat.id ? 'background: var(--pos-primary); color: white;' : 'color: var(--pos-text-muted); background: #F1F5F9;'">
                        <span x-text="cat.name"></span>
                        <span class="ml-1 text-xs opacity-70" x-text="'(' + cat.products_count + ')'"></span>
                    </button>
                </template>
            </div>

            {{-- Product Grid --}}
            <div class="flex-1 pos-scroll p-4" x-ref="productGrid">
                {{-- Loading --}}
                <div x-show="productsLoading" class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <svg class="w-8 h-8 mx-auto mb-2 animate-spin" style="color: var(--pos-primary);" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span class="text-sm" style="color: var(--pos-text-muted);">Loading products...</span>
                    </div>
                </div>

                {{-- Grid --}}
                <div x-show="!productsLoading" class="grid gap-2 sm:gap-3" style="grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));">
                    <template x-for="product in products" :key="product.id">
                        <button @click="addToCart(product)"
                                class="pos-card p-2 text-left transition-all hover:shadow-md active:scale-[0.97]"
                                :class="{ 'opacity-50': !product.in_stock }"
                                :disabled="!product.in_stock">
                            {{-- Image --}}
                            <div class="relative mb-2 rounded-lg overflow-hidden" style="aspect-ratio: 1; background: #F8FAFC;">
                                <img :src="product.image || '{{ asset('images/no-product-image.svg') }}'"
                                     class="w-full h-full object-cover"
                                     x-on:error="$el.src='{{ asset('images/no-product-image.svg') }}'"
                                     loading="lazy">
                                {{-- Stock badge --}}
                                <div x-show="!product.in_stock" class="absolute inset-0 flex items-center justify-center" style="background: rgba(255,255,255,0.8);">
                                    <span class="text-xs font-bold" style="color: var(--pos-danger);">OUT OF STOCK</span>
                                </div>
                                <div x-show="product.low_stock && product.in_stock" class="absolute top-1 right-1">
                                    <span class="pos-badge-stock low-stock" x-text="product.stock + ' left'"></span>
                                </div>
                                {{-- Variant indicator --}}
                                <div x-show="product.has_variants" class="absolute bottom-1 right-1">
                                    <span class="text-xs px-1.5 py-0.5 rounded font-medium" style="background: var(--pos-primary); color: white; font-size: 9px;">VARIANTS</span>
                                </div>
                            </div>
                            {{-- Info --}}
                            <div class="text-xs font-medium truncate" style="color: var(--pos-text);" x-text="product.name"></div>
                            <div class="flex items-baseline gap-1 mt-0.5">
                                <span class="text-sm font-bold pos-mono" style="color: var(--pos-primary);" x-text="'₹' + product.price.toFixed(0)"></span>
                                <span x-show="product.mrp > product.price" class="text-xs pos-mono line-through" style="color: var(--pos-text-muted);" x-text="'₹' + product.mrp.toFixed(0)"></span>
                            </div>
                        </button>
                    </template>
                </div>

                {{-- Empty state --}}
                <div x-show="!productsLoading && products.length === 0" class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto mb-2" style="color: #94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                        <span class="text-sm" style="color: var(--pos-text-muted);">No products found</span>
                    </div>
                </div>

                {{-- Pagination --}}
                <div x-show="pagination.last_page > 1" class="flex items-center justify-center gap-2 mt-4 pb-2">
                    <button @click="prevPage()" :disabled="pagination.current_page <= 1"
                            class="px-3 py-1.5 rounded text-sm" style="border: 1px solid var(--pos-border);"
                            :style="pagination.current_page <= 1 ? 'opacity: 0.4; cursor: not-allowed;' : 'cursor: pointer;'">
                        ← Prev
                    </button>
                    <span class="text-xs pos-mono" style="color: var(--pos-text-muted);"
                          x-text="pagination.current_page + ' / ' + pagination.last_page"></span>
                    <button @click="nextPage()" :disabled="pagination.current_page >= pagination.last_page"
                            class="px-3 py-1.5 rounded text-sm" style="border: 1px solid var(--pos-border);"
                            :style="pagination.current_page >= pagination.last_page ? 'opacity: 0.4; cursor: not-allowed;' : 'cursor: pointer;'">
                        Next →
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════ RIGHT: Cart (40% desktop, slide-up sheet on mobile) ═══════ --}}
        <div class="flex flex-col pos-cart-panel" :class="{ 'pos-cart-open': mobileCartOpen }" style="width: 40%; background: white;" role="region" aria-label="Shopping cart">

            {{-- Mobile: Cart drag handle + close --}}
            <div class="pos-mobile-cart-close flex items-center justify-between px-4 py-2" style="border-bottom: 1px solid var(--pos-border);">
                <button @click="mobileCartOpen = false" class="pos-btn pos-btn-ghost text-sm gap-1 px-3 py-2" aria-label="Close cart">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    Close Cart
                </button>
            </div>

            {{-- Cart Header --}}
            <div class="flex items-center justify-between px-4 py-3" style="border-bottom: 1px solid var(--pos-border);">
                <div class="flex items-center gap-2">
                    <h2 class="text-base font-semibold" style="color: var(--pos-text);">Cart</h2>
                    <span x-show="cart.items.length > 0" class="px-2 py-0.5 rounded-full text-xs font-bold"
                          style="background: var(--pos-primary); color: white;" x-text="cart.items.length"></span>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Customer --}}
                    <button @click="showCustomerModal = true"
                            class="flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-medium transition-colors"
                            :style="cart.customer ? 'background: #F0FDFA; color: var(--pos-primary); border: 1px solid var(--pos-primary);' : 'background: #F1F5F9; color: var(--pos-text-muted); border: 1px solid var(--pos-border);'">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                        <span x-text="cart.customer ? cart.customer.name : 'Walk-in'"></span>
                    </button>
                    {{-- Clear Cart --}}
                    <button x-show="cart.items.length > 0" @click="clearCart()"
                            class="p-1.5 rounded-lg transition-colors" style="color: var(--pos-danger);"
                            title="Clear Cart" aria-label="Clear cart">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Cart Items --}}
            <div class="flex-1 pos-scroll" x-ref="cartItems">
                {{-- Empty Cart --}}
                <div x-show="cart.items.length === 0" class="flex items-center justify-center h-full">
                    <div class="text-center px-8">
                        <svg class="w-16 h-16 mx-auto mb-3" style="color: #94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/>
                        </svg>
                        <p class="text-sm font-medium" style="color: var(--pos-text-muted);">Cart is empty</p>
                        <p class="text-xs mt-1" style="color: var(--pos-text-muted);">Scan a barcode or click a product to add</p>
                    </div>
                </div>

                {{-- Cart Item List --}}
                <template x-for="(item, index) in cart.items" :key="item.cart_item_id || index">
                    <div class="flex items-start gap-3 px-4 py-3 transition-colors"
                         :class="{ 'pos-item-added': item._justAdded }"
                         @animationend="item._justAdded = false"
                         style="border-bottom: 1px solid #F8FAFC;">
                        {{-- Product info --}}
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate" style="color: var(--pos-text);" x-text="item.product_name"></div>
                            <div x-show="item.variant_name" class="text-xs" style="color: var(--pos-text-muted);" x-text="item.variant_name"></div>
                            <div class="text-xs pos-mono mt-0.5" style="color: var(--pos-text-muted);" x-text="'₹' + item.price.toFixed(2) + ' × ' + item.quantity"></div>
                            {{-- Discount per item --}}
                            <div x-show="item.discount > 0" class="text-xs mt-0.5" style="color: var(--pos-success);">
                                -₹<span x-text="item.discount.toFixed(2)" class="pos-mono"></span> discount
                            </div>
                        </div>
                        {{-- Quantity Controls --}}
                        <div class="flex items-center gap-1">
                            <button @click="updateQuantity(item, item.quantity - 1)"
                                    class="w-9 h-9 sm:w-7 sm:h-7 rounded flex items-center justify-center text-sm font-bold transition-colors"
                                    style="border: 1px solid var(--pos-border); color: var(--pos-text-muted);"
                                    aria-label="Decrease quantity">−</button>
                            <input type="number" :value="item.quantity"
                                   @change="updateQuantity(item, parseInt($el.value) || 1)"
                                   class="w-10 h-9 sm:h-7 text-center text-sm font-medium pos-mono rounded border focus:outline-none focus:ring-1"
                                   style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                                   min="1" :aria-label="'Quantity for ' + item.product_name">
                            <button @click="updateQuantity(item, item.quantity + 1)"
                                    class="w-9 h-9 sm:w-7 sm:h-7 rounded flex items-center justify-center text-sm font-bold transition-colors"
                                    style="border: 1px solid var(--pos-border); color: var(--pos-text);"
                                    aria-label="Increase quantity">+</button>
                        </div>
                        {{-- Line Total + Remove --}}
                        <div class="text-right min-w-[70px]">
                            <div class="text-sm font-semibold pos-mono" x-text="'₹' + item.total.toFixed(2)"></div>
                            <button @click="removeItem(item)" class="text-xs mt-1" style="color: var(--pos-danger);">Remove</button>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Cart Footer --}}
            <div style="border-top: 2px solid var(--pos-border);">
                {{-- Coupon / Discount Row --}}
                <div class="px-4 py-2" style="border-bottom: 1px solid #F1F5F9;">
                    <template x-if="!cart.coupon">
                        <div>
                            <div class="flex items-center gap-2 w-full">
                                <input type="text" x-model="couponCode" placeholder="Manual coupon code"
                                       class="flex-1 px-3 py-1.5 rounded text-sm border focus:outline-none focus:ring-1"
                                       style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                                       @keydown.enter="applyCoupon()">
                                <button @click="applyCoupon()" :disabled="!couponCode.trim()"
                                        class="px-3 py-1.5 rounded text-sm font-medium transition-colors"
                                        style="background: var(--pos-primary); color: white;"
                                        :style="!couponCode.trim() ? 'opacity: 0.5;' : ''">Apply</button>
                            </div>
                            {{-- Helper: avoids the FLAT10/SAVE100 confusion (those are auto-schemes, not coupons) --}}
                            <div x-show="(cart.applied_schemes || []).length > 0" class="text-[10px] mt-1" style="color: var(--pos-text-muted);">
                                Auto-schemes already applied above. Use this field only for manual promo codes.
                            </div>
                            <div x-show="couponError" x-text="couponError" x-transition class="text-xs mt-1" style="color: var(--pos-danger);"></div>
                        </div>
                    </template>
                    <template x-if="cart.coupon">
                        <div class="flex items-center justify-between w-full">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-xs font-bold pos-mono" style="background: #DCFCE7; color: #166534;" x-text="cart.coupon.code"></span>
                                <span class="text-xs" style="color: var(--pos-success);" x-text="'-₹' + cart.coupon.discount.toFixed(2)"></span>
                            </div>
                            <button @click="removeCoupon()" class="text-xs" style="color: var(--pos-danger);">Remove</button>
                        </div>
                    </template>
                </div>

                {{-- Applied auto-schemes (MARG buy-X-get-Y / flat / bundle) --}}
                <div x-show="(cart.applied_schemes || []).length > 0"
                     class="px-4 pt-2 pb-1 space-y-1">
                    <template x-for="s in (cart.applied_schemes || [])" :key="s.code">
                        <div class="flex items-center justify-between text-xs px-2 py-1 rounded"
                             style="background: #FEF3C7; border: 1px solid #FCD34D;">
                            <div class="flex-1 min-w-0">
                                <span class="font-bold" style="color: #92400E;" x-text="s.code"></span>
                                <span style="color: #92400E;" x-text="' · ' + (s.description || s.name)"></span>
                            </div>
                            <span class="font-bold pos-mono ml-2" style="color: #92400E;"
                                  x-text="(s.discount > 0) ? '-₹' + s.discount.toFixed(2) : (s.savings > 0 ? '🎁 ₹' + s.savings.toFixed(2) : '✓')"></span>
                        </div>
                    </template>
                </div>

                {{-- Totals --}}
                <div class="px-4 py-3 space-y-1.5">
                    <div class="flex justify-between text-sm" style="color: var(--pos-text-muted);">
                        <span>Subtotal</span>
                        <span class="pos-mono" x-text="'₹' + cart.subtotal.toFixed(2)"></span>
                    </div>
                    <div x-show="cart.discount > 0" class="flex justify-between text-sm" style="color: var(--pos-success);">
                        <span>Discount</span>
                        <span class="pos-mono" x-text="'-₹' + cart.discount.toFixed(2)"></span>
                    </div>
                    <div class="flex justify-between text-lg font-bold pt-1.5" style="border-top: 1px solid var(--pos-border); color: var(--pos-text);">
                        <span>Total</span>
                        <span class="pos-mono" x-text="'₹' + cart.total.toFixed(2)"></span>
                    </div>
                    <div x-show="cart.tax > 0" class="text-[10px] text-right" style="color: var(--pos-text-muted);">
                        Incl. GST <span class="pos-mono" x-text="'₹' + cart.tax.toFixed(2)"></span>
                    </div>
                </div>

                {{-- Payment Buttons --}}
                <div class="px-4 pb-4 grid grid-cols-3 gap-2">
                    <button @click="startPayment('cash')"
                            :disabled="cart.items.length === 0"
                            class="pos-btn flex-col gap-0.5 py-3 text-sm font-medium rounded-lg"
                            :style="cart.items.length === 0 ? 'opacity: 0.4; background: #F1F5F9; color: var(--pos-text-muted);' : 'background: #DCFCE7; color: #166534;'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                        Cash <span class="hidden sm:inline">(F5)</span>
                    </button>
                    <button @click="startPayment('card')"
                            :disabled="cart.items.length === 0"
                            class="pos-btn flex-col gap-0.5 py-3 text-sm font-medium rounded-lg"
                            :style="cart.items.length === 0 ? 'opacity: 0.4; background: #F1F5F9; color: var(--pos-text-muted);' : 'background: #DBEAFE; color: #1E40AF;'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                        Card <span class="hidden sm:inline">(F6)</span>
                    </button>
                    <button @click="startPayment('upi')"
                            :disabled="cart.items.length === 0"
                            class="pos-btn flex-col gap-0.5 py-3 text-sm font-medium rounded-lg"
                            :style="cart.items.length === 0 ? 'opacity: 0.4; background: #F1F5F9; color: var(--pos-text-muted);' : 'background: #F3E8FF; color: #6B21A8;'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        UPI <span class="hidden sm:inline">(F7)</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════ MOBILE CART FAB ═══════ --}}
    <button @click="mobileCartOpen = true" class="pos-cart-fab" x-show="!mobileCartOpen" aria-label="Open cart">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 100 4 2 2 0 000-4z"/></svg>
        <span x-show="cart.items.length > 0" x-text="cart.items.length"
              class="absolute -top-1 -right-1 w-5 h-5 rounded-full text-xs flex items-center justify-center font-bold"
              style="background: #EF4444; color: white;"></span>
    </button>

    {{-- ═══════ CAMERA SCANNER MODAL — ZXing-js mobile/USB-camera barcode scan ═══════ --}}
    {{-- Camera feed + decoder. On detect: closes + drops barcode into the existing scanBarcode pipeline. --}}
    <div x-show="scannerOpen" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4"
         style="background: rgba(0,0,0,0.7); z-index: 200;"
         @click.self="closeScanner()" role="dialog" aria-modal="true" aria-label="Camera barcode scanner">
        <div class="pos-card pos-fade-in w-full max-w-md" @click.stop>
            <div class="px-4 py-2.5 flex items-center justify-between" style="background: #1e293b; color: white;">
                <div class="flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-sm font-semibold">Scan Barcode</span>
                </div>
                <button @click="closeScanner()" class="text-sm" style="color: #cbd5e1;" aria-label="Close scanner">✕</button>
            </div>
            <div class="p-4">
                <div class="relative" style="background:#000; border-radius:6px; overflow:hidden;">
                    <video x-ref="scannerVideo" autoplay playsinline muted
                           style="width:100%; height:auto; max-height:60vh; display:block;"></video>
                    {{-- Aiming reticle --}}
                    <div x-show="!scannerError" class="absolute inset-0 pointer-events-none flex items-center justify-center">
                        <div style="width:75%; height:30%; border:2px solid rgba(255,255,255,0.4); border-radius:6px; box-shadow:0 0 0 9999px rgba(0,0,0,0.25);"></div>
                    </div>
                </div>
                <div x-show="scannerError" x-text="scannerError" x-transition
                     class="mt-3 p-2 rounded text-xs"
                     style="background:#fee2e2; color:#991b1b;"></div>
                <p class="mt-3 text-xs text-center" style="color: var(--pos-text-muted);">
                    Point camera at barcode. EAN-13, Code 128, UPC, QR all supported.
                </p>
                <div class="mt-3 flex gap-2">
                    <button @click="closeScanner()" class="pos-btn pos-btn-ghost flex-1 text-sm">Cancel</button>
                    <button x-show="scannerError" @click="openScanner()" class="pos-btn pos-btn-primary flex-1 text-sm">Retry</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════ VARIANT PICKER MODAL — MARG-style size × color matrix ═══════ --}}
    <div x-show="showVariantPicker" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.55); z-index: 100;" @click.self="showVariantPicker = false" role="dialog" aria-modal="true" aria-label="Select variant size and color">
        <div class="pos-card pos-fade-in w-full max-w-2xl" @click.stop style="font-family: ui-monospace, Consolas, monospace;">
            {{-- MARG-style header --}}
            <div class="px-4 py-2.5 flex items-center justify-between" style="background: #1e293b; color: white; border-bottom: 2px solid #475569;">
                <div>
                    <div class="text-xs uppercase tracking-wider" style="color: #cbd5e1;">Select Variant — Size × Color</div>
                    <div class="text-sm font-semibold" x-text="(selectedProductVariants[0]?.product_name) || 'Product'"></div>
                </div>
                <button @click="showVariantPicker = false" class="text-sm" style="color: #cbd5e1;">✕ Close</button>
            </div>

            {{-- Tabular variant grid --}}
            <div class="p-3" style="background: white;">
                <div class="grid grid-cols-12 gap-2 px-2 py-1.5 text-[10px] uppercase tracking-wider font-bold"
                     style="background: #f1f5f9; color: #475569; border-bottom: 1px solid #cbd5e1;">
                    <div class="col-span-3">Size</div>
                    <div class="col-span-3">Color</div>
                    <div class="col-span-3">SKU</div>
                    <div class="col-span-1 text-right">Stock</div>
                    <div class="col-span-2 text-right">Rate</div>
                </div>
                <div class="max-h-72 pos-scroll">
                    <template x-for="variant in selectedProductVariants" :key="variant.id">
                        <button @click="addVariantToCart(variant)" :disabled="!variant.in_stock"
                                class="w-full grid grid-cols-12 gap-2 px-2 py-2 text-left items-center marg-find-row"
                                :style="variant.in_stock ? '' : 'opacity: 0.45; cursor: not-allowed;'"
                                style="border-bottom: 1px solid #f1f5f9;">
                            <div class="col-span-3 text-sm font-semibold" x-text="variant.size_name || variant.size || '—'"></div>
                            <div class="col-span-3 text-sm flex items-center gap-2">
                                <span x-show="variant.color_hex" class="inline-block w-3 h-3 rounded-full border" :style="'background:' + variant.color_hex"></span>
                                <span x-text="variant.color_name || variant.color || '—'"></span>
                            </div>
                            <div class="col-span-3 text-xs" x-text="variant.sku" style="color: #64748b;"></div>
                            <div class="col-span-1 text-right text-sm font-bold"
                                 :style="variant.in_stock ? 'color: #15803d' : 'color: #dc2626'"
                                 x-text="variant.stock"></div>
                            <div class="col-span-2 text-right text-sm font-bold" style="color: #1e40af;" x-text="'₹' + variant.price.toFixed(2)"></div>
                        </button>
                    </template>
                </div>
            </div>

            <div class="px-4 py-2.5 flex items-center justify-between" style="background: #f8fafc; border-top: 1px solid #e2e8f0;">
                <span class="text-xs" style="color: #64748b;">Click any row to add to cart · <kbd style="background:#e2e8f0;padding:0 4px;border-radius:2px;">Esc</kbd> to close</span>
                <button @click="showVariantPicker = false" class="text-xs px-3 py-1 rounded" style="background:#475569;color:white;">Cancel</button>
            </div>
        </div>
    </div>

    {{-- ═══════ PAYMENT MODAL ═══════ --}}
    <div x-show="showPaymentModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4); z-index: 100;" @click.self="showPaymentModal = false" role="dialog" aria-modal="true" aria-label="Payment">
        <div class="pos-card p-5 sm:p-6 w-full max-w-md pos-fade-in max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-semibold" style="color: var(--pos-text);">Payment</h3>
                <button @click="showPaymentModal = false" class="p-1 rounded" style="color: var(--pos-text-muted);" aria-label="Close payment dialog">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Total --}}
            <div class="text-center mb-5 p-4 rounded-lg" style="background: #F8FAFC;">
                <div class="text-sm" style="color: var(--pos-text-muted);">Amount Due</div>
                <div class="text-3xl font-bold pos-mono" style="color: var(--pos-text);" x-text="'₹' + cart.total.toFixed(2)"></div>
            </div>

            {{-- Payment method tabs (MARG: Cash · Card · UPI · Wallet · Credit · Split) --}}
            <div class="grid grid-cols-3 gap-1 mb-4 p-1 rounded-lg" style="background: #F1F5F9;">
                <button @click="paymentMethod = 'cash'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'cash' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'">Cash</button>
                <button @click="paymentMethod = 'card'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'card' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'">Card</button>
                <button @click="paymentMethod = 'upi'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'upi' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'">UPI</button>
                <button @click="paymentMethod = 'wallet'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'wallet' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'">Wallet</button>
                <button @click="paymentMethod = 'credit'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'credit' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'"
                        :disabled="!cart.customer">
                    Credit<span x-show="!cart.customer" class="text-[9px] block" style="color:#dc2626;">attach customer</span>
                </button>
                <button @click="paymentMethod = 'split'" class="py-2 rounded-md text-sm font-medium transition-colors"
                        :style="paymentMethod === 'split' ? 'background: white; color: var(--pos-text); box-shadow: 0 1px 3px rgba(0,0,0,0.1);' : 'color: var(--pos-text-muted);'">Split</button>
            </div>

            {{-- Wallet payment --}}
            <template x-if="paymentMethod === 'wallet'">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--pos-text-muted);">Wallet Provider</label>
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <template x-for="prov in [{c:'paytm',n:'Paytm'},{c:'phonepe',n:'PhonePe'},{c:'gpay',n:'GPay'},{c:'amazon_pay',n:'Amazon Pay'},{c:'mobikwik',n:'MobiKwik'},{c:'other',n:'Other'}]" :key="prov.c">
                            <button @click="walletProvider = prov.c" class="py-2 rounded text-xs font-medium border"
                                    :style="walletProvider === prov.c ? 'background: var(--pos-primary); color: white; border-color: var(--pos-primary);' : 'border-color: var(--pos-border); color: var(--pos-text);'"
                                    x-text="prov.n"></button>
                        </template>
                    </div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--pos-text-muted);">Transaction Reference</label>
                    <input type="text" x-model="paymentRef"
                           class="w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2"
                           style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                           placeholder="Wallet txn ID"
                           @keydown.enter="completeSale()">
                </div>
            </template>

            {{-- Credit (charge to customer account) --}}
            <template x-if="paymentMethod === 'credit'">
                <div class="p-4 rounded-lg" style="background: #FEF3C7; border: 1px solid #FBBF24;">
                    <div class="text-sm font-semibold mb-1" style="color: #78350F;">Charge to Customer Account</div>
                    <div class="text-xs mb-3" style="color: #92400E;">
                        Bill amount of <strong x-text="'₹' + cart.total.toFixed(2)"></strong> will be added to
                        <strong x-text="cart.customer ? cart.customer.name : '(no customer)'"></strong>'s outstanding balance.
                    </div>
                    <div x-show="cart.customer && cart.customer.party_id" class="text-xs" style="color: #78350F;">
                        Server will check the credit limit before completing. If exceeded, the sale is blocked.
                    </div>
                </div>
            </template>

            {{-- Cash payment --}}
            <template x-if="paymentMethod === 'cash'">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--pos-text-muted);">Cash Received</label>
                    <input type="text" x-model="cashReceived" x-ref="cashInput"
                           class="w-full px-4 py-3 rounded-lg border text-xl pos-mono text-right font-medium focus:outline-none focus:ring-2"
                           style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                           inputmode="decimal" @keydown.enter="completeSale()">
                    {{-- Quick amounts --}}
                    <div class="flex gap-2 mt-3">
                        <button @click="cashReceived = cart.total.toFixed(2)" class="flex-1 py-2 rounded text-sm font-medium border transition-colors"
                                style="border-color: var(--pos-border);">Exact</button>
                        <button @click="cashReceived = (Math.ceil(cart.total / 100) * 100).toFixed(2)" class="flex-1 py-2 rounded text-sm font-medium border transition-colors"
                                style="border-color: var(--pos-border);"
                                x-text="'₹' + (Math.ceil(cart.total / 100) * 100)"></button>
                        <button @click="cashReceived = (Math.ceil(cart.total / 500) * 500).toFixed(2)" class="flex-1 py-2 rounded text-sm font-medium border transition-colors"
                                style="border-color: var(--pos-border);"
                                x-text="'₹' + (Math.ceil(cart.total / 500) * 500)"></button>
                    </div>
                    {{-- Change --}}
                    <div x-show="parseFloat(cashReceived) >= cart.total" class="mt-3 p-3 rounded-lg text-center" style="background: #DCFCE7;">
                        <div class="text-sm" style="color: #166534;">Change Due</div>
                        <div class="text-2xl font-bold pos-mono" style="color: #166534;" x-text="'₹' + (parseFloat(cashReceived) - cart.total).toFixed(2)"></div>
                    </div>
                </div>
            </template>

            {{-- Card / UPI payment --}}
            <template x-if="paymentMethod === 'card' || paymentMethod === 'upi'">
                <div>
                    <label class="block text-sm font-medium mb-2" style="color: var(--pos-text-muted);">
                        Reference / Transaction ID <span class="font-normal">(optional)</span>
                    </label>
                    <input type="text" x-model="paymentRef"
                           class="w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2"
                           style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                           :placeholder="paymentMethod === 'card' ? 'Last 4 digits / approval code' : 'UPI transaction ID'"
                           @keydown.enter="completeSale()">
                </div>
            </template>

            {{-- Split payment --}}
            <template x-if="paymentMethod === 'split'">
                <div class="space-y-3">
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--pos-text-muted);">Cash</label>
                        <input type="text" x-model="splitCash" inputmode="decimal"
                               class="w-full px-3 py-2 rounded border text-sm pos-mono focus:outline-none focus:ring-1"
                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--pos-text-muted);">Card</label>
                        <input type="text" x-model="splitCard" inputmode="decimal"
                               class="w-full px-3 py-2 rounded border text-sm pos-mono focus:outline-none focus:ring-1"
                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                    </div>
                    <div>
                        <label class="block text-xs font-medium mb-1" style="color: var(--pos-text-muted);">UPI</label>
                        <input type="text" x-model="splitUpi" inputmode="decimal"
                               class="w-full px-3 py-2 rounded border text-sm pos-mono focus:outline-none focus:ring-1"
                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                    </div>
                    <div class="flex justify-between text-sm p-2 rounded" style="background: #F8FAFC;">
                        <span style="color: var(--pos-text-muted);">Remaining</span>
                        <span class="pos-mono font-medium"
                              :style="splitRemaining() > 0 ? 'color: var(--pos-danger);' : 'color: var(--pos-success);'"
                              x-text="'₹' + splitRemaining().toFixed(2)"></span>
                    </div>
                </div>
            </template>

            {{-- Credit Note Redemption --}}
            <div class="mt-4 p-3 rounded-lg" style="border: 1px dashed var(--pos-border);">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-medium" style="color: var(--pos-text-muted);">Apply Credit Note</span>
                    <template x-if="creditNote">
                        <button @click="removeCreditNote()" class="text-xs" style="color: var(--pos-danger);">Remove</button>
                    </template>
                </div>
                <template x-if="!creditNote">
                    <div class="flex gap-2">
                        <input type="text" x-model="creditNoteCode" placeholder="Credit note code..."
                               class="flex-1 px-3 py-1.5 rounded border text-sm pos-mono focus:outline-none focus:ring-1"
                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);"
                               @keydown.enter="validateCreditNote()">
                        <button @click="validateCreditNote()" :disabled="!creditNoteCode.trim()"
                                class="px-3 py-1.5 rounded text-xs font-medium"
                                style="background: var(--pos-primary); color: white;"
                                :style="!creditNoteCode.trim() ? 'opacity: 0.5;' : ''">Verify</button>
                    </div>
                </template>
                <template x-if="creditNote">
                    <div class="p-2 rounded" style="background: #DCFCE7;">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold pos-mono" style="color: #166534;" x-text="creditNote.number"></span>
                            <span class="text-xs" style="color: #166534;">Balance: ₹<span x-text="creditNote.remaining.toFixed(2)" class="pos-mono"></span></span>
                        </div>
                        <div class="flex items-center justify-between mt-1">
                            <span class="text-xs" style="color: #166534;">Applied:</span>
                            <span class="text-sm font-bold pos-mono" style="color: #166534;" x-text="'-₹' + creditNoteApplied.toFixed(2)"></span>
                        </div>
                        <div x-show="amountAfterCreditNote() > 0" class="text-xs mt-1" style="color: #166534;">
                            Remaining to pay: ₹<span x-text="amountAfterCreditNote().toFixed(2)" class="pos-mono font-medium"></span>
                        </div>
                    </div>
                </template>

                {{-- Walk-in exchange credit (session-bound, no credit_notes row) --}}
                <template x-if="walkinExchange">
                    <div class="p-2 mt-2 rounded" style="background: #FEF3C7; border: 1px solid #FCD34D;">
                        <div class="flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold" style="color: #92400E;">Walk-in Exchange Credit</span>
                                <span class="text-xs ml-2 pos-mono" style="color: #92400E;" x-text="walkinExchange.return_number"></span>
                            </div>
                            <span class="text-sm font-bold pos-mono" style="color: #92400E;" x-text="'-₹' + walkinExchange.amount.toFixed(2)"></span>
                        </div>
                        <button @click="walkinExchange = null" class="text-xs mt-1" style="color: var(--pos-danger);">Remove</button>
                    </div>
                </template>
            </div>

            {{-- Error --}}
            <p x-show="paymentError" x-text="paymentError" x-transition class="text-sm mt-3" style="color: var(--pos-danger);"></p>

            {{-- Complete Sale Button --}}
            <button @click="completeSale()" :disabled="saleLoading"
                    class="pos-btn pos-btn-success w-full mt-5 text-base py-3.5 gap-2" style="font-size: 15px;">
                <svg x-show="!saleLoading" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
                <span x-show="!saleLoading">Complete Sale</span>
                <span x-show="saleLoading">Processing...</span>
            </button>
        </div>
    </div>

    {{-- ═══════ SALE SUCCESS MODAL ═══════ --}}
    <div x-show="showSuccessModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5); z-index: 100;" role="dialog" aria-modal="true" aria-label="Sale complete">
        <div class="pos-card p-6 sm:p-8 w-full max-w-sm text-center pos-fade-in">
            <div class="w-20 h-20 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: #DCFCE7; animation: pos-success-bounce 0.5s ease;">
                <svg class="w-10 h-10" style="color: var(--pos-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>
            <h3 class="text-xl font-bold mb-1" style="color: var(--pos-text);">Sale Complete!</h3>
            <p class="text-sm mb-1" style="color: var(--pos-text-muted);">Bill #<span x-text="lastSale.sale_number" class="pos-mono font-medium"></span></p>
            <p x-show="lastSale.change > 0" class="text-lg font-bold pos-mono mb-4" style="color: var(--pos-success);">
                Change: ₹<span x-text="lastSale.change.toFixed(2)"></span>
            </p>
            <div class="flex gap-2 mt-5">
                <button @click="printReceipt()" class="flex-1 pos-btn pos-btn-ghost text-sm gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                    Thermal
                </button>
                <button @click="newSale()" class="flex-1 pos-btn pos-btn-primary text-sm gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                    New Sale
                </button>
            </div>

            {{-- MARG-style A4 invoice format selector (Retail / Wholesale / Proforma / Tax) --}}
            <div class="mt-3 pt-3" style="border-top: 1px solid var(--pos-border);">
                <div class="text-xs mb-2" style="color: var(--pos-text-muted);">A4 Invoice Format</div>
                <div class="grid grid-cols-4 gap-1.5">
                    <button @click="printInvoice('retail')"    class="pos-btn pos-btn-ghost text-xs py-1.5" title="B2C simplified (Retail)">Retail</button>
                    <button @click="printInvoice('wholesale')" class="pos-btn pos-btn-ghost text-xs py-1.5" title="B2B with MRP/Tier">Wholesale</button>
                    <button @click="printInvoice('proforma')"  class="pos-btn pos-btn-ghost text-xs py-1.5" title="Quotation, NOT a tax invoice">Proforma</button>
                    <button @click="printInvoice('tax')"       class="pos-btn pos-btn-ghost text-xs py-1.5" title="Full GST tax invoice with HSN">Tax</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ═══════ CUSTOMER SEARCH MODAL ═══════ --}}
    <div x-show="showCustomerModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4); z-index: 100;" @click.self="showCustomerModal = false" role="dialog" aria-modal="true" aria-label="Customer search">
        <div class="pos-card p-5 sm:p-6 w-full max-w-md pos-fade-in max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold" style="color: var(--pos-text);">Customer</h3>
                <button @click="showCustomerModal = false" class="p-1 rounded" style="color: var(--pos-text-muted);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <input type="text" x-model="customerSearch" x-ref="customerSearchInput"
                   @input.debounce.300ms="searchCustomers()"
                   placeholder="Search by name, phone, or email..."
                   class="w-full px-4 py-3 rounded-lg border text-sm focus:outline-none focus:ring-2 mb-3"
                   style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">

            {{-- Customer Results --}}
            <div class="space-y-1 max-h-48 pos-scroll">
                <template x-for="c in customerResults" :key="c.id">
                    <button @click="selectCustomer(c)" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-left transition-colors"
                            style="border: 1px solid transparent;"
                            @mouseenter="$el.style.background='#F8FAFC'" @mouseleave="$el.style.background='transparent'">
                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold" style="background: var(--pos-primary); color: white;"
                             x-text="(c.name || '?')[0].toUpperCase()"></div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-medium truncate" x-text="c.name"></div>
                            <div class="text-xs" style="color: var(--pos-text-muted);" x-text="c.phone || c.email"></div>
                        </div>
                    </button>
                </template>
            </div>

            {{-- Walk-in button --}}
            <div class="flex gap-2 mt-3" style="border-top: 1px solid #F1F5F9; padding-top: 12px;">
                <button @click="selectCustomer(null)" class="flex-1 pos-btn pos-btn-ghost text-sm">Walk-in Customer</button>
                <button @click="showNewCustomerForm = !showNewCustomerForm" class="flex-1 pos-btn pos-btn-primary text-sm">+ New Customer</button>
            </div>

            {{-- New Customer Form --}}
            <div x-show="showNewCustomerForm" x-transition class="mt-3 pt-3 space-y-2" style="border-top: 1px solid #F1F5F9;">
                <input type="text" x-model="newCustomer.name" placeholder="Name *"
                       class="w-full px-3 py-2 rounded border text-sm focus:outline-none focus:ring-1"
                       style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                <input type="tel" x-model="newCustomer.phone" placeholder="Phone * (10 digits)"
                       maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                       @input="newCustomer.phone = newCustomer.phone.replace(/[^0-9]/g, '').slice(0, 10)"
                       class="w-full px-3 py-2 rounded border text-sm focus:outline-none focus:ring-1"
                       style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                <input type="email" x-model="newCustomer.email" placeholder="Email (optional)"
                       class="w-full px-3 py-2 rounded border text-sm focus:outline-none focus:ring-1"
                       style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                <button @click="createCustomer()" :disabled="!newCustomer.name || newCustomer.phone.length !== 10"
                        class="w-full pos-btn pos-btn-primary text-sm py-2.5"
                        :style="(!newCustomer.name || newCustomer.phone.length !== 10) ? 'opacity: 0.5;' : ''">Save Customer</button>
            </div>
        </div>
    </div>

    {{-- ═══════ HELD BILLS MODAL ═══════ --}}
    <div x-show="showHeldBillsModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.4); z-index: 100;" @click.self="showHeldBillsModal = false" role="dialog" aria-modal="true" aria-label="Held bills">
        <div class="pos-card p-5 sm:p-6 w-full max-w-lg pos-fade-in max-h-[90vh] overflow-y-auto" @click.stop>
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base font-semibold" style="color: var(--pos-text);">Held Bills</h3>
                <button @click="showHeldBillsModal = false" class="p-1 rounded" style="color: var(--pos-text-muted);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div x-show="heldBills.length === 0" class="text-center py-8">
                <p class="text-sm" style="color: var(--pos-text-muted);">No held bills</p>
            </div>
            <div class="space-y-2 max-h-80 pos-scroll">
                <template x-for="bill in heldBills" :key="bill.id">
                    <div class="flex items-center justify-between p-3 rounded-lg" style="border: 1px solid var(--pos-border);">
                        <div>
                            <div class="text-sm font-medium" x-text="bill.reference || 'Bill #' + bill.id"></div>
                            <div class="text-xs" style="color: var(--pos-text-muted);">
                                <span x-text="bill.items_count + ' items'"></span> ·
                                <span x-text="'₹' + parseFloat(bill.total).toFixed(2)" class="pos-mono"></span> ·
                                <span x-text="bill.created_at_human"></span>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <button @click="resumeBill(bill)" class="pos-btn pos-btn-primary text-xs px-3 py-1.5">Resume</button>
                            <button @click="deleteHeldBill(bill)" class="pos-btn pos-btn-ghost text-xs px-2 py-1.5" style="color: var(--pos-danger);">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- ═══════ RETURNS MODAL ═══════ --}}
    <div x-show="showReturnsModal" x-transition.opacity class="fixed inset-0 flex items-center justify-center p-4" style="background: rgba(0,0,0,0.5); z-index: 100;" @click.self="showReturnsModal = false" role="dialog" aria-modal="true" aria-label="Process return">
        <div class="pos-card w-full max-w-2xl pos-fade-in flex flex-col" style="max-height: 90vh;" @click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4" style="border-bottom: 1px solid var(--pos-border);">
                <h3 class="text-base font-semibold" style="color: var(--pos-text);">Process Return</h3>
                <button @click="showReturnsModal = false; returnSuccess = null;" class="p-1 rounded" style="color: var(--pos-text-muted);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Success State --}}
            <template x-if="returnSuccess">
                <div class="p-8 text-center">
                    <div class="w-16 h-16 rounded-full mx-auto mb-4 flex items-center justify-center" style="background: #DCFCE7;">
                        <svg class="w-8 h-8" style="color: var(--pos-success);" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                    <h4 class="text-lg font-bold mb-1" style="color: var(--pos-text);" x-text="returnSuccess.is_exchange ? 'Exchange Started' : 'Return Processed'"></h4>
                    <p class="text-sm" style="color: var(--pos-text-muted);"><span x-text="returnSuccess.is_exchange ? 'Exchange' : 'Return'"></span> #<span x-text="returnSuccess.return_number" class="pos-mono font-medium"></span></p>
                    <p class="text-xl font-bold pos-mono mt-2" style="color: var(--pos-success);"><span x-text="returnSuccess.is_exchange ? 'Credit Issued' : 'Refund'"></span>: ₹<span x-text="returnSuccess.refund_amount.toFixed(2)"></span></p>
                    <p x-show="returnSuccess.credit_note" class="text-sm mt-1" style="color: var(--pos-primary);">Credit Note: <span x-text="returnSuccess.credit_note" class="pos-mono font-medium"></span></p>
                    <p x-show="returnSuccess.is_exchange" class="text-xs mt-3 p-2 rounded" style="background: #FEF3C7; color: #92400E;">Credit attached to current cart. Scan replacement items now.</p>
                    <button @click="returnSuccess = null; showReturnsModal = false" class="pos-btn pos-btn-primary mt-5 px-8 text-sm" x-text="returnSuccess.is_exchange ? 'Continue to Replacement' : 'Done'"></button>
                </div>
            </template>

            {{-- Search + Content --}}
            <template x-if="!returnSuccess">
                <div class="flex-1 overflow-hidden flex flex-col">
                    {{-- Search bar --}}
                    <div class="px-6 py-3" style="border-bottom: 1px solid #F1F5F9;">
                        <input type="text" x-model="returnSearch"
                               @input.debounce.400ms="searchForReturn()"
                               placeholder="Search by bill number, customer name or phone..."
                               class="w-full px-4 py-2.5 rounded-lg border text-sm focus:outline-none focus:ring-2"
                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                    </div>

                    <div class="flex-1 overflow-y-auto px-6 py-3">
                        {{-- Sale search results --}}
                        <template x-if="!returnSelectedSale">
                            <div>
                                <div x-show="returnSales.length === 0 && returnSearch.length >= 3" class="text-center py-8">
                                    <p class="text-sm" style="color: var(--pos-text-muted);">No sales found matching your search.</p>
                                </div>
                                <div x-show="returnSales.length === 0 && returnSearch.length < 3" class="text-center py-8">
                                    <svg class="w-12 h-12 mx-auto mb-2" style="color: #94A3B8;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                                    </svg>
                                    <p class="text-sm" style="color: var(--pos-text-muted);">Enter a bill number, customer name or phone to find a sale.</p>
                                </div>
                                <div class="space-y-2">
                                    <template x-for="sale in returnSales" :key="sale.id">
                                        <button @click="selectSaleForReturn(sale)"
                                                class="w-full flex items-center justify-between p-3 rounded-lg text-left transition-colors"
                                                style="border: 1px solid var(--pos-border);"
                                                @mouseenter="$el.style.borderColor='var(--pos-primary)'" @mouseleave="$el.style.borderColor='var(--pos-border)'">
                                            <div>
                                                <div class="text-sm font-medium" style="color: var(--pos-text);" x-text="sale.sale_number"></div>
                                                <div class="text-xs" style="color: var(--pos-text-muted);">
                                                    <span x-text="sale.customer"></span> · <span x-text="sale.date"></span>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-bold pos-mono" x-text="'₹' + sale.total.toFixed(2)"></div>
                                                <div class="text-xs" style="color: var(--pos-text-muted);" x-text="sale.items.length + ' items'"></div>
                                            </div>
                                        </button>
                                    </template>
                                </div>
                            </div>
                        </template>

                        {{-- Selected sale - item selection --}}
                        <template x-if="returnSelectedSale">
                            <div>
                                <button @click="returnSelectedSale = null; returnItems = []" class="flex items-center gap-1 text-sm font-medium mb-3" style="color: var(--pos-primary);">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                                    Back to search
                                </button>

                                <div class="flex items-center justify-between mb-3 p-2.5 rounded-lg" style="background: #F8FAFC;">
                                    <div>
                                        <span class="text-sm font-medium pos-mono" x-text="returnSelectedSale.sale_number"></span>
                                        <span class="text-xs ml-2" style="color: var(--pos-text-muted);" x-text="returnSelectedSale.date"></span>
                                    </div>
                                    <span class="text-sm font-bold pos-mono" x-text="'₹' + returnSelectedSale.total.toFixed(2)"></span>
                                </div>

                                <p class="text-xs font-medium mb-2" style="color: var(--pos-text-muted);">Select items to return:</p>

                                <div class="space-y-2 mb-4">
                                    <template x-for="(item, idx) in returnItems" :key="idx">
                                        <div class="p-3 rounded-lg transition-colors"
                                             :style="item.selected ? 'border: 1.5px solid var(--pos-primary); background: #F0FDFA;' : 'border: 1px solid var(--pos-border);'">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox" x-model="item.selected" class="rounded" style="accent-color: var(--pos-primary);">
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-sm font-medium truncate" x-text="item.product_name"></div>
                                                    <div class="text-xs" style="color: var(--pos-text-muted);">₹<span x-text="item.price.toFixed(2)" class="pos-mono"></span> × <span x-text="item.max_qty"></span></div>
                                                </div>
                                                <div x-show="item.selected" class="flex items-center gap-1">
                                                    <button @click="item.qty = Math.max(1, item.qty - 1)" class="w-6 h-6 rounded border flex items-center justify-center text-xs font-bold"
                                                            style="border-color: var(--pos-border);">−</button>
                                                    <span class="w-8 text-center text-sm pos-mono font-medium" x-text="item.qty"></span>
                                                    <button @click="item.qty = Math.min(item.max_qty, item.qty + 1)" class="w-6 h-6 rounded border flex items-center justify-center text-xs font-bold"
                                                            style="border-color: var(--pos-border);">+</button>
                                                </div>
                                                <div class="text-right min-w-[60px]">
                                                    <span class="text-sm font-bold pos-mono" x-text="item.selected ? '₹' + (item.price * item.qty).toFixed(2) : ''"></span>
                                                </div>
                                            </div>
                                            <div x-show="item.selected" class="mt-2 flex gap-2">
                                                <select x-model="item.condition" class="flex-1 px-2 py-1 rounded border text-xs focus:outline-none"
                                                        style="border-color: var(--pos-border);">
                                                    <option value="unused_with_tags">Unused with tags</option>
                                                    <option value="used">Used</option>
                                                    <option value="defective">Defective</option>
                                                </select>
                                                <input type="text" x-model="item.reason" placeholder="Reason (optional)"
                                                       class="flex-1 px-2 py-1 rounded border text-xs focus:outline-none"
                                                       style="border-color: var(--pos-border);">
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                {{-- Refund summary --}}
                                <div x-show="returnItems.some(i => i.selected)" class="space-y-3">
                                    <div class="p-3 rounded-lg" style="background: #FFF7ED; border: 1px solid #FED7AA;">
                                        <div class="flex justify-between text-sm font-medium">
                                            <span>Refund Total</span>
                                            <span class="pos-mono font-bold" style="color: var(--pos-accent);" x-text="'₹' + returnItems.filter(i=>i.selected).reduce((s,i)=>s+i.price*i.qty,0).toFixed(2)"></span>
                                        </div>
                                    </div>

                                    {{-- MARG Exchange flow toggle (forces credit-note refund + auto-attaches to cart for replacement) --}}
                                    <label class="flex items-start gap-2 p-3 rounded-lg cursor-pointer"
                                           style="background: #FEF3C7; border: 1px solid #FCD34D;">
                                        <input type="checkbox" x-model="returnIsExchange"
                                               @change="if (returnIsExchange) returnRefundMethod = 'credit_note'"
                                               class="mt-0.5 rounded" style="accent-color: var(--pos-primary);">
                                        <div class="flex-1">
                                            <div class="text-sm font-semibold" style="color: #92400E;">Exchange (replacement sale)</div>
                                            <div class="text-xs mt-0.5" style="color: #92400E;">Issue credit note and auto-redeem on the next bill. Customer required.</div>
                                        </div>
                                    </label>

                                    <div>
                                        <label class="block text-xs font-medium mb-1.5" style="color: var(--pos-text-muted);">Refund Method</label>
                                        <div class="flex gap-2">
                                            <button @click="returnRefundMethod = 'cash'" :disabled="returnIsExchange" class="flex-1 py-2 rounded-lg text-xs font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                    :style="returnRefundMethod === 'cash' ? 'background: var(--pos-primary); color: white;' : 'background: #F1F5F9; color: var(--pos-text-muted);'">Cash</button>
                                            <button @click="returnRefundMethod = 'original_payment'" :disabled="returnIsExchange" class="flex-1 py-2 rounded-lg text-xs font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                                                    :style="returnRefundMethod === 'original_payment' ? 'background: var(--pos-primary); color: white;' : 'background: #F1F5F9; color: var(--pos-text-muted);'">Original</button>
                                            <button @click="returnRefundMethod = 'credit_note'" class="flex-1 py-2 rounded-lg text-xs font-medium transition-colors"
                                                    :style="returnRefundMethod === 'credit_note' ? 'background: var(--pos-primary); color: white;' : 'background: #F1F5F9; color: var(--pos-text-muted);'">Credit Note</button>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-medium mb-1.5" style="color: var(--pos-text-muted);">Reason (optional)</label>
                                        <input type="text" x-model="returnReason" placeholder="Overall reason for return..."
                                               class="w-full px-3 py-2 rounded-lg border text-sm focus:outline-none focus:ring-1"
                                               style="border-color: var(--pos-border); --tw-ring-color: var(--pos-primary);">
                                    </div>

                                    <button @click="processReturn()" :disabled="returnLoading"
                                            class="pos-btn pos-btn-primary w-full py-3 text-sm gap-2">
                                        <svg x-show="!returnLoading" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                                        </svg>
                                        <span x-text="returnLoading ? 'Processing...' : 'Process Return'"></span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- ═══════ MARG-STYLE F-KEY FOOTER ═══════ --}}
    {{-- Always-visible hotkey strip at the bottom of the POS screen, mirrors MARG's bottom bar --}}
    <div class="marg-fkey-footer" role="toolbar" aria-label="Keyboard shortcuts">
        <span class="marg-fkey"><kbd>F2</kbd> Search</span>
        <span class="marg-fkey"><kbd>F3</kbd> Edit</span>
        <span class="marg-fkey"><kbd>F4</kbd> Hold</span>
        <span class="marg-fkey"><kbd>F5</kbd> Index</span>
        <span class="marg-fkey"><kbd>F6</kbd> Old&nbsp;Rate</span>
        <span class="marg-fkey"><kbd>F8</kbd> Return</span>
        <span class="marg-fkey"><kbd>F9</kbd> Group</span>
        <span class="marg-fkey"><kbd>F10</kbd> Held&nbsp;Bills</span>
        <span class="marg-fkey-sep"></span>
        <span class="marg-fkey"><kbd>Ctrl</kbd>+<kbd>I</kbd> Item</span>
        <span class="marg-fkey"><kbd>Ctrl</kbd>+<kbd>L</kbd> Party</span>
        <span class="marg-fkey"><kbd>Ctrl</kbd>+<kbd>W</kbd> Fast</span>
        <span class="marg-fkey">+ Label</span>
        <span class="marg-fkey-sep"></span>
        <span class="marg-fkey"><kbd>Esc</kbd> Close</span>
        <span class="marg-fkey-status" x-text="cart.items.length + ' item' + (cart.items.length !== 1 ? 's' : '') + ' · ₹' + (cart.total || 0).toFixed(2)"></span>
    </div>

</div>

@push('styles')
<style>
.marg-fkey-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.4rem 0.9rem;
    background: linear-gradient(to right, #1e293b, #0f172a);
    color: #cbd5e1;
    font-size: 0.72rem;
    border-top: 1px solid rgba(148, 163, 184, 0.18);
    box-shadow: 0 -2px 6px rgba(0, 0, 0, 0.18);
    overflow-x: auto;
    white-space: nowrap;
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
}
.marg-fkey { display: inline-flex; align-items: center; gap: 0.25rem; }
.marg-fkey kbd {
    background: rgba(255, 255, 255, 0.10);
    color: #fff;
    border: 1px solid rgba(255, 255, 255, 0.15);
    border-radius: 3px;
    padding: 0.05rem 0.3rem;
    font-size: 0.66rem;
    font-weight: 600;
}
.marg-fkey-sep { width: 1px; height: 14px; background: rgba(148, 163, 184, 0.25); margin: 0 0.25rem; }
.marg-fkey-status {
    margin-left: auto;
    color: #fbbf24;
    font-weight: 600;
    flex-shrink: 0;
}
/* Push the main container up so cart isn't hidden behind footer */
.pos-container { padding-bottom: 36px; }
/* MARG-style search-result row hover */
.marg-find-row:hover { background: #fef3c7; }
.marg-find-row:focus { background: #fde68a; outline: none; }
</style>
@endpush

@push('scripts')
{{-- ZXing-js (vendored) — provides window.ZXing.BrowserMultiFormatReader for camera scanning --}}
<script src="{{ asset('js/zxing-browser.min.js') }}" defer></script>
<script>
function posBilling() {
    return {
        // ── Products ──
        products: [],
        categories: [],
        selectedCategory: null,
        productsLoading: true,
        pagination: { current_page: 1, last_page: 1, total: 0 },

        // ── Mobile ──
        mobileCartOpen: false,
        mobileSearchOpen: false,

        // ── Search ──
        searchQuery: '',
        searchResults: [],
        showSearchResults: false,

        // ── Cart ──
        cart: {
            items: [],
            customer: null,
            coupon: null,
            subtotal: 0,
            discount: 0,
            scheme_discount: 0,
            applied_schemes: [],
            tax: 0,
            total: 0,
        },
        couponCode: '',
        couponError: '',

        // ── Variants ──
        showVariantPicker: false,
        selectedProductForVariant: null,
        selectedProductVariants: [],

        // ── Camera scanner (ZXing-js, /js/zxing-browser.min.js) ──
        // Lifecycle: openScanner() spins up BrowserMultiFormatReader → modal video tag.
        // On decode: 1s cooldown, beep, close modal, fall through to scanBarcode() (same path as USB).
        scannerOpen: false,
        scannerError: '',
        zxingReader: null,
        lastDecodeAt: 0,

        // ── Payment ──
        showPaymentModal: false,
        paymentMethod: 'cash',
        cashReceived: '',
        paymentRef: '',
        walletProvider: 'paytm', // MARG-parity: which wallet (paytm/phonepe/gpay/amazon_pay/mobikwik/other)
        splitCash: '0',
        splitCard: '0',
        splitUpi: '0',
        paymentError: '',
        saleLoading: false,
        managerPin: '', // for discount-authorization gate

        // ── Sale Success ──
        showSuccessModal: false,
        lastSale: { sale_number: '', change: 0 },

        // ── Customer ──
        showCustomerModal: false,
        customerSearch: '',
        customerResults: [],
        showNewCustomerForm: false,
        newCustomer: { name: '', phone: '', email: '' },

        // ── Held Bills ──
        showHeldBillsModal: false,
        heldBills: [],
        heldBillsCount: 0,

        // ── Returns ──
        showReturnsModal: false,
        returnSearch: '',
        returnSales: [],
        returnSelectedSale: null,
        returnItems: [],
        returnRefundMethod: 'cash',
        returnReason: '',
        returnIsExchange: false,
        returnLoading: false,
        returnSuccess: null,

        // ── Credit Note (in payment) ──
        creditNoteCode: '',
        creditNote: null,
        creditNoteApplied: 0,

        // ── Walk-in exchange (session-bound, no credit_note row) ──
        // Set when a walk-in customer's exchange return is auto-attached to the current cart.
        // Cleared on bill complete or modal cancel.
        walkinExchange: null, // { return_id, return_number, amount }

        // ── Other ──
        barcodeBuffer: '',
        barcodeTimeout: null,

        async init() {
            await Promise.all([
                this.loadCategories(),
                this.loadProducts(),
                this.loadCart(),
                this.loadHeldBillsCount(),
            ]);
            this.$refs.searchInput?.focus();
        },

        // ═══════ PRODUCT LOADING ═══════
        async loadCategories() {
            try {
                const res = await axios.get('{{ route("pos.categories") }}');
                this.categories = res.data.categories;
            } catch (e) { console.error('Failed to load categories', e); }
        },

        async loadProducts(page = 1) {
            this.productsLoading = true;
            try {
                const params = { page, per_page: 24 };
                if (this.selectedCategory) params.category = this.selectedCategory;

                const res = await axios.get('{{ route("pos.products.index") }}', { params });
                this.products = res.data.products;
                this.pagination = res.data.pagination;
            } catch (e) { console.error('Failed to load products', e); }
            finally { this.productsLoading = false; }
        },

        nextPage() {
            if (this.pagination.current_page < this.pagination.last_page) {
                this.loadProducts(this.pagination.current_page + 1);
            }
        },
        prevPage() {
            if (this.pagination.current_page > 1) {
                this.loadProducts(this.pagination.current_page - 1);
            }
        },

        async searchProducts() {
            if (this.searchQuery.length < 2) {
                this.searchResults = [];
                this.showSearchResults = false;
                return;
            }
            try {
                const res = await axios.get('{{ route("pos.products.search") }}', {
                    params: { q: this.searchQuery }
                });
                this.searchResults = res.data.products;
                this.showSearchResults = this.searchResults.length > 0;
            } catch (e) { console.error('Search failed', e); }
        },

        // ═══════ CAMERA SCANNER (ZXing-js) ═══════
        // Click 📷 next to search → modal opens → ZXing decodes camera stream → drops the
        // detected code into the same scanBarcode() pipeline as USB scanner. Identical UX.
        async openScanner() {
            if (typeof ZXing === 'undefined' || !ZXing.BrowserMultiFormatReader) {
                this.scannerError = 'Scanner library not loaded. Refresh the page and try again.';
                this.scannerOpen = true;
                return;
            }
            this.scannerOpen = true;
            this.scannerError = '';
            await this.$nextTick();
            try {
                this.zxingReader = new ZXing.BrowserMultiFormatReader();
                // null device → ZXing picks default; on mobile this prefers rear camera.
                await this.zxingReader.decodeFromVideoDevice(
                    null,
                    this.$refs.scannerVideo,
                    (result, err) => {
                        if (!result) return;
                        const now = Date.now();
                        if (now - this.lastDecodeAt < 1000) return; // 1s cooldown — prevent repeat-fire
                        this.lastDecodeAt = now;
                        const code = result.getText();
                        this.beepScanner();
                        this.closeScanner();
                        this.scanBarcode(code); // same path as USB — adds to cart
                    }
                );
            } catch (e) {
                this.scannerError = (e && e.name === 'NotAllowedError')
                    ? 'Camera access denied. Allow camera permission in browser settings, or use the USB scanner.'
                    : 'Camera unavailable. Use the USB scanner or type the code in the search bar.';
                console.error('Scanner error:', e);
            }
        },
        closeScanner() {
            if (this.zxingReader) {
                try { this.zxingReader.reset(); } catch (e) { /* ignore */ }
                this.zxingReader = null;
            }
            this.scannerOpen = false;
            this.scannerError = '';
        },
        beepScanner() {
            // Short 1kHz blip on successful decode. Mirrors USB scanner's audible feedback.
            try {
                const ctx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();
                osc.frequency.value = 1000;
                gain.gain.setValueAtTime(0.15, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                osc.connect(gain); gain.connect(ctx.destination);
                osc.start(); osc.stop(ctx.currentTime + 0.12);
            } catch (e) { /* silent — not all browsers permit audio without user gesture */ }
        },

        // Adds a search-result row to the cart. If the row came back with
        // matched_variant (because the query was an exact match for a variant's
        // barcode/SKU), add THAT variant directly — skip the variant picker.
        // Otherwise fall through to the normal addToCart path.
        addSearchResult(product) {
            if (product.matched_variant) {
                this.addVariantToCartById(product, product.matched_variant.id);
                return;
            }
            this.addToCart(product);
        },

        // Manual barcode/SKU entry: cashier types into the search bar (USB scanner is
        // detected separately by the rapid-keystroke handler in handleKeydown). Pressing
        // Enter here triggers a real scan against /pos/products/barcode/{code} — same
        // pipeline as a USB or camera scan — bypassing the search dropdown's debounce.
        onSearchEnter() {
            const code = (this.searchQuery || '').trim();
            if (! code) return;
            // If the search dropdown already shows exactly one match, add it (covers
            // partial-name searches like "chikankari" that return one product).
            // addSearchResult() routes matched_variant rows to the variant directly.
            if (this.searchResults.length === 1) {
                this.addSearchResult(this.searchResults[0]);
                this.searchQuery = '';
                this.searchResults = [];
                this.showSearchResults = false;
                return;
            }
            // Otherwise route as a barcode/SKU scan
            this.scanBarcode(code);
            this.searchQuery = '';
            this.searchResults = [];
            this.showSearchResults = false;
        },

        // ═══════ BARCODE SCANNING ═══════
        async scanBarcode(code) {
            try {
                const res = await axios.get('{{ url("/pos/products/barcode") }}/' + encodeURIComponent(code));
                if (res.data.found) {
                    if (res.data.variant_id) {
                        this.addVariantToCartById(res.data.product, res.data.variant_id);
                    } else {
                        this.addToCart(res.data.product);
                    }
                }
            } catch (e) {
                console.error('Barcode not found:', code);
            }
        },

        // ═══════ CART MANAGEMENT ═══════
        async addToCart(product) {
            if (!product.in_stock) return;

            // If product has variants, show variant picker
            if (product.has_variants && product.variants.length > 0) {
                this.selectedProductForVariant = product;
                this.selectedProductVariants = product.variants;
                this.showVariantPicker = true;
                return;
            }

            try {
                const res = await axios.post('{{ route("pos.cart.add") }}', {
                    product_id: product.id,
                    quantity: 1,
                });
                if (res.data.cart) {
                    this.updateCartData(res.data.cart);
                    // Mark last item as just added for animation
                    if (this.cart.items.length > 0) {
                        this.cart.items[this.cart.items.length - 1]._justAdded = true;
                    }
                    this.scrollCartToBottom();
                }
            } catch (e) {
                console.error('Add to cart failed', e);
                alert(e.response?.data?.message || 'Failed to add item');
            }
        },

        async addVariantToCart(variant) {
            if (!variant.in_stock) return;
            this.showVariantPicker = false;

            try {
                const res = await axios.post('{{ route("pos.cart.add") }}', {
                    product_id: this.selectedProductForVariant.id,
                    variant_id: variant.id,
                    quantity: 1,
                });
                if (res.data.cart) {
                    this.updateCartData(res.data.cart);
                    if (this.cart.items.length > 0) {
                        this.cart.items[this.cart.items.length - 1]._justAdded = true;
                    }
                    this.scrollCartToBottom();
                }
            } catch (e) {
                console.error('Add variant to cart failed', e);
                alert(e.response?.data?.message || 'Failed to add item');
            }
        },

        addVariantToCartById(product, variantId) {
            const variant = product.variants.find(v => v.id === variantId);
            if (variant) {
                this.selectedProductForVariant = product;
                this.addVariantToCart(variant);
            } else {
                this.addToCart(product);
            }
        },

        async updateQuantity(item, newQty) {
            if (newQty <= 0) {
                return this.removeItem(item);
            }
            try {
                const res = await axios.patch('{{ url("/pos/cart") }}/' + item.cart_item_id, {
                    quantity: newQty,
                });
                if (res.data.cart) this.updateCartData(res.data.cart);
            } catch (e) {
                alert(e.response?.data?.message || 'Failed to update quantity');
            }
        },

        async removeItem(item) {
            try {
                const res = await axios.delete('{{ url("/pos/cart") }}/' + item.cart_item_id);
                if (res.data.cart) this.updateCartData(res.data.cart);
            } catch (e) {
                console.error('Remove failed', e);
            }
        },

        async clearCart() {
            if (!confirm('Clear all items from the cart?')) return;
            try {
                const res = await axios.delete('{{ route("pos.cart.clear") }}');
                if (res.data.cart) this.updateCartData(res.data.cart);
            } catch (e) {
                console.error('Clear cart failed', e);
            }
        },

        async loadCart() {
            try {
                const res = await axios.get('{{ route("pos.cart.data") }}');
                if (res.data.cart) this.updateCartData(res.data.cart);
            } catch (e) { console.error('Failed to load cart', e); }
        },

        updateCartData(data) {
            this.cart.items = data.items || [];
            this.cart.customer = data.customer || null;
            this.cart.coupon = data.coupon || null;
            this.cart.subtotal = parseFloat(data.subtotal) || 0;
            this.cart.discount = parseFloat(data.discount) || 0;
            this.cart.scheme_discount = parseFloat(data.scheme_discount) || 0;
            this.cart.applied_schemes = data.applied_schemes || [];
            this.cart.tax = parseFloat(data.tax) || 0;
            this.cart.total = parseFloat(data.total) || 0;
        },

        scrollCartToBottom() {
            this.$nextTick(() => {
                const el = this.$refs.cartItems;
                if (el) el.scrollTop = el.scrollHeight;
            });
        },

        // ═══════ COUPON ═══════
        async applyCoupon() {
            if (!this.couponCode.trim()) return;
            this.couponError = '';
            try {
                const res = await axios.post('{{ route("pos.cart.coupon") }}', { code: this.couponCode.trim() });
                if (res.data.cart) {
                    this.updateCartData(res.data.cart);
                    this.couponCode = '';
                }
            } catch (e) {
                // Surface inline (red text under the field) instead of a jarring alert.
                // Covers the "user typed FLAT10/SAVE100 here, but those are schemes" case.
                this.couponError = e.response?.data?.message || 'Invalid coupon';
            }
        },

        async removeCoupon() {
            try {
                const res = await axios.delete('{{ route("pos.cart.coupon.remove") }}');
                if (res.data.cart) this.updateCartData(res.data.cart);
            } catch (e) {
                console.error('Remove coupon failed', e);
            }
        },

        // ═══════ CUSTOMER ═══════
        async searchCustomers() {
            if (this.customerSearch.length < 2) { this.customerResults = []; return; }
            try {
                const res = await axios.get('{{ route("pos.customers.search") }}', {
                    params: { q: this.customerSearch }
                });
                this.customerResults = res.data.customers;
            } catch (e) { console.error('Customer search failed', e); }
        },

        async selectCustomer(customer) {
            try {
                await axios.post('{{ route("pos.cart.customer") }}', {
                    customer_id: customer?.id || null
                });
                this.cart.customer = customer;
                this.showCustomerModal = false;
                this.customerSearch = '';
                this.customerResults = [];
            } catch (e) {
                console.error('Attach customer failed', e);
            }
        },

        async createCustomer() {
            try {
                const res = await axios.post('{{ route("pos.customers.store") }}', this.newCustomer);
                if (res.data.customer) {
                    await this.selectCustomer(res.data.customer);
                    this.newCustomer = { name: '', phone: '', email: '' };
                    this.showNewCustomerForm = false;
                }
            } catch (e) {
                alert(e.response?.data?.message || 'Failed to create customer');
            }
        },

        // ═══════ PAYMENT ═══════
        startPayment(method) {
            if (this.cart.items.length === 0) return;
            this.paymentMethod = method;
            this.cashReceived = this.cart.total.toFixed(2);
            this.paymentRef = '';
            this.splitCash = '0';
            this.splitCard = '0';
            this.splitUpi = '0';
            this.paymentError = '';
            this.showPaymentModal = true;

            this.$nextTick(() => {
                if (method === 'cash') this.$refs.cashInput?.select();
            });
        },

        splitRemaining() {
            const paid = (parseFloat(this.splitCash) || 0) + (parseFloat(this.splitCard) || 0) + (parseFloat(this.splitUpi) || 0);
            return Math.max(0, this.cart.total - paid);
        },

        async completeSale() {
            this.paymentError = '';

            // Validate cash (minus credit note)
            if (this.paymentMethod === 'cash') {
                const received = parseFloat(this.cashReceived) || 0;
                if (received < this.amountAfterCreditNote()) {
                    this.paymentError = 'Insufficient cash received.';
                    return;
                }
            }

            // Validate split
            if (this.paymentMethod === 'split' && this.splitRemaining() > 0.01) {
                this.paymentError = 'Split payment does not cover the total amount.';
                return;
            }

            this.saleLoading = true;

            const payload = {
                payment_method: this.paymentMethod,
            };

            // Credit note
            if (this.creditNote && this.creditNoteApplied > 0) {
                payload.credit_note_id = this.creditNote.id;
                payload.credit_note_amount = this.creditNoteApplied;
            }

            // Walk-in exchange credit (session-bound, no credit_notes row)
            if (this.walkinExchange && this.walkinExchange.amount > 0) {
                payload.exchange_return_id = this.walkinExchange.return_id;
                payload.exchange_return_amount = this.walkinExchange.amount;
            }

            const effectiveTotal = this.amountAfterCreditNote();
            const exchangeAmt    = this.walkinExchange ? this.walkinExchange.amount : 0;

            if (this.paymentMethod === 'cash') {
                payload.paid_amount = (parseFloat(this.cashReceived) || 0) + this.creditNoteApplied + exchangeAmt;
            } else if (this.paymentMethod === 'card' || this.paymentMethod === 'upi') {
                payload.paid_amount = this.cart.total;
                payload.payment_ref = this.paymentRef;
            } else if (this.paymentMethod === 'wallet') {
                payload.paid_amount = this.cart.total;
                payload.payment_ref = this.paymentRef;
                payload.wallet_provider = this.walletProvider || 'other';
            } else if (this.paymentMethod === 'credit') {
                // Charge to customer account; paid_amount = 0, balance_due = total
                payload.paid_amount = 0;
            } else if (this.paymentMethod === 'split') {
                payload.paid_amount = this.cart.total;
                payload.payment_details = {
                    cash: parseFloat(this.splitCash) || 0,
                    card: parseFloat(this.splitCard) || 0,
                    upi: parseFloat(this.splitUpi) || 0,
                };
            }

            // Manager PIN (only sent if user has filled it; backend decides if needed)
            if (this.managerPin) {
                payload.manager_pin = this.managerPin;
            }

            try {
                const res = await axios.post('{{ route("pos.sale.complete") }}', payload);
                if (res.data.success) {
                    this.lastSale = {
                        sale_number: res.data.sale_number,
                        change: parseFloat(res.data.change) || 0,
                        receipt_url: res.data.receipt_url || '',
                    };
                    this.showPaymentModal = false;
                    this.showSuccessModal = true;
                    this.updateCartData({ items: [], subtotal: 0, discount: 0, tax: 0, total: 0 });
                    this.removeCreditNote();
                    this.walkinExchange = null;
                    this.loadHeldBillsCount();
                }
            } catch (e) {
                const status = e.response?.status;
                const data   = e.response?.data || {};
                // Manager-PIN gate triggered by server — prompt the user inline
                if (data.needs_manager) {
                    const pin = window.prompt(data.message + '\n\nEnter manager PIN to authorize:');
                    if (pin) {
                        this.managerPin = pin;
                        this.saleLoading = false;
                        return this.completeSale(); // retry once with the PIN
                    }
                    this.paymentError = 'Sale cancelled — discount needs manager authorization.';
                } else if (data.credit_check) {
                    // Credit limit exceeded — show the structured detail
                    this.paymentError = data.message + (data.credit_check.availableCredit !== undefined
                        ? ' Available credit: ₹' + data.credit_check.availableCredit.toFixed(2)
                        : '');
                } else {
                    this.paymentError = data.message || 'Sale failed. Please try again.';
                }
            } finally {
                this.saleLoading = false;
            }
        },

        // ═══════ POST-SALE ═══════
        printReceipt() {
            if (!this.lastSale.receipt_url) return;

            // Desktop EXE: fetch structured JSON and print via native thermal printer
            if (window.posDesktop?.isDesktop) {
                const jsonUrl = this.lastSale.receipt_url.replace('/receipt', '/receipt-data');
                fetch(jsonUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                })
                .then(r => r.json())
                .then(data => window.posDesktop.printReceipt(data))
                .then(result => {
                    if (result && !result.success) {
                        // Thermal print failed — fall back to browser print
                        window.open(this.lastSale.receipt_url, '_blank');
                    }
                })
                .catch(() => {
                    // JSON endpoint unavailable — fall back to browser print
                    window.open(this.lastSale.receipt_url, '_blank');
                });
                return;
            }

            // Browser: open HTML receipt in new tab
            window.open(this.lastSale.receipt_url, '_blank');
        },

        // MARG-style A4 invoice in one of: retail | wholesale | proforma | tax
        printInvoice(format) {
            if (!this.lastSale.receipt_url) return;
            // receipt_url looks like /pos/sale/{id}/receipt — derive the invoice URL from it
            const url = this.lastSale.receipt_url.replace(/\/receipt$/, '/invoice/' + format);
            window.open(url, '_blank');
        },

        newSale() {
            this.showSuccessModal = false;
            this.lastSale = { sale_number: '', change: 0 };
            this.$refs.searchInput?.focus();
        },

        // ═══════ HELD BILLS ═══════
        async holdBill() {
            if (this.cart.items.length === 0) return;
            const reference = prompt('Label for held bill (optional):');
            try {
                const res = await axios.post('{{ route("pos.held-bills.hold") }}', { reference });
                if (res.data.success) {
                    this.updateCartData({ items: [], subtotal: 0, discount: 0, tax: 0, total: 0 });
                    this.loadHeldBillsCount();
                }
            } catch (e) {
                alert(e.response?.data?.message || 'Failed to hold bill');
            }
        },

        async showHeldBills() {
            try {
                const res = await axios.get('{{ route("pos.held-bills") }}');
                this.heldBills = res.data.bills || [];
                this.showHeldBillsModal = true;
            } catch (e) { console.error('Failed to load held bills', e); }
        },

        async resumeBill(bill) {
            try {
                const res = await axios.post('{{ url("/pos/held-bills") }}/' + bill.id + '/resume');
                if (res.data.cart) {
                    this.updateCartData(res.data.cart);
                    this.showHeldBillsModal = false;
                    this.loadHeldBillsCount();
                }
            } catch (e) {
                alert(e.response?.data?.message || 'Failed to resume bill');
            }
        },

        async deleteHeldBill(bill) {
            if (!confirm('Delete this held bill?')) return;
            try {
                await axios.delete('{{ url("/pos/held-bills") }}/' + bill.id);
                this.heldBills = this.heldBills.filter(b => b.id !== bill.id);
                this.heldBillsCount = Math.max(0, this.heldBillsCount - 1);
            } catch (e) { console.error('Delete held bill failed', e); }
        },

        async loadHeldBillsCount() {
            try {
                const res = await axios.get('{{ route("pos.held-bills") }}');
                this.heldBillsCount = (res.data.bills || []).length;
            } catch (e) {}
        },

        // ═══════ RETURNS ═══════
        async searchForReturn() {
            if (this.returnSearch.length < 3) { this.returnSales = []; return; }
            try {
                const res = await axios.get('{{ route("pos.returns.find") }}', { params: { q: this.returnSearch } });
                this.returnSales = res.data.sales || [];
            } catch (e) { console.error('Return search failed', e); }
        },

        selectSaleForReturn(sale) {
            this.returnSelectedSale = sale;
            this.returnItems = sale.items.map(item => ({
                sale_item_id: item.id,
                product_name: item.product_name,
                price: item.price,
                max_qty: item.quantity,
                qty: item.quantity,
                selected: false,
                condition: 'unused_with_tags',
                reason: '',
            }));
        },

        async processReturn(managerPinOverride = null) {
            const selectedItems = this.returnItems.filter(i => i.selected);
            if (selectedItems.length === 0) { alert('Select at least one item to return.'); return; }

            this.returnLoading = true;
            try {
                const payload = {
                    pos_sale_id: this.returnSelectedSale.id,
                    items: selectedItems.map(i => ({
                        sale_item_id: i.sale_item_id,
                        quantity: i.qty,
                        reason: i.reason || null,
                        condition: i.condition,
                    })),
                    refund_method: this.returnRefundMethod,
                    reason: this.returnReason || null,
                    is_exchange: this.returnIsExchange,
                };
                // If we're retrying after a manager-PIN prompt, attach it
                if (managerPinOverride) payload.manager_pin = managerPinOverride;

                const res = await axios.post('{{ route("pos.returns.process") }}', payload);

                if (res.data.success) {
                    const refundAmt = parseFloat(res.data.refund_amount) || 0;
                    this.returnSuccess = {
                        return_id: res.data.return_id,
                        return_number: res.data.return_number,
                        refund_amount: refundAmt,
                        credit_note: res.data.credit_note || null,
                        is_exchange: !!res.data.is_exchange,
                        walkin_exchange: !!res.data.walkin_exchange,
                    };
                    const wasExchange = !!res.data.is_exchange;
                    const isWalkin = !!res.data.walkin_exchange;
                    const issuedCreditNote = res.data.credit_note || null;

                    this.returnSelectedSale = null;
                    this.returnItems = [];
                    this.returnSearch = '';
                    this.returnSales = [];
                    this.returnIsExchange = false;
                    this.returnRefundMethod = 'cash';

                    // Exchange flow: attach refund to the current cart for the replacement sale.
                    // Two paths: registered customer → real credit_note row, validate & redeem normally.
                    //            walk-in customer → session-bound credit (no credit_notes row).
                    if (wasExchange && isWalkin) {
                        this.walkinExchange = {
                            return_id: res.data.return_id,
                            return_number: res.data.return_number,
                            amount: refundAmt,
                        };
                    } else if (wasExchange && issuedCreditNote) {
                        this.creditNoteCode = issuedCreditNote;
                        try {
                            await this.validateCreditNote();
                        } catch (e) { /* validateCreditNote already alerts on failure */ }
                    }
                }
            } catch (e) {
                const data = e.response?.data || {};
                // Server is asking for manager PIN — prompt inline and retry once
                if (data.needs_manager_auth) {
                    const pin = window.prompt(
                        data.message + '\n\nManager: enter your PIN to authorize this refund:'
                    );
                    if (pin) {
                        this.returnLoading = false;
                        return this.processReturn(pin); // retry with PIN
                    }
                    alert('Refund cancelled — manager authorization required for this amount.');
                } else if (e.response?.status === 403 && /manager pin/i.test(data.message || '')) {
                    // Wrong PIN — let them try again
                    const retry = window.confirm(data.message + '\n\nTry again?');
                    if (retry) {
                        const pin = window.prompt('Re-enter manager PIN:');
                        if (pin) {
                            this.returnLoading = false;
                            return this.processReturn(pin);
                        }
                    }
                } else {
                    alert(data.message || 'Return processing failed.');
                }
            } finally { this.returnLoading = false; }
        },

        // ═══════ CREDIT NOTE ═══════
        async validateCreditNote() {
            if (!this.creditNoteCode.trim()) return;
            try {
                const res = await axios.get('{{ url("/pos/credit-note") }}/' + encodeURIComponent(this.creditNoteCode.trim()) + '/validate');
                if (res.data.valid) {
                    this.creditNote = res.data;
                    this.creditNoteApplied = Math.min(res.data.remaining, this.cart.total);
                    this.creditNoteCode = '';
                }
            } catch (e) {
                alert(e.response?.data?.message || 'Invalid credit note.');
            }
        },

        removeCreditNote() {
            this.creditNote = null;
            this.creditNoteApplied = 0;
            this.creditNoteCode = '';
        },

        amountAfterCreditNote() {
            // Net cart total after credit-note redemption AND walk-in exchange credit
            const cnOffset  = this.creditNote ? this.creditNoteApplied : 0;
            const exOffset  = this.walkinExchange ? this.walkinExchange.amount : 0;
            return Math.max(0, this.cart.total - cnOffset - exOffset);
        },

        // ═══════ LOGOUT ═══════
        async doLogout() {
            if (!confirm('Log out of POS?')) return;
            try {
                const res = await axios.post('{{ route("pos.logout") }}');
                window.location.href = res.data.redirect || '{{ route("pos.login") }}';
            } catch (e) {
                window.location.href = '{{ route("pos.login") }}';
            }
        },

        // ═══════ KEYBOARD SHORTCUTS ═══════
        handleKeydown(e) {
            // Don't capture in modals with text inputs
            if (e.target.tagName === 'INPUT' && e.target.type !== 'button') {
                // Allow barcode scanning in search
                if (e.target === this.$refs.searchInput) return;
                return;
            }

            // MARG-parity: Ctrl+L = Party (customer) modal — global, MARG muscle memory
            if (e.ctrlKey && (e.key === 'l' || e.key === 'L')) {
                e.preventDefault();
                this.showCustomerModal = true;
                return;
            }
            // Ctrl+I = Item search focus (MARG Ctrl+I)
            if (e.ctrlKey && (e.key === 'i' || e.key === 'I')) {
                e.preventDefault();
                this.$refs.searchInput?.focus();
                return;
            }
            // Ctrl+W = Fast-search by short code (MARG Ctrl+W)
            if (e.ctrlKey && (e.key === 'w' || e.key === 'W')) {
                e.preventDefault();
                this.$refs.searchInput?.focus();
                return;
            }

            switch (e.key) {
                case 'F2':
                    e.preventDefault();
                    this.$refs.searchInput?.focus();
                    break;
                case 'F5':
                    e.preventDefault();
                    this.startPayment('cash');
                    break;
                case 'F6':
                    e.preventDefault();
                    this.startPayment('card');
                    break;
                case 'F7':
                    e.preventDefault();
                    this.startPayment('upi');
                    break;
                case 'F9':
                    e.preventDefault();
                    this.holdBill();
                    break;
                case 'F10':
                    e.preventDefault();
                    this.showHeldBills();
                    break;
                case 'F8':
                    e.preventDefault();
                    this.showReturnsModal = true;
                    this.returnSuccess = null;
                    break;
                case 'Escape':
                    this.showVariantPicker = false;
                    this.showPaymentModal = false;
                    this.showCustomerModal = false;
                    this.showHeldBillsModal = false;
                    this.showSuccessModal = false;
                    this.showReturnsModal = false;
                    break;
            }

            // Barcode scanner detection (rapid keypresses ending with Enter)
            if (!this.showPaymentModal && !this.showCustomerModal) {
                if (e.key.length === 1 && !e.ctrlKey && !e.altKey && !e.metaKey) {
                    this.barcodeBuffer += e.key;
                    clearTimeout(this.barcodeTimeout);
                    this.barcodeTimeout = setTimeout(() => { this.barcodeBuffer = ''; }, 100);
                } else if (e.key === 'Enter' && this.barcodeBuffer.length >= 4) {
                    e.preventDefault();
                    this.scanBarcode(this.barcodeBuffer);
                    this.barcodeBuffer = '';
                }
            }
        },
    };
}
</script>
@endpush
</x-pos.layout>
