<?php
require_once 'config.php';

if(!isset($_SESSION['loggedin'])) { header("Location: index.php"); exit; }

$switch_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($switch_id == 0) {
    $first = $conn->query("SELECT id FROM switches LIMIT 1");
    $sw_row = $first->fetch_assoc();
    $switch_id = $sw_row ? $sw_row['id'] : 0;
}

$res = $conn->query("SELECT * FROM switches WHERE id = $switch_id");
$switch = $res->fetch_assoc();

$traffic_data = [];
$snmp_target = "";

if($switch) {
    $ip_input = $switch['ip_address'];
    if (strpos($ip_input, ':') !== false) {
        list($ip, $port) = explode(':', $ip_input);
    } else {
        $ip = $ip_input;
        $port = 161;
    }
    $snmp_target = $ip . ":" . $port;
    $com = $switch['community'];

    if (function_exists('snmp_set_quick_print')) {
        snmp_set_quick_print(1);
        
        $portNames = @snmp2_real_walk($snmp_target, $com, ".1.3.6.1.2.1.2.2.1.2");
        $portAlias = @snmp2_real_walk($snmp_target, $com, ".1.3.6.1.2.1.31.1.1.1.18");
        $portStatus = @snmp2_walk($snmp_target, $com, ".1.3.6.1.2.1.2.2.1.8");

        if($portNames) {
            $descriptions = [];
            if ($portAlias) {
                foreach ($portAlias as $oid => $val) {
                    $idx = substr(strrchr($oid, "."), 1);
                    $descriptions[$idx] = str_replace('"', '', $val);
                }
            }

            $i = 0;
            foreach($portNames as $oid => $val) {
                $index = substr(strrchr($oid, "."), 1);
                $port_name = str_replace('"', '', $val);
                
                $traffic_data[$index] = [
                    'name' => $port_name,
                    'desc' => (!empty($descriptions[$index])) ? $descriptions[$index] : "—",
                    'status' => (isset($portStatus[$i]) && (int)$portStatus[$i] == 1) ? "UP" : "DOWN"
                ];
                $i++;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard | BDCOM Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --sidebar-bg: #0f172a; --sidebar-hover: #1e293b; --accent-blue: #0ea5e9; --bg-light: #f8fafc; }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; }
        .sidebar { min-height: 100vh; background: var(--sidebar-bg); color: white; position: sticky; top: 0; }
        .nav-link { color: #cbd5e1; border-radius: 10px; margin: 4px 0; padding: 10px 15px; transition: 0.3s; }
        .nav-link:hover, .nav-link.active { background: var(--sidebar-hover); color: var(--accent-blue); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .status-up { color: #10b981; font-weight: bold; }
        .status-down { color: #ef4444; font-weight: bold; }
        .table thead { background-color: var(--sidebar-bg); color: white; }
        .rx-power, .tx-power { font-family: 'Courier New', monospace; font-weight: 600; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3">
            <div class="text-center py-4">
                <i class="fas fa-network-wired fa-2x text-primary mb-2"></i>
                <div class="fw-bold text-uppercase">BDCOM Monitor</div>
            </div>
            <?php $all = $conn->query("SELECT * FROM switches"); while($s = $all->fetch_assoc()): ?>
                <a href="?id=<?=$s['id']?>" class="nav-link <?=$s['id']==$switch_id?'active':''?>">
                    <i class="fas fa-server me-2"></i> <?=$s['name']?>
                </a>
            <?php endwhile; ?>
            <hr class="text-secondary">
            <a href="devices.php" class="nav-link"><i class="fas fa-plus-circle me-2"></i> Add Device</a>
            <a href="settings.php" class="nav-link"><i class="fab fa-telegram me-2"></i> Bot Settings</a>
            <a href="logout.php" class="nav-link text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0 text-dark"><?= $switch ? $switch['name'] : 'Switch' ?></h3>
                    <small class="text-muted"><i class="fas fa-map-marker-alt me-1"></i> IP: <?= $snmp_target ?></small>
                </div>
                <div class="badge bg-primary px-3 py-2">Auto Refreshing</div>
            </div>

            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">PORT</th>
                                <th>NAME</th>
                                <th>DESCRIPTION</th>
                                <th>STATUS</th>
                                <th>VLAN ID</th>
                                <th>TRAFFIC</th>
                                <th>RX POWER</th>
                                <th>TX POWER</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($traffic_data as $idx => $d): ?>
                            <tr data-index="<?=$idx?>">
                                <td class="ps-4 fw-bold"><?=$idx?></td>
                                <td><?=$d['name']?></td>
                                <td class="text-muted small"><?=$d['desc']?></td>
                                <td><span class="<?=$d['status']=='UP'?'status-up':'status-down'?>">● <?=$d['status']?></span></td>
                                <td class="vlan-id">--</td>
                                <td class="traffic-val"><span class="badge bg-light text-dark border">0.00 Mbps</span></td>
                                <td class="rx-power text-muted">Loading...</td>
                                <td class="tx-power text-muted">Loading...</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const switchId = <?= $switch_id ?>;

function updateData() {
    if(!switchId) return;

    fetch(`fetch_traffic.php?id=${switchId}`)
        .then(res => res.json())
        .then(data => {
            data.forEach(port => {
                const row = document.querySelector(`tr[data-index="${port.index}"]`);
                if (row) {
                    // ১. ভিলান আপডেট (VLAN ID)
                    const vlanCell = row.querySelector('.vlan-id');
                    if(vlanCell) vlanCell.innerHTML = `<span class="badge bg-secondary">${port.vlan || 'N/A'}</span>`;

                    // ২. ট্রাফিক আপডেট (Traffic)
                    const trafficCell = row.querySelector('.traffic-val');
                    if(trafficCell) trafficCell.innerHTML = `<span class="badge bg-info text-dark">${port.mbps} Mbps</span>`;

                    // ৩. RX Power আপডেট
                    const rxCell = row.querySelector('.rx-power');
                    if(rxCell) {
                        rxCell.innerText = port.rx;
                        // কালার কোডিং
                        let val = parseFloat(port.rx);
                        rxCell.className = 'rx-power ' + (val > -15 ? 'text-success' : (val > -25 ? 'text-warning' : 'text-danger'));
                    }

                    // ৪. TX Power আপডেট
                    const txCell = row.querySelector('.tx-power');
                    if(txCell) {
                        txCell.innerText = port.tx;
                        let val = parseFloat(port.tx);
                        txCell.className = 'tx-power ' + (val > 0 ? 'text-success' : 'text-primary');
                    }
                }
            });
        }).catch(err => console.error("Update error:", err));
}

// প্রতি ৩ সেকেন্ড অন্তর আপডেট হবে
if(switchId > 0) {
    updateData();
    setInterval(updateData, 2000);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>