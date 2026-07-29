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
SILVER_URL = "https://melligold.com/api/v1/exchange/buy-sell-price/?format=api&symbol=XAG"

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


async def main():
    gold = get_gold_price()
    silver = get_silver_price()

    last = load_prices()

    last_gold = last.get("gold", 0)
    last_silver = last.get("silver", 0)

    if last_gold == 0 and last_silver == 0:
        save_prices(gold, silver)
        return

    if gold == last_gold and silver == last_silver:
        return

    text = "📊 بروزرسانی بازار\n\n"

    if gold != last_gold:
        diff = gold - last_gold
        emoji = "📈" if diff > 0 else "📉"

        text += (
            f"🥇 طلا\n"
            f"{last_gold:,} ➜ {gold:,}\n"
            f"{emoji} {diff:+,}\n\n"
        )

    if silver != last_silver:
        diff = silver - last_silver
        emoji = "📈" if diff > 0 else "📉"

        text += (
            f"🥈 نقره\n"
            f"{last_silver:,} ➜ {silver:,}\n"
            f"{emoji} {diff:+,}\n"
        )

    await bot.send_message(chat_id=CHAT_ID, text=text)

    save_prices(gold, silver)


if __name__ == "__main__":
    import asyncio
    asyncio.run(main())