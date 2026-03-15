<?php
require_once 'config.php';
header('Content-Type: application/json');

error_reporting(0);

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if(!$switch_id) {
    echo json_encode([]);
    exit;
}

$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

if(!$switch) {
    echo json_encode([]);
    exit;
}

// Parse IP and port
$ip_input = $switch['ip_address'];
$com = $switch['community'];

if (strpos($ip_input, ':') !== false) {
    list($ip, $snmp_port) = explode(':', $ip_input);
} else {
    $ip = $ip_input;
    $snmp_port = 161;
}
$target = $ip . ":" . $snmp_port;

// Set SNMP options
if(function_exists('snmp_set_quick_print')) {
    snmp_set_quick_print(1);
}

$response = [];

// Get all port indexes first
$portNames = @snmp2_real_walk($target, $com, ".1.3.6.1.2.1.2.2.1.2", 2000000, 2);

if($portNames && is_array($portNames)) {
    
    // Get traffic data
    $portInRaw = @snmp2_walk($target, $com, ".1.3.6.1.2.1.2.2.1.10", 2000000, 2);
    
    // Get BDCOM Optical Power Data - Try different OIDs
    $rxRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.101.10.5.1.5", 3000000, 2);
    $txRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.101.10.5.1.6", 3000000, 2);
    
    // Alternative OIDs if above doesn't work
    if(empty($rxRaw)) {
        $rxRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.101.10.5.1.5", 3000000, 2);
    }
    if(empty($txRaw)) {
        $txRaw = @snmp2_real_walk($target, $com, ".1.3.6.1.4.1.3320.101.10.5.1.6", 3000000, 2);
    }

    // Debug: Log raw data (remove in production)
    // error_log("RX Raw: " . print_r($rxRaw, true));
    // error_log("TX Raw: " . print_r($txRaw, true));

    $i = 0;
    foreach($portNames as $oid => $val) {
        // Extract port index properly
        $parts = explode('.', $oid);
        $index = end($parts);
        
        // --- Traffic Calculation ---
        $current_in = 0;
        if(isset($portInRaw[$i])) {
            $current_in = (float)preg_replace('/[^0-9.]/', '', $portInRaw[$i]);
        }
        
        $mbps = 0;
        $last_res = $conn->query("SELECT in_octets FROM port_traffic WHERE port_index='$index' AND switch_id=$switch_id ORDER BY id DESC LIMIT 1");
        
        if($last_res && $last_res->num_rows > 0) {
            $last = $last_res->fetch_assoc();
            if($current_in > 0 && $last['in_octets'] > 0) {
                $diff = $current_in - (float)$last['in_octets'];
                if($diff > 0) {
                    // Assume 3 second interval (adjust based on your update frequency)
                    $mbps = round(($diff * 8) / (3000000), 2);
                }
            }
        }
        
        // Store current reading
        if($current_in > 0) {
            $conn->query("INSERT INTO port_traffic (switch_id, port_index, in_octets) VALUES ($switch_id, '$index', '$current_in')");
        }
        
		// --- VLAN ID সংগ্রহ (উন্নত লজিক) ---
$vlan_id = "N/A";

// ১. প্রথম চেষ্টা: BDCOM Dot1q Port PVID OID (Standard)
$vlan_oid_1 = ".1.3.6.1.4.1.3320.101.12.1.1.3." . $index;
// ২. দ্বিতীয় চেষ্টা: Generic Bridge Port VLAN OID (Alternative)
$vlan_oid_2 = ".1.3.6.1.2.1.17.7.1.4.5.1.1." . $index;

$vlan_raw = @snmp2_get($target, $com, $vlan_oid_1, 1000000, 1);

if(!$vlan_raw || strpos($vlan_raw, 'No Such') !== false) {
    // যদি প্রথম OID কাজ না করে তবে দ্বিতীয়টি চেষ্টা করবে
    $vlan_raw = @snmp2_get($target, $com, $vlan_oid_2, 1000000, 1);
}

if($vlan_raw) {
    // ডাটা থেকে শুধু নাম্বারটি আলাদা করা
    $vlan_id = preg_replace('/[^0-9]/', '', $vlan_raw);
    
    // যদি ভ্যালু ০ বা খালি আসে তবে N/A দেখাবে
    if(empty($vlan_id) || $vlan_id == "0") {
        $vlan_id = "N/A";
    }
}
		
        // --- BDCOM Power Calculation ---
        $rx_dbm = "N/A";
        $tx_dbm = "N/A";
        
        // Build the full OIDs for this port
        $rx_oid = ".1.3.6.1.4.1.3320.101.10.5.1.5." . $index;
        $tx_oid = ".1.3.6.1.4.1.3320.101.10.5.1.6." . $index;
        
        // Check if we have data for this specific port
        if(isset($rxRaw[$rx_oid])) {
            $rx_val = trim(str_replace('"', '', $rxRaw[$rx_oid]));
            $rx_val = preg_replace('/[^0-9.-]/', '', $rx_val);
            
            if(is_numeric($rx_val) && $rx_val != 0) {
                $rx_val = (float)$rx_val;
                // If value is very negative, divide by 10 (BDCOM format)
                if($rx_val < -100) {
                    $rx_val = $rx_val / 10;
                }
                $rx_dbm = round($rx_val, 2) . " dBm";
            }
        }
        
        if(isset($txRaw[$tx_oid])) {
            $tx_val = trim(str_replace('"', '', $txRaw[$tx_oid]));
            $tx_val = preg_replace('/[^0-9.-]/', '', $tx_val);
            
            if(is_numeric($tx_val) && $tx_val != 0) {
                $tx_val = (float)$tx_val;
                if($tx_val < -100) {
                    $tx_val = $tx_val / 10;
                }
                $tx_dbm = round($tx_val, 2) . " dBm";
            }
        }
        
        $response[] = [
            'index' => $index,
            'mbps'  => number_format($mbps, 2),
            'rx'    => $rx_dbm,
            'tx'    => $tx_dbm
        ];
        
        $i++;
    }
}

// Debug output (remove in production)
if(empty($response)) {
    $response = [['error' => 'No data received']];
}

echo json_encode($response);
?>