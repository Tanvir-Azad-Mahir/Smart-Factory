<?php
header('Content-Type: application/json');

// Connect to MySQL
$conn = new mysqli("localhost", "root", "", "smart_factory");
if ($conn->connect_error) {
    echo json_encode([]);
    exit;
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
            'mpu_value' => $row['mpu_value']
        ];
    }
}

$conn->close();
echo json_encode($data);
?>
