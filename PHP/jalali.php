<?php
// توابع کمکی تقویم شمسی: تبدیل تاریخ میلادی به شمسی و محاسبه‌ی ماه قبل.

$MONTH_NAMES = [
    'فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور',
    'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند',
];

// الگوریتم استاندارد تبدیل میلادی به شمسی (مبتنی بر jdf.php، به‌کاررفته در پروژه‌های زیادی).
function gregorian_to_jalali($gy, $gm, $gd)
{
    $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100)
        + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];

    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;

    if ($days > 365) {
        $jy += intdiv($days - 1, 365);
        $days = ($days - 1) % 365;
    }

    if ($days < 186) {
        $jm = 1 + intdiv($days, 31);
        $jd = 1 + ($days % 31);
    } else {
        $jm = 7 + intdiv($days - 186, 30);
        $jd = 1 + (($days - 186) % 30);
    }

    return [$jy, $jm, $jd];
}

function to_jalali(DateTime $dt)
{
    return gregorian_to_jalali((int) $dt->format('Y'), (int) $dt->format('n'), (int) $dt->format('j'));
}

function month_label($year, $month)
{
    global $MONTH_NAMES;
    return $MONTH_NAMES[$month - 1] . ' ' . $year;
}

function previous_jalali_month(DateTime $now)
{
    [$year, $month] = to_jalali($now);
    $month -= 1;

    if ($month === 0) {
        $year -= 1;
        $month = 12;
    }

    return [$year, $month];
}
