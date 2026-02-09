<?php
$conn = new mysqli("localhost", "root", "", "smart_factory");
if ($conn->connect_error) {
    die("DB Error: " . $conn->connect_error);
}

// Receive POST data
$site = isset($_POST['site_id']) ? $_POST['site_id'] : 0;
$gas = isset($_POST['gas_value']) ? $_POST['gas_value'] : 0;
$gas_status = isset($_POST['gas_status']) ? $_POST['gas_status'] : '';
$temp = isset($_POST['temperature']) ? $_POST['temperature'] : 0;
$temp_status = isset($_POST['temp_status']) ? $_POST['temp_status'] : '';
$humidity = isset($_POST['humidity']) ? $_POST['humidity'] : 0;
$fan = isset($_POST['fan_status']) ? $_POST['fan_status'] : '';
$mpu_value = isset($_POST['mpu_value']) ? $_POST['mpu_value'] : 0;

// Insert using prepared statement
$stmt = $conn->prepare("INSERT INTO sensor_data 
(site_id, gas_value, gas_status, temperature, temp_status, humidity, fan_status, mpu_value) 
VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
$stmt->bind_param("issdsdsd", $site, $gas, $gas_status, $temp, $temp_status, $humidity, $fan, $mpu_value);

if ($stmt->execute()) {
    echo "OK";
} else {
    echo "ERROR: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>
