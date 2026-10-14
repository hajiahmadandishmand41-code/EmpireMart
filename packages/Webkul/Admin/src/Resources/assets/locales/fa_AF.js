/**
 * The Dari wording of a date picker.
 *
 * flatpickr ships Farsi (fa), which spells the Gregorian months the Iranian
 * way, and no Afghan locale at all. Afghan month names are their own set and
 * the week starts on Saturday, so the calendar is written out here.
 */
const Dari = {
    weekdays: {
        shorthand: ["شنبه", "یک‌شنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنج‌شنبه", "جمعه"],
        longhand: ["شنبه", "یک‌شنبه", "دوشنبه", "سه‌شنبه", "چهارشنبه", "پنج‌شنبه", "جمعه"],
    },

    months: {
        shorthand: ["جنوری", "فبروری", "مارچ", "اپریل", "می", "جون", "جولای", "اگست", "سپتمبر", "اکتوبر", "نومبر", "دسمبر"],
        longhand: ["جنوری", "فبروری", "مارچ", "اپریل", "می", "جون", "جولای", "اگست", "سپتمبر", "اکتوبر", "نومبر", "دسمبر"],
    },

    firstDayOfWeek: 6,

    weekAbbreviation: "هفته",

    rangeSeparator: " تا ",

    scrollTitle: "برای افزایش، اسکرول کنید",

    toggleTitle: "برای تغییر، کلیک کنید",

    amPM: ["ق.ظ", "ب.ظ"],

    yearAriaLabel: "سال",

    monthAriaLabel: "ماه",

    hourAriaLabel: "ساعت",

    minuteAriaLabel: "دقیقه",

    ordinal: () => "",
};

export default Dari;
