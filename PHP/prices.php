<?php
// گرفتن قیمت لحظه‌ای طلا و نقره از API‌های بیرونی.

function http_get_json($url, $headers = [])
{
    $last_error = '';

    for ($attempt = 1; $attempt <= HTTP_MAX_ATTEMPTS; $attempt++) {
        if ($attempt > 1) {
            sleep(HTTP_RETRY_DELAY);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, HTTP_TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_COOKIEFILE, ''); // فعال‌سازی حافظه‌ی کوکی برای نگه‌داشتن کوکی بین ریدایرکت‌ها

        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $last_error = "خطای شبکه: $error";
            continue;
        }

        // ۴۰۱ و ۴۰۳ و امثالش با تکرار درست نمی‌شن، پس بلافاصله خطا می‌دیم
        if ($status < 200 || $status >= 300) {
            if ($status !== 429 && $status < 500) {
                throw new RuntimeException("پاسخ HTTP $status از $url");
            }

            $last_error = "پاسخ HTTP $status";
            continue;
        }

        $data = json_decode($response, true);

        if (!is_array($data)) {
            $last_error = 'پاسخ JSON نامعتبر';
            continue;
        }

        if ($attempt > 1) {
            error_log("درخواست به $url در تلاش $attempt موفق شد");
        }

        return $data;
    }

    throw new RuntimeException("بعد از " . HTTP_MAX_ATTEMPTS . " تلاش به $url نرسیدیم — $last_error");
}

function extract_price($data, $field, $url)
{
    $value = $data['data'][$field] ?? null;

    if (!is_numeric($value)) {
        throw new RuntimeException("فیلد $field توی پاسخ $url پیدا نشد یا عدد نیست");
    }

    return (int) $value;
}

function get_gold_price()
{
    return extract_price(http_get_json(GOLD_URL), 'price18', GOLD_URL);
}

function get_silver_price()
{
    return extract_price(http_get_json(SILVER_URL), 'price_buy', SILVER_URL);
}

function extract_tgju_price($data, $field, $url)
{
    $value = $data['current'][$field]['p'] ?? null;
    $numeric = is_string($value) ? str_replace(',', '', $value) : $value;

    if (!is_numeric($numeric)) {
        throw new RuntimeException("فیلد $field توی پاسخ $url پیدا نشد یا عدد نیست");
    }

    return $numeric;
}

function get_tgju_prices()
{
    $data = http_get_json(TGJU_URL);

    return [
        'usd' => (int) extract_tgju_price($data, 'price_dollar_rl', TGJU_URL),
        'ounce' => (float) extract_tgju_price($data, 'tether_gold_xaut', TGJU_URL),
        'cny' => (int) extract_tgju_price($data, 'price_cny', TGJU_URL),
        'aed' => (int) extract_tgju_price($data, 'price_aed', TGJU_URL),
        'eur' => (int) extract_tgju_price($data, 'price_eur', TGJU_URL),
        'try' => (int) extract_tgju_price($data, 'price_try', TGJU_URL),
    ];
}

// مقیاس نمایشی ربات: تومانِ هر «میلی» (یک‌هزارم گرم). tablo.gold تومانِ هر گرم می‌ده،
// پس بر ۱۰۰۰ تقسیم می‌شه؛ price18 میلی‌گلد تومانِ هر سوت (صدم گرم) است و توی notifier
// با toman() بر ۱۰ تقسیم می‌شه. هر دو مسیر به یک عدد می‌رسن.
// مثال: 23,913,000 تومان بر گرم ← 23,913
function tablo_to_bot_scale($toman_per_gram)
{
    return (int) round($toman_per_gram / 1000);
}

// ارزون‌ترین قیمت طلای گرمی ۱۸ عیار بین پلتفرم‌های tablo.gold؛ اگه API در دسترس نبود
// (کلید غلط، rate limit، قطعی) به‌جای اینکه کل پیام رو خراب کنه، این خط رو null برمی‌گردونه
function get_tablo_cheapest_platform()
{
    try {
        $data = http_get_json(TABLO_GOLD_URL, ['Authorization: Bearer ' . TABLO_API_KEY]);

        // شکل پاسخ هم چک می‌شه: هر چیزی جز آرایه (خطای API، تغییر فرمت) نباید کل پیام رو بندازه
        $platforms = array_filter(
            is_array($data['platforms'] ?? null) ? $data['platforms'] : [],
            fn($p) => is_array($p) && is_numeric($p['price_toman'] ?? null) && ($p['platform_slug'] ?? '') !== ''
        );

        $cheapest = null;

        foreach ($platforms as $platform) {
            if ($cheapest === null || $platform['price_toman'] < $cheapest['price_toman']) {
                $cheapest = $platform;
            }
        }

        if ($cheapest === null) {
            return null;
        }

        return [
            'platform' => (string) $cheapest['platform_slug'],
            'price' => tablo_to_bot_scale($cheapest['price_toman']),
        ];
    } catch (Throwable $e) {
        error_log('دریافت قیمت‌های tablo.gold ناموفق بود: ' . $e->getMessage());

        return null;
    }
}

// نرخ مرجع مستقل طلای ۱۸ عیار (به تومان) از tablo.gold — همون عددیه که بالای صفحه‌ی tala-18 نشون داده می‌شه.
// اگه API در دسترس نبود null برمی‌گرده و main.php قیمت milli.gold رو به‌جاش نشون می‌ده
function get_tablo_reference_price()
{
    try {
        $data = http_get_json(TABLO_REFERENCE_URL, ['Authorization: Bearer ' . TABLO_API_KEY]);

        // طبق مستندات، reference_price ممکنه null باشه
        $value = $data['reference_price']['value'] ?? null;

        return is_numeric($value) ? tablo_to_bot_scale($value) : null;
    } catch (Throwable $e) {
        error_log('دریافت نرخ مرجع tablo.gold ناموفق بود: ' . $e->getMessage());

        return null;
    }
}
