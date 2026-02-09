# 🏭 Smart Factory Safety & Intelligent Navigation System

A **Smart Factory prototype** that detects hazardous situations (gas leaks, fire/temperature rise, and human presence) and **automatically reroutes workers**, controls gates/doors, and provides **zone‑wise alerts** to ensure maximum safety.

This project combines **IoT sensors, computer vision, and smart routing logic** using **ESP32, ESP32‑CAM, Raspberry Pi 4**, and multiple sensors.

---

## 🚀 Project Overview

Modern factories require fast and reliable safety responses. This system continuously monitors different factory zones and reacts in real time:

* Detects **gas leaks** and **abnormal temperature rise**
* Identifies **human presence** in restricted/danger zones
* Performs **face detection** for access control
* Automatically **reroutes workers** when a zone becomes unsafe
* Controls **gates/doors** to block or open paths dynamically
* Sends **zone‑specific alerts** (e.g., Site 1, Site 2)

The entire setup is implemented as a **scaled physical prototype** representing a smart factory layout.

---

## 🧠 System Architecture

### 🔹 Microcontrollers & Processing Units

* **ESP32** – Sensor data collection, zone monitoring, control logic
* **ESP32‑CAM** – Human presence detection in restricted zones
* **Raspberry Pi 4** – Face detection and gate control

### 🔹 Sensors & Modules

* Gas Sensor (MQ series)
* Temperature Sensor
* Camera Modules (ESP32‑CAM & Pi Camera)
* Servo Motors / Relays (for gates & doors)

---

## 🏗️ Factory Zones

The prototype factory is divided into multiple zones:

* 🟢 **Working Zones**
* 🟡 **Lunch / Rest Zones**
* 🔴 **Danger Zones (Site 1, Site 2, Site 3)**

Even with **a single gas sensor**, the system can:

* Detect danger
* Identify the affected zone logically
* Alert users with **exact zone information**

---

## ⚙️ Key Features

* ✅ Real‑time gas leak detection
* ✅ Temperature‑based fire warning
* ✅ Zone‑wise hazard identification
* ✅ Intelligent worker rerouting
* ✅ Automatic door/gate control
* ✅ Human presence detection
* ✅ Face detection for secure access
* ✅ Scalable for real factory deployment

---

## 🛠️ Tech Stack

| Category        | Technology                     |
| --------------- | ------------------------------ |
| Microcontroller | ESP32, ESP32‑CAM               |
| Processor       | Raspberry Pi 4                 |
| Programming     | C / C++, Python                |
| Sensors         | Gas Sensor, Temperature Sensor |
|                 | mpu                            |
| Computer Vision | OpenCV (Raspberry Pi)          |
| Communication   | GPIO / Serial / Wi‑Fi          |

---

## 📂 Project Structure

```text
Smart-Factory/
│
├── ESP32/
│   ├── sensor_logic.ino
│   ├── zone_management.ino
│
├── ESP32-CAM/
│   ├── human_detection.ino
│
├── RaspberryPi/
│   ├── face_detection.py
│   ├── gate_control.py
│
├── Docs/
│   ├── system_diagram.png
│   ├── zone_layout.png
│
└── README.md
```

---

## 🧪 How It Works

1. Sensors continuously monitor environmental conditions
2. ESP32 analyzes data and determines zone safety
3. If danger is detected:

   * 해당 zone is marked unsafe
   * Alternate routes are opened
   * Unsafe paths are blocked
4. ESP32‑CAM detects human presence in restricted areas
5. Raspberry Pi performs face detection and controls gate access

---

## 📸 Prototype Demonstration

> Images and videos of the working prototype can be found in the **Docs/** folder.

---

## 🎯 Future Improvements

* Mobile app for real‑time alerts
* Cloud dashboard for monitoring
* AI‑based hazard prediction
* Multiple gas sensor fusion
* RFID‑based worker tracking

---

## 👨‍💻 Author

**Tanvir Azad (Mahir),Sifatullah,Omar,Labib,Tasnuva**
BSc in Computer Science & Engineering
United International University (UIU)

---

## 📜 License

This project is for **academic and research purposes**.
Feel free to fork and improve 🚀

---

⭐ If you find this project useful, don’t forget to give it a star on GitHub!
