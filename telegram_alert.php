<?php
require_once 'config.php';

// টেলিগ্রাম সেটিংস
$botToken = "xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx";
$chatId = "xxxxxxxxxxxxxxxxxx";

function sendTelegram($msg, $token, $id) {
    $url = "https://api.telegram.org/bot$token/sendMessage?chat_id=$id&text=" . urlencode($msg) . "&parse_mode=HTML";
    @file_get_contents($url);
}

// ডাটাবেস থেকে সুইচগুলো নিন
$switches = $conn->query("SELECT * FROM switches");

while($sw = $switches->fetch_assoc()) {
    $ip = $sw['ip_address'];
    $com = $sw['community'];

    // SNMP দিয়ে পোর্টের নাম এবং বর্তমান অবস্থা দেখা (OID .1.3.6.1.2.1.2.2.1.8)
    $names = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.2");
    $statuses = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.8");

    if($names && $statuses) {
        foreach($statuses as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $current_state = (int)$val; // 1 = Up, 2 = Down
            $port_name = str_replace('"', '', $names[".1.3.6.1.2.1.2.2.1.2.$index"]);

            // ডাটাবেস থেকে পোর্টের শেষ রেকর্ড দেখা
            $stmt = $conn->query("SELECT last_status FROM port_alerts WHERE switch_id={$sw['id']} AND port_index='$index'");
            $row = $stmt->fetch_assoc();

            if($row) {
                // যদি আগে UP ছিল কিন্তু এখন DOWN হয়েছে
                if($row['last_status'] == 1 && $current_state == 2) {
                    $message = "🔴 <b>PORT DOWN ALERT!</b>\n\n";
                    $message .= "🏢 Switch: <b>{$sw['name']}</b>\n";
                    $message .= "🔌 Port: <b>$port_name</b>\n";
                    $message .= "⚠️ Status: <b>DOWN</b>\n";
                    $message .= "⏰ Time: " . date('d-M-Y H:i:s');
                    
                    sendTelegram($message, $botToken, $chatId);
                } 
                // যদি আগে DOWN ছিল এখন UP হয়েছে (Recovery Alert)
                else if($row['last_status'] == 2 && $current_state == 1) {
                    $message = "✅ <b>PORT RECOVERED</b>\n\n";
                    $message .= "🏢 Switch: <b>{$sw['name']}</b>\n";
                    $message .= "🔌 Port: <b>$port_name</b>\n";
                    $message .= "🟢 Status: <b>UP</b>\n";
                    $message .= "⏰ Time: " . date('d-M-Y H:i:s');
                    
                    sendTelegram($message, $botToken, $chatId);
                }
                
                // অবস্থা আপডেট করা
                $conn->query("UPDATE port_alerts SET last_status=$current_state WHERE switch_id={$sw['id']} AND port_index='$index'");
            } else {
                // প্রথমবার পোর্টের অবস্থা সেভ করা
                $conn->query("INSERT INTO port_alerts (switch_id, port_index, last_status) VALUES ({$sw['id']}, '$index', $current_state)");
            }
        }
    }
}

echo "Check Completed at " . date('H:i:s');
