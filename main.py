# نقطه‌ی ورود ربات: قیمت رو می‌گیره، تو تاریخچه ثبت می‌کنه، در صورت لزوم میانگین
# ماه قبل رو می‌فرسته و در نهایت پیام بروزرسانی قیمت لحظه‌ای رو به تلگرام ارسال می‌کنه.

import asyncio
from datetime import datetime, timedelta

from config import TEHRAN_TZ
from prices import get_gold_price, get_silver_price
from storage import (
    load_prices,
    save_prices,
    append_data,
    month_average,
    load_last_average_month,
    save_last_average_month,
)
from notifier import send_price_update, send_monthly_average


def previous_month_str(now):
    first_of_this_month = now.replace(day=1)
    last_month_end = first_of_this_month - timedelta(days=1)
    return last_month_end.strftime("%Y-%m")


async def check_monthly_average():
    month = previous_month_str(datetime.now(TEHRAN_TZ))

    if load_last_average_month() == month:
        return

    averages = month_average(month)

    if averages is not None:
        avg_gold, avg_silver = averages
        await send_monthly_average(month, avg_gold, avg_silver)

    save_last_average_month(month)


async def main():
    gold = get_gold_price()
    silver = get_silver_price()

    append_data(gold, silver)
    await check_monthly_average()

    last = load_prices()
    await send_price_update(gold, silver, last.get("gold", 0), last.get("silver", 0))
    save_prices(gold, silver)


if __name__ == "__main__":
    asyncio.run(main())
