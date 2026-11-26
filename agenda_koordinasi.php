<?php
session_start();
require_once 'config.php';

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

$allowed_agenda_status = ['Terjadwal', 'Selesai', 'Ditunda'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $act = isset($_POST['action']) ? (string)$_POST['action'] : '';

        if ($act === 'add_agenda') {
            $namaInst = trim((string)(isset($_POST['nama_instansi']) ? $_POST['nama_instansi'] : ''));
            $tglAg    = normalizeDate(isset($_POST['tanggal_agenda']) ? (string)$_POST['tanggal_agenda'] : '');
            $jamAg    = trim((string)(isset($_POST['jam_agenda']) ? $_POST['jam_agenda'] : ''));
            $jenisAg  = trim((string)(isset($_POST['jenis_kegiatan']) ? $_POST['jenis_kegiatan'] : 'Koordinasi'));
            $catatan  = trim((string)(isset($_POST['catatan_agenda']) ? $_POST['catatan_agenda'] : ''));

            if ($namaInst !== '') {
                $stmt = $conn->prepare("INSERT INTO berkas_agenda (nama_instansi, tanggal_agenda, jam_agenda, jenis_kegiatan, catatan_agenda, status_agenda) VALUES (?, ?, ?, ?, ?, 'Terjadwal')");
                $stmt->bind_param("sssss", $namaInst, $tglAg, $jamAg, $jenisAg, $catatan);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Agenda koordinasi disimpan.'];
            }
            header('Location: agenda_koordinasi.php');
            exit;
        }

        if ($act === 'update_status') {
            $idAg = isset($_POST['id']) ? (int)$_POST['id'] : 0;
            $stAg = isset($_POST['status_agenda']) ? (string)$_POST['status_agenda'] : 'Terjadwal';
            if ($idAg > 0 && in_array($stAg, $allowed_agenda_status, true)) {
                $stmt = $conn->prepare("UPDATE berkas_agenda SET status_agenda = ? WHERE id = ?");
                $stmt->bind_param("si", $stAg, $idAg);
                $stmt->execute();
                $stmt->close();
                $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Status agenda diperbarui.'];
            }
            header('Location: agenda_koordinasi.php');
            exit;
        }
    }

    if (isset($_GET['hapus'])) {
        $idDel = (int)$_GET['hapus'];
        if ($idDel > 0) {
            $stmt = $conn->prepare("DELETE FROM berkas_agenda WHERE id = ?");
            $stmt->bind_param("i", $idDel);
            $stmt->execute();
            $stmt->close();
            $_SESSION['toast'] = ['type' => 'success', 'msg' => 'Agenda dihapus.'];
        }
        header('Location: agenda_koordinasi.php');
        exit;
    }

    $resAg = $conn->query("SELECT id, nama_instansi, tanggal_agenda, jam_agenda, jenis_kegiatan, catatan_agenda, status_agenda FROM berkas_agenda ORDER BY tanggal_agenda DESC, id DESC");
    $agendas = $resAg ? $resAg->fetch_all(MYSQLI_ASSOC) : [];

    $resInst = $conn->query("SELECT DISTINCT nama_instansi FROM berkas_tracking ORDER BY nama_instansi ASC");
    $instansis = $resInst ? $resInst->fetch_all(MYSQLI_ASSOC) : [];
} catch (Throwable $e) {
    $agendas   = [];
    $instansis = [];
    $toast     = ['type' => 'error', 'msg' => 'Kesalahan: ' . $e->getMessage()];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda Koordinasi Berkas</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .anim-up { animation: fadeUp 0.35s cubic-bezier(0.22, 1, 0.36, 1) both; }
        .lift { transition: transform 0.2s ease, box-shadow 0.2s ease; }
        .lift:hover { transform: translateY(-2px); box-shadow: 0 10px 20px -10px rgba(15, 23, 42, 0.14); }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 min-h-screen antialiased">

    <?php if (is_array($toast)): ?>
    <div id="toast" class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg border text-sm font-medium <?= $toast['type'] === 'success' ? 'bg-emerald-900 text-emerald-50 border-emerald-700' : 'bg-rose-900 text-rose-50 border-rose-700' ?>">
        <i class="fa-solid <?= $toast['type'] === 'success' ? 'fa-circle-check text-emerald-300' : 'fa-triangle-exclamation text-rose-300' ?>"></i>
        <span><?= htmlspecialchars((string)$toast['msg']) ?></span>
    </div>
    <?php endif; ?>

    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <div class="anim-up flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-300/80 text-slate-900 text-xs font-bold uppercase mb-2">
                    <i class="fa-solid fa-calendar-check"></i> Jadwal &amp; Tindak Lanjut
                </div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">Agenda Koordinasi Berkas</h1>
                <p class="text-sm text-slate-600 mt-1">Catat jadwal rapat, klarifikasi, atau penyerahan dokumen terkait instansi.</p>
            </div>
            <?php require 'nav_menu.php'; ?>
            <!-- <div class="flex gap-2">
                <a href="index.php" class="px-4 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-sm shadow-sm transition">
                    <i class="fa-solid fa-arrow-left mr-1"></i> Berkas
                </a>
                <a href="rekap_instansi.php" class="px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-sm shadow-md transition">
                    <i class="fa-solid fa-building-columns mr-1"></i> Rekap Instansi
                </a>
            </div> -->
        </div>

        <!-- Form Input Agenda -->
        <form method="POST" action="agenda_koordinasi.php" class="anim-up bg-white rounded-2xl p-4 sm:p-5 shadow-sm border border-slate-200/80 mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-12 gap-3 text-sm">
            <input type="hidden" name="action" value="add_agenda">
            <div class="lg:col-span-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">Nama Instansi *</label>
                <input type="text" name="nama_instansi" list="daftarInstansi" required placeholder="Ketik atau pilih instansi..."
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
                <datalist id="daftarInstansi">
                    <?php foreach ($instansis as $ins): ?>
                        <option value="<?= htmlspecialchars((string)$ins['nama_instansi']) ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">Tanggal</label>
                <input type="date" name="tanggal_agenda" value="<?= date('Y-m-d') ?>"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="lg:col-span-2">
                <label class="block text-xs font-bold text-slate-700 mb-1">Jam</label>
                <input type="text" name="jam_agenda" placeholder="09:00 WIB"
                    class="w-full px-3 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="lg:col-span-4">
                <label class="block text-xs font-bold text-slate-700 mb-1">Jenis Kegiatan</label>
                <input type="text" name="jenis_kegiatan" value="Koordinasi Berkas"
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="lg:col-span-9">
                <input type="text" name="catatan_agenda" placeholder="Catatan agenda / poin pembahasan..."
                    class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 focus:border-slate-900 outline-none">
            </div>
            <div class="lg:col-span-3">
                <button type="submit" class="w-full px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-bold transition">
                    <i class="fa-solid fa-plus mr-1"></i> Simpan Agenda
                </button>
            </div>
        </form>

        <!-- Daftar Agenda -->
        <div class="space-y-3">
            <?php if (empty($agendas)): ?>
                <div class="bg-white rounded-2xl p-8 text-center text-slate-500 border border-slate-200/80">
                    Belum ada agenda koordinasi tercatat.
                </div>
            <?php else: ?>
                <?php foreach ($agendas as $i => $ag): ?>
                    <div class="lift anim-up bg-white rounded-2xl p-4 sm:p-5 border border-slate-200/80 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4" style="animation-delay: <?= min($i * 35, 280) ?>ms;">
                        <div class="space-y-1">
                            <div class="text-xs font-bold text-slate-400">
                                <i class="fa-regular fa-calendar mr-1"></i> <?= formatTanggalIndo((string)$ag['tanggal_agenda']) ?>
                                <?php if (!empty($ag['jam_agenda'])): ?> &bull; <?= htmlspecialchars((string)$ag['jam_agenda']) ?><?php endif; ?>
                                &bull; <span class="text-amber-700"><?= htmlspecialchars((string)$ag['jenis_kegiatan']) ?></span>
                            </div>
                            <h2 class="text-base font-bold text-slate-900"><?= htmlspecialchars((string)$ag['nama_instansi']) ?></h2>
                            <?php if (!empty($ag['catatan_agenda'])): ?>
                                <p class="text-sm text-slate-600"><?= htmlspecialchars((string)$ag['catatan_agenda']) ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-2 self-end md:self-center">
                            <form method="POST" action="agenda_koordinasi.php">
                                <input type="hidden" name="action" value="update_status">
                                <input type="hidden" name="id" value="<?= (int)$ag['id'] ?>">
                                <select name="status_agenda" onchange="this.form.submit()" class="px-3 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-bold outline-none">
                                    <?php foreach ($allowed_agenda_status as $stA): ?>
                                        <option value="<?= htmlspecialchars($stA) ?>" <?= $ag['status_agenda'] === $stA ? 'selected' : '' ?>><?= htmlspecialchars($stA) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                            <a href="agenda_koordinasi.php?hapus=<?= (int)$ag['id'] ?>" onclick="return confirm('Hapus agenda ini?')"
                                class="p-2 rounded-lg bg-slate-100 hover:bg-rose-500 text-slate-600 hover:text-white text-xs transition" title="Hapus">
                                <i class="fa-solid fa-trash"></i>
                            </a>
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
                toast.style.transition = 'opacity 0.35s ease';
                toast.style.opacity = '0';
                setTimeout(function () { toast.remove(); }, 350);
            }, 3000);
        }
    </script>
</body>
</html>