<?php
session_start();
require_once 'config.php';

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

$allowed_stage  = ['Belum', 'Proses', 'Selesai', 'Revisi'];
$allowed_status = ['Masuk', 'Diproses', 'Revisi', 'Selesai'];

try {
    // Tambah catatan tindak lanjut manual pada riwayat berkas
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['action']) ? $_POST['action'] : '') === 'add_log') {
        $berkas_id = isset($_POST['berkas_id']) ? (int)$_POST['berkas_id'] : 0;
        $catatan   = trim((string)(isset($_POST['catatan_tindak_lanjut']) ? $_POST['catatan_tindak_lanjut'] : ''));

        if ($berkas_id > 0 && $catatan !== '') {
            $stmtGet = $conn->prepare("SELECT nama_instansi, status_ringkas, tahap_opd, tahap_disdukcapil, tahap_ditjen FROM berkas_tracking WHERE id = ?");
            $stmtGet->bind_param("i", $berkas_id);
            $stmtGet->execute();
            $resGet = $stmtGet->get_result();
            $bRow   = $resGet ? $resGet->fetch_assoc() : null;
            $stmtGet->close();

            if ($bRow) {
                $stmtIns = $conn->prepare(
                    "INSERT INTO berkas_riwayat
                     (berkas_id, nama_instansi, status_ringkas, tahap_opd, tahap_disdukcapil, tahap_ditjen, catatan_tindak_lanjut)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $nInst = (string)$bRow['nama_instansi'];
                $sRing = (string)$bRow['status_ringkas'];
                $tOpd  = (string)$bRow['tahap_opd'];
                $tDis  = (string)$bRow['tahap_disdukcapil'];
                $tDit  = (string)$bRow['tahap_ditjen'];

                $stmtIns->bind_param("issssss", $berkas_id, $nInst, $sRing, $tOpd, $tDis, $tDit, $catatan);
                $stmtIns->execute();
                $stmtIns->close();

                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Catatan riwayat berhasil ditambahkan.'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Data berkas tidak ditemukan.'];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'msg' => 'Pilih berkas dan isi catatan tindak lanjut.'];
        }

        header('Location: riwayat_status.php');
        exit;
    }

    // Daftar berkas untuk dropdown form catatan
    $listRes     = $conn->query("SELECT id, nama_instansi, tanggal_masuk, status_ringkas FROM berkas_tracking ORDER BY nama_instansi ASC");
    $berkasList  = $listRes ? $listRes->fetch_all(MYSQLI_ASSOC) : [];

    // Filter riwayat
    $q          = trim((string)(isset($_GET['q']) ? $_GET['q'] : ''));
    $berkasFilter = isset($_GET['berkas_id']) ? (int)$_GET['berkas_id'] : 0;

    $sql    = "SELECT id, berkas_id, nama_instansi, status_ringkas, tahap_opd, tahap_disdukcapil, tahap_ditjen, catatan_tindak_lanjut, dicatat_pada FROM berkas_riwayat WHERE 1=1";
    $params = [];
    $types  = "";

    if ($berkasFilter > 0) {
        $sql     .= " AND berkas_id = ?";
        $params[] = $berkasFilter;
        $types   .= "i";
    }

    if ($q !== '') {
        $sql     .= " AND (nama_instansi LIKE ? OR catatan_tindak_lanjut LIKE ?)";
        $like     = "%" . $q . "%";
        $params[] = $like;
        $params[] = $like;
        $types   .= "ss";
    }

    $sql .= " ORDER BY dicatat_pada DESC, id DESC";

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
    $logs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} catch (Throwable $e) {
    $logs        = [];
    $berkasList  = [];
    $toast       = ['type' => 'error', 'msg' => 'Terjadi kesalahan: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Riwayat Status Berkas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes slideUpItem {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes popToast {
            from { opacity: 0; transform: scale(0.95) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .animate-item {
            animation: slideUpItem 0.34s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .animate-pop {
            animation: popToast 0.25s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        .card-lift {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .card-lift:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 22px -10px rgba(15, 23, 42, 0.16);
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased selection:bg-amber-300 selection:text-slate-900">

    <?php if (is_array($toast)): ?>
    <div id="toast" class="fixed bottom-5 right-5 z-50 animate-pop flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium <?= $toast['type'] === 'success' ? 'bg-emerald-900 text-emerald-50 border-emerald-700' : 'bg-rose-900 text-rose-50 border-rose-700' ?>">
        <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check text-emerald-300' : 'fa-triangle-exclamation text-rose-300' ?>"></i>
        <span><?= htmlspecialchars((string)$toast['msg']) ?></span>
    </div>
    <?php endif; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <!-- Header -->
        <div class="animate-item flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-300/80 text-slate-900 text-xs font-bold uppercase tracking-wide mb-2">
                    <i class="fa-solid fa-clock-rotate-left"></i> Log Perubahan &amp; Tindak Lanjut
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                    Riwayat Status Berkas
                </h1>
                <p class="text-sm text-slate-600 mt-1">
                    Jejak perubahan status dan tahap OPD, Disdukcapil, serta Ditjen Dukcapil.
                </p>
            </div>
            <?php require 'nav_menu.php'; ?>
            <!-- <div class="flex flex-wrap items-center gap-2.5">
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-table-list"></i> Data Berkas
                </a>
                <a href="laporan.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-md transition">
                    <i class="fa-solid fa-print"></i> Laporan Cetak
                </a>
            </div> -->
        </div>

        <!-- Form Tambah Catatan Tindak Lanjut + Filter -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 mb-6">
            <form method="POST" action="riwayat_status.php" class="animate-item lg:col-span-7 bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 space-y-3 text-sm">
                <input type="hidden" name="action" value="add_log">
                <div class="font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-pen-to-square text-amber-500"></i> Tambah Catatan Tindak Lanjut
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-1">
                        <select name="berkas_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-300 bg-white focus:border-slate-900 outline-none text-xs sm:text-sm">
                            <option value="">-- Pilih Berkas --</option>
                            <?php foreach ($berkasList as $b): ?>
                                <option value="<?= (int)$b['id'] ?>">
                                    <?= htmlspecialchars((string)$b['nama_instansi']) ?> (<?= formatTanggalIndo((string)$b['tanggal_masuk']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2 flex gap-2">
                        <input type="text" name="catatan_tindak_lanjut" required placeholder="Contoh: Koordinasi verifikasi berkas ke bidang terkait..."
                            class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
                        <button type="submit" class="px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold transition active:scale-[0.98] shrink-0">
                            Simpan
                        </button>
                    </div>
                </div>
            </form>

            <form method="GET" action="riwayat_status.php" class="animate-item lg:col-span-5 bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 space-y-3 text-sm">
                <div class="font-bold text-slate-900 flex items-center gap-2">
                    <i class="fa-solid fa-filter text-slate-500"></i> Filter Riwayat
                </div>
                <div class="flex flex-col sm:flex-row gap-2">
                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Cari instansi / catatan..."
                        class="flex-1 px-3.5 py-2 rounded-xl border border-slate-200 focus:border-slate-900 outline-none">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold transition">
                        Cari
                    </button>
                    <?php if ($q !== '' || $berkasFilter > 0): ?>
                        <a href="riwayat_status.php" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 flex items-center justify-center transition" title="Reset">
                            <i class="fa-solid fa-rotate-left"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Daftar Riwayat -->
        <div class="space-y-3">
            <?php if (empty($logs)): ?>
                <div class="animate-item bg-white rounded-2xl p-10 text-center text-slate-500 border border-slate-200/80 shadow-sm">
                    <i class="fa-regular fa-clock text-3xl mb-2 block text-slate-400"></i>
                    Belum ada catatan riwayat status berkas.
                </div>
            <?php else: ?>
                <?php foreach ($logs as $i => $lg): ?>
                    <?php
                        $bOpd = badgeStage((string)$lg['tahap_opd']);
                        $bDis = badgeStage((string)$lg['tahap_disdukcapil']);
                        $bDit = badgeStage((string)$lg['tahap_ditjen']);
                        $waktuFmt = date('d/m/Y H:i', strtotime((string)$lg['dicatat_pada']));
                    ?>
                    <div class="animate-item card-lift bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm" style="animation-delay: <?= min($i * 35, 300) ?>ms;">
                        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 pb-3 border-b border-slate-100">
                            <div>
                                <div class="text-xs text-slate-400 font-semibold">
                                    <i class="fa-regular fa-calendar mr-1"></i> <?= htmlspecialchars($waktuFmt) ?> WIB
                                    &bull; <a href="riwayat_status.php?berkas_id=<?= (int)$lg['berkas_id'] ?>" class="text-amber-700 hover:underline">Lihat berkas ini saja</a>
                                </div>
                                <h2 class="text-base sm:text-lg font-bold text-slate-900 mt-0.5">
                                    <?= htmlspecialchars((string)$lg['nama_instansi']) ?>
                                </h2>
                            </div>
                            <span class="self-start md:self-auto px-3 py-1 rounded-full text-xs font-bold <?= badgeStatus((string)$lg['status_ringkas']) ?>">
                                Status: <?= htmlspecialchars((string)$lg['status_ringkas']) ?>
                            </span>
                        </div>

                        <p class="text-sm text-slate-700 mt-3 bg-slate-50 px-3.5 py-2.5 rounded-xl border border-slate-100">
                            <?= htmlspecialchars((string)($lg['catatan_tindak_lanjut'] ?: 'Pembaruan status berkas')) ?>
                        </p>

                        <div class="grid grid-cols-3 gap-2 mt-3 text-center text-xs">
                            <div class="px-2.5 py-1.5 rounded-xl border <?= $bOpd[0] ?>">
                                <span class="font-bold">OPD:</span> <?= htmlspecialchars((string)$lg['tahap_opd']) ?>
                            </div>
                            <div class="px-2.5 py-1.5 rounded-xl border <?= $bDis[0] ?>">
                                <span class="font-bold">Disdukcapil:</span> <?= htmlspecialchars((string)$lg['tahap_disdukcapil']) ?>
                            </div>
                            <div class="px-2.5 py-1.5 rounded-xl border <?= $bDit[0] ?>">
                                <span class="font-bold">Ditjen:</span> <?= htmlspecialchars((string)$lg['tahap_ditjen']) ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
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