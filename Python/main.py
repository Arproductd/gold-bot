# نقطه‌ی ورود ربات: قیمت رو می‌گیره، تو تاریخچه ثبت می‌کنه، در صورت لزوم میانگین
# ماه قبل رو می‌فرسته و در نهایت پیام بروزرسانی قیمت لحظه‌ای رو به تلگرام ارسال می‌کنه.

import asyncio
from datetime import datetime, timedelta

from config import TEHRAN_TZ
from jalali import previous_jalali_month, month_label
from prices import get_gold_price, get_silver_price
from storage import (
    load_prices,
    save_prices,
    append_data,
    month_average,
    load_last_average_month,
    save_last_average_month,
    week_high_low,
    load_last_weekly,
    save_last_weekly,
)
from notifier import send_price_update, send_monthly_average, send_weekly_summary


async def check_monthly_average():
    year, month = previous_jalali_month(datetime.now(TEHRAN_TZ))
    key = f"{year}-{month:02d}"

    if load_last_average_month() == key:
        return

    averages = month_average(year, month)

    if averages is not None:
        avg_gold, avg_silver = averages
        await send_monthly_average(month_label(year, month), avg_gold, avg_silver)

    save_last_average_month(key)


async def check_weekly_summary():
    now = datetime.now(TEHRAN_TZ)

    # جمعه (weekday()==4) ساعت ۹ صبح؛ هفته از شنبه تا جمعه حساب می‌شه
    if now.weekday() != 4 or now.hour != 9:
        return

    week_end = now.date()
    week_start = week_end - timedelta(days=6)

    if load_last_weekly() == str(week_end):
        return

    summary = week_high_low(week_start, week_end)

    if summary is not None:
        await send_weekly_summary(
            summary["gold_high"],
            summary["gold_low"],
            summary["silver_high"],
            summary["silver_low"],
        )

    save_last_weekly(week_end)


async def main():
    gold = get_gold_price()
    silver = get_silver_price()

    append_data(gold, silver)
    await check_monthly_average()
    await check_weekly_summary()

    last = load_prices()
    await send_price_update(gold, silver, last.get("gold", 0), last.get("silver", 0))
    save_prices(gold, silver)


if __name__ == "__main__":
    asyncio.run(main())
