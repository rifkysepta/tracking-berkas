<?php
session_start();
require_once 'config.php';

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

$allowed_status = ['Masuk', 'Diproses', 'Revisi', 'Selesai'];
$allowed_stage  = ['Belum', 'Proses', 'Selesai', 'Revisi'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action        = isset($_POST['action']) ? (string)$_POST['action'] : 'create';
        $nama_instansi = trim((string)(isset($_POST['nama_instansi']) ? $_POST['nama_instansi'] : ''));
        $mode_tanggal  = ((isset($_POST['mode_tanggal']) ? $_POST['mode_tanggal'] : 'otomatis') === 'manual') ? 'manual' : 'otomatis';
        $raw_tanggal   = trim((string)(isset($_POST['tanggal_masuk']) ? $_POST['tanggal_masuk'] : ''));

        if ($mode_tanggal === 'manual') {
            $tanggal_masuk = normalizeDate($raw_tanggal);
        } else {
            if ($action === 'update' && !empty($_POST['id']) && $raw_tanggal !== '') {
                $tanggal_masuk = normalizeDate($raw_tanggal);
            } else {
                $tanggal_masuk = date('Y-m-d');
            }
        }

        $req_status     = isset($_POST['status_ringkas']) ? (string)$_POST['status_ringkas'] : 'Masuk';
        $status_ringkas = in_array($req_status, $allowed_status, true) ? $req_status : 'Masuk';

        $keterangan_berkas = trim((string)(isset($_POST['keterangan_berkas']) ? $_POST['keterangan_berkas'] : ''));

        $req_opd   = isset($_POST['tahap_opd']) ? (string)$_POST['tahap_opd'] : 'Belum';
        $tahap_opd = in_array($req_opd, $allowed_stage, true) ? $req_opd : 'Belum';

        $req_dis          = isset($_POST['tahap_disdukcapil']) ? (string)$_POST['tahap_disdukcapil'] : 'Belum';
        $tahap_disdukcapil = in_array($req_dis, $allowed_stage, true) ? $req_dis : 'Belum';

        $req_dit      = isset($_POST['tahap_ditjen']) ? (string)$_POST['tahap_ditjen'] : 'Belum';
        $tahap_ditjen = in_array($req_dit, $allowed_stage, true) ? $req_dit : 'Belum';

        if ($nama_instansi === '') {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Nama instansi wajib diisi.'];
            header('Location: index.php');
            exit;
        }

        if ($action === 'update' && !empty($_POST['id'])) {
            $id = (int)$_POST['id'];
            $stmt = $conn->prepare(
                "UPDATE berkas_tracking
                 SET nama_instansi = ?, tanggal_masuk = ?, mode_tanggal = ?, status_ringkas = ?,
                     keterangan_berkas = ?, tahap_opd = ?, tahap_disdukcapil = ?, tahap_ditjen = ?
                 WHERE id = ?"
            );
            $stmt->bind_param(
                "ssssssssi",
                $nama_instansi,
                $tanggal_masuk,
                $mode_tanggal,
                $status_ringkas,
                $keterangan_berkas,
                $tahap_opd,
                $tahap_disdukcapil,
                $tahap_ditjen,
                $id
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION['toast'] = [
                'type' => 'success',
                'msg'  => 'Data berkas berhasil diperbarui (' . formatTanggalIndo($tanggal_masuk) . ').'
            ];
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO berkas_tracking
                 (nama_instansi, tanggal_masuk, mode_tanggal, status_ringkas, keterangan_berkas, tahap_opd, tahap_disdukcapil, tahap_ditjen)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param(
                "ssssssss",
                $nama_instansi,
                $tanggal_masuk,
                $mode_tanggal,
                $status_ringkas,
                $keterangan_berkas,
                $tahap_opd,
                $tahap_disdukcapil,
                $tahap_ditjen
            );
            $stmt->execute();
            $stmt->close();

            $_SESSION['toast'] = [
                'type' => 'success',
                'msg'  => 'Berkas baru berhasil ditambahkan (' . formatTanggalIndo($tanggal_masuk) . ').'
            ];
        }

        header('Location: index.php');
        exit;
    }

    if (isset($_GET['hapus'])) {
        $id = (int)$_GET['hapus'];
        if ($id > 0) {
            $stmt = $conn->prepare("DELETE FROM berkas_tracking WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Data berkas berhasil dihapus.'];
        }
        header('Location: index.php');
        exit;
    }

    $q            = trim((string)(isset($_GET['q']) ? $_GET['q'] : ''));
    $filterStatus = (string)(isset($_GET['status']) ? $_GET['status'] : '');
    $exportMode   = isset($_GET['export']) ? (string)$_GET['export'] : '';

    // Rekap keseluruhan data di database
    $allRes  = $conn->query("SELECT status_ringkas, tahap_opd, tahap_disdukcapil, tahap_ditjen FROM berkas_tracking");
    $allRows = $allRes ? $allRes->fetch_all(MYSQLI_ASSOC) : [];
    $rekapAll = hitungRekapBerkas($allRows);

    $sql    = "SELECT id, nama_instansi, tanggal_masuk, mode_tanggal, status_ringkas, keterangan_berkas, tahap_opd, tahap_disdukcapil, tahap_ditjen FROM berkas_tracking WHERE 1=1";
    $params = [];
    $types  = "";

    if ($q !== '') {
        $sql     .= " AND (nama_instansi LIKE ? OR keterangan_berkas LIKE ?)";
        $like     = "%" . $q . "%";
        $params[] = $like;
        $params[] = $like;
        $types   .= "ss";
    }

    if (in_array($filterStatus, $allowed_status, true)) {
        $sql     .= " AND status_ringkas = ?";
        $params[] = $filterStatus;
        $types   .= "s";
    }

    $sql .= " ORDER BY tanggal_masuk DESC, id DESC";

    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $bindArgs = array_merge([$types], $params);
        $refs = [];
        foreach ($bindArgs as $k => $v) {
            $refs[$k] = &$bindArgs[$k];
        }
        call_user_func_array([$stmt, 'bind_param'], $refs);
    }
    $stmt->execute();
    $res  = $stmt->get_result();
    $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();

    // Export CSV (kompatibel Excel)
    if ($exportMode === 'csv') {
        $filename = 'tracking_berkas_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, [
            'NO', 'NAMA INSTANSI', 'TGL MASUK', 'MODE TANGGAL', 'STATUS',
            'KETERANGAN BERKAS', 'OPD', 'DISDUKCAPIL', 'DITJEN DUKCAPIL'
        ]);
        foreach ($rows as $i => $item) {
            fputcsv($out, [
                $i + 1,
                $item['nama_instansi'],
                formatTanggalIndo((string)$item['tanggal_masuk']),
                $item['mode_tanggal'],
                $item['status_ringkas'],
                preg_replace("/\r|\n/", " ", (string)$item['keterangan_berkas']),
                $item['tahap_opd'],
                $item['tahap_disdukcapil'],
                $item['tahap_ditjen']
            ]);
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    $rows     = [];
    $rekapAll = hitungRekapBerkas([]);
    $toast    = ['type' => 'error', 'msg' => 'Terjadi kesalahan sistem: ' . $e->getMessage()];
}

$todayDate = date('Y-m-d');
$todayIndo = date('d/m/Y');

$queryParams = [];
if ($q !== '') $queryParams['q'] = $q;
if ($filterStatus !== '') $queryParams['status'] = $filterStatus;
$queryParams['export'] = 'csv';
$csvUrl = 'index.php?' . http_build_query($queryParams);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tracking Berkas Instansi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes fadeSlideUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popIn {
            from { opacity: 0; transform: scale(0.95) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-fade-up {
            animation: fadeSlideUp 0.38s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .animate-pop-in {
            animation: popIn 0.25s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .row-hover {
            transition: transform 0.2s ease, background-color 0.2s ease, box-shadow 0.2s ease;
        }
        .row-hover:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px -6px rgba(15, 23, 42, 0.12);
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased selection:bg-amber-300 selection:text-slate-900">

    <?php if (is_array($toast)): ?>
    <div id="toast" class="fixed bottom-5 right-5 z-50 animate-pop-in flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium <?= $toast['type'] === 'success' ? 'bg-emerald-900 text-emerald-50 border-emerald-700' : 'bg-rose-900 text-rose-50 border-rose-700' ?>">
        <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check text-emerald-300' : 'fa-triangle-exclamation text-rose-300' ?>"></i>
        <span><?= htmlspecialchars((string)$toast['msg']) ?></span>
    </div>
    <?php endif; ?>

    <div id="liveToast" class="fixed bottom-5 right-5 z-50 hidden items-center gap-3 px-4 py-3 rounded-xl shadow-lg border bg-slate-900 text-white border-slate-700 text-sm font-medium">
        <i class="fa-solid fa-circle-check text-emerald-400"></i>
        <span id="liveToastText">Tersalin ke clipboard.</span>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <!-- Header -->
        <div class="animate-fade-up flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-300/80 text-slate-900 text-xs font-bold tracking-wide uppercase mb-2">
                    <i class="fa-solid fa-folder-tree"></i> Sistem Monitoring Berkas
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                    Tracking Berkas &amp; Alur Verifikasi
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Pantau tanggal masuk berkas, status, keterangan, dan progres OPD &rarr; Disdukcapil &rarr; Ditjen Dukcapil.
                </p>
            </div>
            <?php require 'nav_menu.php'; ?>
            <!-- <div class="flex flex-wrap items-center gap-2">
                <a href="index.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-amber-300 text-slate-950 font-bold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-table-list"></i> Berkas
                </a>
                <a href="rekap_instansi.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-building-columns text-slate-600"></i> Rekap Instansi
                </a>
                <a href="checklist_berkas.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-list-check text-amber-600"></i> Checklist
                </a>
                <a href="agenda_koordinasi.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-calendar-check text-sky-600"></i> Agenda
                </a>
                <a href="riwayat_status.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-clock-rotate-left text-indigo-600"></i> Riwayat
                </a>
                <a href="laporan.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-file-lines text-amber-600"></i> Laporan
                </a>
                <a href="<?= htmlspecialchars($csvUrl) ?>" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-emerald-50 border border-slate-200 hover:border-emerald-300 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-file-csv text-emerald-600"></i> CSV
                </a>
                <a href="backup_db.php" class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
                    <i class="fa-solid fa-download text-emerald-700"></i> Backup SQL
                </a>
                <button type="button" onclick="openModal('create')" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 active:scale-[0.98] text-white font-semibold text-xs sm:text-sm shadow-md transition">
                    <i class="fa-solid fa-plus"></i> Input Berkas Baru
                </button>
            </div> -->
        </div>

        <!-- Kartu Rekap Statistik (Klik untuk Filter Status) -->
        <div class="animate-fade-up grid grid-cols-2 sm:grid-cols-5 gap-3 mb-6">
            <a href="index.php" class="row-hover bg-white rounded-2xl p-4 border <?= $filterStatus === '' ? 'border-slate-900 ring-2 ring-slate-900/10' : 'border-slate-200/80' ?> shadow-sm">
                <div class="text-xs font-bold uppercase text-slate-500">Total Berkas</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= (int)$rekapAll['total'] ?></div>
            </a>
            <a href="index.php?status=Masuk" class="row-hover bg-white rounded-2xl p-4 border <?= $filterStatus === 'Masuk' ? 'border-indigo-600 ring-2 ring-indigo-600/15' : 'border-slate-200/80' ?> shadow-sm">
                <div class="text-xs font-bold uppercase text-indigo-600">Masuk</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= (int)$rekapAll['Masuk'] ?></div>
            </a>
            <a href="index.php?status=Diproses" class="row-hover bg-white rounded-2xl p-4 border <?= $filterStatus === 'Diproses' ? 'border-amber-500 ring-2 ring-amber-500/20' : 'border-slate-200/80' ?> shadow-sm">
                <div class="text-xs font-bold uppercase text-amber-700">Diproses</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= (int)$rekapAll['Diproses'] ?></div>
            </a>
            <a href="index.php?status=Revisi" class="row-hover bg-white rounded-2xl p-4 border <?= $filterStatus === 'Revisi' ? 'border-rose-600 ring-2 ring-rose-600/15' : 'border-slate-200/80' ?> shadow-sm">
                <div class="text-xs font-bold uppercase text-rose-600">Revisi</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= (int)$rekapAll['Revisi'] ?></div>
            </a>
            <a href="index.php?status=Selesai" class="row-hover col-span-2 sm:col-span-1 bg-white rounded-2xl p-4 border <?= $filterStatus === 'Selesai' ? 'border-emerald-600 ring-2 ring-emerald-600/15' : 'border-slate-200/80' ?> shadow-sm">
                <div class="text-xs font-bold uppercase text-emerald-700">Selesai</div>
                <div class="text-2xl font-extrabold text-slate-900 mt-1"><?= (int)$rekapAll['Selesai'] ?></div>
            </a>
        </div>

        <!-- Filter & Pencarian -->
        <form method="GET" action="index.php" class="animate-fade-up bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 mb-6 grid grid-cols-1 sm:grid-cols-12 gap-3">
            <div class="sm:col-span-6 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama instansi atau keterangan berkas..."
                    class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-slate-200 focus:border-slate-800 focus:ring-2 focus:ring-slate-800/10 outline-none text-sm transition">
            </div>
            <div class="sm:col-span-4">
                <select name="status" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-slate-800 focus:ring-2 focus:ring-slate-800/10 outline-none text-sm bg-white transition">
                    <option value="">Semua Status</option>
                    <?php foreach ($allowed_status as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sm:col-span-2 flex gap-2">
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-900 font-semibold text-sm transition active:scale-[0.98]">
                    Filter
                </button>
                <?php if ($q !== '' || $filterStatus !== ''): ?>
                    <a href="index.php" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm flex items-center justify-center transition" title="Reset">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <!-- Tabel Desktop / Laptop / PC -->
        <div class="hidden lg:block animate-fade-up bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left">
                    <thead>
                        <tr class="bg-yellow-300 text-slate-950 text-xs font-extrabold uppercase tracking-wider border-b-2 border-slate-900/15">
                            <th rowspan="2" class="py-3.5 px-3 text-center border-r border-slate-900/15 w-14">NO</th>
                            <th rowspan="2" class="py-3.5 px-4 border-r border-slate-900/15">NAMA INSTANSI</th>
                            <th rowspan="2" class="py-3.5 px-3 text-center border-r border-slate-900/15 w-36">TGL MASUK</th>
                            <th rowspan="2" class="py-3.5 px-3 text-center border-r border-slate-900/15 w-32">STATUS</th>
                            <th rowspan="2" class="py-3.5 px-4 border-r border-slate-900/15">KETERANGAN BERKAS</th>
                            <th colspan="3" class="py-2.5 px-3 text-center border-b border-r border-slate-900/15">STATUS BERKAS</th>
                            <th rowspan="2" class="py-3.5 px-3 text-center w-28">AKSI</th>
                        </tr>
                        <tr class="bg-yellow-300 text-slate-950 text-[11px] font-extrabold uppercase tracking-wider border-b-2 border-slate-900/20">
                            <th class="py-2 px-2.5 text-center border-r border-slate-900/15 w-28">OPD</th>
                            <th class="py-2 px-2.5 text-center border-r border-slate-900/15 w-32">DISDUKCAPIL</th>
                            <th class="py-2 px-2.5 text-center border-r border-slate-900/15 w-32">DITJEN DUKCAPIL</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80 text-sm">
                        <?php if (empty($rows)): ?>
                            <tr>
                                <td colspan="9" class="py-12 text-center text-slate-500">
                                    <i class="fa-regular fa-folder-open text-3xl mb-2 block text-slate-400"></i>
                                    Belum ada data berkas yang sesuai.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $idx => $r): ?>
                                <?php
                                    $stages = [
                                        (string)$r['tahap_opd'],
                                        (string)$r['tahap_disdukcapil'],
                                        (string)$r['tahap_ditjen'],
                                    ];
                                    $encoded = json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    $jsonRow = htmlspecialchars($encoded ? $encoded : '{}', ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr class="row-hover bg-white hover:bg-amber-50/40" style="animation: fadeSlideUp 0.3s ease both; animation-delay: <?= min($idx * 40, 320) ?>ms;">
                                    <td class="py-3.5 px-3 text-center font-bold text-slate-700 border-r border-slate-100"><?= $idx + 1 ?></td>
                                    <td class="py-3.5 px-4 font-semibold text-slate-900 border-r border-slate-100">
                                        <?= htmlspecialchars((string)$r['nama_instansi']) ?>
                                    </td>
                                    <td class="py-3.5 px-3 text-center border-r border-slate-100">
                                        <div class="font-semibold text-slate-800"><?= formatTanggalIndo((string)$r['tanggal_masuk']) ?></div>
                                        <span class="inline-block mt-0.5 text-[10px] px-2 py-0.5 rounded-full font-medium <?= $r['mode_tanggal'] === 'otomatis' ? 'bg-sky-50 text-sky-700' : 'bg-purple-50 text-purple-700' ?>">
                                            <?= htmlspecialchars(ucfirst((string)$r['mode_tanggal'])) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-3 text-center border-r border-slate-100">
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold <?= badgeStatus((string)$r['status_ringkas']) ?>">
                                            <?= htmlspecialchars((string)$r['status_ringkas']) ?>
                                        </span>
                                    </td>
                                    <td class="py-3.5 px-4 text-slate-600 border-r border-slate-100 max-w-xs">
                                        <?= nl2br(htmlspecialchars((string)($r['keterangan_berkas'] ?: '-'))) ?>
                                    </td>
                                    <?php foreach ($stages as $stg): ?>
                                        <?php
                                            $badgeInfo = badgeStage($stg);
                                            $cls = $badgeInfo[0];
                                            $ico = $badgeInfo[1];
                                        ?>
                                        <td class="py-3.5 px-2 text-center border-r border-slate-100">
                                            <span class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border text-xs font-semibold <?= $cls ?>">
                                                <i class="fa-solid <?= $ico ?> text-[11px]"></i>
                                                <?= htmlspecialchars($stg) ?>
                                            </span>
                                        </td>
                                    <?php endforeach; ?>
                                    <td class="py-3.5 px-3 text-center">
                                        <div class="inline-flex items-center gap-1">
                                            <button type="button" onclick="copyRingkasanWA(<?= $jsonRow ?>)"
                                                class="p-2 rounded-lg bg-slate-100 hover:bg-emerald-500 text-slate-700 hover:text-white transition" title="Salin Ringkasan WA">
                                                <i class="fa-brands fa-whatsapp"></i>
                                            </button>
                                            <button type="button" onclick="openModal('edit', <?= $jsonRow ?>)"
                                                class="p-2 rounded-lg bg-slate-100 hover:bg-amber-300 text-slate-700 hover:text-slate-900 transition" title="Edit">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>
                                            <a href="index.php?hapus=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus berkas ini?')"
                                                class="p-2 rounded-lg bg-slate-100 hover:bg-rose-500 text-slate-700 hover:text-white transition" title="Hapus">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu HP / Tablet -->
        <div class="lg:hidden space-y-3.5">
            <?php if (empty($rows)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-slate-500 border border-slate-200 animate-fade-up">
                    Belum ada data berkas.
                </div>
            <?php else: ?>
                <?php foreach ($rows as $idx => $r): ?>
                    <?php
                        $bOpd = badgeStage((string)$r['tahap_opd']);
                        $bDis = badgeStage((string)$r['tahap_disdukcapil']);
                        $bDit = badgeStage((string)$r['tahap_ditjen']);
                        $encoded = json_encode($r, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                        $jsonRow = htmlspecialchars($encoded ? $encoded : '{}', ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="row-hover bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 space-y-3" style="animation: fadeSlideUp 0.3s ease both; animation-delay: <?= min($idx * 45, 300) ?>ms;">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <span class="text-xs font-bold text-slate-400">#<?= $idx + 1 ?> &bull; Masuk <?= formatTanggalIndo((string)$r['tanggal_masuk']) ?> (<?= htmlspecialchars((string)$r['mode_tanggal']) ?>)</span>
                                <h2 class="font-bold text-slate-900 text-base mt-0.5"><?= htmlspecialchars((string)$r['nama_instansi']) ?></h2>
                            </div>
                            <span class="px-2.5 py-1 rounded-full text-xs font-bold shrink-0 <?= badgeStatus((string)$r['status_ringkas']) ?>">
                                <?= htmlspecialchars((string)$r['status_ringkas']) ?>
                            </span>
                        </div>
                        <p class="text-sm text-slate-600 bg-slate-50 p-2.5 rounded-xl border border-slate-100">
                            <?= nl2br(htmlspecialchars((string)($r['keterangan_berkas'] ?: 'Tidak ada keterangan.'))) ?>
                        </p>
                        <div class="grid grid-cols-3 gap-2 text-center text-xs">
                            <div class="p-2 rounded-xl border <?= $bOpd[0] ?>">
                                <div class="text-[10px] uppercase font-bold opacity-75 mb-0.5">OPD</div>
                                <div class="font-bold"><?= htmlspecialchars((string)$r['tahap_opd']) ?></div>
                            </div>
                            <div class="p-2 rounded-xl border <?= $bDis[0] ?>">
                                <div class="text-[10px] uppercase font-bold opacity-75 mb-0.5">Disdukcapil</div>
                                <div class="font-bold"><?= htmlspecialchars((string)$r['tahap_disdukcapil']) ?></div>
                            </div>
                            <div class="p-2 rounded-xl border <?= $bDit[0] ?>">
                                <div class="text-[10px] uppercase font-bold opacity-75 mb-0.5">Ditjen</div>
                                <div class="font-bold"><?= htmlspecialchars((string)$r['tahap_ditjen']) ?></div>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-1 border-t border-slate-100">
                            <button type="button" onclick="copyRingkasanWA(<?= $jsonRow ?>)"
                                class="px-3 py-1.5 rounded-lg bg-emerald-50 hover:bg-emerald-500 text-emerald-700 hover:text-white text-xs font-bold transition">
                                <i class="fa-brands fa-whatsapp"></i>
                            </button>
                            <button type="button" onclick="openModal('edit', <?= $jsonRow ?>)"
                                class="px-3.5 py-1.5 rounded-lg bg-amber-300/80 hover:bg-amber-300 text-slate-900 text-xs font-bold transition">
                                <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                            </button>
                            <a href="index.php?hapus=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus berkas ini?')"
                                class="px-3.5 py-1.5 rounded-lg bg-rose-50 hover:bg-rose-500 text-rose-700 hover:text-white text-xs font-bold transition">
                                <i class="fa-solid fa-trash mr-1"></i> Hapus
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Tambah / Edit -->
    <div id="formModal" class="fixed inset-0 z-40 hidden items-center justify-center bg-slate-950/50 backdrop-blur-[2px] p-4">
        <div class="animate-pop-in bg-white w-full max-w-xl rounded-2xl shadow-2xl border border-slate-200 overflow-hidden max-h-[92vh] flex flex-col">
            <div class="px-5 py-4 bg-yellow-300 flex items-center justify-between">
                <h3 id="modalTitle" class="font-extrabold text-slate-950 text-base">Input Berkas Baru</h3>
                <button type="button" onclick="closeModal()" class="w-8 h-8 rounded-lg bg-slate-950/10 hover:bg-slate-950/20 text-slate-950 flex items-center justify-center transition">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <form method="POST" action="index.php" class="p-5 space-y-4 overflow-y-auto text-sm">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="id" id="formId" value="">

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Nama Instansi *</label>
                    <input type="text" name="nama_instansi" id="namaInstansi" required placeholder="Contoh: Dinas Sosial Kab. Situbondo"
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
                </div>

                <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="font-semibold text-slate-800">Tanggal Berkas Masuk</label>
                        <div class="inline-flex rounded-lg bg-white p-1 border border-slate-200">
                            <label class="cursor-pointer">
                                <input type="radio" name="mode_tanggal" value="otomatis" id="modeOtomatis" checked onchange="toggleTanggalMode()" class="sr-only peer">
                                <span class="px-3 py-1 rounded-md text-xs font-bold block peer-checked:bg-slate-900 peer-checked:text-white text-slate-600 transition">
                                    Otomatis
                                </span>
                            </label>
                            <label class="cursor-pointer">
                                <input type="radio" name="mode_tanggal" value="manual" id="modeManual" onchange="toggleTanggalMode()" class="sr-only peer">
                                <span class="px-3 py-1 rounded-md text-xs font-bold block peer-checked:bg-slate-900 peer-checked:text-white text-slate-600 transition">
                                    Manual
                                </span>
                            </label>
                        </div>
                    </div>
                    <input type="date" name="tanggal_masuk" id="tanggalMasuk" value="<?= $todayDate ?>"
                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 bg-white focus:border-slate-900 outline-none disabled:bg-slate-100 disabled:text-slate-500">
                    <p id="hintTanggal" class="text-xs text-slate-500">Mode otomatis akan mengisi tanggal hari ini (<?= $todayIndo ?>) saat disimpan.</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <label class="block font-semibold text-slate-700 mb-1">Status Utama</label>
                        <select name="status_ringkas" id="statusRingkas" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white outline-none">
                            <?php foreach ($allowed_status as $st): ?>
                                <option value="<?= htmlspecialchars($st) ?>"><?= htmlspecialchars($st) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="grid grid-cols-3 gap-2 sm:col-span-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">OPD</label>
                            <select name="tahap_opd" id="tahapOpd" class="w-full px-2.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold outline-none">
                                <?php foreach ($allowed_stage as $tg): ?>
                                    <option value="<?= htmlspecialchars($tg) ?>"><?= htmlspecialchars($tg) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Disdukcapil</label>
                            <select name="tahap_disdukcapil" id="tahapDisdukcapil" class="w-full px-2.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold outline-none">
                                <?php foreach ($allowed_stage as $tg): ?>
                                    <option value="<?= htmlspecialchars($tg) ?>"><?= htmlspecialchars($tg) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 mb-1">Ditjen Dukcapil</label>
                            <select name="tahap_ditjen" id="tahapDitjen" class="w-full px-2.5 py-2 rounded-xl border border-slate-300 bg-white text-xs font-semibold outline-none">
                                <?php foreach ($allowed_stage as $tg): ?>
                                    <option value="<?= htmlspecialchars($tg) ?>"><?= htmlspecialchars($tg) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Keterangan Berkas</label>
                    <textarea name="keterangan_berkas" id="keteranganBerkas" rows="3" placeholder="Catatan kelengkapan berkas, revisi, atau nomor surat..."
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none"></textarea>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" onclick="closeModal()" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition">
                        Batal
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold shadow transition active:scale-[0.98]">
                        Simpan Data
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const modal = document.getElementById('formModal');
        const todayStr = <?= json_encode($todayDate) ?>;
        const todayIndoStr = <?= json_encode($todayIndo) ?>;

        function showLiveToast(message) {
            const lt = document.getElementById('liveToast');
            const txt = document.getElementById('liveToastText');
            if (!lt || !txt) return;
            txt.textContent = message;
            lt.classList.remove('hidden');
            lt.classList.add('flex');
            setTimeout(function () {
                lt.classList.add('hidden');
                lt.classList.remove('flex');
            }, 2500);
        }

        function copyRingkasanWA(data) {
            if (!data) return;
            const lines = [
                '*UPDATE TRACKING BERKAS*',
                'Instansi: ' + (data.nama_instansi || '-'),
                'Tgl Masuk: ' + (data.tanggal_masuk || '-'),
                'Status: ' + (data.status_ringkas || '-'),
                'OPD: ' + (data.tahap_opd || '-') + ' | Disdukcapil: ' + (data.tahap_disdukcapil || '-') + ' | Ditjen: ' + (data.tahap_ditjen || '-'),
                'Keterangan: ' + (data.keterangan_berkas || '-')
            ];
            const text = lines.join('\n');
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function () {
                    showLiveToast('Ringkasan berkas disalin untuk WA.');
                }).catch(function () {
                    fallbackCopy(text);
                });
            } else {
                fallbackCopy(text);
            }
        }

        function fallbackCopy(text) {
            const ta = document.createElement('textarea');
            ta.value = text;
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                showLiveToast('Ringkasan berkas disalin.');
            } catch (e) {}
            document.body.removeChild(ta);
        }

        function toggleTanggalMode() {
            const isManual = document.getElementById('modeManual').checked;
            const dateInput = document.getElementById('tanggalMasuk');
            const hint = document.getElementById('hintTanggal');

            if (isManual) {
                dateInput.disabled = false;
                if (!dateInput.value) dateInput.value = todayStr;
                hint.textContent = 'Mode manual aktif: pilih tanggal masuk berkas sesuai dokumen.';
            } else {
                dateInput.value = todayStr;
                dateInput.disabled = true;
                hint.textContent = 'Mode otomatis aktif: menggunakan tanggal hari ini (' + todayIndoStr + ') saat disimpan.';
            }
        }

        function openModal(mode, data) {
            data = data || null;
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            if (mode === 'edit' && data) {
                document.getElementById('modalTitle').textContent = 'Edit Data Berkas';
                document.getElementById('formAction').value = 'update';
                document.getElementById('formId').value = data.id || '';
                document.getElementById('namaInstansi').value = data.nama_instansi || '';
                document.getElementById('statusRingkas').value = data.status_ringkas || 'Masuk';
                document.getElementById('tahapOpd').value = data.tahap_opd || 'Belum';
                document.getElementById('tahapDisdukcapil').value = data.tahap_disdukcapil || 'Belum';
                document.getElementById('tahapDitjen').value = data.tahap_ditjen || 'Belum';
                document.getElementById('keteranganBerkas').value = data.keterangan_berkas || '';

                const dateInput = document.getElementById('tanggalMasuk');
                const hint = document.getElementById('hintTanggal');

                if (data.mode_tanggal === 'manual') {
                    document.getElementById('modeManual').checked = true;
                    dateInput.disabled = false;
                    dateInput.value = data.tanggal_masuk || todayStr;
                    hint.textContent = 'Mode manual aktif: ubah tanggal jika diperlukan.';
                } else {
                    document.getElementById('modeOtomatis').checked = true;
                    dateInput.disabled = true;
                    dateInput.value = data.tanggal_masuk || todayStr;
                    hint.textContent = 'Mode otomatis tersimpan. Ganti ke Manual jika ingin mengubah tanggal secara bebas.';
                }
            } else {
                document.getElementById('modalTitle').textContent = 'Input Berkas Baru';
                document.getElementById('formAction').value = 'create';
                document.getElementById('formId').value = '';
                document.getElementById('namaInstansi').value = '';
                document.getElementById('statusRingkas').value = 'Masuk';
                document.getElementById('tahapOpd').value = 'Proses';
                document.getElementById('tahapDisdukcapil').value = 'Belum';
                document.getElementById('tahapDitjen').value = 'Belum';
                document.getElementById('keteranganBerkas').value = '';
                document.getElementById('modeOtomatis').checked = true;
                toggleTanggalMode();
            }
        }

        function closeModal() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        const toast = document.getElementById('toast');
        if (toast) {
            setTimeout(function () {
                toast.style.transition = 'opacity 0.35s ease, transform 0.35s ease';
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(8px)';
                setTimeout(function () {
                    toast.remove();
                }, 350);
            }, 3200);
        }
    </script>
</body>
</html>