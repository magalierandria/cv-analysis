<?php
/** @var string $activeNav 'import' | 'revue' | 'admin' */
$activeNav = $activeNav ?? '';
$role = $_SESSION['role'] ?? null;

$navItems = [
    'import' => ['href' => '/', 'label' => 'Import', 'icon' => 'upload-cloud'],
    'revue' => ['href' => '/revue', 'label' => 'Revue', 'icon' => 'list-checks'],
];
if ($role === 'admin') {
    $navItems['admin'] = ['href' => '/admin/utilisateurs', 'label' => 'Admin', 'icon' => 'users'];
}
?>
<button type="button" x-show="!sidebarOpen" x-cloak @click="sidebarOpen = true"
        class="fixed left-4 top-4 z-30 inline-flex items-center justify-center rounded-md border border-border bg-card p-2 shadow-sm lg:hidden">
    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/menu.svg" class="h-5 w-5" alt="Ouvrir le menu">
</button>

<div x-show="sidebarOpen" x-cloak x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : ''"
       class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col -translate-x-full bg-sidebar text-sidebar-foreground transition-transform duration-200 lg:translate-x-0">
    <div class="flex items-center border-b border-sidebar-border px-5 py-5">
        <img src="/images/novity-logo-noir.svg" class="h-4 w-auto brightness-0 invert" alt="Novity">
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
        <?php foreach ($navItems as $key => $item): ?>
            <a href="<?= htmlspecialchars($item['href']) ?>"
               class="flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors <?= $activeNav === $key ? 'bg-primary text-primary-foreground' : 'hover:bg-white/10' ?>">
                <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/<?= $item['icon'] ?>.svg"
                     class="h-4 w-4 brightness-0 invert <?= $activeNav === $key ? '' : 'opacity-60' ?>" alt="">
                <?= htmlspecialchars($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
