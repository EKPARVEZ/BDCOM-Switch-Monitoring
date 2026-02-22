<?php
require_once 'config.php';
snmp_set_quick_print(1);

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

$response = [];

if($switch) {
    $ip = $switch['ip_address'];
    $com = $switch['community'];
    
    // 64-bit HC Counters for 10G
    $portNames = @snmp2_real_walk($ip, $com, ".1.3.6.1.2.1.2.2.1.2", 2000000, 2);
    $portInRaw = @snmp2_walk($ip, $com, ".1.3.6.1.2.1.31.1.1.1.6", 2000000, 2);

    if($portNames) {
        $i = 0;
        foreach($portNames as $oid => $val) {
            $index = substr(strrchr($oid, "."), 1);
            $current_in = (float)preg_replace('/[^0-9.]/', '', $portInRaw[$i] ?? 0);

            // Traffic Calculation
            $mbps = 0;
            $last_res = $conn->query("SELECT in_octets, recorded_at FROM port_traffic WHERE port_index=$index AND switch_id=$switch_id ORDER BY id DESC LIMIT 1");
            $last = $last_res->fetch_assoc();
            
            if($last && $current_in > 0) {
                $diff = $current_in - $last['in_octets'];
                $time = time() - strtotime($last['recorded_at']);
                if($time > 0 && $diff > 0) $mbps = round(($diff * 8) / ($time * 1000000), 2);
            }

            $conn->query("INSERT INTO port_traffic (switch_id, port_index, in_octets) VALUES ($switch_id, $index, $current_in)");

            $response[] = [
                'index' => $index,
                'mbps' => $mbps,
                'percent' => min(($mbps / 10000) * 100, 100)
            ];
            $i++;
        }
    }
}
echo json_encode($response);