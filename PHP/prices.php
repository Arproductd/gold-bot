<?php
// گرفتن قیمت لحظه‌ای طلا و نقره از API‌های بیرونی.

function http_get_json($url, $headers = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_COOKIEFILE, ''); // فعال‌سازی حافظه‌ی کوکی برای نگه‌داشتن کوکی بین ریدایرکت‌ها

    if ($headers) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);

    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("درخواست به $url ناموفق بود: $error");
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status < 200 || $status >= 300) {
        throw new RuntimeException("پاسخ HTTP $status از $url");
    }

    $data = json_decode($response, true);

    if (!is_array($data)) {
        throw new RuntimeException("پاسخ JSON نامعتبر از $url");
    }

    return $data;
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

        return ['platform' => (string) $cheapest['platform_slug'], 'price' => (float) $cheapest['price_toman']];
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

        return is_numeric($value) ? (int) $value : null;
    } catch (Throwable $e) {
        error_log('دریافت نرخ مرجع tablo.gold ناموفق بود: ' . $e->getMessage());

        return null;
    }
}

function calculate_gold_bubble($gold, $usd, $ounce)
{
    // milli.gold قیمت رو به ازای هر «میلی» (۱ میلی‌گرم) می‌ده، نه هر گرم؛ برای همین قیمت ذاتی هم به میلی‌گرم تبدیل می‌شه
    $intrinsic = ($ounce / GRAMS_PER_TROY_OUNCE) * $usd * GOLD_PURITY_18K / 1000;

    if ($intrinsic <= 0) {
        return ['amount' => 0, 'percent' => 0.0];
    }

    $amount = $gold - $intrinsic;

    return [
        'amount' => (int) round($amount),
        'percent' => round($amount / $intrinsic * 100, 1),
    ];
}
