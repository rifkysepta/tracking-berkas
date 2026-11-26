<?php
session_start();
require_once 'config.php';

$allowed_status = ['Masuk', 'Diproses', 'Revisi', 'Selesai'];

$q            = trim((string)(isset($_GET['q']) ? $_GET['q'] : ''));
$filterStatus = (string)(isset($_GET['status']) ? $_GET['status'] : '');
$tglAwal      = trim((string)(isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : ''));
$tglAkhir     = trim((string)(isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : ''));

$rows     = [];
$errorMsg = null;

try {
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

    if ($tglAwal !== '') {
        $dtAwal = DateTime::createFromFormat('Y-m-d', $tglAwal);
        if ($dtAwal !== false && $dtAwal->format('Y-m-d') === $tglAwal) {
            $sql     .= " AND tanggal_masuk >= ?";
            $params[] = $tglAwal;
            $types   .= "s";
        } else {
            $tglAwal = '';
        }
    }

    if ($tglAkhir !== '') {
        $dtAkhir = DateTime::createFromFormat('Y-m-d', $tglAkhir);
        if ($dtAkhir !== false && $dtAkhir->format('Y-m-d') === $tglAkhir) {
            $sql     .= " AND tanggal_masuk <= ?";
            $params[] = $tglAkhir;
            $types   .= "s";
        } else {
            $tglAkhir = '';
        }
    }

    $sql .= " ORDER BY tanggal_masuk ASC, id ASC";

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
} catch (Throwable $e) {
    $errorMsg = 'Gagal memuat data laporan: ' . $e->getMessage();
}

$rekap = hitungRekapBerkas($rows);

$bulanIndo = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$now = new DateTime('now');
$tanggalCetakIndo = (int)$now->format('j') . ' ' . $bulanIndo[(int)$now->format('n')] . ' ' . $now->format('Y');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Tracking Berkas - Disdukcapil Kab. Situbondo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style id="pageOrientationStyle">
        @page {
            size: A4 landscape;
            margin: 12mm;
        }
    </style>
    <style>
        @keyframes fadeInSheet {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .report-sheet {
            animation: fadeInSheet 0.35s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .kop-wrapper {
            display: grid;
            grid-template-columns: 84px 1fr;
            align-items: center;
            gap: 12px;
            border-bottom: 3px double #0f172a;
            padding-bottom: 10px;
        }
        .ttd-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            margin-top: 28px;
        }
        .ttd-box {
            width: 320px;
            text-align: center;
        }
        @media (max-width: 640px) {
            .kop-wrapper {
                grid-template-columns: 60px 1fr;
                gap: 8px;
            }
            .ttd-grid {
                grid-template-columns: 1fr;
            }
            .ttd-box {
                width: 100%;
                margin-top: 16px;
            }
        }
        @media print {
            body {
                background: #ffffff !important;
                color: #000000 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none !important;
            }
            .report-sheet {
                animation: none !important;
                box-shadow: none !important;
                border: none !important;
                padding: 0 !important;
                max-width: 100% !important;
            }
            .ttd-grid {
                grid-template-columns: 1fr auto !important;
                page-break-inside: avoid;
                break-inside: avoid;
            }
            .ttd-box {
                width: 320px !important;
            }
            table {
                page-break-inside: auto;
            }
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen antialiased">

    <!-- Bar Navigasi & Filter (Tidak ikut tercetak) -->
    <div class="no-print max-w-7xl mx-auto px-4 sm:px-6 pt-6 pb-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
            <div class="flex flex-wrap items-center gap-2">
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-arrow-left"></i> Kembali ke Tracking
                </a>
                <a href="laporan.php" class="inline-flex items-center gap-2 px-3.5 py-2.5 rounded-xl bg-slate-200/70 hover:bg-slate-200 text-slate-700 font-semibold text-sm transition">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select id="orientSelect" onchange="changeOrientation(this.value)" class="px-3.5 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-800 text-sm font-semibold shadow-sm outline-none">
                    <option value="landscape" selected>Cetak: A4 Landscape</option>
                    <option value="portrait">Cetak: A4 Portrait</option>
                </select>
                <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 active:scale-[0.98] text-white font-semibold text-sm shadow-md transition">
                    <i class="fa-solid fa-print"></i> Cetak / Simpan PDF
                </button>
            </div>
        </div>

        <form method="GET" action="laporan.php" class="bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-sm">
            <div class="lg:col-span-4 relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400"></i>
                <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari instansi / keterangan..."
                    class="w-full pl-10 pr-3.5 py-2 rounded-xl border border-slate-200 focus:border-slate-800 outline-none">
            </div>
            <div class="lg:col-span-2">
                <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white focus:border-slate-800 outline-none">
                    <option value="">Semua Status</option>
                    <?php foreach ($allowed_status as $st): ?>
                        <option value="<?= htmlspecialchars($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="lg:col-span-2">
                <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tglAwal) ?>" title="Tanggal Masuk Mulai"
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-slate-800 outline-none">
            </div>
            <div class="lg:col-span-2">
                <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tglAkhir) ?>" title="Tanggal Masuk Sampai"
                    class="w-full px-3 py-2 rounded-xl border border-slate-200 focus:border-slate-800 outline-none">
            </div>
            <div class="lg:col-span-2">
                <button type="submit" class="w-full px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold transition">
                    Terapkan
                </button>
            </div>
        </form>
    </div>

    <!-- Lembar Laporan -->
    <div class="report-sheet max-w-7xl mx-auto bg-white sm:rounded-2xl shadow-sm border border-slate-200/80 p-5 sm:p-8 mb-10">

        <!-- Kop Instansi -->
        <header class="kop-wrapper">
            <div class="flex items-center justify-center">
                <img src="image/logo-situbondo.png" alt="Logo Kabupaten Situbondo" class="w-16 sm:w-20 h-auto object-contain"
                     onerror="this.style.display='none'; if (this.nextElementSibling) this.nextElementSibling.style.display='flex';">
                <div class="hidden w-16 sm:w-20 h-20 rounded-lg border border-dashed border-slate-400 text-[10px] text-slate-500 items-center justify-center text-center p-1">
                    image/logo-situbondo.png
                </div>
            </div>
            <div class="text-center leading-snug">
                <div class="text-base sm:text-xl font-bold tracking-wide text-slate-900 uppercase">
                    PEMERINTAH KABUPATEN SITUBONDO
                </div>
                <div class="text-lg sm:text-2xl font-extrabold tracking-tight text-slate-950 uppercase">
                    DINAS KEPENDUDUKAN DAN PENCATATAN SIPIL
                </div>
                <div class="text-xs sm:text-sm text-slate-700 mt-0.5">
                    Jalan Sucipto No. 57, Dawuhan, Situbondo, Jawa Timur 68311,
                </div>
                <div class="text-xs sm:text-sm text-slate-700">
                    Telepon (0338) 671738, Faksmile (0338) 671738,
                </div>
                <div class="text-xs sm:text-sm text-slate-700">
                    Laman https:// dispendukcapil.situbondokab.go.id, Pos-el dispenduk_situbondo@yahoo.co.id
                </div>
            </div>
        </header>

        <!-- Judul Laporan -->
        <div class="text-center mt-5 mb-4">
            <h1 class="text-base sm:text-lg font-extrabold uppercase tracking-wide text-slate-950 underline underline-offset-4">
                LAPORAN TRACKING BERKAS INSTANSI
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 mt-1">
                <?php if ($tglAwal !== '' || $tglAkhir !== ''): ?>
                    Periode Tanggal Masuk:
                    <strong><?= $tglAwal !== '' ? formatTanggalIndo($tglAwal) : 'Awal' ?></strong>
                    s/d
                    <strong><?= $tglAkhir !== '' ? formatTanggalIndo($tglAkhir) : 'Sekarang' ?></strong>
                <?php else: ?>
                    Rekapitulasi Seluruh Berkas Masuk &amp; Progres Verifikasi
                <?php endif; ?>
                <?php if ($filterStatus !== ''): ?>
                    &bull; Status: <strong><?= htmlspecialchars($filterStatus) ?></strong>
                <?php endif; ?>
            </p>
        </div>

        <!-- Ringkasan Singkat Laporan -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2 mb-4 text-xs">
            <div class="border border-slate-800 rounded-lg px-3 py-2 bg-slate-50">
                <span class="text-slate-600">Total Berkas:</span>
                <strong class="text-slate-950 ml-1"><?= (int)$rekap['total'] ?></strong>
            </div>
            <div class="border border-slate-800 rounded-lg px-3 py-2 bg-slate-50">
                <span class="text-slate-600">Diproses:</span>
                <strong class="text-slate-950 ml-1"><?= (int)$rekap['Diproses'] ?></strong>
            </div>
            <div class="border border-slate-800 rounded-lg px-3 py-2 bg-slate-50">
                <span class="text-slate-600">Revisi:</span>
                <strong class="text-slate-950 ml-1"><?= (int)$rekap['Revisi'] ?></strong>
            </div>
            <div class="border border-slate-800 rounded-lg px-3 py-2 bg-slate-50">
                <span class="text-slate-600">Selesai:</span>
                <strong class="text-slate-950 ml-1"><?= (int)$rekap['Selesai'] ?></strong>
            </div>
            <div class="col-span-2 sm:col-span-1 border border-slate-800 rounded-lg px-3 py-2 bg-slate-50">
                <span class="text-slate-600">Selesai Ditjen:</span>
                <strong class="text-slate-950 ml-1"><?= (int)$rekap['ditjen_selesai'] ?></strong>
            </div>
        </div>

        <?php if ($errorMsg !== null): ?>
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm mb-4">
                <?= htmlspecialchars($errorMsg) ?>
            </div>
        <?php endif; ?>

        <!-- Tabel Laporan -->
        <div class="overflow-x-auto">
            <table class="w-full border-collapse border border-slate-900 text-left text-xs sm:text-sm">
                <thead>
                    <tr class="bg-yellow-300 text-slate-950 font-extrabold uppercase text-[11px] sm:text-xs">
                        <th rowspan="2" class="border border-slate-900 py-2.5 px-2 text-center w-12">NO</th>
                        <th rowspan="2" class="border border-slate-900 py-2.5 px-3">NAMA INSTANSI</th>
                        <th rowspan="2" class="border border-slate-900 py-2.5 px-2 text-center w-28">TGL MASUK</th>
                        <th rowspan="2" class="border border-slate-900 py-2.5 px-2 text-center w-28">STATUS</th>
                        <th rowspan="2" class="border border-slate-900 py-2.5 px-3">KETERANGAN BERKAS</th>
                        <th colspan="3" class="border border-slate-900 py-1.5 px-2 text-center">STATUS BERKAS</th>
                    </tr>
                    <tr class="bg-yellow-300 text-slate-950 font-extrabold uppercase text-[10px] sm:text-xs">
                        <th class="border border-slate-900 py-1.5 px-2 text-center w-24">OPD</th>
                        <th class="border border-slate-900 py-1.5 px-2 text-center w-28">DISDUKCAPIL</th>
                        <th class="border border-slate-900 py-1.5 px-2 text-center w-28">DITJEN DUKCAPIL</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                        <tr>
                            <td colspan="8" class="border border-slate-900 py-8 px-4 text-center text-slate-500">
                                Tidak ada data berkas untuk ditampilkan pada laporan ini.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($rows as $idx => $r): ?>
                            <tr class="hover:bg-slate-50/70 transition-colors">
                                <td class="border border-slate-900 py-2 px-2 text-center font-semibold"><?= $idx + 1 ?></td>
                                <td class="border border-slate-900 py-2 px-3 font-semibold text-slate-900">
                                    <?= htmlspecialchars((string)$r['nama_instansi']) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-2 text-center whitespace-nowrap">
                                    <?= formatTanggalIndo((string)$r['tanggal_masuk']) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-2 text-center font-semibold">
                                    <?= htmlspecialchars((string)$r['status_ringkas']) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-3 text-slate-700">
                                    <?= nl2br(htmlspecialchars((string)($r['keterangan_berkas'] ?: '-'))) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-2 text-center font-medium">
                                    <?= htmlspecialchars((string)$r['tahap_opd']) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-2 text-center font-medium">
                                    <?= htmlspecialchars((string)$r['tahap_disdukcapil']) ?>
                                </td>
                                <td class="border border-slate-900 py-2 px-2 text-center font-medium">
                                    <?= htmlspecialchars((string)$r['tahap_ditjen']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Tanda Tangan di Kanan Bawah -->
        <div class="ttd-grid">
            <div></div>
            <div class="ttd-box">
                <div class="text-xs sm:text-sm text-slate-800 mb-1">
                    Situbondo, <?= htmlspecialchars($tanggalCetakIndo) ?>
                </div>
                <div class="text-xs sm:text-sm font-bold uppercase text-slate-950 leading-snug">
                    KEPALA BIDANG PDIP<br>
                    KEPENDUDUKAN DAN PENCATATAN SIPIL<br>
                    KABUPATEN SITUBONDO
                </div>
                <div class="h-20"></div>
                <div class="text-xs sm:text-sm font-extrabold uppercase text-slate-950 underline underline-offset-4">
                    BUDI MULYONO, S.T
                </div>
                <div class="text-xs sm:text-sm text-slate-800 mt-0.5">
                    Pembina
                </div>
                <div class="text-xs sm:text-sm text-slate-800">
                    NIP.19730106 200501 1 006
                </div>
            </div>
        </div>

    </div>

    <script>
        function changeOrientation(mode) {
            const styleTag = document.getElementById('pageOrientationStyle');
            if (!styleTag) return;
            const orient = (mode === 'portrait') ? 'portrait' : 'landscape';
            styleTag.textContent = '@page { size: A4 ' + orient + '; margin: 12mm; }';
        }
    </script>
</body>
</html>