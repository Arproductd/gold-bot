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

// همون API نقره با نماد XCU؛ قیمت به ازای هر گرم و به تومانه
function get_copper_price()
{
    return extract_price(http_get_json(COPPER_URL), 'price_buy', COPPER_URL);
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
