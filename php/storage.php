<?php
// خواندن و نوشتن داده‌های ربات روی دیسک:
// - price.json: آخرین قیمت ثبت‌شده (برای تشخیص تغییر قیمت)
// - data.jsonl: تاریخچه‌ی کامل قیمت‌ها به همراه زمان هر بار دریافت (هیچ‌وقت پاک نمی‌شه)
// - last_average.txt / last_weekly.txt: جلوگیری از ارسال تکراری گزارش‌ها

function load_prices()
{
    $defaults = ['gold' => 0, 'silver' => 0, 'usd' => 0, 'ounce' => 0];

    if (!file_exists(PRICE_FILE)) {
        return $defaults;
    }

    return (json_decode(file_get_contents(PRICE_FILE), true) ?: []) + $defaults;
}

function save_prices($gold, $silver, $usd, $ounce)
{
    return file_put_contents(PRICE_FILE, json_encode([
        'gold' => $gold,
        'silver' => $silver,
        'usd' => $usd,
        'ounce' => $ounce,
    ])) !== false;
}

function append_data($gold, $silver, $usd, $ounce)
{
    $entry = [
        'time' => tehran_now()->format(DATE_ATOM),
        'gold' => $gold,
        'silver' => $silver,
        'usd' => $usd,
        'ounce' => $ounce,
    ];

    file_put_contents(DATA_FILE, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function month_average($year, $month)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $sums = ['gold' => 0, 'silver' => 0, 'usd' => 0, 'ounce' => 0];
    $count = 0;

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        $dt = new DateTime($entry['time']);
        [$jy, $jm] = to_jalali($dt);

        if ($jy !== $year || $jm !== $month) {
            continue;
        }

        $sums['gold'] += $entry['gold'];
        $sums['silver'] += $entry['silver'];
        $sums['usd'] += $entry['usd'] ?? 0;
        $sums['ounce'] += $entry['ounce'] ?? 0;
        $count++;
    }

    if ($count === 0) {
        return null;
    }

    return [
        'gold' => (int) round($sums['gold'] / $count),
        'silver' => (int) round($sums['silver'] / $count),
        'usd' => (int) round($sums['usd'] / $count),
        'ounce' => round($sums['ounce'] / $count, 2),
    ];
}

function week_high_low($start_date, $end_date)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $prices = ['gold' => [], 'silver' => [], 'usd' => [], 'ounce' => []];

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        $entry_date = (new DateTime($entry['time']))->format('Y-m-d');

        if ($entry_date < $start_date || $entry_date > $end_date) {
            continue;
        }

        $prices['gold'][] = $entry['gold'];
        $prices['silver'][] = $entry['silver'];
        $prices['usd'][] = $entry['usd'] ?? 0;
        $prices['ounce'][] = $entry['ounce'] ?? 0;
    }

    if (empty($prices['gold'])) {
        return null;
    }

    return [
        'gold_high' => max($prices['gold']),
        'gold_low' => min($prices['gold']),
        'silver_high' => max($prices['silver']),
        'silver_low' => min($prices['silver']),
        'usd_high' => max($prices['usd']),
        'usd_low' => min($prices['usd']),
        'ounce_high' => max($prices['ounce']),
        'ounce_low' => min($prices['ounce']),
    ];
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
