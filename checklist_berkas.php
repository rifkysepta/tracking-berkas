<?php
session_start();
require_once 'config.php';

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

$selectedBerkasId = isset($_REQUEST['berkas_id']) ? (int)$_REQUEST['berkas_id'] : 0;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = isset($_POST['action']) ? (string)$_POST['action'] : '';
        $bid = isset($_POST['berkas_id']) ? (int)$_POST['berkas_id'] : 0;

        if ($act === 'add_item' && $bid > 0) {
            $namaDok = trim((string)(isset($_POST['nama_dokumen']) ? $_POST['nama_dokumen'] : ''));
            $catatan = trim((string)(isset($_POST['catatan']) ? $_POST['catatan'] : ''));
            if ($namaDok !== '') {
                $stmt = $conn->prepare("INSERT INTO berkas_checklist (berkas_id, nama_dokumen, is_lengkap, catatan) VALUES (?, ?, 0, ?)");
                $stmt->bind_param("iss", $bid, $namaDok, $catatan);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Item checklist ditambahkan.'];
            }
            header('Location: checklist_berkas.php?berkas_id=' . $bid);
            exit;
        }

        if ($act === 'toggle_item' && $bid > 0) {
            $itemId  = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            $lengkap = (isset($_POST['is_lengkap']) && (string)$_POST['is_lengkap'] === '1') ? 1 : 0;
            if ($itemId > 0) {
                $stmt = $conn->prepare("UPDATE berkas_checklist SET is_lengkap = ? WHERE id = ? AND berkas_id = ?");
                $stmt->bind_param("iii", $lengkap, $itemId, $bid);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Status kelengkapan diperbarui.'];
            }
            header('Location: checklist_berkas.php?berkas_id=' . $bid);
            exit;
        }

        if ($act === 'delete_item' && $bid > 0) {
            $itemId = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            if ($itemId > 0) {
                $stmt = $conn->prepare("DELETE FROM berkas_checklist WHERE id = ? AND berkas_id = ?");
                $stmt->bind_param("ii", $itemId, $bid);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Item checklist dihapus.'];
            }
            header('Location: checklist_berkas.php?berkas_id=' . $bid);
            exit;
        }
    }

    $resB = $conn->query("SELECT id, nama_instansi, tanggal_masuk, status_ringkas FROM berkas_tracking ORDER BY id DESC");
    $berkasList = $resB ? $resB->fetch_all(MYSQLI_ASSOC) : [];

    if ($selectedBerkasId <= 0 && !empty($berkasList)) {
        $selectedBerkasId = (int)$berkasList[0]['id'];
    }

    $items = [];
    if ($selectedBerkasId > 0) {
        $stmtI = $conn->prepare("SELECT id, berkas_id, nama_dokumen, is_lengkap, catatan FROM berkas_checklist WHERE berkas_id = ? ORDER BY id ASC");
        $stmtI->bind_param("i", $selectedBerkasId);
        $stmtI->execute();
        $resI  = $stmtI->get_result();
        $items = $resI ? $resI->fetch_all(MYSQLI_ASSOC) : [];
        $stmtI->close();
    }
} catch (Throwable $e) {
    $berkasList = [];
    $items      = [];
    $toast      = ['type' => 'error', 'msg' => 'Kesalahan: ' . $e->getMessage()];
}

$totalItem = count($items);
$selesaiItem = 0;
foreach ($items as $it) {
    if ((int)$it['is_lengkap'] === 1) $selesaiItem++;
}
$persen = ($totalItem > 0) ? (int)round(($selesaiItem / $totalItem) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checklist Kelengkapan Berkas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-up { animation: fadeUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .progress-fill { transition: width 0.45s cubic-bezier(0.22, 1, 0.36, 1); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased">

    <?php if (is_array($toast)): ?>
    <div id="toast" class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium <?= $toast['type'] === 'success' ? 'bg-emerald-900 text-emerald-50 border-emerald-700' : 'bg-rose-900 text-rose-50 border-rose-700' ?>">
        <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check text-emerald-300' : 'fa-triangle-exclamation text-rose-300' ?>"></i>
        <span><?= htmlspecialchars((string)$toast['msg']) ?></span>
    </div>
    <?php endif; ?>

    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <div class="anim-up flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-300/80 text-slate-900 text-xs font-bold uppercase mb-2">
                    <i class="fa-solid fa-list-check"></i> Verifikasi Dokumen
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Checklist Kelengkapan Berkas</h1>
                <p class="text-sm text-slate-600 mt-1">Pantau kesiapan dokumen persyaratan per instansi.</p>
            </div>
            <?php require 'nav_menu.php'; ?>
            <!-- <div class="flex gap-2">
                <a href="index.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Berkas
                </a>
                <a href="laporan.php" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-md transition">
                    <i class="fa-solid fa-print mr-1"></i> Laporan
                </a>
            </div> -->
        </div>

        <!-- Pilih Berkas -->
        <form method="GET" action="checklist_berkas.php" class="anim-up bg-white rounded-2xl p-4 shadow-sm border border-slate-200/80 mb-5 flex flex-col sm:flex-row gap-3">
            <select name="berkas_id" onchange="this.form.submit()" class="flex-1 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white focus:border-slate-900 outline-none text-sm font-semibold">
                <?php if (empty($berkasList)): ?>
                    <option value="0">Belum ada data berkas di database</option>
                <?php else: ?>
                    <?php foreach ($berkasList as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === $selectedBerkasId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$b['nama_instansi']) ?> &mdash; Masuk <?= formatTanggalIndo((string)$b['tanggal_masuk']) ?> (<?= htmlspecialchars((string)$b['status_ringkas']) ?>)
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </form>

        <?php if ($selectedBerkasId > 0): ?>
        <!-- Progress Bar -->
        <div class="anim-up bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 mb-5">
            <div class="flex items-center justify-between text-sm mb-2">
                <span class="font-bold text-slate-800">Kelengkapan Dokumen</span>
                <span class="font-extrabold text-slate-900"><?= $selesaiItem ?> / <?= $totalItem ?> (<?= $persen ?>%)</span>
            </div>
            <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden">
                <div class="progress-fill h-full rounded-full <?= $persen === 100 ? 'bg-emerald-500' : 'bg-amber-400' ?>" style="width: <?= $persen ?>%;"></div>
            </div>
        </div>

        <!-- Tambah Item Checklist -->
        <form method="POST" action="checklist_berkas.php" class="anim-up bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 mb-5 grid grid-cols-1 sm:grid-cols-12 gap-3 text-sm">
            <input type="hidden" name="action" value="add_item">
            <input type="hidden" name="berkas_id" value="<?= $selectedBerkasId ?>">
            <div class="sm:col-span-6">
                <input type="text" name="nama_dokumen" required placeholder="Nama dokumen (misal: Surat Permohonan PKS, Draft Perjanjian...)"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="sm:col-span-4">
                <input type="text" name="catatan" placeholder="Catatan singkat (opsional)"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold transition">
                    <i class="fa-solid fa-plus mr-1"></i> Tambah
                </button>
            </div>
        </form>

        <!-- Daftar Item -->
        <div class="space-y-2.5">
            <?php if (empty($items)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-slate-500 border border-slate-200/80">
                    Belum ada daftar dokumen untuk berkas ini.
                </div>
            <?php else: ?>
                <?php foreach ($items as $i => $it): ?>
                    <?php $done = ((int)$it['is_lengkap'] === 1); ?>
                    <div class="anim-up bg-white rounded-2xl p-4 border border-slate-200/80 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-3" style="animation-delay: <?= min($i * 35, 250) ?>ms;">
                        <div class="flex items-start gap-3">
                            <form method="POST" action="checklist_berkas.php">
                                <input type="hidden" name="action" value="toggle_item">
                                <input type="hidden" name="berkas_id" value="<?= $selectedBerkasId ?>">
                                <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                                <input type="hidden" name="is_lengkap" value="<?= $done ? '0' : '1' ?>">
                                <button type="submit" class="mt-0.5 w-6 h-6 rounded-lg border flex items-center justify-center transition <?= $done ? 'bg-emerald-500 border-emerald-500 text-white' : 'bg-white border-slate-300 text-transparent hover:border-slate-500' ?>" title="Tandai Lengkap/Belum">
                                    <i class="fa-solid fa-check text-xs"></i>
                                </button>
                            </form>
                            <div>
                                <div class="font-bold text-sm sm:text-base <?= $done ? 'line-through text-slate-400' : 'text-slate-900' ?>">
                                    <?= htmlspecialchars((string)$it['nama_dokumen']) ?>
                                </div>
                                <?php if (!empty($it['catatan'])): ?>
                                    <div class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string)$it['catatan']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <form method="POST" action="checklist_berkas.php" onsubmit="return confirm('Hapus item ini?')" class="self-end sm:self-auto">
                            <input type="hidden" name="action" value="delete_item">
                            <input type="hidden" name="berkas_id" value="<?= $selectedBerkasId ?>">
                            <input type="hidden" name="item_id" value="<?= (int)$it['id'] ?>">
                            <button type="submit" class="p-2 rounded-lg bg-slate-100 hover:bg-rose-500 text-slate-600 hover:text-white text-xs transition" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const toast = document.getElementById('toast');
        if (toast) {
            setTimeout(function () {
                toast.style.transition = 'opacity 0.35s ease';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 350);
            }, 3000);
        }
    </script>
</body>
</html>