<?php
// تنظیمات مشترک ربات: توکن‌ها، آدرس API‌ها، مسیر فایل‌های ذخیره‌سازی.

// به‌جای نمایش خطاها (که تو Cron جایی دیده نمی‌شن)، توی error.log همین پوشه ذخیره‌شون کن.
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$error_log_path = __DIR__ . '/error.log';

// اگه لاگ خیلی بزرگ شد (بیشتر از ۲ مگابایت)، از نو شروع کن تا فضا هدر نره.
if (file_exists($error_log_path) && filesize($error_log_path) > 2 * 1024 * 1024) {
    file_put_contents($error_log_path, '');
}

ini_set('error_log', $error_log_path);

function load_env($path)
{
    if (!file_exists($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        putenv(trim($key) . '=' . trim($value));
    }
}

load_env(__DIR__ . '/.env');

define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('CHAT_ID', getenv('CHAT_ID') ?: '');

// چند مقصد اضافی برای ارسال پیام (مثل آیدی کانال)، جدا شده با کاما در .env
// مثال: CHAT_IDS=912109494,-1001234567890
$chat_ids_raw = getenv('CHAT_IDS') ?: CHAT_ID;
$GLOBALS['CHAT_IDS'] = array_values(array_filter(array_map('trim', explode(',', $chat_ids_raw))));

if (BOT_TOKEN === '' || empty($GLOBALS['CHAT_IDS'])) {
    error_log('تنظیمات ناقصه: BOT_TOKEN یا CHAT_ID خالیه — فایل .env رو چک کن.');
}

define('GOLD_URL', 'https://milli.gold/api/v1/public/milli-price/detail');
define('SILVER_URL', 'https://melligold.com/api/v1/exchange/buy-sell-price/?format=json&symbol=XAG');
define('TGJU_URL', 'https://call2.tgju.org/ajax.json?rev=HJogHjCOgu6awK2rIJN09u8MtOOogD3jj5knmgw12qF8oL9G43FTscPQs6pu');

// برای محاسبه‌ی قیمت ذاتی طلای ۱۸ عیار از روی انس جهانی و نرخ دلار
define('GRAMS_PER_TROY_OUNCE', 31.1034768);
define('GOLD_PURITY_18K', 0.75);

define('PRICE_FILE', __DIR__ . '/price.json');
define('DATA_FILE', __DIR__ . '/data.jsonl');
define('LAST_AVERAGE_FILE', __DIR__ . '/last_average.txt');
define('LAST_WEEKLY_FILE', __DIR__ . '/last_weekly.txt');
define('LAST_MORNING_FILE', __DIR__ . '/last_morning.txt');
define('LAST_MARKET_OPEN_FILE', __DIR__ . '/last_market_open.txt');
define('LAST_CURRENCY_UPDATE_FILE', __DIR__ . '/last_currency_update.txt');

// بین این دو ساعت پیامی ارسال نمی‌شه؛ اولین پیام بعد از این بازه، خلاصه‌ی صبحگاهیه
define('QUIET_HOURS_START', '00:03');
define('QUIET_HOURS_END', '07:03');

// ساعت باز شدن بازار؛ فقط شنبه تا چهارشنبه (پنجشنبه و جمعه بازار تعطیله)
define('MARKET_OPEN_TIME', '11:03');

// بین پایان سکوت و باز شدن بازار (۰۷:۰۳ تا ۱۱:۰۳)، پیام‌های عادی ارزها (دلار/یورو/درهم/یوان/لیر) رو
// نشون نمی‌دن؛ به‌جاش دقیقاً همین ساعت‌ها یه پیام مخصوص فقط-ارزها می‌ره
define('CURRENCY_UPDATE_TIMES', ['07:03', '10:03']);

define('TEHRAN_TZ_NAME', 'Asia/Tehran');

function tehran_now()
{
    return new DateTime('now', new DateTimeZone(TEHRAN_TZ_NAME));
}
