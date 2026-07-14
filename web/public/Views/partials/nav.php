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
<header class="mb-6">
    <div class="mx-auto flex max-w-5xl items-center justify-between rounded-lg border border-border bg-card px-3 py-2 shadow-sm">
        <nav class="flex gap-1">
            <?php foreach ($navItems as $key => $item): ?>
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium transition-colors <?= $activeNav === $key ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted' ?>">
                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/<?= $item['icon'] ?>.svg" class="h-4 w-4" alt="">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted">
                <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/user.svg" class="h-4 w-4" alt="">
                Compte
            </button>
            <div x-show="open" x-transition x-cloak class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-md border border-border bg-card p-1 shadow-md">
                <form method="POST" action="/logout">
                    <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/log-out.svg" class="h-4 w-4" alt="">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
