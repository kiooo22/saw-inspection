<?php
$data = json_decode(file_get_contents("php://input"), true);
$scores = $data['scores'];

$detail = [];
$total = 0;

foreach ($scores as $s) {
    $nilai_normalisasi = ($s['kriteria'] === 'cost' && $s['nilai'] != 0)
        ? 1 / $s['nilai']
        : $s['nilai'];

    $nilai_akhir = $nilai_normalisasi * $s['bobot'];

    $detail[] = [
        "name" => $s['name'],
        "nilai" => $s['nilai'],
        "bobot" => $s['bobot'],
        "kriteria" => $s['kriteria'],
        "nilai_akhir" => $nilai_akhir
    ];

    $total += $nilai_akhir;
}

echo json_encode([
    "detail" => $detail,
    "total" => $total
]);
?>
