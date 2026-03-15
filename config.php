<?php
// ১. ডাটাবেস কনফিগারেশন
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'Parvez@9810#'); // আপনার ডাটাবেস পাসওয়ার্ড
define('DB_NAME', 'switch_monitor');

// ২. ডাটাবেস কানেকশন তৈরি
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// কানেকশন চেক করা
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ৩. টেলিগ্রাম সেটিংস লোড করা (ডাটাবেস থেকে)
// প্রথমে চেক করবে settings টেবিল আছে কি না, না থাকলে এরর এড়াবে
$tg_token = "";
$tg_chat_id = "";

$check_table = $conn->query("SHOW TABLES LIKE 'settings'");
if($check_table->num_rows > 0) {
    $res = $conn->query("SELECT * FROM settings");
    while($row = $res->fetch_assoc()) {
        if($row['setting_key'] == 'tg_token') $tg_token = $row['setting_value'];
        if($row['setting_key'] == 'tg_chat_id') $tg_chat_id = $row['setting_value'];
    }
}

define('TELEGRAM_TOKEN', $tg_token);
define('TELEGRAM_CHAT_ID', $tg_chat_id);

// ৪. টেলিগ্রামে মেসেজ পাঠানোর গ্লোবাল ফাংশন
function sendTelegramMessage($message) {
    if (TELEGRAM_TOKEN != "" && TELEGRAM_CHAT_ID != "") {
        $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
        $data = [
            'chat_id' => TELEGRAM_CHAT_ID,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_exec($ch);
        curl_close($ch);
    }
}

// ৫. গ্লোবাল সেটিংস
$site_title = "BDCOM Network Monitor";
$refresh_rate = 10; 

// ৬. SNMP ডিফল্ট সেটিংস
$default_community = "public";

// ৭. টাইমজোন সেট করা
date_default_timezone_set('Asia/Dhaka');

// সেশন স্টার্ট
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>