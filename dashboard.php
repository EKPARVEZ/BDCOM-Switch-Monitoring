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
if($switch) {
    // SNMP ফাংশনগুলো কাজ করার জন্য এক্সটেনশন এনাবল থাকতে হবে
    if (function_exists('snmp_set_quick_print')) {
        snmp_set_quick_print(1);
        $portNames = @snmp2_real_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.2.1.2.2.1.2");
        $portAlias = @snmp2_real_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.2.1.31.1.1.1.18");
        $portStatus = @snmp2_walk($switch['ip_address'], $switch['community'], ".1.3.6.1.2.1.2.2.1.8");

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
                $description = (!empty($descriptions[$index])) ? $descriptions[$index] : "—";

                $traffic_data[$index] = [
                    'name' => $port_name,
                    'desc' => $description,
                    'status' => (isset($portStatus[$i]) && (int)$portStatus[$i] == 1) ? "UP" : "DOWN",
                    'is_lacp' => (stripos($port_name, 'aggregator') !== false || stripos($port_name, 'channel') !== false || stripos($port_name, 'Eth-Trunk') !== false)
                ];
                $i++;
            }
        }
    }
}
?> <!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Switch Monitoring</title>
    <link rel="icon" type="image/png" sizes="32x32" href="bd.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { 
            --sidebar-bg: #0f172a; 
            --sidebar-hover: #1e293b; 
            --accent-blue: #0ea5e9; 
            --accent-purple: #8b5cf6;
            --text-gray: #94a3b8;
            --bg-light: #f8fafc;
        }
        body { background-color: var(--bg-light); font-family: 'Inter', sans-serif; }
        .sidebar { min-height: 100vh; background: var(--sidebar-bg); color: white; border-right: 1px solid #1e293b; }
        .brand-logo { background: linear-gradient(45deg, var(--accent-blue), var(--accent-purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-size: 1.5rem; font-weight: 800; }
        .section-title { color: var(--text-gray); font-size: 0.75rem; letter-spacing: 0.05rem; margin-top: 1.5rem; padding-left: 0.75rem; }
        .nav-link { color: #cbd5e1; border-radius: 10px; margin: 4px 0; padding: 10px 15px; transition: 0.3s; font-size: 0.9rem; border-left: 3px solid transparent; text-decoration: none; display: block; }
        .nav-link:hover { background: var(--sidebar-hover); color: var(--accent-blue); }
        .nav-link.active { background: rgba(14, 165, 233, 0.15); color: var(--accent-blue); border-left: 3px solid var(--accent-blue); font-weight: 600; }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        .status-up { color: #10b981; }
        .status-down { color: #ef4444; }
        .table-dark { background-color: var(--sidebar-bg); }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-md-2 sidebar p-3 d-none d-md-block">
            <div class="text-center py-4">
                <i class="fas fa-bolt fa-2x text-primary mb-2"></i>
                <div class="brand-logo">Monitoring</div>
            </div>
            <div class="nav flex-column mt-3">
                <p class="section-title text-uppercase fw-bold mb-2">Network Switches</p>
                <?php $all = $conn->query("SELECT * FROM switches"); while($s = $all->fetch_assoc()): ?>
                    <a href="?id=<?=$s['id']?>" class="nav-link px-3 <?=$s['id']==$switch_id?'active':''?>">
                        <i class="fas fa-server me-2"></i> <?=$s['name']?>
                    </a>
                <?php endwhile; ?>
                <p class="section-title text-uppercase fw-bold mb-2">Configuration</p>
                <a href="devices.php" class="nav-link px-3"><i class="fas fa-sliders-h me-2"></i> Manage Devices</a>
                <a href="users.php" class="nav-link px-3"><i class="fas fa-user-shield me-2"></i> User Access</a>
                <a href="logout.php" class="nav-link px-3 text-danger"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
            </div>
        </div>

        <div class="col-md-10 p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="fw-bold text-slate-800 mb-0"><?= $switch ? $switch['name'] : 'Dashboard' ?></h2>
                    <span class="text-muted small">SNMP IP: <?= $switch ? $switch['ip_address'] : 'N/A' ?></span>
                </div>
            </div>
            
            <div class="card p-4 mb-4">
                <canvas id="myChart" height="80"></canvas>
            </div>

            <div class="card overflow-hidden">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">PORT</th>
                                <th>INTERFACE</th>
                                <th>DESCRIPTION</th>
                                <th>STATUS</th>
                                <th>THROUGHPUT</th>
                                <th class="pe-4">LOAD</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($traffic_data as $idx => $d): ?>
                            <tr data-index="<?=$idx?>">
                                <td class="ps-4 fw-bold"><?=$idx?></td>
                                <td><?=$d['name']?></td>
                                <td class="text-muted small"><i><?=$d['desc']?></i></td>
                                <td><span class="<?=$d['status']=='UP'?'status-up':'status-down'?> fw-bold"><?=$d['status']?></span></td>
                                <td><span class="badge rounded-pill bg-light text-dark border px-3 mbps-text">0.00 Mbps</span></td>
                                <td class="pe-4">
                                    <div class="progress" style="height: 8px; width: 100px;">
                                        <div class="progress-bar" style="width: 0%"></div>
                                    </div>
                                </td>
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
let trafficChart;

function initChart() {
    const ctx = document.getElementById('myChart').getContext('2d');
    trafficChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?= '"' . implode('","', array_keys($traffic_data)) . '"' ?>],
            datasets: [{
                label: 'Mbps',
                data: Array(<?= count($traffic_data) ?>).fill(0),
                borderColor: '#0ea5e9',
                fill: true,
                tension: 0.4
            }]
        }
    });
}

function updateTraffic() {
    fetch(`fetch_traffic.php?id=${switchId}`)
        .then(res => res.json())
        .then(data => {
            let chartValues = [];
            data.forEach(port => {
                const row = document.querySelector(`tr[data-index="${port.index}"]`);
                if (row) {
                    row.querySelector('.mbps-text').innerText = port.mbps + ' Mbps';
                    row.querySelector('.progress-bar').style.width = port.percent + '%';
                }
                chartValues.push(port.mbps);
            });
            trafficChart.data.datasets[0].data = chartValues;
            trafficChart.update();
        });
}

document.addEventListener('DOMContentLoaded', () => {
    initChart();
    setInterval(updateTraffic, 3000);
});
</script>
</body>
</html>
