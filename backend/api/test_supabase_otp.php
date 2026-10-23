<?php
/**
 * Test Supabase Email OTP API
 * Book Banko Backend & API
 * Accepts: email (GET or POST)
 * Tests Supabase Auth OTP delivery and reports diagnostics
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$email = trim($_REQUEST['email'] ?? 'bookbankoindia@gmail.com');
$supabaseUrl = 'https://hdvcmoyqdpurjsmpbkjt.supabase.co';
$anonKey = 'sb_publishable_E3FlFjqTNGpFBTsVDOW8dQ_W_UazdWY';

$url = "$supabaseUrl/auth/v1/otp";
$payload = json_encode([
    'email' => $email,
    'create_user' => true
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $anonKey",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$decoded = json_decode($response, true) ?: ['raw' => $response];

echo json_encode([
    'target_email' => $email,
    'supabase_url' => $url,
    'http_status' => $httpCode,
    'success' => ($httpCode === 200 || $httpCode === 201),
    'supabase_response' => $decoded,
    'instructions' => [
        'step_1' => 'Ensure Email Provider is enabled in Supabase Dashboard -> Authentication -> Providers -> Email',
        'step_2' => 'In Email Templates -> Magic Link, ensure {{ .Token }} is included to display the 6-digit OTP code',
        'step_3' => 'If you get 500 "Error sending confirmation email" or 429 "rate limit", configure custom SMTP under Authentication -> SMTP Settings'
    ]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
