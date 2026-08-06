# گرفتن قیمت لحظه‌ای طلا و نقره از API‌های بیرونی.

import requests
from config import GOLD_URL, SILVER_URL


def get_gold_price():
    res = requests.get(GOLD_URL, timeout=10)
    res.raise_for_status()
    return int(res.json()["data"]["price18"])


def get_silver_price():
    res = requests.get(SILVER_URL, timeout=10)
    res.raise_for_status()
    return int(res.json()["data"]["price_buy"])
