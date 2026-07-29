import json
import os
import requests
from telegram import Bot
from dotenv import load_dotenv

load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
CHAT_ID = os.getenv("CHAT_ID")

bot = Bot(BOT_TOKEN)

GOLD_URL = "https://milli.gold/api/v1/public/milli-price/detail"
SILVER_URL = "https://melligold.com/api/v1/exchange/buy-sell-price/?format=json&symbol=XAG"

PRICE_FILE = "price.json"


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