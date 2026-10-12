<?php
/**
 * API: Get Global App Config, Banners & Announcements
 * Method: GET
 * Endpoint: /backend/api/get_app_config.php
 */
define('IS_API_REQUEST', true);
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

try {
    $db = Database::getConnection();

    // Fetch settings key-values
    $settingsStmt = $db->query("SELECT setting_key, setting_value FROM app_settings");
    $settings = [];
    while ($row = $settingsStmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Fetch active banners
    $bannerStmt = $db->query("
        SELECT id, title, sub_title, image_path, action_type, action_value, display_order 
        FROM banners 
        WHERE status = 'active' 
        ORDER BY display_order ASC
    ");
    $banners = $bannerStmt->fetchAll();

    foreach ($banners as &$b) {
        $b['full_image_url'] = resolveMediaUrl($b['image_path'], 'banners');
    }

    $response = [
        'app_name' => $settings['app_name'] ?? 'Book Banko',
        'app_version' => $settings['app_version'] ?? '1.0.0',
        'force_update' => ($settings['force_update'] ?? '0') === '1',
        'maintenance_mode' => ($settings['maintenance_mode'] ?? '0') === '1',
        'maintenance_message' => $settings['maintenance_message'] ?? '',
        'contact_email' => $settings['contact_email'] ?? 'support@bookbanko.com',
        'privacy_policy_url' => $settings['privacy_policy_url'] ?? '',
        'terms_conditions_url' => $settings['terms_conditions_url'] ?? '',
        'announcement' => [
            'title' => $settings['notice_title'] ?? '',
            'message' => $settings['notice_message'] ?? ''
        ],
        'default_jee_neet_text' => $settings['default_jee_neet_text'] ?? '👉 Click here for the best JEE & NEET Questions ↗',
        'admob_enabled' => ($settings['admob_enabled'] ?? '1') === '1',
        'admob_app_id' => $settings['admob_app_id'] ?? 'ca-app-pub-3940256099942544~3347511713',
        'admob_interstitial_ad_unit_id' => $settings['admob_interstitial_ad_unit_id'] ?? 'ca-app-pub-3940256099942544/1033173712',
        'banners' => $banners
    ];

    jsonResponse('success', 'App configuration fetched successfully', $response);
} catch (PDOException $e) {
    jsonResponse('error', 'Failed to fetch app configuration: ' . $e->getMessage(), null, 500);
}
