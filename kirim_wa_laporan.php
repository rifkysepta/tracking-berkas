<?php
session_start();
require_once 'config.php';

$allowed_status = ['Masuk', 'Diproses', 'Revisi', 'Selesai'];

$q            = trim((string)(isset($_GET['q']) ? $_GET['q'] : ''));
$filterStatus = (string)(isset($_GET['status']) ? $_GET['status'] : '');
$tglAwal      = trim((string)(isset($_GET['tgl_awal']) ? $_GET['tgl_awal'] : ''));
$tglAkhir     = trim((string)(isset($_GET['tgl_akhir']) ? $_GET['tgl_akhir'] : ''));
$noWaTujuan   = preg_replace('/[^0-9]/', '', (string)(isset($_GET['no_wa']) ? $_GET['no_wa'] : ''));

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

$waLines = [
    "Assalamu'alaikum Wr. Wb.",
    "Berikut ringkasan *Laporan Tracking Berkas Instansi* (Disdukcapil Kab. Situbondo) per tanggal *" . $tanggalCetakIndo . "*:",
    "- Total Berkas: *" . (int)$rekap['total'] . "*",
    "- Diproses: *" . (int)$rekap['Diproses'] . "* | Revisi: *" . (int)$rekap['Revisi'] . "* | Selesai: *" . (int)$rekap['Selesai'] . "*",
    "- Selesai Ditjen Dukcapil: *" . (int)$rekap['ditjen_selesai'] . "*",
    "File laporan gambar/PDF telah dilampirkan/diunduh."
];
$waDefaultText = implode("\n", $waLines);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kirim Laporan Gambar / PDF ke WhatsApp</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-up { animation: fadeUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) both; }
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
            .kop-wrapper { grid-template-columns: 60px 1fr; gap: 8px; }
            .ttd-grid { grid-template-columns: 1fr; }
            .ttd-box { width: 100%; margin-top: 16px; }
        }
        @media print {
            @page { size: A4 landscape; margin: 12mm; }
            body { background: #ffffff !important; color: #000000 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
            #captureArea { box-shadow: none !important; border: none !important; padding: 0 !important; max-width: 100% !important; }
            .ttd-grid { grid-template-columns: 1fr auto !important; page-break-inside: avoid; break-inside: avoid; }
            .ttd-box { width: 320px !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 min-h-screen antialiased">

    <div id="statusBanner" class="no-print fixed bottom-5 right-5 z-50 hidden items-center gap-2.5 px-4 py-3 rounded-xl bg-slate-900 text-white text-sm font-medium shadow-xl border border-slate-700">
        <i class="fa-solid fa-circle-info text-amber-300"></i>
        <span id="statusBannerText">Memproses...</span>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <!-- Header & Navigasi -->
        <div class="no-print anim-up flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-5">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-200/80 text-emerald-950 text-xs font-bold uppercase mb-2">
                    <i class="fa-brands fa-whatsapp"></i> Ekspor Gambar &amp; PDF ke WhatsApp
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Kirim Laporan ke WhatsApp</h1>
                <p class="text-sm text-slate-600 mt-1">Ubah laporan menjadi gambar (.png) atau PDF lalu bagikan ke WhatsApp.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php if (file_exists('nav_menu.php')): ?>
                    <?php require 'nav_menu.php'; ?>
                <?php else: ?>
                    <a href="index.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                        <i class="fa-solid fa-table-list mr-1"></i> Berkas
                    </a>
                    <a href="laporan.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                        <i class="fa-solid fa-file-lines mr-1"></i> Laporan
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel Filter & Kirim WhatsApp -->
        <div class="no-print anim-up bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 mb-6 space-y-4 text-sm">
            <form method="GET" action="kirim_wa_laporan.php" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3">
                <div class="lg:col-span-3">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari instansi / keterangan..."
                        class="w-full px-3.5 py-2 rounded-xl border border-slate-200 focus:border-slate-900 outline-none">
                </div>
                <div class="lg:col-span-2">
                    <select name="status" class="w-full px-3 py-2 rounded-xl border border-slate-200 bg-white outline-none">
                        <option value="">Semua Status</option>
                        <?php foreach ($allowed_status as $st): ?>
                            <option value="<?= htmlspecialchars($st) ?>" <?= $filterStatus === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="lg:col-span-2">
                    <input type="date" name="tgl_awal" value="<?= htmlspecialchars($tglAwal) ?>"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none">
                </div>
                <div class="lg:col-span-2">
                    <input type="date" name="tgl_akhir" value="<?= htmlspecialchars($tglAkhir) ?>"
                        class="w-full px-3 py-2 rounded-xl border border-slate-200 outline-none">
                </div>
                <div class="lg:col-span-3 flex gap-2">
                    <button type="submit" class="flex-1 px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold transition">
                        Filter
                    </button>
                    <a href="kirim_wa_laporan.php" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center" title="Reset">
                        <i class="fa-solid fa-rotate-left"></i>
                    </a>
                </div>
            </form>

            <div class="pt-3 border-t border-slate-100 grid grid-cols-1 lg:grid-cols-12 gap-3 items-start">
                <div class="lg:col-span-7 space-y-2">
                    <label class="block text-xs font-bold text-slate-700">Pesan Ringkasan WhatsApp</label>
                    <textarea id="waCaption" rows="4" class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none text-xs sm:text-sm"><?= htmlspecialchars($waDefaultText) ?></textarea>
                </div>
                <div class="lg:col-span-5 space-y-2.5">
                    <label class="block text-xs font-bold text-slate-700">Nomor WA Tujuan (Opsional, format 628...)</label>
                    <input type="text" id="waPhone" value="<?= htmlspecialchars($noWaTujuan) ?>" placeholder="Contoh: 6281234567890"
                        class="w-full px-3.5 py-2 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                        <button type="button" onclick="shareGambarWA()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs sm:text-sm shadow transition active:scale-[0.98]">
                            <i class="fa-brands fa-whatsapp"></i> Share Gambar ke WA
                        </button>
                        <button type="button" onclick="unduhGambarPNG()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs sm:text-sm shadow transition active:scale-[0.98]">
                            <i class="fa-solid fa-image"></i> Simpan Gambar (.PNG)
                        </button>
                        <button type="button" onclick="window.print()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-xs sm:text-sm transition">
                            <i class="fa-solid fa-file-pdf"></i> Cetak / Simpan PDF
                        </button>
                        <button type="button" onclick="bukaLinkWA()" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-emerald-50 border border-emerald-300 text-emerald-800 font-bold text-xs sm:text-sm transition">
                            <i class="fa-solid fa-paper-plane"></i> Buka Chat WA + Teks
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Area Laporan yang Ditangkap Menjadi Gambar / PDF -->
        <div id="captureArea" class="anim-up bg-white sm:rounded-2xl shadow-sm border border-slate-200/80 p-5 sm:p-8 mb-10">
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

            <div class="text-center mt-5 mb-4">
                <h2 class="text-base sm:text-lg font-extrabold uppercase tracking-wide text-slate-950 underline underline-offset-4">
                    LAPORAN TRACKING BERKAS INSTANSI
                </h2>
                <p class="text-xs sm:text-sm text-slate-600 mt-1">
                    Tanggal Cetak: <strong><?= htmlspecialchars($tanggalCetakIndo) ?></strong>
                    &bull; Total Berkas: <strong><?= (int)$rekap['total'] ?></strong>
                    &bull; Selesai: <strong><?= (int)$rekap['Selesai'] ?></strong>
                    &bull; Revisi: <strong><?= (int)$rekap['Revisi'] ?></strong>
                </p>
            </div>

            <?php if ($errorMsg !== null): ?>
                <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm mb-4">
                    <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

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
                                    Tidak ada data berkas untuk ditampilkan.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $idx => $r): ?>
                                <tr>
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
    </div>

    <script>
        function notifyStatus(msg) {
            const el = document.getElementById('statusBanner');
            const tx = document.getElementById('statusBannerText');
            if (!el || !tx) return;
            tx.textContent = msg;
            el.classList.remove('hidden');
            el.classList.add('flex');
            setTimeout(function () {
                el.classList.add('hidden');
                el.classList.remove('flex');
            }, 3200);
        }

        function renderCanvasLaporan() {
            const target = document.getElementById('captureArea');
            if (!target || typeof html2canvas !== 'function') {
                return Promise.reject(new Error('Renderer gambar tidak tersedia.'));
            }
            notifyStatus('Menyiapkan gambar laporan...');
            return html2canvas(target, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#ffffff'
            });
        }

        function unduhGambarPNG() {
            renderCanvasLaporan().then(function (canvas) {
                const link = document.createElement('a');
                link.download = 'laporan_tracking_berkas_<?= date('Ymd_His') ?>.png';
                link.href = canvas.toDataURL('image/png');
                link.click();
                notifyStatus('Gambar laporan (.PNG) berhasil diunduh.');
            }).catch(function (err) {
                notifyStatus('Gagal membuat gambar: ' + err.message);
            });
        }

        function bukaLinkWA() {
            const captionEl = document.getElementById('waCaption');
            const phoneEl   = document.getElementById('waPhone');
            const text      = encodeURIComponent(captionEl ? captionEl.value : '');
            const phone     = phoneEl ? phoneEl.value.replace(/[^0-9]/g, '') : '';
            const url       = phone !== ''
                ? ('https://wa.me/' + phone + '?text=' + text)
                : ('https://api.whatsapp.com/send?text=' + text);
            window.open(url, '_blank', 'noopener,noreferrer');
        }

        function shareGambarWA() {
            const captionEl = document.getElementById('waCaption');
            const captionText = captionEl ? captionEl.value : 'Laporan Tracking Berkas';

            renderCanvasLaporan().then(function (canvas) {
                canvas.toBlob(function (blob) {
                    if (!blob) {
                        notifyStatus('Gagal mengonversi gambar.');
                        return;
                    }
                    const file = new File([blob], 'laporan_tracking_berkas_<?= date('Ymd_His') ?>.png', {
                        type: 'image/png'
                    });

                    if (navigator.canShare && navigator.canShare({ files: [file] }) && navigator.share) {
                        navigator.share({
                            title: 'Laporan Tracking Berkas',
                            text: captionText,
                            files: [file]
                        }).then(function () {
                            notifyStatus('Berhasil membuka menu share.');
                        }).catch(function () {
                            notifyStatus('Berbagi dibatalkan atau tidak didukung.');
                        });
                    } else {
                        unduhGambarPNG();
                        bukaLinkWA();
                        notifyStatus('Gambar diunduh & WhatsApp dibuka. Silakan lampirkan file .PNG.');
                    }
                }, 'image/png');
            }).catch(function (err) {
                notifyStatus('Error: ' + err.message);
            });
        }
    </script>
</body>
</html>