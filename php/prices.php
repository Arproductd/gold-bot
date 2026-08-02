<?php
// گرفتن قیمت لحظه‌ای طلا و نقره از API‌های بیرونی.

function http_get_json($url)
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36');
    curl_setopt($ch, CURLOPT_COOKIEFILE, ''); // فعال‌سازی حافظه‌ی کوکی برای نگه‌داشتن کوکی بین ریدایرکت‌ها
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
