<x-layouts.app>
    <x-slot name="title">Finalising Your Order</x-slot>

    <div class="bg-neutral-50 min-h-screen">
        <div class="container mx-auto px-4 py-12">
            <div class="max-w-xl mx-auto bg-white rounded-2xl border border-neutral-100 p-8 sm:p-10 text-center"
                 x-data="orderProcessing(@js($srOrderId))" x-init="poll()" x-cloak>

                {{-- State A: still waiting for webhook --}}
                <template x-if="state === 'waiting'">
                    <div>
                        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-50 flex items-center justify-center mb-5">
                            <svg class="w-10 h-10 text-emerald-600 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke-width="3"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-neutral-900 mb-2">Order Placed Successfully!</h1>
                        <p class="text-[14px] text-neutral-600 mb-6 leading-relaxed">
                            Thank you — payment received. We're just finalising your order details now.
                            This usually takes a few seconds.
                        </p>

                        <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-6 text-left">
                            <div class="flex items-center gap-2 mb-2">
                                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span class="text-sm font-semibold text-emerald-800">Payment confirmed</span>
                            </div>
                            <p class="text-[12px] text-emerald-700 ml-7">
                                Reference: <span class="font-mono font-medium" x-text="srOrderId"></span>
                            </p>
                        </div>

                        <p class="text-[12px] text-neutral-500 mb-3">
                            Checking order status… <span x-text="attempts"></span>/<span x-text="maxAttempts"></span>
                        </p>

                        <p class="text-[13px] text-neutral-600">
                            You'll receive an email confirmation shortly. Don't refresh this page.
                        </p>
                    </div>
                </template>

                {{-- State B: order resolved → instant redirect --}}
                <template x-if="state === 'resolved'">
                    <div>
                        <div class="w-20 h-20 mx-auto rounded-full bg-emerald-100 flex items-center justify-center mb-5">
                            <svg class="w-10 h-10 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-neutral-900 mb-2">Order Confirmed!</h1>
                        <p class="text-[14px] text-neutral-600 mb-3">Redirecting to your order…</p>
                    </div>
                </template>

                {{-- State C: timed out — webhook still hasn't arrived after ~60s --}}
                <template x-if="state === 'timeout'">
                    <div>
                        <div class="w-20 h-20 mx-auto rounded-full bg-amber-50 flex items-center justify-center mb-5">
                            <svg class="w-10 h-10 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-neutral-900 mb-2">Order Placed — Confirming</h1>
                        <p class="text-[14px] text-neutral-600 mb-6 leading-relaxed">
                            Your payment went through and your order is being recorded.
                            We'll email you the confirmation within a few minutes.
                        </p>
                        <div class="bg-neutral-50 border border-neutral-200 rounded-lg p-4 mb-6 text-left">
                            <p class="text-[12px] text-neutral-700 leading-relaxed">
                                <strong class="font-semibold text-neutral-900">Reference for support:</strong>
                                <span class="font-mono ml-1" x-text="srOrderId"></span>
                            </p>
                            <p class="text-[12px] text-neutral-600 mt-2">
                                If you don't get an email in 10 minutes, contact us with this reference.
                            </p>
                        </div>
                        <a href="{{ route('account.orders.index') }}"
                           class="inline-block bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition-colors">
                            View My Orders
                        </a>
                    </div>
                </template>

            </div>
        </div>
    </div>

    <script>
        function orderProcessing(srOrderId) {
            return {
                state: 'waiting',           // waiting | resolved | timeout
                srOrderId: srOrderId,
                attempts: 0,
                maxAttempts: 30,            // 30 × 2s = 60s budget for webhook to arrive
                successUrl: null,

                async poll() {
                    if (this.state !== 'waiting') return;
                    this.attempts++;
                    try {
                        const res = await fetch(`{{ url('/checkout/shiprocket/order-status') }}?oid=${encodeURIComponent(this.srOrderId)}`, {
                            headers: { 'Accept': 'application/json' },
                            credentials: 'same-origin',
                        });
                        if (res.ok) {
                            const data = await res.json();
                            if (data.found && data.redirect_url) {
                                this.state = 'resolved';
                                this.successUrl = data.redirect_url;
                                // Brief pause so the user sees "Order Confirmed!" then advance
                                setTimeout(() => { window.location.href = this.successUrl; }, 800);
                                return;
                            }
                        }
                    } catch (e) { /* swallow + retry */ }

                    if (this.attempts >= this.maxAttempts) {
                        this.state = 'timeout';
                        return;
                    }
                    setTimeout(() => this.poll(), 2000);
                },
            };
        }
    </script>
</x-layouts.app>
