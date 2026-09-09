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
define('TABLO_GOLD_URL', 'https://tablo.gold/api/v1/gold-prices');
define('TABLO_REFERENCE_URL', 'https://tablo.gold/api/v1/reference');
define('TABLO_API_KEY', getenv('TABLO_API_KEY') ?: '');

// نسخه‌ی مقیاسِ قیمت طلا که توی price.json ذخیره می‌شه. با بالا بردن این عدد،
// مقدار ذخیره‌شده‌ی نسخه‌ی قبل یک بار تبدیل می‌شه و پیام بعد از deploy پرش جعلی نمی‌ده.
define('PRICE_SCALE_VERSION', 2);

define('PRICE_FILE', __DIR__ . '/price.json');
define('DATA_FILE', __DIR__ . '/data.jsonl');
define('LAST_AVERAGE_FILE', __DIR__ . '/last_average.txt');
define('LAST_WEEKLY_FILE', __DIR__ . '/last_weekly.txt');
define('LAST_MORNING_FILE', __DIR__ . '/last_morning.txt');
define('LAST_MARKET_OPEN_FILE', __DIR__ . '/last_market_open.txt');
define('LAST_NIGHT_FILE', __DIR__ . '/last_night.txt');

// بازه‌ی سکوت. اولین اجرای داخل این بازه پیام آخر شب رو می‌فرسته و بقیه‌ی شب ساکته؛
// اولین اجرای بعد از این بازه، خلاصه‌ی صبحگاهیه. هیچ‌کدوم به دقیقه‌ی دقیق گره نخوردن،
// پس هر زمان‌بندی‌ای برای کرون (ساعتی، ۱۵ دقیقه‌ای، ...) درست کار می‌کنه
define('QUIET_HOURS_START', '00:03');
define('QUIET_HOURS_END', '07:03');

// ساعت باز شدن بازار؛ فقط شنبه تا چهارشنبه (پنجشنبه و جمعه بازار تعطیله).
// اگه اجرا از ساعت بازگشایی عقب بیفته (کرون دیر اجرا بشه یا فایل نشانه پاک شده باشه)
// تا MARKET_OPEN_DEADLINE هنوز فرستادنش معنی داره؛ بعد از اون «بازار باز شد» گمراه‌کننده‌ست
define('MARKET_OPEN_TIME', '11:03');
define('MARKET_OPEN_DEADLINE', '13:03');

define('TEHRAN_TZ_NAME', 'Asia/Tehran');

function tehran_now()
{
    return new DateTime('now', new DateTimeZone(TEHRAN_TZ_NAME));
}
