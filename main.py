import json
import os
import requests
from datetime import datetime, timezone, timedelta
from telegram import Bot
from dotenv import load_dotenv

load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
CHAT_ID = os.getenv("CHAT_ID")

bot = Bot(BOT_TOKEN)

GOLD_URL = "https://milli.gold/api/v1/public/milli-price/detail"
SILVER_URL = "https://melligold.com/api/v1/exchange/buy-sell-price/?format=json&symbol=XAG"

PRICE_FILE = "price.json"
DATA_FILE = "data.jsonl"
LAST_AVERAGE_FILE = "last_average.txt"
TEHRAN_TZ = timezone(timedelta(hours=3, minutes=30))


def get_gold_price():
    res = requests.get(GOLD_URL, timeout=10)
    res.raise_for_status()
    return int(res.json()["data"]["price18"])


def get_silver_price():
    res = requests.get(SILVER_URL, timeout=10)
    res.raise_for_status()
    return int(res.json()["data"]["price_buy"])


def load_prices():
    if not os.path.exists(PRICE_FILE):
        return {"gold": 0, "silver": 0}

    with open(PRICE_FILE, "r") as f:
        return json.load(f)


def save_prices(gold, silver):
    with open(PRICE_FILE, "w") as f:
        json.dump(
            {
                "gold": gold,
                "silver": silver
            },
            f
        )


def append_data(gold, silver):
    entry = {
        "time": datetime.now(TEHRAN_TZ).isoformat(),
        "gold": gold,
        "silver": silver,
    }

    with open(DATA_FILE, "a", encoding="utf-8") as f:
        f.write(json.dumps(entry, ensure_ascii=False) + "\n")


def previous_month_str(now):
    first_of_this_month = now.replace(day=1)
    last_month_end = first_of_this_month - timedelta(days=1)
    return last_month_end.strftime("%Y-%m")


def month_average(month):
    gold_sum = silver_sum = count = 0

    with open(DATA_FILE, "r", encoding="utf-8") as f:
        for line in f:
            entry = json.loads(line)
            if entry["time"][:7] != month:
                continue

            gold_sum += entry["gold"]
            silver_sum += entry["silver"]
            count += 1

    if count == 0:
        return None

    return round(gold_sum / count), round(silver_sum / count)


def load_last_average_month():
    if not os.path.exists(LAST_AVERAGE_FILE):
        return None

    with open(LAST_AVERAGE_FILE, "r") as f:
        return f.read().strip()


def save_last_average_month(month):
    with open(LAST_AVERAGE_FILE, "w") as f:
        f.write(month)


async def send_monthly_average_if_due():
    month = previous_month_str(datetime.now(TEHRAN_TZ))

    if load_last_average_month() == month:
        return

    averages = month_average(month)

    if averages is not None:
        avg_gold, avg_silver = averages
        text = (
            f"📅 میانگین قیمت ماه {month}\n\n"
            f"🥇 طلا: {avg_gold:,}\n"
            f"🥈 نقره: {avg_silver:,}"
        )
        await bot.send_message(chat_id=CHAT_ID, text=text)

    save_last_average_month(month)


def format_line(label, price, last_price):
    if last_price == 0 or price == last_price:
        return f"{label}\n{price:,}\n\n"

    diff = price - last_price
    emoji = "📈" if diff > 0 else "📉"

    return (
        f"{label}\n"
        f"{last_price:,} ➜ {price:,}\n"
        f"{emoji} {diff:+,}\n\n"
    )


async def main():
    gold = get_gold_price()
    silver = get_silver_price()

    append_data(gold, silver)
    await send_monthly_average_if_due()

    last = load_prices()

    last_gold = last.get("gold", 0)
    last_silver = last.get("silver", 0)

    text = "📊 بروزرسانی بازار\n\n"
    text += format_line("🥇 طلا", gold, last_gold)
    text += format_line("🥈 نقره", silver, last_silver)

    await bot.send_message(chat_id=CHAT_ID, text=text.strip())

    save_prices(gold, silver)


if __name__ == "__main__":
    import asyncio
    asyncio.run(main())