<v-categories-carousel
    src="{{ $src }}"
    title="{{ $title }}"
    navigation-link="{{ $navigationLink ?? '' }}"
>
    <x-shop::shimmer.categories.carousel
        :count="8"
        :navigation-link="$navigationLink ?? false"
    />
</v-categories-carousel>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-categories-carousel-template"
    >
        <div
            class="empiremart-categories-section container mt-14 max-lg:px-8 max-md:mt-7 max-md:!px-0 max-sm:mt-5"
            v-if="! isLoading && categories?.length"
        >
            <div class="empiremart-section-heading" v-if="title">
                <h2 class="empiremart-section-heading__title" v-text="title"></h2>
            </div>

            <div class="relative">
                <div
                    ref="swiperContainer"
                    class="empiremart-category-list scrollbar-hide flex gap-10 overflow-auto scroll-smooth max-lg:gap-4"
                >
                    <div
                        class="empiremart-category-card grid min-w-[120px] max-w-[120px] grid-cols-1 justify-items-center gap-4 font-medium max-md:min-w-20 max-md:max-w-20 max-md:gap-2.5 max-md:first:ml-4 max-sm:min-w-[60px] max-sm:max-w-[60px] max-sm:gap-1.5"
                        v-for="category in categories"
                    >
                        <a
                            :href="category.slug"
                            class="empiremart-category-card__image h-[110px] w-[110px] rounded-full bg-zinc-100 max-md:h-20 max-md:w-20 max-sm:h-[60px] max-sm:w-[60px]"
                            :aria-label="category.name"
                        >
                            <x-shop::media.images.lazy
                                ::src="category.logo?.small_image_url || fallback"
                                ::srcset="`
                                    ${(category.logo?.small_image_url || fallback)} 60w,
                                    ${(category.logo?.medium_image_url || fallback)} 110w,
                                    ${(category.logo?.large_image_url || fallback)} 300w
                                `"
                                sizes="(max-width: 640px) 108px, 154px"
                                width="154"
                                height="122"
                                class="empiremart-category-card__image-element w-full rounded-full max-sm:h-[60px] max-sm:w-[60px]"
                                ::alt="category.name"
                            />
                        </a>

                        <a
                            :href="category.slug"
                            class=""
                        >
                            <p
                                class="empiremart-category-card__title text-center text-lg text-black max-md:text-base max-md:font-normal max-sm:text-sm"
                                v-text="category.name"
                            >
                            </p>
                        </a>
                    </div>
                </div>

                <span
                    class="empiremart-carousel-arrow icon-arrow-left-stylish rtl:icon-arrow-right-stylish absolute -start-10 top-9 flex h-[50px] w-[50px] cursor-pointer items-center justify-center rounded-full border border-black bg-white text-2xl transition hover:bg-black hover:text-white max-lg:-left-7 max-md:hidden"
                    role="button"
                    aria-label="@lang('shop::components.carousel.previous')"
                    tabindex="0"
                    @click="swipeLeft"
                >
                </span>

                <span
                    class="empiremart-carousel-arrow icon-arrow-right-stylish rtl:icon-arrow-left-stylish absolute -end-6 top-9 flex h-[50px] w-[50px] cursor-pointer items-center justify-center rounded-full border border-black bg-white text-2xl transition hover:bg-black hover:text-white max-lg:-right-7 max-md:hidden"
                    role="button"
                    aria-label="@lang('shop::components.carousel.next')"
                    tabindex="0"
                    @click="swipeRight"
                >
                </span>
            </div>
        </div>

        <!-- Category Carousel Shimmer -->
        <template v-if="isLoading">
            <x-shop::shimmer.categories.carousel
                :count="8"
                :navigation-link="$navigationLink ?? false"
            />
        </template>
    </script>

    <script type="module">
        app.component('v-categories-carousel', {
            template: '#v-categories-carousel-template',

            props: [
                'src',
                'title',
                'navigationLink',
            ],

            data() {
                return {
                    isLoading: true,

                    categories: [],

                    offset: 323,

                    fallback: "{{ bagisto_asset('images/small-product-placeholder.webp') }}"
                };
            },

            mounted() {
                this.getCategories();
            },

            methods: {
                getCategories() {
                    this.$axios.get(this.src)
                        .then(response => {
                            this.isLoading = false;

                            this.categories = response.data.data;
                        }).catch(error => {
                            console.log(error);
                        });
                },

                swipeLeft() {
                    const container = this.$refs.swiperContainer;

                    container.scrollLeft -= this.offset;
                },

                swipeRight() {
                    const container = this.$refs.swiperContainer;

                    container.scrollLeft += this.offset;
                },
            },
        });
    </script>
@endPushOnce
