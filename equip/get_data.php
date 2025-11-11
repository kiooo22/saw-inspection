<?php
$conn = new mysqli("localhost", "root", "", "equipbase");
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

header('Content-Type: application/json');

// 🔹 Ambil daftar EQUIPMENT berdasarkan PLANT (CTA1 / CTA2)
if (isset($_GET['plant'])) {
    $plant = $conn->real_escape_string($_GET['plant']);
    $result = $conn->query("SELECT id, id_equipment_name FROM equipment WHERE id_plant = '$plant'");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// 🔹 Ambil daftar INSPECTION berdasarkan EQUIPMENT
if (isset($_GET['equipment_id'])) {
    $id = intval($_GET['equipment_id']);
    $result = $conn->query("SELECT id, inspection_name FROM inspection WHERE equipment_id = $id");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}

// 🔹 Ambil daftar KRITERIA
if (isset($_GET['type']) && $_GET['type'] == 'criteria') {
    $result = $conn->query("SELECT id, name, bobot, kriteria FROM criteria");
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    echo json_encode($data);
    exit;
}
?>
