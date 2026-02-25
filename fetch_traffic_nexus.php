<?php
require_once 'config.php';
header('Content-Type: application/json');
snmp_set_quick_print(1);

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

$response = [];

if($switch) {
    $ip = $switch['ip_address'];
    $com = $switch['community'];
    
    // ১. ইন্টারফেসের নাম এবং ইনডেক্স
    $portNames = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.2");
    // ২. ট্রাফিক (64-bit Counters for Nexus 10G/40G)
    $portInRaw = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.31.1.1.1.6");
    
    // ৩. Cisco Nexus Power OID (Entity Sensor MIB)
    // Nexus-এ RX/TX পাওয়ার সরাসরি পোর্টের ইনডেক্সে থাকে না, এটি সেন্সর ইনডেক্সে থাকে।
    // আপাতত আমরা স্ট্যান্ডার্ড ট্রাফিক এবং স্ট্যাটাস ফোকাস করছি।
    
    if($portNames) {
        foreach($portNames as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $port_name = str_replace('"', '', $val);

            // শুধু Ethernet বা Physical পোর্টগুলো ফিল্টার করা (Nexus-এ অনেক লজিক্যাল পোর্ট থাকে)
            if (strpos($port_name, 'Ethernet') === false) continue;

            // Mbps ক্যালকুলেশন
            $raw_oid = ".1.3.6.1.2.1.31.1.1.1.6." . $index;
            $current_in = isset($portInRaw[$raw_oid]) ? (float)preg_replace('/[^0-9.]/', '', $portInRaw[$raw_oid]) : 0;
            
            $mbps = 0;
            $last_res = $conn->query("SELECT in_octets, recorded_at FROM port_traffic WHERE port_index='$index' AND switch_id=$switch_id ORDER BY id DESC LIMIT 1");
            if($last = $last_res->fetch_assoc()) {
                $diff = $current_in - (float)$last['in_octets'];
                $time_diff = time() - strtotime($last['recorded_at']);
                if($time_diff > 0 && $diff > 0) {
                    $mbps = round(($diff * 8) / ($time_diff * 1000000), 2);
                }
            }
            if($current_in > 0) {
                $conn->query("INSERT INTO port_traffic (switch_id, port_index, in_octets) VALUES ($switch_id, '$index', '$current_in')");
            }

            $response[] = [
                'index' => $index,
                'mbps' => number_format($mbps, 2),
                'rx' => "N/A", // Nexus DOM এর জন্য আলাদা সেন্সর ম্যাপিং লাগে
                'tx' => "N/A"
            ];
        }
    }
}
echo json_encode($response);