@props([
    'size'      => 'lg',           // 'lg' (product page), 'md' (cart page), 'sm' (cart drawer)
    'label'     => 'BUY NOW',      // Top text — "BUY NOW" / "CHECKOUT"
    'disabled'  => false,
    'fullWidth' => true,
])

@php
    // Jwellers-tinted Shiprocket button. Visual matches the Shiprocket
    // reference (BUY NOW + 'Powered by Shiprocket' badge below) so customers
    // recognise the express-checkout flow. Color uses the Jwellers primary
    // teal (#c9a227) instead of Shiprocket's cyan to fit the brand.
    $sizeClasses = match ($size) {
        'sm' => ['py' => 'py-2',     'text' => 'text-[12px]', 'badgeText' => 'text-[8px]',  'gap' => 'gap-0.5'],
        'md' => ['py' => 'py-2.5',   'text' => 'text-[13px]', 'badgeText' => 'text-[9px]',  'gap' => 'gap-0.5'],
        'lg' => ['py' => 'py-3',     'text' => 'text-[14px]', 'badgeText' => 'text-[10px]', 'gap' => 'gap-1'],
        default => ['py' => 'py-3',  'text' => 'text-[14px]', 'badgeText' => 'text-[10px]', 'gap' => 'gap-1'],
    };
    $widthClass = $fullWidth ? 'w-full' : '';
@endphp

<button type="button"
        @click="$store.cart.checkoutViaShiprocket($event)"
        :disabled="$store.cart.checkoutPending @if($disabled) || true @endif"
        :class="{ 'opacity-60 cursor-wait': $store.cart.checkoutPending }"
        {{ $attributes->merge([
            'class' => "$widthClass {$sizeClasses['py']} px-4 flex flex-col items-center justify-center {$sizeClasses['gap']} rounded-full font-bold tracking-wide text-white transition-all shadow-md hover:shadow-lg",
            'style' => 'background: linear-gradient(135deg, #c9a227 0%, #4f7d83 100%); border: 1px solid #4f7d83;',
        ]) }}>
    {{-- Top: BUY NOW / CHECKOUT --}}
    <span class="{{ $sizeClasses['text'] }} font-bold leading-none"
          x-show="!$store.cart.checkoutPending">
        {{ strtoupper($label) }}
    </span>
    <span class="{{ $sizeClasses['text'] }} font-bold leading-none"
          x-show="$store.cart.checkoutPending"
          x-cloak>
        REDIRECTING...
    </span>

    {{-- Bottom: 'Powered by Shiprocket' badge --}}
    <span class="{{ $sizeClasses['badgeText'] }} font-medium opacity-90 leading-none flex items-center gap-1">
        <span>Powered by</span>
        <span class="font-bold tracking-tight">Shiprocket</span>
    </span>
</button>
