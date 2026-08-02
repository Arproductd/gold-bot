<?php
// خواندن و نوشتن داده‌های ربات روی دیسک:
// - price.json: آخرین قیمت ثبت‌شده (برای تشخیص تغییر قیمت)
// - data.jsonl: تاریخچه‌ی کامل قیمت‌ها به همراه زمان هر بار دریافت (هیچ‌وقت پاک نمی‌شه)
// - last_average.txt / last_weekly.txt: جلوگیری از ارسال تکراری گزارش‌ها

function load_prices()
{
    if (!file_exists(PRICE_FILE)) {
        return ['gold' => 0, 'silver' => 0];
    }

    return json_decode(file_get_contents(PRICE_FILE), true);
}

function save_prices($gold, $silver)
{
    return file_put_contents(PRICE_FILE, json_encode(['gold' => $gold, 'silver' => $silver])) !== false;
}

function append_data($gold, $silver)
{
    $entry = [
        'time' => tehran_now()->format(DATE_ATOM),
        'gold' => $gold,
        'silver' => $silver,
    ];

    file_put_contents(DATA_FILE, json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function month_average($year, $month)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $gold_sum = 0;
    $silver_sum = 0;
    $count = 0;

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        $dt = new DateTime($entry['time']);
        [$jy, $jm] = to_jalali($dt);

        if ($jy !== $year || $jm !== $month) {
            continue;
        }

        $gold_sum += $entry['gold'];
        $silver_sum += $entry['silver'];
        $count++;
    }

    if ($count === 0) {
        return null;
    }

    return [(int) round($gold_sum / $count), (int) round($silver_sum / $count)];
}

function week_high_low($start_date, $end_date)
{
    if (!file_exists(DATA_FILE)) {
        return null;
    }

    $gold_prices = [];
    $silver_prices = [];

    foreach (file(DATA_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $entry = json_decode($line, true);
        $entry_date = (new DateTime($entry['time']))->format('Y-m-d');

        if ($entry_date < $start_date || $entry_date > $end_date) {
            continue;
        }

        $gold_prices[] = $entry['gold'];
        $silver_prices[] = $entry['silver'];
    }

    if (empty($gold_prices)) {
        return null;
    }

    return [
        'gold_high' => max($gold_prices),
        'gold_low' => min($gold_prices),
        'silver_high' => max($silver_prices),
        'silver_low' => min($silver_prices),
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
