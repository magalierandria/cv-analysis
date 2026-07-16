<?php
$role = $_SESSION['role'] ?? null;
?>
<div class="mb-6 flex items-center justify-end gap-3 border-b border-border pb-4">
    <span class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
        <?= $role === 'admin' ? 'Admin' : 'Utilisateur' ?>
    </span>
    <form method="POST" action="/logout">
        <button type="submit"
                class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted">
            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/log-out.svg" class="h-4 w-4" alt="">
            Déconnexion
        </button>
    </form>
</div>
