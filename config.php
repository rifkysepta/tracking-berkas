<?php
date_default_timezone_set('Asia/Jakarta');

if (defined('MYSQLI_REPORT_ERROR') && defined('MYSQLI_REPORT_STRICT')) {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'db_tracking_berkas';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    http_response_code(500);
    die("Koneksi database gagal: " . htmlspecialchars($e->getMessage()));
}

function badgeStage($val)
{
    switch ((string)$val) {
        case 'Selesai':
            return ['bg-emerald-100 text-emerald-800 border-emerald-300', 'fa-circle-check'];
        case 'Proses':
            return ['bg-sky-100 text-sky-800 border-sky-300', 'fa-spinner fa-spin'];
        case 'Revisi':
            return ['bg-rose-100 text-rose-800 border-rose-300', 'fa-circle-exclamation'];
        default:
            return ['bg-slate-100 text-slate-600 border-slate-200', 'fa-clock'];
    }
}

function badgeStatus($val)
{
    switch ((string)$val) {
        case 'Selesai':
            return 'bg-emerald-500/15 text-emerald-700 ring-1 ring-emerald-500/30';
        case 'Diproses':
            return 'bg-amber-500/15 text-amber-800 ring-1 ring-amber-500/30';
        case 'Revisi':
            return 'bg-rose-500/15 text-rose-700 ring-1 ring-rose-500/30';
        default:
            return 'bg-indigo-500/15 text-indigo-700 ring-1 ring-indigo-500/30';
    }
}

function normalizeDate($dateStr)
{
    $str = trim((string)$dateStr);
    if ($str !== '') {
        $dt = DateTime::createFromFormat('Y-m-d', $str);
        if ($dt !== false && $dt->format('Y-m-d') === $str) {
            return $dt->format('Y-m-d');
        }
    }
    return date('Y-m-d');
}

function formatTanggalIndo($dateStr)
{
    $str = trim((string)$dateStr);
    $dt = DateTime::createFromFormat('Y-m-d', $str);
    return ($dt !== false) ? $dt->format('d/m/Y') : htmlspecialchars($str);
}

function hitungRekapBerkas(array $rows)
{
    $rekap = [
        'total'    => count($rows),
        'Masuk'    => 0,
        'Diproses' => 0,
        'Revisi'   => 0,
        'Selesai'  => 0,
        'opd_selesai'     => 0,
        'disduk_selesai'  => 0,
        'ditjen_selesai'  => 0,
    ];

    foreach ($rows as $r) {
        $st = isset($r['status_ringkas']) ? (string)$r['status_ringkas'] : '';
        if (isset($rekap[$st])) {
            $rekap[$st]++;
        }
        if ((isset($r['tahap_opd']) ? $r['tahap_opd'] : '') === 'Selesai') {
            $rekap['opd_selesai']++;
        }
        if ((isset($r['tahap_disdukcapil']) ? $r['tahap_disdukcapil'] : '') === 'Selesai') {
            $rekap['disduk_selesai']++;
        }
        if ((isset($r['tahap_ditjen']) ? $r['tahap_ditjen'] : '') === 'Selesai') {
            $rekap['ditjen_selesai']++;
        }
    }

    return $rekap;
}