<?php
// موقتی و یک‌بارمصرف: چون از بیرون معلوم نیست کرون کدوم نسخه‌ی پروژه رو اجرا می‌کنه،
// خود ربات یک بار وضعیت محیطش رو به چت خصوصی (نه کانال) گزارش می‌ده. بعد از رفع مشکل
// این فایل و صدا زدنش از main.php حذف می‌شن.

define('DIAG_MARKER_FILE', __DIR__ . '/.diag_sent');

// وضعیت واقعی درخواست به tablo.gold، بدون اینکه خود کلید جایی چاپ بشه
function diag_tablo_status()
{
    $ch = curl_init(TABLO_REFERENCE_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . TABLO_API_KEY]);

    $body = curl_exec($ch);
    $error = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($body === false) {
        return 'curl error: ' . $error;
    }

    return 'HTTP ' . $status . ' — ' . substr($body, 0, 90);
}

// کامیتی که واقعاً روی دیسک چک‌اوت شده؛ مستقیم از فایل‌های .git خونده می‌شه چون
// ممکنه اجرای دستور git از داخل PHP روی هاست غیرفعال باشه
function diag_git_commit()
{
    $head_file = dirname(__DIR__) . '/.git/HEAD';

    if (!is_readable($head_file)) {
        return 'در دسترس نیست (این پوشه یک git clone نیست)';
    }

    $head = trim(file_get_contents($head_file));

    if (strpos($head, 'ref: ') !== 0) {
        return substr($head, 0, 12) . ' (detached)';
    }

    $ref = substr($head, 5);
    $ref_file = dirname(__DIR__) . '/.git/' . $ref;

    if (is_readable($ref_file)) {
        return substr(trim(file_get_contents($ref_file)), 0, 12) . ' (' . $ref . ')';
    }

    return $ref . ' — فقط در packed-refs';
}

function diag_file_time($path)
{
    if (!file_exists($path)) {
        return 'وجود ندارد';
    }

    return date('Y-m-d H:i', filemtime($path));
}

function send_diagnostics_once()
{
    if (file_exists(DIAG_MARKER_FILE)) {
        return;
    }

    $env_file = __DIR__ . '/.env';
    $key_len = strlen(TABLO_API_KEY);

    $lines = [
        '🔧 گزارش وضعیت اجرا',
        '',
        'پوشه‌ی در حال اجرا: ' . __DIR__,
        'کاربر: ' . (function_exists('get_current_user') ? get_current_user() : '?'),
        'باینری PHP: ' . PHP_BINARY,
        'نسخه‌ی PHP: ' . PHP_VERSION,
        'کامیت: ' . diag_git_commit(),
        '',
        'فایل .env: ' . ($env_file && file_exists($env_file) ? 'هست' : 'نیست'),
        'طول TABLO_API_KEY: ' . ($key_len > 0 ? $key_len . ' کاراکتر' : 'خالی'),
        'تست tablo: ' . diag_tablo_status(),
        '',
        'price.json: ' . diag_file_time(PRICE_FILE),
        'data.jsonl: ' . diag_file_time(DATA_FILE),
        'error.log: ' . diag_file_time(__DIR__ . '/error.log'),
    ];

    telegram_send_message(CHAT_ID, htmlspecialchars(implode("\n", $lines), ENT_NOQUOTES));

    file_put_contents(DIAG_MARKER_FILE, date('c'));
}
