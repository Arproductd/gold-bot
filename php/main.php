<?php
// نقطه‌ی ورود ربات: قیمت رو می‌گیره، تو تاریخچه ثبت می‌کنه، در صورت لزوم میانگین
// ماه قبل / خلاصه هفتگی رو می‌فرسته و در نهایت پیام بروزرسانی قیمت لحظه‌ای رو به تلگرام ارسال می‌کنه.
// این فایل قراره هر چند دقیقه یک‌بار توسط Cron Job اجرا بشه.

require __DIR__ . '/config.php';
require __DIR__ . '/jalali.php';
require __DIR__ . '/prices.php';
require __DIR__ . '/storage.php';
require __DIR__ . '/notifier.php';

function check_monthly_average()
{
    $now = tehran_now();
    [$year, $month] = previous_jalali_month($now);
    $key = sprintf('%d-%02d', $year, $month);

    if (load_last_average_month() === $key) {
        return;
    }

    $averages = month_average($year, $month);

    if ($averages !== null) {
        [$avg_gold, $avg_silver] = $averages;
        send_monthly_average(month_label($year, $month), $avg_gold, $avg_silver);
    }

    save_last_average_month($key);
}

function check_weekly_summary()
{
    $now = tehran_now();

    // جمعه (ISO-8601 روز ۵) ساعت ۹ صبح؛ هفته از شنبه تا جمعه حساب می‌شه
    if ((int) $now->format('N') !== 5 || (int) $now->format('G') !== 9) {
        return;
    }

    $week_end = $now->format('Y-m-d');
    $week_start = (clone $now)->modify('-6 days')->format('Y-m-d');

    if (load_last_weekly() === $week_end) {
        return;
    }

    $summary = week_high_low($week_start, $week_end);

    if ($summary !== null) {
        send_weekly_summary(
            $summary['gold_high'],
            $summary['gold_low'],
            $summary['silver_high'],
            $summary['silver_low']
        );
    }

    save_last_weekly($week_end);
}

function main()
{
    $gold = get_gold_price();
    $silver = get_silver_price();

    append_data($gold, $silver);
    check_monthly_average();
    check_weekly_summary();

    $last = load_prices();
    send_price_update($gold, $silver, $last['gold'] ?? 0, $last['silver'] ?? 0);

    if (!save_prices($gold, $silver)) {
        error_log('save_prices failed: ' . var_export(error_get_last(), true));
    }
}

try {
    main();
} catch (Throwable $e) {
    error_log('Fatal: ' . $e->getMessage());
}
