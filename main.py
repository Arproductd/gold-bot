import asyncio
import json
import os
import re
import time
from datetime import datetime

from dotenv import load_dotenv
from selenium import webdriver
from selenium.webdriver.common.by import By
from telegram import Bot

load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
CHAT_ID = os.getenv("CHAT_ID")

bot = Bot(token=BOT_TOKEN)

PRICE_FILE = "price.json"


def get_price():
    options = webdriver.ChromeOptions()
    options.add_argument("--headless=new")

    driver = webdriver.Chrome(options=options)

    driver.get("https://milli.gold")

    time.sleep(5)

    elements = driver.find_elements(By.XPATH, "//*[contains(text(),'ریال')]")

    prices = []

    for e in elements:
        m = re.search(r'([\d,]+)\s*ریال', e.text)
        if m:
            prices.append(int(m.group(1).replace(",", "")))

    driver.quit()

    if prices:
        return prices[0]

    return None


def load_last_price():
    if not os.path.exists(PRICE_FILE):
        return None

    with open(PRICE_FILE, "r") as f:
        return json.load(f)["price"]


def save_price(price):
    with open(PRICE_FILE, "w") as f:
        json.dump({"price": price}, f)


async def check_price():

    now = datetime.now()

    if now.hour < 7 or now.hour >= 24:
        return

    price = get_price()

    if price is None:
        return

    last = load_last_price()

    if last is None:
        save_price(price)
        print("اولین قیمت ذخیره شد.")
        return

    if price == last:
        print("تغییری نداشت.")
        return

    diff = price - last

    if diff > 0:
        msg = f"""
📈 قیمت افزایش یافت

از:
{last:,}

به:
{price:,}

(+{diff:,})
"""

    else:
        msg = f"""
📉 قیمت کاهش یافت

از:
{last:,}

به:
{price:,}

({diff:,})
"""

    await bot.send_message(chat_id=CHAT_ID, text=msg)

    save_price(price)

    print("پیام ارسال شد.")


asyncio.run(check_price())