# ارسال پیام به تلگرام: نمونه‌ی ربات و قالب‌بندی متن پیام‌های قیمت و میانگین ماهانه.

from telegram import Bot
from config import BOT_TOKEN, CHAT_ID

bot = Bot(BOT_TOKEN)


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


async def send_price_update(gold, silver, last_gold, last_silver):
    text = "📊 بروزرسانی بازار\n\n"
    text += format_line("🥇 طلا", gold, last_gold)
    text += format_line("🥈 نقره", silver, last_silver)

    await bot.send_message(chat_id=CHAT_ID, text=text.strip())


async def send_monthly_average(month, avg_gold, avg_silver):
    text = (
        f"📅 میانگین قیمت ماه {month}\n\n"
        f"🥇 طلا: {avg_gold:,}\n"
        f"🥈 نقره: {avg_silver:,}"
    )

    await bot.send_message(chat_id=CHAT_ID, text=text)
