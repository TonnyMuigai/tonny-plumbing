<?php
declare(strict_types=1);

/**
 * TONNY M-Pesa Daraja helper
 * Handles OAuth token, STK Push initiation, and password generation.
 */

function mpesa_config(): array
{
    return [
        'env'            => envv('MPESA_ENV', 'sandbox'), // sandbox | production
        'consumer_key'   => envv('MPESA_CONSUMER_KEY'),
        'consumer_secret'=> envv('MPESA_CONSUMER_SECRET'),
        'shortcode'      => envv('MPESA_SHORTCODE', '174379'),
        'passkey'        => envv('MPESA_PASSKEY'),
        'callback_url'   => envv('MPESA_CALLBACK_URL'),
    ];
}

function mpesa_base_url(): string
{
    $env = mpesa_config()['env'];
    return $env === 'production'
        ? 'https://api.safaricom.co.ke'
        : 'https://sandbox.safaricom.co.ke';
}

/**
 * Fetch (and cache in session) an OAuth access token from Daraja.
 */
function mpesa_get_access_token(): string
{
    $cfg = mpesa_config();
    if ($cfg['consumer_key'] === '' || $cfg['consumer_secret'] === '') {
        throw new RuntimeException('M-Pesa credentials are not configured. Check your .env file.');
    }

    // Cache token in session for ~55 minutes (Daraja tokens last 1 hour)
    $cached = $_SESSION['mpesa_token'] ?? null;
    if (is_array($cached) && $cached['expires'] > time() && !empty($cached['token'])) {
        return $cached['token'];
    }

    $url = mpesa_base_url() . '/oauth/v1/generate?grant_type=client_credentials';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Basic ' . base64_encode($cfg['consumer_key'] . ':' . $cfg['consumer_secret']),
        ],
        CURLOPT_TIMEOUT        => 20,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) {
        error_log('M-Pesa token error: HTTP ' . $httpCode . ' ' . $err . ' ' . $response);
        throw new RuntimeException('Could not authenticate with M-Pesa. Please try again.');
    }

    $data = json_decode($response, true);
    if (empty($data['access_token'])) {
        throw new RuntimeException('M-Pesa did not return an access token.');
    }

    $_SESSION['mpesa_token'] = [
        'token'   => $data['access_token'],
        'expires' => time() + 3300, // 55 minutes
    ];
    return $data['access_token'];
}

/**
 * Generate the STK Push password: base64(Shortcode + Passkey + Timestamp).
 */
function mpesa_generate_password(string $shortcode, string $passkey, string $timestamp): string
{
    return base64_encode($shortcode . $passkey . $timestamp);
}

/**
 * Timestamp in East Africa Time (Africa/Nairobi) format yyyyMMddHHmmss.
 * Safaricom always expects EAT regardless of your server timezone. [citation:15]
 */
function mpesa_timestamp(): string
{
    $dt = new DateTime('now', new DateTimeZone('Africa/Nairobi'));
    return $dt->format('YmdHis');
}

/**
 * Normalize a Kenyan phone number to 2547XXXXXXXX / 2541XXXXXXXX.
 * Accepts 0712…, 712…, +254712…, 254712… [citation:1]
 */
function mpesa_normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if (str_starts_with($digits, '0')) {
        $digits = '254' . substr($digits, 1);
    } elseif (str_starts_with($digits, '7') || str_starts_with($digits, '1')) {
        $digits = '254' . $digits;
    } elseif (str_starts_with($digits, '254')) {
        // already fine
    } else {
        throw new InvalidArgumentException('Unsupported phone number format.');
    }
    if (strlen($digits) !== 12) {
        throw new InvalidArgumentException('Phone number must be 12 digits after normalization.');
    }
    return $digits;
}

/**
 * Initiate an STK Push and return the Daraja JSON response.
 *
 * @param int    $orderId    Local shop_orders.id (used as AccountReference)
 * @param string $orderRef   Human-readable order reference (e.g. ORD-260509-ABC123)
 * @param float  $amount     Amount in KES (whole shillings or with cents)
 * @param string $phone      Customer phone in any Kenyan format
 * @return array             Decoded Daraja response
 */
function mpesa_stk_push(int $orderId, string $orderRef, float $amount, string $phone): array
{
    $cfg = mpesa_config();
    if ($cfg['callback_url'] === '') {
        throw new RuntimeException('MPESA_CALLBACK_URL is not set. Use ngrok and update .env.');
    }

    $token     = mpesa_get_access_token();
    $timestamp = mpesa_timestamp();
    $password  = mpesa_generate_password($cfg['shortcode'], $cfg['passkey'], $timestamp);
    $phone     = mpesa_normalize_phone($phone);

    // Daraja AccountReference max 12 characters [citation:1]
    $accountRef = substr($orderRef, 0, 12);

    $payload = [
        'BusinessShortCode' => $cfg['shortcode'],
        'Password'          => $password,
        'Timestamp'         => $timestamp,
        'TransactionType'   => 'CustomerPayBillOnline', // Paybill. For Till use CustomerBuyGoodsOnline
        'Amount'            => (int)round($amount),      // Daraja expects integer KES
        'PartyA'            => $phone,
        'PartyB'            => $cfg['shortcode'],
        'PhoneNumber'       => $phone,
        'CallBackURL'       => $cfg['callback_url'],
        'AccountReference'  => $accountRef,
        'TransactionDesc'   => 'TONNY Order ' . $orderRef,
    ];

    $ch = curl_init(mpesa_base_url() . '/mpesa/stkpush/v1/processrequest');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('M-Pesa STK curl error: ' . $err);
        throw new RuntimeException('Could not reach M-Pesa. Please try again.');
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        error_log('M-Pesa STK invalid JSON: ' . $response);
        throw new RuntimeException('M-Pesa returned an unexpected response.');
    }

    // Log the raw response for debugging
    error_log('M-Pesa STK response (HTTP ' . $httpCode . '): ' . $response);

    return $data;
}