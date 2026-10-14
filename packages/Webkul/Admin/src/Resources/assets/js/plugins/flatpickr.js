import Flatpickr from "flatpickr";
import "flatpickr/dist/flatpickr.css";
import darkThemeCss from "flatpickr/dist/themes/dark.css?inline";
import { Spanish } from "flatpickr/dist/l10n/es.js";
import { Catalan } from "flatpickr/dist/l10n/cat.js"
import { Arabic } from "flatpickr/dist/l10n/ar.js";
import { Persian } from "flatpickr/dist/l10n/fa.js";
import Dari from "../../locales/fa_AF.js";
import { Turkish } from "flatpickr/dist/l10n/tr.js";
import { Bengali } from "flatpickr/dist/l10n/bn.js";
import { German } from "flatpickr/dist/l10n/de.js";
import { English } from "flatpickr/dist/l10n/default.js";
import { French } from "flatpickr/dist/l10n/fr.js";
import { Hebrew } from "flatpickr/dist/l10n/he.js";
import { Hindi } from "flatpickr/dist/l10n/hi.js";
import { Italian } from "flatpickr/dist/l10n/it.js";
import { Japanese } from "flatpickr/dist/l10n/ja.js";
import { Dutch } from "flatpickr/dist/l10n/nl.js";
import { Polish } from "flatpickr/dist/l10n/pl.js";
import { Portuguese } from "flatpickr/dist/l10n/pt.js";
import { Russian } from "flatpickr/dist/l10n/ru.js";
import { Sinhala } from "flatpickr/dist/l10n/si.js";
import { Ukrainian } from "flatpickr/dist/l10n/uk.js";
import { Chinese } from "flatpickr/dist/l10n/zh.js";

export default {
    install: (app) => {
        window.Flatpickr = Flatpickr;

        const setLocaleFromLang = () => {
            const lang = document.documentElement.lang || "en";

            const localeMap = {
                es: Spanish,
                ca: Catalan,
                ar: Arabic,
                fa: Persian,
                fa_AF: Dari,
                tr: Turkish,
                bn: Bengali,
                de: German,
                en: English,
                fr: French,
                he: Hebrew,
                hi: Hindi,
                it: Italian,
                ja: Japanese,
                nl: Dutch,
                pl: Polish,
                pt: Portuguese,
                ru: Russian,
                sin: Sinhala,
                uk: Ukrainian,
                zh: Chinese,
            };

            /**
             * A regional code such as fa_IR or en_GB is not in the map, but the
             * language it starts with is, so the calendar still reads in the
             * language the panel is written in.
             */
            const locale = localeMap[lang]
                || localeMap[String(lang).split("_")[0]]
                || null;

            if (locale) {
                window.Flatpickr.localize(locale);
            }
        };

        setLocaleFromLang();

        const changeTheme = (theme) => {
            const existingTheme = document.getElementById("flatpickr");

            if (existingTheme) {
                existingTheme.remove();
            }

            if (theme === "light") {
                return;
            }

            const styleElement = document.createElement("style");
            styleElement.id = "flatpickr";
            styleElement.textContent = darkThemeCss;

            document.head.appendChild(styleElement);
        };

        const currentTheme = document.documentElement.classList.contains("dark")
            ? "dark"
            : "light";

        changeTheme(currentTheme);

        window.emitter.on("change-theme", (theme) => changeTheme(theme));
    },
};
