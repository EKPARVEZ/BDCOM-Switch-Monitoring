<?php
require_once 'config.php';

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

if(!$switch) die("No switch found");

echo "<h3>Testing SNMP for: " . $switch['name'] . " (" . $switch['ip_address'] . ")</h3>";

// Test basic SNMP
echo "<h4>Testing Port Names:</h4>";
$portNames = @snmp2_real_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.2.1.2.2.1.2");
if($portNames) {
    echo "<pre>Found " . count($portNames) . " ports</pre>";
} else {
    echo "<pre style='color:red'>No port data! SNMP may not be working</pre>";
}

// Test BDCOM Power OIDs
echo "<h4>Testing BDCOM RX Power:</h4>";
$rxTest = @snmp2_real_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.4.1.3320.101.10.5.1.5");
if($rxTest) {
    echo "<pre>RX Data found:</pre>";
    echo "<pre>" . print_r($rxTest, true) . "</pre>";
} else {
    echo "<pre style='color:red'>No RX power data! BDCOM OIDs may not be supported</pre>";
}

echo "<h4>Testing BDCOM TX Power:</h4>";
$txTest = @snmp2_real_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.4.1.3320.101.10.5.1.6");
if($txTest) {
    echo "<pre>TX Data found:</pre>";
    echo "<pre>" . print_r($txTest, true) . "</pre>";
} else {
    echo "<pre style='color:red'>No TX power data! BDCOM OIDs may not be supported</pre>";
}
?>