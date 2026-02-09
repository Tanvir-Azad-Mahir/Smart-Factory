<?php
// Get real-time data from database
function getSensorData() {
    $conn = new mysqli("localhost", "root", "", "smart_factory");
    
    if ($conn->connect_error) {
        return ["error" => "Database connection failed"];
    }
    
    // Get latest row per site
    $sql = "SELECT * FROM sensor_data WHERE id IN (SELECT MAX(id) FROM sensor_data GROUP BY site_id)";
    $result = $conn->query($sql);
    
    $data = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()){
            $data[$row['site_id']] = [
                'gas_value' => $row['gas_value'],
                'gas_status' => $row['gas_status'],
                'temperature' => $row['temperature'],
                'temp_status' => $row['temp_status'],
                'humidity' => $row['humidity'],
                'fan_status' => $row['fan_status'],
                'created_at' => $row['created_at'],
                'mpu_value' => $row['mpu_value']
            ];
        }
    }
    
    // Get historical data for graphs (last 20 readings)
    $history_sql = "SELECT * FROM (
                    SELECT * FROM sensor_data 
                    ORDER BY created_at DESC 
                    LIMIT 20
                ) AS recent 
                ORDER BY created_at ASC";
    $history_result = $conn->query($history_sql);
    
    $history_data = [];
    if ($history_result->num_rows > 0) {
        while($row = $history_result->fetch_assoc()){
            $site_id = $row['site_id'];
            if (!isset($history_data[$site_id])) {
                $history_data[$site_id] = [
                    'timestamps' => [],
                    'gas_values' => [],
                    'temperatures' => [],
                    'humidities' => [],
                    'mpu_values' => []
                ];
            }
            
            $history_data[$site_id]['timestamps'][] = date('H:i', strtotime($row['created_at']));
            $history_data[$site_id]['gas_values'][] = $row['gas_value'];
            $history_data[$site_id]['temperatures'][] = $row['temperature'];
            $history_data[$site_id]['humidities'][] = $row['humidity'];
            $history_data[$site_id]['mpu_values'][] = $row['mpu_value'];
        }
    }
    
    $conn->close();
    
    return [
        'current' => $data,
        'history' => $history_data,
        'timestamp' => time()
    ];
}

// If this is an AJAX request, return JSON only
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: application/json');
    echo json_encode(getSensorData());
    exit;
}

// Get initial data for page load
$allData = getSensorData();
$sensorData = isset($allData['current']) ? $allData['current'] : [];
$historyData = isset($allData['history']) ? $allData['history'] : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Smart Factory Dashboard</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<!-- Chart.js for graphs -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
    background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
    color: #f0f0f0;
    min-height: 100vh;
    padding: 20px;
}

.container {
    max-width: 1600px;
    margin: 0 auto;
}

header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

h1 {
    font-size: 2.8rem;
    background: linear-gradient(90deg, #00c6ff, #0072ff);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 15px;
}

.subtitle {
    color: #aaa;
    font-size: 1.1rem;
    margin-bottom: 5px;
}

.update-info {
    color: #4facfe;
    font-size: 0.9rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.live-indicator {
    display: flex;
    align-items: center;
    gap: 5px;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.controls {
    display: flex;
    justify-content: space-between;
    margin-bottom: 20px;
    gap: 15px;
}

.control-left, .control-right {
    display: flex;
    gap: 15px;
}

.btn {
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.2);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s;
    font-size: 14px;
}

.btn:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.btn.active {
    background: rgba(79, 172, 254, 0.3);
    border-color: #4facfe;
}

.dashboard {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(650px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.site-card {
    background: rgba(25, 40, 55, 0.8);
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.05);
    transition: transform 0.3s;
    position: relative;
    overflow: hidden;
}

.site-card:hover {
    transform: translateY(-5px);
}

.site-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}

.site-title-wrapper {
    display: flex;
    align-items: center;
}

.site-title {
    font-size: 1.8rem;
    margin-left: 15px;
    background: linear-gradient(90deg, #4facfe, #00f2fe);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}

.site-icon {
    font-size: 2rem;
    color: #4facfe;
}

.site-status {
    font-size: 0.9rem;
    padding: 5px 15px;
    border-radius: 20px;
    background: rgba(107, 207, 127, 0.2);
    color: #6bcf7f;
    border: 1px solid rgba(107, 207, 127, 0.3);
}

.sensor-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 25px;
}

.sensor-item {
    background: rgba(15, 30, 45, 0.6);
    border-radius: 10px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    transition: all 0.3s;
    min-height: 180px;
}

.sensor-item:hover {
    background: rgba(25, 40, 65, 0.8);
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
}

.sensor-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.gas-icon { color: #ff6b6b; }
.temp-icon { color: #ff9e6d; }
.humidity-icon { color: #4d96ff; }
.fan-icon { color: #6bcf7f; }
.mpu-icon { color: #9d4edd; }

.sensor-label {
    font-size: 0.9rem;
    color: #aaa;
    margin-bottom: 5px;
}

.sensor-value {
    font-size: 1.8rem;
    font-weight: 600;
    margin-bottom: 8px;
    transition: all 0.5s ease;
}

.value-update {
    animation: highlight 1s ease;
}

@keyframes highlight {
    0% { background-color: rgba(79, 172, 254, 0.3); border-radius: 5px; }
    100% { background-color: transparent; }
}

.sensor-status {
    font-size: 0.9rem;
    padding: 5px 15px;
    border-radius: 20px;
    font-weight: 600;
    margin-top: auto;
    transition: all 0.5s ease;
}

.status-high {
    background-color: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
    border: 1px solid rgba(255, 107, 107, 0.3);
}

.status-normal {
    background-color: rgba(107, 207, 127, 0.2);
    color: #6bcf7f;
    border: 1px solid rgba(107, 207, 127, 0.3);
}

.status-low {
    background-color: rgba(77, 150, 255, 0.2);
    color: #4d96ff;
    border: 1px solid rgba(77, 150, 255, 0.3);
}

.status-warning {
    background-color: rgba(255, 193, 7, 0.2);
    color: #ffc107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.fan-on {
    background-color: rgba(107, 207, 127, 0.2);
    color: #6bcf7f;
    border: 1px solid rgba(107, 207, 127, 0.3);
}

.fan-off {
    background-color: rgba(255, 107, 107, 0.2);
    color: #ff6b6b;
    border: 1px solid rgba(255, 107, 107, 0.3);
}

.graph-section {
    background: rgba(15, 30, 45, 0.6);
    border-radius: 10px;
    padding: 20px;
    margin-top: 20px;
}

.graph-title {
    font-size: 1.2rem;
    margin-bottom: 15px;
    color: #4facfe;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.graph-tabs {
    display: flex;
    gap: 10px;
}

.graph-tab {
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: white;
    padding: 5px 15px;
    border-radius: 15px;
    cursor: pointer;
    font-size: 0.8rem;
    transition: all 0.3s;
}

.graph-tab:hover {
    background: rgba(255, 255, 255, 0.1);
}

.graph-tab.active {
    background: rgba(79, 172, 254, 0.3);
    border-color: #4facfe;
}

.graph-container {
    position: relative;
    height: 250px;
    width: 100%;
}

.last-readings {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    color: #777;
    font-size: 0.9rem;
}

.update-time {
    color: #4facfe;
    font-weight: bold;
}

.footer {
    text-align: center;
    margin-top: 40px;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
    color: #888;
    font-size: 0.9rem;
}

.no-data {
    text-align: center;
    padding: 40px;
    grid-column: span 2;
}

.no-data i {
    font-size: 3rem;
    color: #ff9e6d;
    margin-bottom: 20px;
}

/* Earthquake Animation */
.earthquake-animation {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    pointer-events: none;
    z-index: 1;
    opacity: 0;
    transition: opacity 0.3s;
}

.earthquake-active .earthquake-animation {
    opacity: 1;
}

.quake-line {
    position: absolute;
    height: 2px;
    background: linear-gradient(90deg, transparent, #ff6b6b, transparent);
    width: 100%;
    animation: quake 0.5s infinite;
}

.quake-line:nth-child(1) { top: 20%; animation-delay: 0s; }
.quake-line:nth-child(2) { top: 40%; animation-delay: 0.1s; }
.quake-line:nth-child(3) { top: 60%; animation-delay: 0.2s; }
.quake-line:nth-child(4) { top: 80%; animation-delay: 0.3s; }

@keyframes quake {
    0%, 100% { transform: translateX(0); opacity: 0.3; }
    25% { transform: translateX(-10px); opacity: 0.7; }
    50% { transform: translateX(10px); opacity: 1; }
    75% { transform: translateX(-5px); opacity: 0.7; }
}

.site-card.earthquake-active {
    animation: shake 0.5s infinite;
    border-color: #ff6b6b;
}

@keyframes shake {
    0%, 100% { transform: translateX(0) translateY(0); }
    10%, 30%, 50%, 70%, 90% { transform: translateX(-5px) translateY(-2px); }
    20%, 40%, 60%, 80% { transform: translateX(5px) translateY(2px); }
}

.mpu-severity {
    font-size: 1rem;
    margin-top: 5px;
    color: #ffc107;
}

/* Responsive design */
@media (max-width: 1400px) {
    .dashboard {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .sensor-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .site-card {
        padding: 15px;
    }
    
    .graph-tabs {
        flex-wrap: wrap;
    }
    
    h1 {
        font-size: 2rem;
        flex-direction: column;
        gap: 10px;
    }
    
    .controls {
        flex-direction: column;
    }
    
    .control-left, .control-right {
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .sensor-grid {
        grid-template-columns: 1fr;
    }
}

/* Fan spin animation */
.fa-spin {
    animation: fa-spin 2s infinite linear;
}

@keyframes fa-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Real-time update notification */
.update-notification {
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(107, 207, 127, 0.9);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    display: none;
    z-index: 1000;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateX(100%); opacity: 0; }
    to { transform: translateX(0); opacity: 1; }
}

/* Alert count badge */
.alert-count {
    background: #ff6b6b;
    color: white;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    margin-left: 5px;
}
</style>
</head>
<body>
<!-- Update notification -->
<div class="update-notification" id="updateNotification">
    <i class="fas fa-sync-alt"></i> Data Updated Successfully
</div>

<div class="container">
    <header>
        <h1><i class="fas fa-industry"></i> Smart Factory Dashboard</h1>
        <p class="subtitle">Real-time monitoring of factory sensors and equipment with graphical analytics</p>
        <div class="update-info">
            <div class="live-indicator">
                <i class="fas fa-circle" style="color: #6bcf7f; font-size: 12px;"></i>
                LIVE DATA STREAMING
            </div>
            <span>•</span>
            <span>Last update: <span id="lastUpdateTime">Just now</span></span>
            <span>•</span>
            <span>Update frequency: <span id="updateFrequency">1 second</span></span>
        </div>
    </header>
    
    <div class="controls">
        <div class="control-left">
            <button class="btn" id="manualUpdateBtn" onclick="fetchData(true)">
                <i class="fas fa-sync-alt"></i> Update Now
            </button>
            <button class="btn active" id="autoUpdateBtn" onclick="toggleAutoUpdate()">
                <i class="fas fa-bolt"></i> Auto Update: ON
            </button>
        </div>
        <div class="control-right">
            <button class="btn" onclick="showDataLog()">
                <i class="fas fa-history"></i> Data Log
            </button>
            <button class="btn" onclick="showAlerts()">
                <i class="fas fa-bell"></i> Alerts
                <span id="alertCount" class="alert-count">0</span>
            </button>
        </div>
    </div>
    
    <div class="dashboard" id="dashboard">
        <?php
        // Display data from database
        if (isset($sensorData['error'])) {
            echo '<div class="no-data">
                    <i class="fas fa-exclamation-triangle"></i>
                    <h3>Database Error</h3>
                    <p>' . $sensorData['error'] . '</p>
                  </div>';
        } elseif (empty($sensorData)) {
            echo '<div class="no-data">
                    <i class="fas fa-database"></i>
                    <h3>No Data Available</h3>
                    <p>No sensor readings found in the database</p>
                  </div>';
        } else {
            // Display sites 1 and 2
            for ($site_id = 1; $site_id <= 2; $site_id++) {
                $d = isset($sensorData[$site_id]) ? $sensorData[$site_id] : null;
                $history = isset($historyData[$site_id]) ? $historyData[$site_id] : null;
                
                echo '<div class="site-card" id="site-' . $site_id . '">';
                
                // Earthquake animation overlay
                echo '<div class="earthquake-animation" id="site-' . $site_id . '-quake">
                        <div class="quake-line"></div>
                        <div class="quake-line"></div>
                        <div class="quake-line"></div>
                        <div class="quake-line"></div>
                      </div>';
                
                echo '<div class="site-header">
                        <div class="site-title-wrapper">
                            <i class="fas fa-factory site-icon"></i>
                            <h2 class="site-title">Factory Site ' . $site_id . '</h2>
                        </div>
                        <div class="site-status">LIVE</div>
                      </div>';
                
                if ($d) {
                    // Calculate overall status
                    $isWarning = ($d['gas_status'] == 'HIGH' || $d['temp_status'] == 'HIGH' || $d['temp_status'] == 'LOW');
                    
                    // MPU value analysis
                    $mpu_value = isset($d['mpu_value']) ? floatval($d['mpu_value']) : 0;
                    $mpu_status = 'NORMAL';
                    $mpu_class = 'status-normal';
                    $mpu_severity = '';
                    
                    if ($mpu_value >= 80) {
                        $mpu_status = 'DANGER';
                        $mpu_class = 'status-high';
                        $mpu_severity = '(High Vibration)';
                    } elseif ($mpu_value >= 50) {
                        $mpu_status = 'WARNING';
                        $mpu_class = 'status-warning';
                        $mpu_severity = '(Moderate Vibration)';
                    } elseif ($mpu_value >= 20) {
                        $mpu_status = 'NORMAL';
                        $mpu_class = 'status-normal';
                        $mpu_severity = '(Low Vibration)';
                    } else {
                        $mpu_status = 'LOW';
                        $mpu_class = 'status-low';
                        $mpu_severity = '(Minimal Vibration)';
                    }
                    
                    echo '<div class="sensor-grid">';
                    // Gas sensor
                    $gasColor = $d['gas_status'] == 'HIGH' ? 'status-high' : 'status-normal';
                    echo '<div class="sensor-item" id="site-' . $site_id . '-gas">
                            <i class="fas fa-radiation-alt sensor-icon gas-icon"></i>
                            <div class="sensor-label">GAS LEVEL</div>
                            <div class="sensor-value" id="site-' . $site_id . '-gas-value">' . number_format($d['gas_value'], 1) . ' ppm</div>
                            <div class="sensor-status ' . $gasColor . '" id="site-' . $site_id . '-gas-status">' . $d['gas_status'] . '</div>
                          </div>';
                    
                    // Temperature sensor
                    $tempStatusClass = 'status-normal';
                    if ($d['temp_status'] == 'HIGH') $tempStatusClass = 'status-high';
                    if ($d['temp_status'] == 'LOW') $tempStatusClass = 'status-low';
                    
                    echo '<div class="sensor-item" id="site-' . $site_id . '-temp">
                            <i class="fas fa-thermometer-half sensor-icon temp-icon"></i>
                            <div class="sensor-label">TEMPERATURE</div>
                            <div class="sensor-value" id="site-' . $site_id . '-temp-value">' . number_format($d['temperature'], 1) . ' °C</div>
                            <div class="sensor-status ' . $tempStatusClass . '" id="site-' . $site_id . '-temp-status">' . $d['temp_status'] . '</div>
                          </div>';
                    
                    // Humidity sensor
                    $humidityStatus = 'NORMAL';
                    if ($d['humidity'] > 70) $humidityStatus = 'HIGH';
                    if ($d['humidity'] < 30) $humidityStatus = 'LOW';
                    
                    $humidityClass = 'status-normal';
                    if ($humidityStatus == 'HIGH') $humidityClass = 'status-high';
                    if ($humidityStatus == 'LOW') $humidityClass = 'status-low';
                    
                    echo '<div class="sensor-item" id="site-' . $site_id . '-humidity">
                            <i class="fas fa-tint sensor-icon humidity-icon"></i>
                            <div class="sensor-label">HUMIDITY</div>
                            <div class="sensor-value" id="site-' . $site_id . '-humidity-value">' . number_format($d['humidity'], 1) . ' %</div>
                            <div class="sensor-status ' . $humidityClass . '" id="site-' . $site_id . '-humidity-status">' . $humidityStatus . '</div>
                          </div>';
                    
                    // MPU (Motion Processing Unit) sensor
                    echo '<div class="sensor-item" id="site-' . $site_id . '-mpu">
                            <i class="fas fa-earth-americas sensor-icon mpu-icon"></i>
                            <div class="sensor-label">VIBRATION / MOTION</div>
                            <div class="sensor-value" id="site-' . $site_id . '-mpu-value">' . number_format($mpu_value, 1) . ' G</div>
                            <div class="sensor-status ' . $mpu_class . '" id="site-' . $site_id . '-mpu-status">' . $mpu_status . '</div>
                            <div class="mpu-severity" id="site-' . $site_id . '-mpu-severity">' . $mpu_severity . '</div>
                          </div>';
                    
                    // Fan status
                    $fanIcon = $d['fan_status'] === 'ON' ? 'fa-fan fa-spin' : 'fa-wind';
                    $fanClass = $d['fan_status'] === 'ON' ? 'fan-on' : 'fan-off';
                    
                    echo '<div class="sensor-item" id="site-' . $site_id . '-fan">
                            <i class="fas ' . $fanIcon . ' sensor-icon fan-icon"></i>
                            <div class="sensor-label">FAN STATUS</div>
                            <div class="sensor-value" id="site-' . $site_id . '-fan-value">' . $d['fan_status'] . '</div>
                            <div class="sensor-status ' . $fanClass . '" id="site-' . $site_id . '-fan-status">' . ($d['fan_status'] === 'ON' ? 'ACTIVE' : 'INACTIVE') . '</div>
                          </div>';
                    
                    echo '</div>'; // Close sensor-grid
                    
                    // Graph Section
                    echo '<div class="graph-section">
                            <div class="graph-title">
                                <span><i class="fas fa-chart-line"></i> Sensor Trends</span>
                                <div class="graph-tabs">
                                    <button class="graph-tab active" onclick="switchGraph(' . $site_id . ', \'gas\')">Gas</button>
                                    <button class="graph-tab" onclick="switchGraph(' . $site_id . ', \'temp\')">Temperature</button>
                                    <button class="graph-tab" onclick="switchGraph(' . $site_id . ', \'humidity\')">Humidity</button>
                                    <button class="graph-tab" onclick="switchGraph(' . $site_id . ', \'mpu\')">Vibration</button>
                                    <button class="graph-tab" onclick="switchGraph(' . $site_id . ', \'all\')">All</button>
                                </div>
                            </div>
                            <div class="graph-container">
                                <canvas id="graph-' . $site_id . '"></canvas>
                            </div>
                          </div>';
                    
                    // Show timestamp
                    if (isset($d['created_at'])) {
                        echo '<div class="last-readings">
                                <span><i class="far fa-clock"></i> Last reading: <span class="update-time" id="site-' . $site_id . '-time">' . date('H:i:s', strtotime($d['created_at'])) . '</span></span>
                                <span>' . date('d/m/Y', strtotime($d['created_at'])) . '</span>
                              </div>';
                    }
                    
                } else {
                    echo '<div class="sensor-item" style="grid-column: span 3;">
                            <i class="fas fa-exclamation-triangle sensor-icon" style="color:#ff9e6d;"></i>
                            <div class="sensor-label">NO DATA AVAILABLE</div>
                            <div class="sensor-value">Site Offline</div>
                            <div class="sensor-status status-high">ERROR</div>
                          </div>';
                }
                
                echo '</div>'; // Close site-card
            }
        }
        ?>
    </div>
    
    <div class="footer">
        <p>Smart Factory Monitoring System v2.0 | Real-time MySQL Database Integration</p>
        <p>Live data streaming enabled | No page refresh required | Updates every second</p>
    </div>
</div>

<script>
// Global variables
let autoUpdate = true;
let updateInterval;
let historyData = <?php echo json_encode($historyData); ?>;
let charts = {};

// Initialize charts on page load
document.addEventListener('DOMContentLoaded', function() {
    // Create initial charts
    [1, 2].forEach(siteId => {
        if (historyData[siteId]) {
            createChart(siteId, 'gas');
        }
    });
    
    // Start real-time updates
    startAutoUpdate();
    
    // Calculate initial alert count
    updateAlertCount();
    
    // Check for initial MPU alerts
    checkMPUAlerts();
});

// Fetch data from server without page reload
async function fetchData(showNotification = false) {
    try {
        const response = await fetch('?ajax=1&t=' + new Date().getTime());
        const data = await response.json();
        
        // Update UI with new data
        updateDashboard(data);
        
        // Update last update time
        const now = new Date();
        document.getElementById('lastUpdateTime').textContent = now.toLocaleTimeString();
        
        // Show notification if requested
        if (showNotification) {
            showUpdateNotification('Data updated successfully!');
        }
        
        return data;
    } catch (error) {
        console.error('Error fetching data:', error);
        showUpdateNotification('Error updating data!', 'error');
    }
}

// Update dashboard with new data
function updateDashboard(data) {
    if (!data.current) return;
    
    // Update each site
    [1, 2].forEach(siteId => {
        const siteData = data.current[siteId];
        if (!siteData) return;
        
        // Update gas sensor
        updateSensorValue('site-' + siteId + '-gas-value', siteData.gas_value + ' ppm', siteData.gas_value);
        updateSensorStatus('site-' + siteId + '-gas-status', siteData.gas_status, 
                          siteData.gas_status === 'HIGH' ? 'status-high' : 'status-normal');
        
        // Update temperature
        updateSensorValue('site-' + siteId + '-temp-value', siteData.temperature + ' °C', siteData.temperature);
        let tempStatusClass = 'status-normal';
        if (siteData.temp_status === 'HIGH') tempStatusClass = 'status-high';
        if (siteData.temp_status === 'LOW') tempStatusClass = 'status-low';
        updateSensorStatus('site-' + siteId + '-temp-status', siteData.temp_status, tempStatusClass);
        
        // Update humidity
        updateSensorValue('site-' + siteId + '-humidity-value', siteData.humidity + ' %', siteData.humidity);
        let humidityStatus = 'NORMAL';
        if (siteData.humidity > 70) humidityStatus = 'HIGH';
        if (siteData.humidity < 30) humidityStatus = 'LOW';
        let humidityClass = 'status-normal';
        if (humidityStatus === 'HIGH') humidityClass = 'status-high';
        if (humidityStatus === 'LOW') humidityClass = 'status-low';
        updateSensorStatus('site-' + siteId + '-humidity-status', humidityStatus, humidityClass);
        
        // Update MPU value
        updateMPUData(siteId, siteData.mpu_value);
        
        // Update fan
        updateSensorValue('site-' + siteId + '-fan-value', siteData.fan_status);
        const fanIcon = document.querySelector('#site-' + siteId + ' .fan-icon');
        if (fanIcon) {
            if (siteData.fan_status === 'ON') {
                fanIcon.className = 'fas fa-fan fa-spin sensor-icon fan-icon';
            } else {
                fanIcon.className = 'fas fa-wind sensor-icon fan-icon';
            }
        }
        updateSensorStatus('site-' + siteId + '-fan-status', 
                          siteData.fan_status === 'ON' ? 'ACTIVE' : 'INACTIVE',
                          siteData.fan_status === 'ON' ? 'fan-on' : 'fan-off');
        
        // Update timestamp
        if (siteData.created_at) {
            const timeElement = document.getElementById('site-' + siteId + '-time');
            if (timeElement) {
                const date = new Date(siteData.created_at);
                timeElement.textContent = date.toLocaleTimeString();
            }
        }
    });
    
    // Update history data for graphs
    if (data.history) {
        historyData = data.history;
        // Update charts if they exist
        [1, 2].forEach(siteId => {
            if (charts['graph-' + siteId]) {
                updateChart(siteId);
            }
        });
    }
    
    // Update alert count
    updateAlertCount();
    // Check for MPU alerts
    checkMPUAlerts();
}

// Update MPU data with earthquake animation
function updateMPUData(siteId, mpuValue) {
    const element = document.getElementById('site-' + siteId + '-mpu-value');
    const statusElement = document.getElementById('site-' + siteId + '-mpu-status');
    const severityElement = document.getElementById('site-' + siteId + '-mpu-severity');
    const siteCard = document.getElementById('site-' + siteId);
    
    if (!element || !statusElement || !severityElement) return;
    
    const oldValue = parseFloat(element.textContent) || 0;
    const newValue = parseFloat(mpuValue) || 0;
    
    element.textContent = newValue.toFixed(1) + ' G';
    
    // Determine MPU status
    let mpuStatus = 'NORMAL';
    let mpuClass = 'status-normal';
    let severity = '';
    
    if (newValue >= 80) {
        mpuStatus = 'DANGER';
        mpuClass = 'status-high';
        severity = '(High Vibration)';
        // Trigger earthquake animation
        siteCard.classList.add('earthquake-active');
    } else if (newValue >= 50) {
        mpuStatus = 'WARNING';
        mpuClass = 'status-warning';
        severity = '(Moderate Vibration)';
        // Trigger mild earthquake animation
        siteCard.classList.add('earthquake-active');
        // Remove after 3 seconds if not in danger zone
        setTimeout(() => {
            if (parseFloat(document.getElementById('site-' + siteId + '-mpu-value').textContent) < 80) {
                siteCard.classList.remove('earthquake-active');
            }
        }, 3000);
    } else if (newValue >= 20) {
        mpuStatus = 'NORMAL';
        mpuClass = 'status-normal';
        severity = '(Low Vibration)';
        siteCard.classList.remove('earthquake-active');
    } else {
        mpuStatus = 'LOW';
        mpuClass = 'status-low';
        severity = '(Minimal Vibration)';
        siteCard.classList.remove('earthquake-active');
    }
    
    // Update status
    updateSensorStatus('site-' + siteId + '-mpu-status', mpuStatus, mpuClass);
    severityElement.textContent = severity;
    
    // Add highlight animation if value changed significantly
    if (Math.abs(newValue - oldValue) > 5) {
        element.classList.add('value-update');
        setTimeout(() => {
            element.classList.remove('value-update');
        }, 1000);
    }
}

// Update individual sensor value with animation
function updateSensorValue(elementId, newValue, numericValue = null) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const oldValue = parseFloat(element.textContent) || 0;
    element.textContent = newValue;
    
    // Add highlight animation if value changed significantly
    if (numericValue !== null && Math.abs(numericValue - oldValue) > 0.5) {
        element.classList.add('value-update');
        setTimeout(() => {
            element.classList.remove('value-update');
        }, 1000);
    }
}

// Update sensor status
function updateSensorStatus(elementId, newStatus, statusClass) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const oldClass = element.className.split(' ').find(cls => cls.includes('status-') || cls.includes('fan-'));
    if (oldClass) {
        element.classList.remove(oldClass);
    }
    
    element.textContent = newStatus;
    element.classList.add(statusClass);
}

// Update chart with new data
function updateChart(siteId) {
    const chart = charts['graph-' + siteId];
    if (!chart || !historyData[siteId]) return;
    
    chart.data.labels = historyData[siteId].timestamps;
    
    if (chart.data.datasets.length === 1) {
        // Single dataset chart
        const type = chart.data.datasets[0].label.toLowerCase();
        if (type.includes('gas')) {
            chart.data.datasets[0].data = historyData[siteId].gas_values;
        } else if (type.includes('temp')) {
            chart.data.datasets[0].data = historyData[siteId].temperatures;
        } else if (type.includes('humidity')) {
            chart.data.datasets[0].data = historyData[siteId].humidities;
        } else if (type.includes('vibration')) {
            chart.data.datasets[0].data = historyData[siteId].mpu_values;
        }
    } else {
        // Multi-dataset chart
        chart.data.datasets[0].data = historyData[siteId].gas_values;
        chart.data.datasets[1].data = historyData[siteId].temperatures;
        chart.data.datasets[2].data = historyData[siteId].humidities;
        chart.data.datasets[3].data = historyData[siteId].mpu_values;
    }
    
    chart.update('none'); // Update without animation for smoother updates
}

// Create Chart.js graph
function createChart(siteId, type = 'gas') {
    const ctx = document.getElementById('graph-' + siteId).getContext('2d');
    const data = historyData[siteId];
    
    if (!data) return;
    
    let datasets = [];
    
    switch(type) {
        case 'gas':
            datasets = [{
                label: 'Gas Level (ppm)',
                data: data.gas_values,
                borderColor: '#ff6b6b',
                backgroundColor: 'rgba(255, 107, 107, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }];
            break;
            
        case 'temp':
            datasets = [{
                label: 'Temperature (°C)',
                data: data.temperatures,
                borderColor: '#ff9e6d',
                backgroundColor: 'rgba(255, 158, 109, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }];
            break;
            
        case 'humidity':
            datasets = [{
                label: 'Humidity (%)',
                data: data.humidities,
                borderColor: '#4d96ff',
                backgroundColor: 'rgba(77, 150, 255, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }];
            break;
            
        case 'mpu':
            datasets = [{
                label: 'Vibration (G)',
                data: data.mpu_values,
                borderColor: '#9d4edd',
                backgroundColor: 'rgba(157, 78, 221, 0.1)',
                tension: 0.4,
                fill: true,
                borderWidth: 2
            }];
            break;
            
        case 'all':
            datasets = [
                {
                    label: 'Gas (ppm)',
                    data: data.gas_values,
                    borderColor: '#ff6b6b',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y'
                },
                {
                    label: 'Temperature (°C)',
                    data: data.temperatures,
                    borderColor: '#ff9e6d',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y1'
                },
                {
                    label: 'Humidity (%)',
                    data: data.humidities,
                    borderColor: '#4d96ff',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y2'
                },
                {
                    label: 'Vibration (G)',
                    data: data.mpu_values,
                    borderColor: '#9d4edd',
                    backgroundColor: 'transparent',
                    tension: 0.4,
                    borderWidth: 2,
                    yAxisID: 'y3'
                }
            ];
            break;
    }
    
    // Destroy existing chart if exists
    if (charts['graph-' + siteId]) {
        charts['graph-' + siteId].destroy();
    }
    
    // Create new chart
    charts['graph-' + siteId] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.timestamps,
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 0 // Disable animation for real-time updates
            },
            plugins: {
                legend: {
                    labels: {
                        color: '#f0f0f0'
                    }
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                    backgroundColor: 'rgba(25, 40, 55, 0.9)',
                    titleColor: '#4facfe',
                    bodyColor: '#f0f0f0',
                    borderColor: '#4facfe',
                    borderWidth: 1
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#aaa',
                        maxRotation: 0
                    }
                },
                y: {
                    type: 'linear',
                    display: type !== 'all',
                    position: 'left',
                    grid: {
                        color: 'rgba(255, 255, 255, 0.1)'
                    },
                    ticks: {
                        color: '#aaa'
                    }
                },
                y1: {
                    type: 'linear',
                    display: type === 'all',
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: '#ff9e6d'
                    }
                },
                y2: {
                    type: 'linear',
                    display: type === 'all',
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: '#4d96ff'
                    }
                },
                y3: {
                    type: 'linear',
                    display: type === 'all',
                    position: 'right',
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        color: '#9d4edd'
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'nearest'
            }
        }
    });
}

// Switch graph type
function switchGraph(siteId, type) {
    // Update active tab
    const tabs = document.querySelectorAll('#site-' + siteId + ' .graph-tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Create new chart
    createChart(siteId, type);
}

// Check for MPU alerts
function checkMPUAlerts() {
    [1, 2].forEach(siteId => {
        const mpuElement = document.getElementById('site-' + siteId + '-mpu-value');
        if (mpuElement) {
            const mpuValue = parseFloat(mpuElement.textContent) || 0;
            const siteCard = document.getElementById('site-' + siteId);
            
            if (mpuValue >= 50) {
                siteCard.classList.add('earthquake-active');
            } else {
                siteCard.classList.remove('earthquake-active');
            }
        }
    });
}

// Start auto-update
function startAutoUpdate() {
    if (updateInterval) clearInterval(updateInterval);
    updateInterval = setInterval(() => {
        if (autoUpdate) {
            fetchData();
        }
    }, 1000); // Update every second
}

// Toggle auto-update
function toggleAutoUpdate() {
    autoUpdate = !autoUpdate;
    const btn = document.getElementById('autoUpdateBtn');
    
    if (autoUpdate) {
        btn.innerHTML = '<i class="fas fa-bolt"></i> Auto Update: ON';
        btn.classList.add('active');
        startAutoUpdate();
        document.getElementById('updateFrequency').textContent = '1 second';
    } else {
        btn.innerHTML = '<i class="fas fa-bolt"></i> Auto Update: OFF';
        btn.classList.remove('active');
        clearInterval(updateInterval);
        document.getElementById('updateFrequency').textContent = 'Manual';
    }
}

// Show update notification
function showUpdateNotification(message, type = 'success') {
    const notification = document.getElementById('updateNotification');
    notification.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
    notification.style.display = 'block';
    notification.style.background = type === 'success' ? 'rgba(107, 207, 127, 0.9)' : 'rgba(255, 107, 107, 0.9)';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 2000);
}

// Update alert count
function updateAlertCount() {
    let alertCount = 0;
    
    // Check for alerts in current data
    [1, 2].forEach(siteId => {
        const gasStatus = document.querySelector('#site-' + siteId + '-gas-status');
        const tempStatus = document.querySelector('#site-' + siteId + '-temp-status');
        const mpuStatus = document.querySelector('#site-' + siteId + '-mpu-status');
        
        if (gasStatus && gasStatus.textContent === 'HIGH') alertCount++;
        if (tempStatus && (tempStatus.textContent === 'HIGH' || tempStatus.textContent === 'LOW')) alertCount++;
        if (mpuStatus && (mpuStatus.textContent === 'DANGER' || mpuStatus.textContent === 'WARNING')) alertCount++;
    });
    
    document.getElementById('alertCount').textContent = alertCount;
}

// Show alerts
function showAlerts() {
    let alerts = [];
    
    [1, 2].forEach(siteId => {
        const gasStatus = document.querySelector('#site-' + siteId + '-gas-status');
        const tempStatus = document.querySelector('#site-' + siteId + '-temp-status');
        const mpuStatus = document.querySelector('#site-' + siteId + '-mpu-status');
        const mpuValue = document.querySelector('#site-' + siteId + '-mpu-value');
        
        if (gasStatus && gasStatus.textContent === 'HIGH') {
            alerts.push(`Site ${siteId}: High gas level detected`);
        }
        if (tempStatus && tempStatus.textContent === 'HIGH') {
            alerts.push(`Site ${siteId}: High temperature detected`);
        }
        if (tempStatus && tempStatus.textContent === 'LOW') {
            alerts.push(`Site ${siteId}: Low temperature detected`);
        }
        if (mpuStatus && mpuStatus.textContent === 'DANGER') {
            const value = mpuValue ? mpuValue.textContent : '';
            alerts.push(`Site ${siteId}: DANGER! High vibration detected ${value}`);
        }
        if (mpuStatus && mpuStatus.textContent === 'WARNING') {
            const value = mpuValue ? mpuValue.textContent : '';
            alerts.push(`Site ${siteId}: Warning! Moderate vibration detected ${value}`);
        }
    });
    
    if (alerts.length > 0) {
        alert('Active Alerts:\n\n' + alerts.join('\n'));
    } else {
        alert('No active alerts. All systems are operating normally.');
    }
}

// Show data log
function showDataLog() {
    alert('Data log would show historical sensor readings.\nThis feature can be implemented to display a table of all recorded data.');
}

// Initialize
startAutoUpdate();
</script>
</body>
</html>