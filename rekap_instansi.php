<?php
require_once 'config.php';

$q = trim((string)(isset($_GET['q']) ? $_GET['q'] : ''));
$rows = [];
$errorMsg = null;

try {
    $sql = "SELECT
                nama_instansi,
                COUNT(*) AS total_berkas,
                SUM(CASE WHEN status_ringkas = 'Selesai' THEN 1 ELSE 0 END) AS total_selesai,
                SUM(CASE WHEN status_ringkas = 'Revisi' THEN 1 ELSE 0 END) AS total_revisi,
                SUM(CASE WHEN status_ringkas IN ('Masuk', 'Diproses') THEN 1 ELSE 0 END) AS total_aktif,
                MAX(DATEDIFF(CURDATE(), tanggal_masuk)) AS usia_terlama_hari,
                MIN(tanggal_masuk) AS tgl_pertama,
                MAX(tanggal_masuk) AS tgl_terakhir
            FROM berkas_tracking";
    $params = [];
    $types  = "";

    if ($q !== '') {
        $sql     .= " WHERE nama_instansi LIKE ?";
        $params[] = "%" . $q . "%";
        $types   .= "s";
    }

    $sql .= " GROUP BY nama_instansi ORDER BY total_aktif DESC, usia_terlama_hari DESC, nama_instansi ASC";

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
    $errorMsg = 'Gagal memuat rekap instansi: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi per Instansi</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-up { animation: fadeUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .lift:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(15, 23, 42, 0.15); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <div class="anim-up flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-300/80 text-slate-900 text-xs font-bold uppercase mb-2">
                    <i class="fa-solid fa-building-columns"></i> Rekap &amp; Durasi Proses
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Rekapitulasi per Nama Instansi</h1>
                <p class="text-sm text-slate-600 mt-1">Ringkasan jumlah berkas aktif, revisi, selesai, dan lama hari sejak berkas masuk.</p>
            </div>
            <?php require 'nav_menu.php'; ?>
            <!-- <div class="flex flex-wrap gap-2">
                <a href="index.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-table-list mr-1"></i> Berkas
                </a>
                <a href="checklist_berkas.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-list-check mr-1"></i> Checklist
                </a>
                <a href="agenda_koordinasi.php" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-md transition">
                    <i class="fa-solid fa-calendar-check mr-1"></i> Agenda
                </a>
            </div> -->
        </div>

        <form method="GET" action="rekap_instansi.php" class="anim-up bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 mb-6 flex gap-2">
            <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari nama instansi..."
                class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 focus:border-slate-900 outline-none text-sm">
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold text-sm transition">
                Cari
            </button>
            <?php if ($q !== ''): ?>
                <a href="rekap_instansi.php" class="px-3 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm flex items-center">
                    <i class="fa-solid fa-rotate-left"></i>
                </a>
            <?php endif; ?>
        </form>

        <?php if ($errorMsg !== null): ?>
            <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-sm mb-4"><?= htmlspecialchars($errorMsg) ?></div>
        <?php endif; ?>

        <!-- Tabel Desktop -->
        <div class="hidden md:block anim-up bg-white rounded-2xl shadow-sm border border-slate-200/80 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-left text-sm">
                    <thead>
                        <tr class="bg-yellow-300 text-slate-950 text-xs font-extrabold uppercase border-b-2 border-slate-900/15">
                            <th class="py-3 px-3 text-center w-12">NO</th>
                            <th class="py-3 px-4">NAMA INSTANSI</th>
                            <th class="py-3 px-3 text-center">TOTAL</th>
                            <th class="py-3 px-3 text-center">AKTIF</th>
                            <th class="py-3 px-3 text-center">REVISI</th>
                            <th class="py-3 px-3 text-center">SELESAI</th>
                            <th class="py-3 px-3 text-center">USIA TERLAMA</th>
                            <th class="py-3 px-3 text-center">RENTANG MASUK</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200/80">
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="py-10 text-center text-slate-500">Belum ada data rekap instansi.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $i => $r): ?>
                                <?php $usia = max(0, (int)$r['usia_terlama_hari']); ?>
                                <tr class="hover:bg-amber-50/40 transition" style="animation: fadeUp 0.3s ease both; animation-delay: <?= min($i * 35, 280) ?>ms;">
                                    <td class="py-3 px-3 text-center font-bold text-slate-600"><?= $i + 1 ?></td>
                                    <td class="py-3 px-4 font-bold text-slate-900"><?= htmlspecialchars((string)$r['nama_instansi']) ?></td>
                                    <td class="py-3 px-3 text-center font-bold"><?= (int)$r['total_berkas'] ?></td>
                                    <td class="py-3 px-3 text-center"><span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-900 font-bold text-xs"><?= (int)$r['total_aktif'] ?></span></td>
                                    <td class="py-3 px-3 text-center"><span class="px-2.5 py-0.5 rounded-full bg-rose-100 text-rose-800 font-bold text-xs"><?= (int)$r['total_revisi'] ?></span></td>
                                    <td class="py-3 px-3 text-center"><span class="px-2.5 py-0.5 rounded-full bg-emerald-100 text-emerald-800 font-bold text-xs"><?= (int)$r['total_selesai'] ?></span></td>
                                    <td class="py-3 px-3 text-center">
                                        <span class="px-2.5 py-1 rounded-lg text-xs font-bold <?= $usia >= 14 ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($usia >= 7 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-100 text-slate-700 border border-slate-200') ?>">
                                            <?= $usia ?> hari
                                        </span>
                                    </td>
                                    <td class="py-3 px-3 text-center text-xs text-slate-600 whitespace-nowrap">
                                        <?= formatTanggalIndo((string)$r['tgl_pertama']) ?> &ndash; <?= formatTanggalIndo((string)$r['tgl_terakhir']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Kartu HP -->
        <div class="md:hidden space-y-3">
            <?php if (empty($rows)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-slate-500 border border-slate-200">Belum ada data rekap.</div>
            <?php else: ?>
                <?php foreach ($rows as $i => $r): ?>
                    <div class="lift anim-up bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm space-y-2.5" style="animation-delay: <?= min($i * 40, 280) ?>ms;">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="font-bold text-slate-900 text-base"><?= ($i + 1) . '. ' . htmlspecialchars((string)$r['nama_instansi']) ?></h2>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg bg-slate-100 text-slate-800"><?= max(0, (int)$r['usia_terlama_hari']) ?> hari</span>
                        </div>
                        <div class="grid grid-cols-4 gap-2 text-center text-xs">
                            <div class="p-2 rounded-xl bg-slate-50 border border-slate-100"><div class="text-slate-400">Total</div><div class="font-extrabold text-slate-900"><?= (int)$r['total_berkas'] ?></div></div>
                            <div class="p-2 rounded-xl bg-amber-50/70 border border-amber-100"><div class="text-amber-700">Aktif</div><div class="font-extrabold text-amber-900"><?= (int)$r['total_aktif'] ?></div></div>
                            <div class="p-2 rounded-xl bg-rose-50/70 border border-rose-100"><div class="text-rose-700">Revisi</div><div class="font-extrabold text-rose-900"><?= (int)$r['total_revisi'] ?></div></div>
                            <div class="p-2 rounded-xl bg-emerald-50/70 border border-emerald-100"><div class="text-emerald-700">Selesai</div><div class="font-extrabold text-emerald-900"><?= (int)$r['total_selesai'] ?></div></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>