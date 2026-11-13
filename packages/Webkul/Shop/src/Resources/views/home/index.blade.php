@php
    $channel = core()->getCurrentChannel();

    $pageTitle = $channel->home_seo['meta_title'] ?? config('app.name');

    $hasImageCarousel = collect($sections)->contains(
        fn ($section) => $section->type === $section::IMAGE_CAROUSEL
            && ! empty(data_get($section->options, 'images.0.image'))
    );
@endphp

<!-- SEO Meta Content -->
@push ('meta')
    <meta
        name="title"
        content="{{ $pageTitle }}"
    />

    <meta
        name="description"
        content="{{ $channel->home_seo['meta_description'] ?? '' }}"
    />

    <meta
        name="keywords"
        content="{{ $channel->home_seo['meta_keywords'] ?? '' }}"
    />
@endPush

@push('scripts')
    @if(! empty($categories))
        <script>
            localStorage.setItem('categories', JSON.stringify(@json($categories)));
        </script>
    @endif
@endpush

<x-shop::layouts>
    <!-- Page Title -->
    <x-slot:title>
        {{ $pageTitle }}
    </x-slot>

    @if (! $hasImageCarousel)
        <section
            class="empiremart-hero"
            aria-labelledby="empiremart-hero-title"
        >
            <div class="empiremart-hero__inner">
                <div class="empiremart-hero__content">
                    <span class="empiremart-hero__brand">
                        {{ config('app.name') }}
                    </span>

                    <h1
                        id="empiremart-hero-title"
                        class="empiremart-hero__title"
                    >
                        @lang('shop::app.home.index.hero-title')
                    </h1>

                    <p class="empiremart-hero__description">
                        @lang('shop::app.home.index.hero-description')
                    </p>

                    <form
                        action="{{ route('shop.search.index') }}"
                        class="empiremart-hero__search"
                        role="search"
                    >
                        <label
                            for="empiremart-home-search"
                            class="sr-only"
                        >
                            @lang('shop::app.components.layouts.header.desktop.bottom.search')
                        </label>

                        <span
                            class="icon-search empiremart-hero__search-icon"
                            aria-hidden="true"
                        ></span>

                        <input
                            id="empiremart-home-search"
                            type="search"
                            name="query"
                            value="{{ request('query') }}"
                            minlength="{{ core()->getConfigData('catalog.products.search.min_query_length') }}"
                            maxlength="{{ core()->getConfigData('catalog.products.search.max_query_length') }}"
                            placeholder="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                            aria-label="@lang('shop::app.components.layouts.header.desktop.bottom.search-text')"
                            pattern="[^\\]+"
                            required
                        >

                        <button type="submit">
                            @lang('shop::app.components.layouts.header.desktop.bottom.search')
                        </button>
                    </form>
                </div>
            </div>
        </section>
    @endif

    <!-- Loop over the storefront sections -->
    @foreach ($sections as $section)
        @php ($data = $section->options) @endphp

        {{-- Only the types this page renders; the layout marks the ones it draws. --}}
        @php ($marks = ($preview ?? false) && in_array($section->type, [
            $section::IMAGE_CAROUSEL,
            $section::STATIC_CONTENT,
            $section::CATEGORY_CAROUSEL,
            $section::PRODUCT_CAROUSEL,
        ]))

        @if ($marks)
            <div
                data-section-id="{{ $section->id }}"
                data-section-name="{{ $section->name }}"
            >
        @endif

        <!-- Static Content -->
        @switch ($section->type)
            @case ($section::IMAGE_CAROUSEL)
                <!-- Image Carousel -->
                <x-shop::carousel
                    :options="$data"
                    aria-label="{{ trans('shop::app.home.index.image-carousel') }}"
                />

                @break
            @case ($section::STATIC_CONTENT)
                <!-- Push Style -->
                @if (! empty($data['css']))
                    @push ('styles')
                        <style>
                            {!! $data['css'] !!}
                        </style>
                    @endpush
                @endif

                <!-- Render HTML -->
                @if (! empty($data['html']))
                    <div class="empiremart-home-content">
                        {!! $data['html'] !!}
                    </div>
                @endif

                @break
            @case ($section::CATEGORY_CAROUSEL)
                <!-- Categories carousel -->
                <x-shop::categories.carousel
                    :title="$data['title'] ?? ''"
                    :src="route('shop.api.categories.index', $data['filters'] ?? [])"
                    :navigation-link="route('shop.home.index')"
                    aria-label="{{ trans('shop::app.home.index.categories-carousel') }}"
                />

                @break
            @case ($section::PRODUCT_CAROUSEL)
                <!-- Product Carousel -->
                <x-shop::products.carousel
                    :title="$data['title'] ?? ''"
                    :src="route('shop.api.products.index', $data['filters'] ?? [])"
                    :navigation-link="route('shop.search.index', $data['filters'] ?? [])"
                    aria-label="{{ trans('shop::app.home.index.product-carousel') }}"
                />

                @break
        @endswitch

        @if ($marks)
            </div>
        @endif
    @endforeach

    @if ($preview ?? false)
        @include('shop::home.preview-bridge')
    @endif
</x-shop::layouts>
