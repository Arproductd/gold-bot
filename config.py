# تنظیمات مشترک ربات: توکن‌ها، آدرس API‌ها، مسیر فایل‌های ذخیره‌سازی و منطقه‌ی زمانی.

import os
from datetime import timezone, timedelta
from dotenv import load_dotenv

load_dotenv()

BOT_TOKEN = os.getenv("BOT_TOKEN")
CHAT_ID = os.getenv("CHAT_ID")

# چند مقصد اضافی برای ارسال پیام (مثل آیدی کانال)، جدا شده با کاما در .env
# مثال: CHAT_IDS=912109494,-1001234567890
CHAT_IDS = [c.strip() for c in os.getenv("CHAT_IDS", str(CHAT_ID or "")).split(",") if c.strip()]

GOLD_URL = "https://milli.gold/api/v1/public/milli-price/detail"
SILVER_URL = "https://melligold.com/api/v1/exchange/buy-sell-price/?format=json&symbol=XAG"

PRICE_FILE = "price.json"
DATA_FILE = "data.jsonl"
LAST_AVERAGE_FILE = "last_average.txt"
LAST_WEEKLY_FILE = "last_weekly.txt"

TEHRAN_TZ = timezone(timedelta(hours=3, minutes=30))
