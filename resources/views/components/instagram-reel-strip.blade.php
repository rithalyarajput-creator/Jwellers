@props(['reels'])

@if($reels->isEmpty())
    {{-- Render nothing — keeps PDP clean for products with no matched reels --}}
@else
    @php
        $igHandle = \App\Models\Setting::get('social_instagram_handle', 'jwellers');
        $igHandle = ltrim((string) $igHandle, '@') ?: 'jwellers';
    @endphp

    <section class="ig-reel-strip" aria-labelledby="ig-reel-strip-heading">
        <style>
            .ig-reel-strip {
                margin: 1.25rem 0;
                background: #fff;
                border: 1px solid #e5e5e5;
                border-radius: 12px;
                padding: 1rem 1.1rem 1.1rem;
            }
            .ig-reel-strip .ig-strip-header {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 0.75rem;
                margin-bottom: 0.85rem;
            }
            .ig-reel-strip .ig-strip-title {
                display: flex;
                align-items: center;
                gap: 0.55rem;
                font-size: 14px;
                font-weight: 700;
                color: #0F1111;
                margin: 0;
            }
            .ig-reel-strip .ig-strip-title svg {
                width: 18px; height: 18px; color: #c9a227;
            }
            .ig-reel-strip .ig-follow-btn {
                display: inline-flex;
                align-items: center;
                gap: 0.4rem;
                padding: 0.35rem 0.75rem;
                border-radius: 9999px;
                font-size: 12px;
                font-weight: 600;
                color: #fff;
                text-decoration: none;
                background: linear-gradient(45deg, #f09433 0%, #e6683c 25%, #dc2743 50%, #cc2366 75%, #bc1888 100%);
                box-shadow: 0 1px 3px rgba(0,0,0,0.12);
                transition: transform 0.15s ease, box-shadow 0.15s ease;
                white-space: nowrap;
            }
            .ig-reel-strip .ig-follow-btn:hover {
                transform: translateY(-1px);
                box-shadow: 0 3px 8px rgba(0,0,0,0.16);
            }
            .ig-reel-strip .ig-follow-btn svg {
                width: 14px; height: 14px;
            }

            .ig-reel-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 0.6rem;
            }
            .ig-reel-tile {
                position: relative;
                display: block;
                aspect-ratio: 9 / 16;
                border-radius: 12px;
                overflow: hidden;
                background: #f3f4f6;
                text-decoration: none;
                color: inherit;
                transition: transform 0.2s ease, box-shadow 0.2s ease;
                box-shadow: 0 1px 3px rgba(0,0,0,0.06);
            }
            .ig-reel-tile:hover {
                transform: translateY(-3px);
                box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            }
            .ig-reel-tile img.ig-reel-thumb {
                width: 100%;
                height: 100%;
                object-fit: cover;
                display: block;
            }
            .ig-reel-tile .ig-reel-overlay {
                position: absolute;
                inset: 0;
                background: linear-gradient(180deg, rgba(0,0,0,0.05) 0%, rgba(0,0,0,0) 25%, rgba(0,0,0,0.55) 100%);
                pointer-events: none;
            }
            .ig-reel-tile .ig-reel-play {
                position: absolute;
                top: 8px; right: 8px;
                width: 28px; height: 28px;
                border-radius: 50%;
                background: rgba(255,255,255,0.9);
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 1px 3px rgba(0,0,0,0.18);
            }
            .ig-reel-tile .ig-reel-play svg {
                width: 14px; height: 14px;
                color: #0F1111;
                margin-left: 2px;
            }
            .ig-reel-tile .ig-reel-caption {
                position: absolute;
                left: 8px; right: 8px; bottom: 8px;
                color: #fff;
                font-size: 11px;
                line-height: 1.3;
                font-weight: 500;
                text-shadow: 0 1px 2px rgba(0,0,0,0.45);
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                overflow: hidden;
            }

            @media (max-width: 640px) {
                .ig-reel-strip { padding: 0.85rem 0.95rem 1rem; }
                .ig-reel-grid {
                    display: flex;
                    overflow-x: auto;
                    scroll-snap-type: x mandatory;
                    -webkit-overflow-scrolling: touch;
                    gap: 0.55rem;
                    padding-bottom: 0.25rem;
                    scrollbar-width: none;
                }
                .ig-reel-grid::-webkit-scrollbar { display: none; }
                .ig-reel-tile {
                    flex: 0 0 calc(50% - 0.275rem);
                    scroll-snap-align: start;
                }
            }
        </style>

        <div class="ig-strip-header">
            <h3 id="ig-reel-strip-heading" class="ig-strip-title">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <rect x="3" y="3" width="18" height="18" rx="5" stroke-width="2"/>
                    <circle cx="12" cy="12" r="4" stroke-width="2"/>
                    <circle cx="17.5" cy="6.5" r="1" fill="currentColor"/>
                </svg>
                On Instagram
            </h3>
            {{-- Instagram Follow button. Tap-target is the whole pill (mobile-friendly).
                 Opens the configured profile in a new tab. --}}
            <a href="https://instagram.com/{{ $igHandle }}"
               target="_blank"
               rel="noopener"
               class="ig-follow-btn"
               aria-label="Follow @{{ $igHandle }} on Instagram">
                <svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                Follow
            </a>
        </div>

        <div class="ig-reel-grid">
            @foreach($reels as $reel)
                <a href="{{ $reel->permalink }}"
                   target="_blank"
                   rel="noopener"
                   class="ig-reel-tile"
                   aria-label="Watch reel on Instagram">
                    <img class="ig-reel-thumb"
                         src="{{ $reel->thumbnail_url }}"
                         alt="{{ \Illuminate\Support\Str::limit($reel->short_caption, 80) ?: 'Instagram reel' }}"
                         loading="lazy"
                         decoding="async"
                         referrerpolicy="no-referrer">
                    <div class="ig-reel-overlay"></div>
                    <div class="ig-reel-play" aria-hidden="true">
                        <svg fill="currentColor" viewBox="0 0 20 20"><path d="M5 4.5v11l9-5.5z"/></svg>
                    </div>
                    @if($reel->short_caption)
                        <div class="ig-reel-caption">{{ \Illuminate\Support\Str::limit($reel->short_caption, 60) }}</div>
                    @endif
                </a>
            @endforeach
        </div>
    </section>
@endif
