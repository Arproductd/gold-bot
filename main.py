import json
import os
import requests
from telegram import Bot
from dotenv import load_dotenv

load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
CHAT_ID = os.getenv("CHAT_ID")

URL = "https://milli.gold/api/v1/public/milli-price/detail"
PRICE_FILE = "price.json"

bot = Bot(BOT_TOKEN)


def get_price():
    res = requests.get(URL, timeout=10)
    res.raise_for_status()

    data = res.json()

    return int(data["data"]["price18"])


def load_price():
    if not os.path.exists(PRICE_FILE):
        return None

    with open(PRICE_FILE, "r") as f:
        return json.load(f)["price"]


def save_price(price):
    with open(PRICE_FILE, "w") as f:
        json.dump({"price": price}, f)


async def main():

    price = get_price()
    last = load_price()

    if last is None:
        save_price(price)
        return

    if price == last:
        return

    diff = price - last

    if diff > 0:
        text = f"""📈 قیمت افزایش یافت

از:
{last:,}

به:
{price:,}

(+{diff:,})
"""

    else:
        text = f"""📉 قیمت کاهش یافت

از:
{last:,}

به:
{price:,}

({diff:,})
"""

    await bot.send_message(CHAT_ID, text)

    save_price(price)


if __name__ == "__main__":
    import asyncio
    asyncio.run(main())