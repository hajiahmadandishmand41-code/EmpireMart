import Flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import { Arabic } from "flatpickr/dist/l10n/ar.js";
import { Persian } from "flatpickr/dist/l10n/fa.js";
import { Hebrew } from "flatpickr/dist/l10n/he.js";
import Dari from "../../locales/fa_AF.js";

export default {
    install: (app) => {
        window.Flatpickr = Flatpickr;

        /**
         * The storefront is read in Dari, so the calendar it opens is written in
         * Dari too, as are the other right to left languages it can be read in.
         * English is flatpickr's own default and is left alone.
         */
        const localeMap = {
            ar: Arabic,
            fa: Persian,
            fa_AF: Dari,
            he: Hebrew,
        };

        const lang = document.documentElement.lang || "en";

        const locale = localeMap[lang] || localeMap[String(lang).split("_")[0]] || null;

        if (locale) {
            window.Flatpickr.localize(locale);
        }
    },
};
