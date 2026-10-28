<?php
// خواندن و نوشتن داده‌های ربات روی دیسک:
// - price.json: آخرین قیمت ثبت‌شده (برای تشخیص تغییر قیمت)
// - data.jsonl: تاریخچه‌ی کامل قیمت‌ها به همراه زمان هر بار دریافت (هیچ‌وقت پاک نمی‌شه)
// - last_average.txt / last_weekly.txt: جلوگیری از ارسال تکراری گزارش‌ها

// قیمت‌هایی که توی تاریخچه ثبت می‌شن. هر وقت یکی اضافه بشه، ردیف‌های قدیمیِ data.jsonl
// اون فیلد رو ندارن؛ برای همین میانگین ماهانه و کمینه/بیشینه‌ی هفتگی برای هر فیلد
// جداگانه شمرده می‌شن و فیلدِ بدون داده اصلاً توی گزارش نمیاد (نه اینکه صفر نشون داده بشه).
const TRACKED_PRICES = ['gold', 'silver', 'copper', 'usd', 'ounce', 'cny', 'aed', 'eur', 'try'];

function load_prices()
{
    $defaults = ['gold_ref' => 0, 'scale' => 0];

    foreach (TRACKED_PRICES as $key) {
        $defaults[$key] = 0;
    }

    if (!file_exists(PRICE_FILE)) {
        return $defaults;
    }

    $prices = (json_decode(file_get_contents(PRICE_FILE), true) ?: []) + $defaults;

    if ((int) $prices['scale'] !== PRICE_SCALE_VERSION) {
        $prices['gold_ref'] = migrate_gold_ref($prices['gold_ref']);
        $prices['scale'] = PRICE_SCALE_VERSION;
    }

    return $prices;
}

// نسخه‌های قبلی gold_ref رو با مقیاس‌های دیگه‌ای نوشته بودن: تومانِ هر گرم (۸ رقمی) و
// تومانِ هر سوت (۶ رقمی). چون شماره‌ی مقیاس داخل price.json ذخیره می‌شه، این حدس‌زدن از
// روی تعداد رقم فقط یک بار روی داده‌ی قدیمی اجرا می‌شه و بعدش هیچ‌وقت.
function migrate_gold_ref($value)
{
    if ($value <= 0) {
        return 0;
    }

    if ($value >= 1000000) {
        return (int) round($value / 1000);
    }

    if ($value >= 100000) {
        return (int) round($value / 10);
    }

    return (int) $value;
}

function save_prices(array $prices)
{
    $record = ['scale' => PRICE_SCALE_VERSION, 'gold_ref' => $prices['gold_ref'] ?? 0];

    foreach (TRACKED_PRICES as $key) {
        $record[$key] = $prices[$key] ?? 0;
    }

    return file_put_contents(PRICE_FILE, json_encode($record)) !== false;
}

function append_data(array $prices)
{
    $entry = ['time' => tehran_now()->format(DATE_ATOM)];

    foreach (TRACKED_PRICES as $key) {
        $entry[$key] = $prices[$key] ?? null;
    }

    file_put_contents(DATA_FILE, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function month_average($year, $month)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $sums = [];
    $counts = [];

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);

        if (!isset($entry['time'])) {
            continue;
        }

        [$jy, $jm] = to_jalali(new DateTime($entry['time']));

        if ($jy !== $year || $jm !== $month) {
            continue;
        }

        foreach (TRACKED_PRICES as $key) {
            if (!is_numeric($entry[$key] ?? null)) {
                continue;
            }

            $sums[$key] = ($sums[$key] ?? 0) + $entry[$key];
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }
    }

    if (empty($counts)) {
        return null;
    }

    $averages = [];

    foreach ($counts as $key => $count) {
        $averages[$key] = $sums[$key] / $count;
    }

    return $averages;
}

function week_high_low($start_date, $end_date)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $values = [];

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);

        if (!isset($entry['time'])) {
            continue;
        }

        $entry_date = (new DateTime($entry['time']))->format('Y-m-d');

        if ($entry_date < $start_date || $entry_date > $end_date) {
            continue;
        }

        foreach (TRACKED_PRICES as $key) {
            if (is_numeric($entry[$key] ?? null)) {
                $values[$key][] = $entry[$key];
            }
        }
    }

    if (empty($values)) {
        return null;
    }

    $summary = [];

    foreach ($values as $key => $list) {
        $summary[$key . '_high'] = max($list);
        $summary[$key . '_low'] = min($list);
    }

    return $summary;
}

function load_last_average_month()
{
    if (!file_exists(LAST_AVERAGE_FILE)) {
        return null;
    }

    return trim(file_get_contents(LAST_AVERAGE_FILE));
}

function save_last_average_month($month)
{
    file_put_contents(LAST_AVERAGE_FILE, $month);
}

function load_last_weekly()
{
    if (!file_exists(LAST_WEEKLY_FILE)) {
        return null;
    }

    return trim(file_get_contents(LAST_WEEKLY_FILE));
}

function save_last_weekly($week_end)
{
    file_put_contents(LAST_WEEKLY_FILE, (string) $week_end);
}

function load_last_morning()
{
    if (!file_exists(LAST_MORNING_FILE)) {
        return null;
    }

    return trim(file_get_contents(LAST_MORNING_FILE));
}

function save_last_morning($date)
{
    file_put_contents(LAST_MORNING_FILE, (string) $date);
}

function load_last_market_open()
{
    if (!file_exists(LAST_MARKET_OPEN_FILE)) {
        return null;
    }

    return trim(file_get_contents(LAST_MARKET_OPEN_FILE));
}

function save_last_market_open($date)
{
    file_put_contents(LAST_MARKET_OPEN_FILE, (string) $date);
}

function load_last_night()
{
    if (!file_exists(LAST_NIGHT_FILE)) {
        return null;
    }

    return trim(file_get_contents(LAST_NIGHT_FILE));
}

function save_last_night($date)
{
    file_put_contents(LAST_NIGHT_FILE, (string) $date);
}
