# خواندن و نوشتن داده‌های ربات روی دیسک:
# - price.json: آخرین قیمت ثبت‌شده (برای تشخیص تغییر قیمت)
# - data.jsonl: تاریخچه‌ی کامل قیمت‌ها به همراه زمان هر بار دریافت (هیچ‌وقت پاک نمی‌شه)
# - last_average.txt: آخرین ماهی که میانگینش برای تلگرام ارسال شده

import json
import os
from datetime import datetime
from config import PRICE_FILE, DATA_FILE, LAST_AVERAGE_FILE, LAST_WEEKLY_FILE, TEHRAN_TZ
from jalali import to_jalali


def load_prices():
    if not os.path.exists(PRICE_FILE):
        return {"gold": 0, "silver": 0}

    with open(PRICE_FILE, "r") as f:
        return json.load(f)


def save_prices(gold, silver):
    with open(PRICE_FILE, "w") as f:
        json.dump({"gold": gold, "silver": silver}, f)


def append_data(gold, silver):
    entry = {
        "time": datetime.now(TEHRAN_TZ).isoformat(),
        "gold": gold,
        "silver": silver,
    }

    with open(DATA_FILE, "a", encoding="utf-8") as f:
        f.write(json.dumps(entry, ensure_ascii=False) + "\n")


def month_average(year, month):
    gold_sum = silver_sum = count = 0

    with open(DATA_FILE, "r", encoding="utf-8") as f:
        for line in f:
            entry = json.loads(line)
            entry_date = to_jalali(datetime.fromisoformat(entry["time"]))
            if (entry_date.year, entry_date.month) != (year, month):
                continue

            gold_sum += entry["gold"]
            silver_sum += entry["silver"]
            count += 1

    if count == 0:
        return None

    return round(gold_sum / count), round(silver_sum / count)


def week_high_low(start_date, end_date):
    gold_prices = []
    silver_prices = []

    with open(DATA_FILE, "r", encoding="utf-8") as f:
        for line in f:
            entry = json.loads(line)
            entry_date = datetime.fromisoformat(entry["time"]).date()
            if not (start_date <= entry_date <= end_date):
                continue

            gold_prices.append(entry["gold"])
            silver_prices.append(entry["silver"])

    if not gold_prices:
        return None

    return {
        "gold_high": max(gold_prices),
        "gold_low": min(gold_prices),
        "silver_high": max(silver_prices),
        "silver_low": min(silver_prices),
    }


def load_last_average_month():
    if not os.path.exists(LAST_AVERAGE_FILE):
        return None

    with open(LAST_AVERAGE_FILE, "r") as f:
        return f.read().strip()


def save_last_average_month(month):
    with open(LAST_AVERAGE_FILE, "w") as f:
        f.write(month)


def load_last_weekly():
    if not os.path.exists(LAST_WEEKLY_FILE):
        return None

    with open(LAST_WEEKLY_FILE, "r") as f:
        return f.read().strip()


def save_last_weekly(week_end):
    with open(LAST_WEEKLY_FILE, "w") as f:
        f.write(str(week_end))
