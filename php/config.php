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

define('PRICE_FILE', __DIR__ . '/price.json');
define('DATA_FILE', __DIR__ . '/data.jsonl');
define('LAST_AVERAGE_FILE', __DIR__ . '/last_average.txt');
define('LAST_WEEKLY_FILE', __DIR__ . '/last_weekly.txt');

define('TEHRAN_TZ_NAME', 'Asia/Tehran');

function tehran_now()
{
    return new DateTime('now', new DateTimeZone(TEHRAN_TZ_NAME));
}
