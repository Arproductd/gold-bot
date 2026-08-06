# توابع کمکی تقویم شمسی: تبدیل تاریخ میلادی به شمسی و محاسبه‌ی ماه قبل.

import jdatetime

MONTH_NAMES = [
    "فروردین", "اردیبهشت", "خرداد", "تیر", "مرداد", "شهریور",
    "مهر", "آبان", "آذر", "دی", "بهمن", "اسفند",
]


def to_jalali(dt):
    return jdatetime.date.fromgregorian(date=dt.date())


def month_label(year, month):
    return f"{MONTH_NAMES[month - 1]} {year}"


def previous_jalali_month(now):
    today = to_jalali(now)
    year, month = today.year, today.month - 1

    if month == 0:
        year -= 1
        month = 12

    return year, month
