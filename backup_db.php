<?php
require_once 'config.php';

try {
    $res = $conn->query("SELECT id, nama_instansi, tanggal_masuk, mode_tanggal, status_ringkas, keterangan_berkas, tahap_opd, tahap_disdukcapil, tahap_ditjen FROM berkas_tracking ORDER BY id ASC");
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

    $filename = 'backup_berkas_tracking_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    echo "-- Backup Data berkas_tracking\n";
    echo "-- Tanggal: " . date('Y-m-d H:i:s') . "\n\n";
    echo "SET NAMES utf8mb4;\n\n";

    foreach ($rows as $r) {
        $namaInst = "'" . $conn->real_escape_string((string)$r['nama_instansi']) . "'";
        $tglMasuk = "'" . $conn->real_escape_string((string)$r['tanggal_masuk']) . "'";
        $modeTgl  = "'" . $conn->real_escape_string((string)$r['mode_tanggal']) . "'";
        $stRing   = "'" . $conn->real_escape_string((string)$r['status_ringkas']) . "'";
        $ket      = ($r['keterangan_berkas'] !== null && $r['keterangan_berkas'] !== '')
            ? "'" . $conn->real_escape_string((string)$r['keterangan_berkas']) . "'"
            : "NULL";
        $tOpd     = "'" . $conn->real_escape_string((string)$r['tahap_opd']) . "'";
        $tDis     = "'" . $conn->real_escape_string((string)$r['tahap_disdukcapil']) . "'";
        $tDit     = "'" . $conn->real_escape_string((string)$r['tahap_ditjen']) . "'";

        echo "INSERT INTO berkas_tracking (nama_instansi, tanggal_masuk, mode_tanggal, status_ringkas, keterangan_berkas, tahap_opd, tahap_disdukcapil, tahap_ditjen) VALUES ("
            . $namaInst . ", " . $tglMasuk . ", " . $modeTgl . ", " . $stRing . ", " . $ket . ", " . $tOpd . ", " . $tDis . ", " . $tDit
            . ");\n";
    }
    exit;
} catch (Throwable $e) {
    http_response_code(500);
    echo "Gagal membuat backup: " . htmlspecialchars($e->getMessage());
}