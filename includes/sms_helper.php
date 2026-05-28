<?php
/**
 * Quick mart SMS Helper
 * Supports multiple providers (Simulation, Fast2SMS, Twilio)
 */

function sendSMS($phone, $message) {
    global $pdo;
    
    // Get SMS settings
    $enabled = (int)get_setting('sms_enabled', '0');
    if (!$enabled) return false;
    
    $provider = get_setting('sms_provider', 'simulation');
    
    // Sanitize phone number (keep only digits)
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) < 10) return false;

    // Log the attempt
    $log_msg = "[" . date('Y-m-d H:i:s') . "] To: $phone | Msg: $message | Provider: $provider\n";
    file_put_contents(__DIR__ . '/../sms_log.txt', $log_msg, FILE_APPEND);

    switch ($provider) {
        case 'fast2sms':
            return sendFast2SMS($phone, $message);
        case 'twilio':
            return sendTwilio($phone, $message);
        case 'simulation':
        default:
            // Already logged to file above
            return true;
    }
}

function sendFast2SMS($phone, $message) {
    $apiKey = get_setting('sms_fast2sms_key');
    if (!$apiKey) return false;

    $fields = array(
        "message" => $message,
        "language" => "english",
        "route" => "q",
        "numbers" => $phone,
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => json_encode($fields),
        CURLOPT_HTTPHEADER => array(
            "authorization: $apiKey",
            "accept: */*",
            "cache-control: no-cache",
            "content-type: application/json"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) return false;
    
    $res = json_decode($response, true);
    return isset($res['return']) && $res['return'] === true;
}

function sendTwilio($phone, $message) {
    $sid = get_setting('sms_twilio_sid');
    $token = get_setting('sms_twilio_token');
    $from = get_setting('sms_twilio_from');
    
    if (!$sid || !$token || !$from) return false;

    // Twilio requires +country code
    if (strlen($phone) == 10) $phone = "+91" . $phone; // Default to India if no code
    elseif (strpos($phone, '+') !== 0) $phone = "+" . $phone;

    $url = "https://api.twilio.com/2010-04-01/Accounts/$sid/Messages.json";
    $auth = base64_encode("$sid:$token");

    $data = array(
        'From' => $from,
        'To' => $phone,
        'Body' => $message
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => array(
            "Authorization: Basic $auth"
        ),
    ));

    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);

    if ($err) return false;
    
    $res = json_decode($response, true);
    return isset($res['sid']);
}
