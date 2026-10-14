{{--
    Typography for right-to-left locales (Persian/Dari, Arabic, Hebrew).

    Vazirmatn is self-hosted under public/fonts/vazirmatn so no storefront page or
    admin screen depends on a third-party font CDN. It carries every letter Dari is
    written with but not the sign of the Afghani, so that one glyph comes from a
    self-hosted subset of Noto Sans Arabic, pulled in for U+060B only. The rules below only take over
    while the document direction is RTL, so every Latin locale keeps the
    Poppins/Inter stack the theme was designed with, and the icon fonts are left
    untouched so their glyphs keep rendering.
--}}
<style>
    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-arabic-400-normal.woff2') }}') format('woff2');
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+1EE00-1EEFF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-400-normal.woff2') }}') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-ext-400-normal.woff2') }}') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-arabic-500-normal.woff2') }}') format('woff2');
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+1EE00-1EEFF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-500-normal.woff2') }}') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 500;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-ext-500-normal.woff2') }}') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-arabic-600-normal.woff2') }}') format('woff2');
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+1EE00-1EEFF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-600-normal.woff2') }}') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 600;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-ext-600-normal.woff2') }}') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-arabic-700-normal.woff2') }}') format('woff2');
        unicode-range: U+0600-06FF, U+0750-077F, U+0870-088E, U+0890-0891, U+0897-08E1, U+08E3-08FF, U+200C-200E, U+2010-2011, U+204F, U+2E41, U+FB50-FDFF, U+FE70-FE74, U+FE76-FEFC, U+102E0-102FB, U+10E60-10E7E, U+1EE00-1EEFF;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-700-normal.woff2') }}') format('woff2');
        unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+0304, U+0308, U+0329, U+2000-206F, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD;
    }

    @font-face {
        font-family: 'Vazirmatn';
        font-style: normal;
        font-weight: 700;
        font-display: swap;
        src: url('{{ asset('fonts/vazirmatn/vazirmatn-latin-ext-700-normal.woff2') }}') format('woff2');
        unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+0304, U+0308, U+0329, U+1D00-1DBF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+20AD-20C0, U+2113, U+2C60-2C7F, U+A720-A7FF;
    }

    @font-face {
        font-family: 'Afghani Sign';
        font-style: normal;
        font-weight: 400;
        font-display: swap;
        src: url('{{ asset('fonts/noto-arabic/afghani-sign.woff2') }}') format('woff2');
        unicode-range: U+060B;
    }

    html[dir="rtl"] body,
    html[dir="rtl"] button,
    html[dir="rtl"] input,
    html[dir="rtl"] optgroup,
    html[dir="rtl"] select,
    html[dir="rtl"] textarea {
        font-family: 'Vazirmatn', 'Afghani Sign', 'Poppins', 'Inter', 'Segoe UI', Tahoma, Arial, sans-serif;
    }

    html[dir="rtl"] :not([class^="icon-"]):not([class*=" icon-"]) {
        font-family: 'Vazirmatn', 'Afghani Sign', 'Poppins', 'Inter', 'Segoe UI', Tahoma, Arial, sans-serif;
    }
</style>
