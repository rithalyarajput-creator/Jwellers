<x-layouts.app>
    <x-slot name="title">Size Guide - {{ config('app.name') }}</x-slot>

    @push('meta')
        <meta name="description" content="Jewellery size guide at {{ config('app.name') }}. Find your perfect ring, bangle, and chain size with our sizing charts.">
        <link rel="canonical" href="{{ url('/size-guide') }}">
    @endpush

    <!-- Breadcrumb -->
    <div class="bg-neutral-50 border-b border-neutral-100">
        <div class="container mx-auto px-4 py-3">
            <x-breadcrumb :items="[['label' => 'Size Guide', 'url' => null]]" />
        </div>
    </div>

    <section class="py-10 sm:py-14">
        <div class="container mx-auto px-4">
            <div class="max-w-4xl mx-auto">

                <!-- Header -->
                <div class="text-center mb-10">
                    <h1 class="text-2xl sm:text-3xl font-bold text-neutral-900 mb-3">Jewellery Size Guide</h1>
                    <p class="text-sm text-neutral-600 max-w-xl mx-auto">Find your perfect fit. Use the charts below to match your measurements with our ring, bangle, and chain sizes.</p>
                </div>

                <!-- Ring Size Chart -->
                <div class="bg-white rounded-xl border border-neutral-100 overflow-hidden mb-10">
                    <div class="px-4 sm:px-6 py-3 bg-neutral-50 border-b border-neutral-100">
                        <h2 class="text-sm font-bold text-neutral-900">Ring Size Chart (India)</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-neutral-50">
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Indian Size</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Diameter (mm)</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Circumference (mm)</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">US Size</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">6</td><td class="px-4 py-2.5 text-neutral-600">14.0</td><td class="px-4 py-2.5 text-neutral-600">44.0</td><td class="px-4 py-2.5 text-neutral-600">3</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">8</td><td class="px-4 py-2.5 text-neutral-600">14.6</td><td class="px-4 py-2.5 text-neutral-600">45.9</td><td class="px-4 py-2.5 text-neutral-600">3.75</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">10</td><td class="px-4 py-2.5 text-neutral-600">15.3</td><td class="px-4 py-2.5 text-neutral-600">48.0</td><td class="px-4 py-2.5 text-neutral-600">4.5</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">12</td><td class="px-4 py-2.5 text-neutral-600">15.9</td><td class="px-4 py-2.5 text-neutral-600">50.0</td><td class="px-4 py-2.5 text-neutral-600">5.25</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">14</td><td class="px-4 py-2.5 text-neutral-600">16.5</td><td class="px-4 py-2.5 text-neutral-600">51.8</td><td class="px-4 py-2.5 text-neutral-600">6</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">16</td><td class="px-4 py-2.5 text-neutral-600">17.1</td><td class="px-4 py-2.5 text-neutral-600">53.8</td><td class="px-4 py-2.5 text-neutral-600">6.75</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">18</td><td class="px-4 py-2.5 text-neutral-600">17.8</td><td class="px-4 py-2.5 text-neutral-600">55.7</td><td class="px-4 py-2.5 text-neutral-600">7.5</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">20</td><td class="px-4 py-2.5 text-neutral-600">18.4</td><td class="px-4 py-2.5 text-neutral-600">57.8</td><td class="px-4 py-2.5 text-neutral-600">8.25</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">22</td><td class="px-4 py-2.5 text-neutral-600">19.0</td><td class="px-4 py-2.5 text-neutral-600">59.5</td><td class="px-4 py-2.5 text-neutral-600">9</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">24</td><td class="px-4 py-2.5 text-neutral-600">19.7</td><td class="px-4 py-2.5 text-neutral-600">61.6</td><td class="px-4 py-2.5 text-neutral-600">9.75</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Bangle Size Chart -->
                <div class="bg-white rounded-xl border border-neutral-100 overflow-hidden mb-10">
                    <div class="px-4 sm:px-6 py-3 bg-neutral-50 border-b border-neutral-100">
                        <h2 class="text-sm font-bold text-neutral-900">Bangle Size Chart</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-neutral-50">
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Size</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Diameter (inches)</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Circumference (mm)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">2.2</td><td class="px-4 py-2.5 text-neutral-600">2.20"</td><td class="px-4 py-2.5 text-neutral-600">175</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">2.4</td><td class="px-4 py-2.5 text-neutral-600">2.375"</td><td class="px-4 py-2.5 text-neutral-600">190</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">2.6</td><td class="px-4 py-2.5 text-neutral-600">2.625"</td><td class="px-4 py-2.5 text-neutral-600">210</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">2.8</td><td class="px-4 py-2.5 text-neutral-600">2.875"</td><td class="px-4 py-2.5 text-neutral-600">230</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">2.10</td><td class="px-4 py-2.5 text-neutral-600">3.00"</td><td class="px-4 py-2.5 text-neutral-600">240</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Chain Length Chart -->
                <div class="bg-white rounded-xl border border-neutral-100 overflow-hidden mb-10">
                    <div class="px-4 sm:px-6 py-3 bg-neutral-50 border-b border-neutral-100">
                        <h2 class="text-sm font-bold text-neutral-900">Chain &amp; Necklace Length Guide</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-neutral-50">
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Length</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Style</th>
                                    <th class="px-4 py-3 text-left font-semibold text-neutral-700">Sits At</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-neutral-100">
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">14"</td><td class="px-4 py-2.5 text-neutral-600">Choker</td><td class="px-4 py-2.5 text-neutral-600">Base of the neck</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">16"</td><td class="px-4 py-2.5 text-neutral-600">Collar</td><td class="px-4 py-2.5 text-neutral-600">Around the collarbone</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">18"</td><td class="px-4 py-2.5 text-neutral-600">Princess</td><td class="px-4 py-2.5 text-neutral-600">On the collarbone</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">20"–24"</td><td class="px-4 py-2.5 text-neutral-600">Matinee</td><td class="px-4 py-2.5 text-neutral-600">On the chest</td></tr>
                                <tr class="hover:bg-neutral-50/50"><td class="px-4 py-2.5 font-medium text-neutral-900">28"–36"</td><td class="px-4 py-2.5 text-neutral-600">Opera / Rope</td><td class="px-4 py-2.5 text-neutral-600">Below the chest</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- How to Measure -->
                <div class="bg-white rounded-xl border border-neutral-100 p-6 sm:p-8">
                    <h2 class="text-lg font-bold text-neutral-900 mb-5">How to Measure</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-[#c9a227]/10 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-[#c9a227]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="8" stroke-width="1.5"/><circle cx="12" cy="12" r="3.5" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-neutral-900 mb-1">Ring Size</h3>
                            <p class="text-sm text-neutral-600 leading-relaxed">Wrap a thin strip of paper around the base of your finger, mark where it overlaps, then measure the length in mm and match it to the circumference column.</p>
                        </div>
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-[#7a1f2b]/10 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-[#7a1f2b]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <ellipse cx="12" cy="12" rx="8" ry="6" stroke-width="1.5"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-neutral-900 mb-1">Bangle Size</h3>
                            <p class="text-sm text-neutral-600 leading-relaxed">Bring your thumb and little finger together and measure around the widest part of your hand. Match that measurement to the bangle circumference.</p>
                        </div>
                        <div>
                            <div class="w-10 h-10 rounded-lg bg-[#c9a227]/10 flex items-center justify-center mb-3">
                                <svg class="w-5 h-5 text-[#c9a227]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3a5 5 0 015 5c0 4-5 9-5 9s-5-5-5-9a5 5 0 015-5z"/>
                                </svg>
                            </div>
                            <h3 class="text-sm font-semibold text-neutral-900 mb-1">Chain Length</h3>
                            <p class="text-sm text-neutral-600 leading-relaxed">Use a soft measuring tape around your neck at the height you want the necklace to sit, then add 2–4 inches for a comfortable drop.</p>
                        </div>
                    </div>

                    <div class="mt-6 p-4 bg-amber-50 rounded-lg border border-amber-100">
                        <p class="text-sm text-amber-800"><strong>Tip:</strong> If your measurement falls between two sizes, we recommend choosing the larger size for a comfortable fit. For a precise ring size, visit any jeweller for a ring-sizer measurement.</p>
                    </div>
                </div>

            </div>
        </div>
    </section>
</x-layouts.app>
