<?php
// ১. ডাটাবেস কনফিগারেশন
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', ''); // আপনার ডাটাবেস পাসওয়ার্ড দিন
define('DB_NAME', 'switch_monitor');

// ২. ডাটাবেস কানেকশন তৈরি
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// কানেকশন চেক করা
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ৩. গ্লোবাল সেটিংস
$site_title = "BDCOM Network Monitor";
$refresh_rate = 10; // ড্যাশবোর্ড কত সেকেন্ড পর পর রিফ্রেশ হবে

// ৪. SNMP ডিফল্ট সেটিংস (যদি ডাটাবেসে না থাকে)
$default_community = "public";

// ৫. টাইমজোন সেট করা (বাংলাদেশ সময় অনুযায়ী ট্রাফিক হিস্ট্রি রাখার জন্য)
date_default_timezone_set('Asia/Dhaka');

// সেশন স্টার্ট (প্রতি পেজে বারবার লিখতে হবে না)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

?>
