<?php
$currentPage = basename((string)(isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : 'index.php'));

$navItems = [
    [
        'href'  => 'index.php',
        'label' => 'Berkas',
        'icon'  => 'fa-table-list',
        'tone'  => 'text-slate-700',
    ],
    [
        'href'  => 'rekap_instansi.php',
        'label' => 'Rekap Instansi',
        'icon'  => 'fa-building-columns',
        'tone'  => 'text-slate-600',
    ],
    [
        'href'  => 'checklist_berkas.php',
        'label' => 'Checklist',
        'icon'  => 'fa-list-check',
        'tone'  => 'text-amber-600',
    ],
    [
        'href'  => 'agenda_koordinasi.php',
        'label' => 'Agenda',
        'icon'  => 'fa-calendar-check',
        'tone'  => 'text-sky-600',
    ],
    [
        'href'  => 'riwayat_status.php',
        'label' => 'Riwayat',
        'icon'  => 'fa-clock-rotate-left',
        'tone'  => 'text-indigo-600',
    ],
    [
        'href'  => 'kirim_wa_laporan.php',
        'label' => 'Kirim WA',
        'icon'  => 'fa-clock-rotate-left',
        'tone'  => 'text-indigo-600',
    ],
    [
        'href'  => 'laporan.php',
        'label' => 'Laporan',
        'icon'  => 'fa-file-lines',
        'tone'  => 'text-amber-600',
    ],
];

$csvHref = isset($csvUrl) && (string)$csvUrl !== '' ? (string)$csvUrl : 'index.php?export=csv';
?>
<nav aria-label="Navigasi Utama" class="no-print flex flex-wrap items-center gap-2">
    <?php foreach ($navItems as $item): ?>
        <?php $isActive = ($currentPage === $item['href']); ?>
        <a href="<?= htmlspecialchars($item['href']) ?>"
           class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl text-xs sm:text-sm transition <?= $isActive
               ? 'bg-amber-300 text-slate-950 font-extrabold ring-2 ring-amber-400/70 shadow-sm'
               : 'bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold shadow-sm' ?>">
            <i class="fa-solid <?= htmlspecialchars($item['icon']) ?> <?= $isActive ? 'text-slate-950' : htmlspecialchars($item['tone']) ?>"></i>
            <span><?= htmlspecialchars($item['label']) ?></span>
        </a>
    <?php endforeach; ?>

    <a href="<?= htmlspecialchars($csvHref) ?>"
       class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-emerald-50 border border-slate-200 hover:border-emerald-300 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
        <i class="fa-solid fa-file-csv text-emerald-600"></i>
        <span>CSV</span>
    </a>

    <a href="backup_db.php"
       class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 rounded-xl bg-white hover:bg-slate-50 border border-slate-200 text-slate-800 font-semibold text-xs sm:text-sm shadow-sm transition">
        <i class="fa-solid fa-download text-emerald-700"></i>
        <span>Backup SQL</span>
    </a>

    <?php if ($currentPage === 'index.php'): ?>
        <button type="button" onclick="openModal('create')"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 active:scale-[0.98] text-white font-semibold text-xs sm:text-sm shadow-md transition">
            <i class="fa-solid fa-plus"></i>
            <span>Input Berkas Baru</span>
        </button>
    <?php endif; ?>
</nav>