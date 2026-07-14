# Refonte visuelle shadcn du dashboard post-login — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer Bootstrap 5 par un design system façon shadcn/ui (Tailwind CSS + Alpine.js + Lucide, sans React) sur les 4 pages post-login : `import.php`, `revue_liste.php`, `revue.php`, `admin_utilisateurs.php`.

**Architecture:** Aucun changement de Controllers/Models/routes/BDD. Seuls les fichiers de vue PHP, un nouveau partial de navigation, et l'ajout d'un stage de build Tailwind dans le Dockerfile `web` sont modifiés. Le CSS est compilé au build de l'image Docker (binaire CLI standalone, pas de npm), livré comme un fichier statique `public/css/app.css` servi par Apache.

**Tech Stack:** Tailwind CSS (CLI standalone v3.4.17), Alpine.js 3.14.3 (CDN), Lucide (icônes SVG statiques via CDN jsdelivr), PHP 8.3 natif (inchangé).

## Global Constraints

- Aucune dépendance Node/npm à l'exécution ni dans l'image finale — seul le stage de build intermédiaire télécharge le binaire `tailwindcss`, qui n'est pas copié dans l'image finale.
- Aucune modification de `web/public/Controllers/*.php`, `web/public/Models/*.php`, ni de `web/public/index.php` (routes inchangées).
- `login.php` reste en Bootstrap, non touché.
- Couleur d'accent primaire : vert Novity `#16A34A`.
- Chaque vue restylée doit conserver exactement les mêmes attributs `name`, `action`, `method` de formulaire et les mêmes routes que l'original — seul le HTML/CSS/JS visuel change.
- Les tests d'intégration existants (`tests/web/test_auth.py`, `tests/web/test_import_flow.py`) ne doivent présenter aucune régression après chaque tâche impliquant le service `web`.

---

## File Structure

```
web/
  assets/
    css/input.css              # [NEW] directives @tailwind + tokens + composants
    tailwind.config.js         # [NEW] content globs + thème (couleurs/radius/police)
  Dockerfile                   # [MODIFIED] build multi-stage (ajout stage cssbuild)
  public/
    css/app.css                # [GENERATED at build time, gitignored]
    Views/
      partials/
        nav.php                 # [NEW] topbar partagée (nav + dropdown compte Alpine)
      import.php                # [MODIFIED]
      revue_liste.php           # [MODIFIED]
      revue.php                 # [MODIFIED]
      admin_utilisateurs.php    # [MODIFIED]
.gitignore                      # [MODIFIED] ajout de web/public/css/
```

---

### Task 1: Pipeline de build Tailwind (Dockerfile + tokens + config)

**Files:**
- Create: `web/assets/css/input.css`
- Create: `web/assets/tailwind.config.js`
- Modify: `web/Dockerfile`
- Modify: `.gitignore`

**Interfaces:**
- Produces: classes composants utilisables dans toutes les vues des tâches suivantes —
  `.btn-default`, `.btn-outline`, `.btn-destructive`, `.btn-ghost`, `.btn-sm`, `.card`,
  `.card-header`, `.card-title`, `.card-content`, `.badge-default`, `.badge-secondary`,
  `.badge-destructive`, `.input`, `.label`, `.table-shadcn`, `.alert-destructive`,
  attribut `[x-cloak]`.
- Produces: fichier statique servi à l'URL `/css/app.css`.

- [ ] **Step 1: Vérifier l'état actuel (pas de CSS compilé)**

Run: `test -f web/public/css/app.css && echo EXISTS || echo MISSING`
Expected: `MISSING`

- [ ] **Step 2: Créer `web/assets/css/input.css`**

```css
@tailwind base;
@tailwind components;
@tailwind utilities;

:root {
  /* Triplets RGB (espace-séparés) plutôt que hex : requis par Tailwind pour
     que les modificateurs d'opacité (bg-primary/90, bg-destructive/10, ...)
     fonctionnent via rgb(var(--x) / <alpha-value>) dans tailwind.config.js. */
  --background: 255 255 255; /* #ffffff */
  --foreground: 10 10 10; /* #0a0a0a */
  --card: 255 255 255; /* #ffffff */
  --border: 229 231 235; /* #e5e7eb */
  --muted: 244 244 245; /* #f4f4f5 */
  --muted-foreground: 113 113 122; /* #71717a */
  --primary: 22 163 74; /* #16a34a */
  --primary-foreground: 255 255 255; /* #ffffff */
  --destructive: 220 38 38; /* #dc2626 */
  --radius: 0.5rem;
}

[x-cloak] {
  display: none !important;
}

@layer components {
  .btn {
    @apply inline-flex items-center justify-center gap-2 rounded-md px-4 py-2 text-sm font-medium transition-colors disabled:pointer-events-none disabled:opacity-50;
  }
  .btn-default {
    @apply btn bg-primary text-primary-foreground hover:bg-primary/90;
  }
  .btn-outline {
    @apply btn border border-border bg-background hover:bg-muted;
  }
  .btn-destructive {
    @apply btn bg-destructive text-white hover:bg-destructive/90;
  }
  .btn-ghost {
    @apply btn hover:bg-muted;
  }
  .btn-sm {
    @apply px-3 py-1.5 text-xs;
  }

  .card {
    @apply rounded-lg border border-border bg-card shadow-sm;
  }
  .card-header {
    @apply flex flex-col gap-1.5 p-6;
  }
  .card-title {
    @apply font-semibold leading-none;
  }
  .card-content {
    @apply p-6;
  }

  .badge-default {
    @apply inline-flex items-center rounded-full border border-transparent bg-primary px-2.5 py-0.5 text-xs font-semibold text-primary-foreground;
  }
  .badge-secondary {
    @apply inline-flex items-center rounded-full border border-transparent bg-muted px-2.5 py-0.5 text-xs font-semibold text-muted-foreground;
  }
  .badge-destructive {
    @apply inline-flex items-center rounded-full border border-transparent bg-destructive px-2.5 py-0.5 text-xs font-semibold text-white;
  }

  .input {
    @apply block w-full rounded-md border border-border bg-background px-3 py-2 text-sm shadow-sm focus:outline-none focus:ring-2 focus:ring-primary;
  }
  .label {
    @apply text-sm font-medium;
  }

  .table-shadcn {
    @apply w-full text-sm;
  }
  .table-shadcn thead tr {
    @apply border-b border-border;
  }
  .table-shadcn th {
    @apply px-3 py-2 text-left font-medium text-muted-foreground;
  }
  .table-shadcn td {
    @apply px-3 py-2 align-middle;
  }
  .table-shadcn tbody tr {
    @apply border-b border-border;
  }
  .table-shadcn tbody tr:hover {
    @apply bg-muted/50;
  }

  .alert-destructive {
    @apply rounded-md border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive;
  }
}
```

- [ ] **Step 3: Créer `web/assets/tailwind.config.js`**

```js
/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./public/Views/**/*.php"],
  // Les classes composants ci-dessous sont définies via @apply dans
  // assets/css/input.css (@layer components). Tailwind ne génère les classes
  // qu'il trouve dans son propre CSS que si leur nom apparaît littéralement
  // dans le contenu scanné ci-dessus — avant les tâches 2-6, aucune vue PHP
  // ne les utilise encore. Le safelist garantit leur présence dès cette tâche.
  safelist: [
    "btn-default",
    "btn-outline",
    "btn-destructive",
    "btn-ghost",
    "btn-sm",
    "card-header",
    "card-title",
    "card-content",
    "badge-default",
    "badge-secondary",
    "badge-destructive",
    "table-shadcn",
    "alert-destructive",
  ],
  theme: {
    extend: {
      colors: {
        background: "rgb(var(--background) / <alpha-value>)",
        foreground: "rgb(var(--foreground) / <alpha-value>)",
        card: "rgb(var(--card) / <alpha-value>)",
        border: "rgb(var(--border) / <alpha-value>)",
        muted: {
          DEFAULT: "rgb(var(--muted) / <alpha-value>)",
          foreground: "rgb(var(--muted-foreground) / <alpha-value>)",
        },
        primary: {
          DEFAULT: "rgb(var(--primary) / <alpha-value>)",
          foreground: "rgb(var(--primary-foreground) / <alpha-value>)",
        },
        destructive: "rgb(var(--destructive) / <alpha-value>)",
      },
      borderRadius: {
        lg: "var(--radius)",
        md: "calc(var(--radius) - 2px)",
      },
      fontFamily: {
        sans: ["Inter", "system-ui", "sans-serif"],
      },
    },
  },
};
```

- [ ] **Step 4: Modifier `web/Dockerfile`**

Remplacer tout le contenu du fichier par :

```dockerfile
FROM debian:bookworm-slim AS cssbuild

RUN apt-get update \
    && apt-get install -y --no-install-recommends ca-certificates curl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sLo /usr/local/bin/tailwindcss \
      https://github.com/tailwindlabs/tailwindcss/releases/download/v3.4.17/tailwindcss-linux-x64 \
    && chmod +x /usr/local/bin/tailwindcss

WORKDIR /build
COPY assets/ assets/
COPY public/Views/ public/Views/
RUN mkdir -p public/css \
    && tailwindcss -c assets/tailwind.config.js -i assets/css/input.css -o public/css/app.css --minify

FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite

# Désactive les connexions persistantes : sous WSL2, le tunnel réseau
# Windows<->WSL2 peut faire retomber une requête sur une connexion keep-alive
# qu'Apache vient de fermer (KeepAliveTimeout par défaut = 5s). Le navigateur
# retente silencieusement les GET mais pas les POST, ce qui fait échouer
# aléatoirement la soumission du formulaire de login.
RUN echo 'KeepAlive Off' > /etc/apache2/conf-enabled/keepalive-off.conf

RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

RUN { \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/session.ini

COPY public/ /var/www/html/
COPY --from=cssbuild /build/public/css/app.css /var/www/html/css/app.css
```

- [ ] **Step 5: Ajouter l'exclusion gitignore**

Dans `.gitignore`, sous la section `# Dépendances & build`, ajouter la ligne :

```
web/public/css/app.css
```

- [ ] **Step 6: Builder l'image et vérifier le CSS généré**

Run: `docker compose build web`
Expected: build réussi, dernière étape `COPY --from=cssbuild ...` sans erreur.

Run: `docker compose up -d && sleep 2 && docker compose exec web grep -c "btn-default" /var/www/html/css/app.css`
Expected: un nombre > 0 (confirme que la classe composant est bien présente dans le CSS compilé).

- [ ] **Step 7: Commit**

```bash
git add web/assets/ web/Dockerfile .gitignore
git commit -m "build(web): ajoute le pipeline Tailwind CLI standalone pour le futur design shadcn"
```

---

### Task 2: Partial de navigation + bascule Bootstrap → Tailwind/Alpine dans les 4 vues

**Files:**
- Create: `web/public/Views/partials/nav.php`
- Modify: `web/public/Views/import.php` (uniquement le `<head>` et le bloc nav — le reste du body est retravaillé en Task 3)
- Modify: `web/public/Views/revue_liste.php` (idem, body retravaillé en Task 4)
- Modify: `web/public/Views/revue.php` (idem, body retravaillé en Task 5)
- Modify: `web/public/Views/admin_utilisateurs.php` (idem, body retravaillé en Task 6)

**Interfaces:**
- Consumes: `.btn-*`, `.card*`, tokens CSS de Task 1 ; variable de session `$_SESSION['role']`.
- Produces: partial `partials/nav.php`, qui lit une variable `$activeNav` (`'import'`, `'revue'`,
  ou `'admin'`) définie par la vue appelante avant l'`include`. N'affiche l'onglet Admin que si
  `$_SESSION['role'] === 'admin'`.

- [ ] **Step 1: Vérifier l'état actuel (Bootstrap encore chargé)**

Run: `grep -l "bootstrap@5.3.0" web/public/Views/import.php web/public/Views/revue_liste.php web/public/Views/revue.php web/public/Views/admin_utilisateurs.php`
Expected: les 4 fichiers listés (Bootstrap encore présent partout).

- [ ] **Step 2: Créer `web/public/Views/partials/nav.php`**

```php
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
                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/<?= $item['icon'] ?>.svg" class="h-4 w-4" alt="">
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="relative" x-data="{ open: false }" @click.outside="open = false">
            <button type="button" @click="open = !open" class="inline-flex items-center gap-2 rounded-md px-3 py-1.5 text-sm font-medium text-muted-foreground hover:bg-muted">
                <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/user.svg" class="h-4 w-4" alt="">
                Compte
            </button>
            <div x-show="open" x-transition x-cloak class="absolute right-0 z-10 mt-2 w-40 overflow-hidden rounded-md border border-border bg-card p-1 shadow-md">
                <form method="POST" action="/logout">
                    <button type="submit" class="flex w-full items-center gap-2 rounded-sm px-2 py-1.5 text-left text-sm hover:bg-muted">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/log-out.svg" class="h-4 w-4" alt="">
                        Déconnexion
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
```

- [ ] **Step 3: Remplacer le `<head>` et le bloc nav de `import.php`**

Remplacer les lignes 1 à 46 (de `<!DOCTYPE html>` jusqu'à la fermeture de la `<nav>` custom, avant
`<!-- Section Import -->`) par :

```php
<?php
/** @var array $historique */
$activeNav = 'import';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | CV Analyser</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-3xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>
```

Le reste du body (section Import, section Historique) est laissé tel quel pour l'instant — il sera
retravaillé en Task 3. Fermer manuellement la `<div class="mx-auto ...">` ouverte ci-dessus avant
la balise `</body>` existante (ajouter `</div>` juste avant `</body>`).

- [ ] **Step 4: Remplacer le `<head>` et le bloc nav de `revue_liste.php`**

Remplacer les lignes 1 à 40 (jusqu'à la fermeture de la `<nav>` custom) par :

```php
<?php
/** @var array $profils */
/** @var string $recherche */
/** @var string $statut */
/** @var array $competences */
/** @var array $toutesLesCompetences */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Liste des profils</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>
```

- [ ] **Step 5: Remplacer le `<head>` et le bloc nav de `admin_utilisateurs.php`**

Remplacer les lignes 1 à 37 (jusqu'à la fermeture de la `<nav>` custom) par :

```php
<?php
/** @var array $utilisateurs */
/** @var string|null $erreur */
$activeNav = 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Administration des comptes</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>
```

- [ ] **Step 6: Remplacer le `<head>` de `revue.php` (pas de bloc nav dans ce fichier — voir Task 5)**

Remplacer les lignes 1 à 18 (jusqu'à `<body>`) par :

```php
<?php
/** @var array|null $profil */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil - Novity</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground">
```

- [ ] **Step 7: Vérifier qu'aucune référence Bootstrap ne subsiste dans les `<head>`**

Run: `grep -rn "bootstrap" web/public/Views/import.php web/public/Views/revue_liste.php web/public/Views/revue.php web/public/Views/admin_utilisateurs.php`
Expected: aucune sortie (le corps de page peut encore contenir des classes Bootstrap orphelines
jusqu'aux tâches suivantes — c'est attendu et sans impact fonctionnel, seulement visuel temporaire).

- [ ] **Step 8: Reconstruire et lancer la suite de tests existante (aucune régression attendue)**

Run: `docker compose up -d --build`
Run: `tests/.venv/bin/pytest tests/web/ -v`
Expected: tous les tests passent (ils vérifient des codes HTTP et redirections, pas le contenu
HTML — inchangés par cette tâche).

- [ ] **Step 9: Commit**

```bash
git add web/public/Views/partials/nav.php web/public/Views/import.php web/public/Views/revue_liste.php web/public/Views/revue.php web/public/Views/admin_utilisateurs.php
git commit -m "feat(web): remplace Bootstrap par Tailwind/Alpine et factorise la nav en partial"
```

---

### Task 3: Restyle du body de `import.php`

**Files:**
- Modify: `web/public/Views/import.php`

**Interfaces:**
- Consumes: `.card`, `.card-header`, `.card-title`, `.card-content`, `.btn-default`,
  `.btn-outline`, `.table-shadcn`, `.badge-default`, `.badge-secondary` (Task 1) ; partial
  `partials/nav.php` déjà inclus (Task 2).

- [ ] **Step 1: Vérifier l'état actuel (encore des classes Bootstrap dans le body)**

Run: `grep -c "btn btn-primary\|class=\"card p-4" web/public/Views/import.php`
Expected: un nombre > 0.

- [ ] **Step 2: Remplacer tout le contenu de `web/public/Views/import.php`**

```php
<?php
/** @var array $historique */
$activeNav = 'import';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | CV Analyser</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-3xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="card mb-6">
            <div class="card-header text-center">
                <h1 class="card-title text-2xl">Novity CV Analyser</h1>
                <p class="text-sm text-muted-foreground">Analyse intelligente de candidatures</p>
            </div>
            <div class="card-content pt-0">
                <form id="upload-form" action="/import/upload" method="POST" enctype="multipart/form-data">
                    <div id="drop-zone-container">
                        <label for="cv_file" class="flex w-full cursor-pointer flex-col items-center rounded-lg border-2 border-dashed border-border bg-background px-6 py-10 text-center transition-colors hover:border-primary hover:bg-muted">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/upload-cloud.svg" class="mb-3 h-8 w-8" alt="">
                            <p class="text-sm">Glissez votre CV ou <b>parcourez</b></p>
                        </label>
                        <input type="file" name="cv_file" id="cv_file" class="hidden" accept=".pdf" onchange="showFile(this)">
                    </div>

                    <div id="file-preview" class="mb-3 mt-3 hidden items-center justify-between rounded-lg border border-border bg-muted p-3">
                        <div class="flex items-center gap-3">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/file-text.svg" class="h-6 w-6" alt="">
                            <div>
                                <div id="file-name" class="text-sm font-medium">...</div>
                                <small id="file-size" class="text-muted-foreground"></small>
                            </div>
                        </div>
                        <button type="button" class="btn-outline btn-sm" onclick="resetFile()">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/x.svg" class="h-4 w-4" alt="">
                        </button>
                    </div>

                    <button type="submit" id="submit-btn" class="btn-default mt-3 hidden w-full">
                        <span id="submit-btn-icon" class="inline-flex items-center gap-2">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/cpu.svg" class="h-4 w-4" alt="">
                            Lancer l'analyse
                        </span>
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="card-title flex items-center gap-2 text-base">
                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/history.svg" class="h-4 w-4" alt="">
                    Historique des analyses
                </h2>
            </div>
            <div class="card-content overflow-x-auto pt-0">
                <table class="table-shadcn">
                    <thead>
                        <tr>
                            <th>Candidat</th>
                            <th>Nom du fichier</th>
                            <th>Date</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($historique)): ?>
                            <?php foreach ($historique as $item): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($item['profile_id'])): ?>
                                            <a href="/revue/<?= $item['profile_id'] ?>" class="font-medium text-primary hover:underline">
                                                <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['candidat_nom'] ?? 'En attente d’analyse') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['profile_id'])): ?>
                                            <a href="/revue/<?= $item['profile_id'] ?>" class="text-primary hover:underline">
                                                <?= htmlspecialchars($item['nom_fichier']) ?>
                                            </a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['nom_fichier'] ?? '—') ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($item['cree_le'])) ?></td>
                                    <td>
                                        <span class="<?= $item['statut'] == 'réussi' ? 'badge-default' : 'badge-secondary' ?>">
                                            <?= htmlspecialchars($item['statut']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="text-center text-muted-foreground">Aucun CV importé.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showFile(input) {
            if (input.files && input.files[0]) {
                document.getElementById('file-name').textContent = input.files[0].name;
                document.getElementById('file-size').textContent = (input.files[0].size / 1024 / 1024).toFixed(2) + ' MB';
                document.getElementById('drop-zone-container').classList.add('hidden');
                document.getElementById('file-preview').classList.remove('hidden');
                document.getElementById('file-preview').classList.add('flex');
                document.getElementById('submit-btn').classList.remove('hidden');
            }
        }
        function resetFile() {
            document.getElementById('cv_file').value = '';
            document.getElementById('drop-zone-container').classList.remove('hidden');
            document.getElementById('file-preview').classList.add('hidden');
            document.getElementById('file-preview').classList.remove('flex');
            document.getElementById('submit-btn').classList.add('hidden');
        }

        document.getElementById('upload-form').addEventListener('submit', function () {
            const btn = document.getElementById('submit-btn');
            btn.disabled = true;
            document.getElementById('submit-btn-icon').innerHTML =
                '<span class="h-4 w-4 animate-spin rounded-full border-2 border-primary-foreground border-t-transparent"></span> Envoi en cours…';
        });
    </script>
</body>
</html>
```

Note : les chemins `/import/upload` et `/revue/<id>` remplacent les anciens chemins absolus
`/cv-analysis/web/public/...` — `web/public/index.php:52` retire déjà ce préfixe s'il est présent,
donc les deux formes fonctionnaient, mais les chemins courts sont ceux utilisés partout ailleurs
(`revue_liste.php`) et sont conservés pour homogénéité.

- [ ] **Step 3: Vérifier qu'aucune classe Bootstrap ne subsiste**

Run: `grep -n "btn btn-\|class=\"card \|d-none\|d-flex" web/public/Views/import.php`
Expected: aucune sortie.

- [ ] **Step 4: Reconstruire et tester manuellement**

Run: `docker compose up -d --build web`

Ouvrir `http://localhost:8080/` dans un navigateur (session valide requise — se connecter via
`/login` au préalable) : vérifier que la carte d'upload, la zone de dépôt, et le tableau
d'historique s'affichent avec le nouveau style (coins arrondis, vert `#16A34A`, icônes Lucide).
Tester l'upload d'un fichier PDF : la zone de dépôt doit se masquer, l'aperçu de fichier
apparaître, le bouton "Lancer l'analyse" se révéler.

- [ ] **Step 5: Lancer la suite de tests existante**

Run: `tests/.venv/bin/pytest tests/web/ -v`
Expected: tous les tests passent.

- [ ] **Step 6: Commit**

```bash
git add web/public/Views/import.php
git commit -m "style(web): restyle import.php en shadcn (cards, table, badges)"
```

---

### Task 4: Restyle du body de `revue_liste.php`

**Files:**
- Modify: `web/public/Views/revue_liste.php`

**Interfaces:**
- Consumes: `.card`, `.card-content`, `.input`, `.label`, `.btn-default`, `.btn-outline`,
  `.badge-default`, `.badge-secondary` (Task 1) ; partial `partials/nav.php` déjà inclus
  (Task 2).

- [ ] **Step 1: Vérifier l'état actuel**

Run: `grep -c "form-control\|form-select\|form-check" web/public/Views/revue_liste.php`
Expected: un nombre > 0.

- [ ] **Step 2: Remplacer tout le contenu de `web/public/Views/revue_liste.php`**

```php
<?php
/** @var array $profils */
/** @var string $recherche */
/** @var string $statut */
/** @var array $competences */
/** @var array $toutesLesCompetences */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Liste des profils</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="card mb-6">
            <div class="card-content">
                <h1 class="mb-4 text-xl font-bold">Liste des profils</h1>

                <form method="GET" action="/revue" class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-5">
                        <label class="label mb-1 block">Recherche</label>
                        <input type="text" name="q" class="input" placeholder="Nom, titre, description, formation, secteur…"
                               value="<?= htmlspecialchars($recherche) ?>">
                    </div>
                    <div class="md:col-span-3">
                        <label class="label mb-1 block">Statut</label>
                        <select name="statut" class="input">
                            <option value="">Tous</option>
                            <option value="à valider" <?= $statut === 'à valider' ? 'selected' : '' ?>>À valider</option>
                            <option value="validé" <?= $statut === 'validé' ? 'selected' : '' ?>>Validé</option>
                        </select>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Compétences</label>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-border p-2">
                            <?php if (empty($toutesLesCompetences)): ?>
                                <span class="text-sm text-muted-foreground">Aucune compétence en base.</span>
                            <?php endif; ?>
                            <?php foreach ($toutesLesCompetences as $competence): ?>
                                <label class="flex items-center gap-2 py-0.5 text-sm" for="comp-<?= htmlspecialchars($competence) ?>">
                                    <input type="checkbox" name="competences[]" id="comp-<?= htmlspecialchars($competence) ?>"
                                           value="<?= htmlspecialchars($competence) ?>"
                                           class="h-4 w-4 rounded border-border text-primary focus:ring-primary"
                                           <?= in_array($competence, $competences, true) ? 'checked' : '' ?>>
                                    <?= htmlspecialchars($competence) ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="flex gap-2 md:col-span-12">
                        <button type="submit" class="btn-default">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/search.svg" class="h-4 w-4" alt="">
                            Filtrer
                        </button>
                        <a href="/revue" class="btn-outline">Réinitialiser</a>
                    </div>
                </form>
            </div>
        </div>

        <?php if (empty($profils)): ?>
            <div class="card p-6 text-center text-muted-foreground">
                Aucun profil ne correspond à ces critères.
            </div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
                <?php foreach ($profils as $profil): ?>
                    <a href="/revue/<?= $profil['id'] ?>" class="card block p-4 transition-shadow hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                            </div>
                            <span class="<?= $profil['statut'] === 'validé' ? 'badge-default' : 'badge-secondary' ?>">
                                <?= htmlspecialchars($profil['statut']) ?>
                            </span>
                        </div>
                        <?php if (!empty($profil['description'])): ?>
                            <p class="mb-2 mt-2 text-sm text-muted-foreground"><?= htmlspecialchars($profil['description']) ?></p>
                        <?php endif; ?>
                        <?php
                            $competencesProfil = [];
                            foreach ($profil['experiences'] as $exp) {
                                foreach ($exp['competences'] as $comp) {
                                    $competencesProfil[$comp] = true;
                                }
                            }
                        ?>
                        <?php if (!empty($competencesProfil)): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_keys($competencesProfil) as $comp): ?>
                                    <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
```

- [ ] **Step 3: Vérifier qu'aucune classe Bootstrap ne subsiste**

Run: `grep -n "form-control\|form-select\|form-check\|btn btn-\|class=\"card " web/public/Views/revue_liste.php`
Expected: aucune sortie.

- [ ] **Step 4: Reconstruire et tester manuellement**

Run: `docker compose up -d --build web`

Ouvrir `http://localhost:8080/revue` : vérifier le rendu des filtres (recherche, statut,
compétences), soumettre une recherche, vérifier que les cards de profils s'affichent avec badges
verts (validé) ou gris (à valider).

- [ ] **Step 5: Lancer la suite de tests existante**

Run: `tests/.venv/bin/pytest tests/web/ -v`
Expected: tous les tests passent.

- [ ] **Step 6: Commit**

```bash
git add web/public/Views/revue_liste.php
git commit -m "style(web): restyle revue_liste.php en shadcn (filtres, cards)"
```

---

### Task 5: Restyle de `revue.php` (layout 2 colonnes + collapsibles Alpine)

**Files:**
- Modify: `web/public/Views/revue.php`

**Interfaces:**
- Consumes: `.card`, `.input`, `.label`, `.btn-default`, `.btn-outline`, `.badge-secondary`
  (Task 1) ; partial `partials/nav.php` (Task 2) — remplace l'ancien bloc manuel
  Retour/Déconnexion.
- Produces: motif Alpine réutilisable pour les collapsibles — `x-data="{ addOpen: false }"` sur
  le conteneur de section (Expériences/Formations) et `x-data="{ editOpen: false }"` sur chaque
  carte d'item, avec `x-show`/`x-cloak` sur le formulaire associé.

- [ ] **Step 1: Vérifier l'état actuel (Bootstrap collapse encore utilisé)**

Run: `grep -c "data-bs-toggle" web/public/Views/revue.php`
Expected: un nombre > 0.

- [ ] **Step 2: Remplacer tout le contenu de `web/public/Views/revue.php`**

```php
<?php
/** @var array|null $profil */
$activeNav = 'revue';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil - Novity</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground">

<div class="flex h-screen gap-4 p-4">
    <div class="flex-[2] overflow-hidden rounded-lg border border-border bg-muted">
        <?php if (!empty($profil['pdf_source'])): ?>
            <iframe src="/pdfs/<?= (int) $profil['id'] ?>" width="100%" height="100%" class="border-0"></iframe>
        <?php else: ?>
            <div class="flex h-full items-center justify-center text-muted-foreground">Aucun PDF disponible</div>
        <?php endif; ?>
    </div>

    <div class="card flex-1 overflow-y-auto p-6">
        <?php include __DIR__ . '/partials/nav.php'; ?>
        <h1 class="mb-4 text-xl font-bold">Validation du profil</h1>

        <?php if (isset($profil)): ?>
            <form action="/profil/update" method="POST" class="space-y-3">
                <input type="hidden" name="id" value="<?= htmlspecialchars($profil['id'] ?? '') ?>">

                <div>
                    <label class="label mb-1 block">Nom complet</label>
                    <input type="text" name="nom" class="input" value="<?= htmlspecialchars($profil['nom'] ?? '') ?>">
                </div>

                <div>
                    <label class="label mb-1 block">Titre</label>
                    <input type="text" name="titre" class="input" value="<?= htmlspecialchars($profil['titre'] ?? '') ?>">
                </div>

                <div>
                    <label class="label mb-1 block">Email</label>
                    <input type="text" class="input bg-muted" value="<?= htmlspecialchars($profil['email'] ?? '—') ?>" readonly>
                </div>

                <div>
                    <label class="label mb-1 block">LinkedIn</label>
                    <input type="text" class="input bg-muted" value="<?= htmlspecialchars($profil['linkedin'] ?? '—') ?>" readonly>
                </div>

                <div>
                    <label class="label mb-1 block">Description</label>
                    <textarea class="input bg-muted" rows="3" readonly><?= htmlspecialchars($profil['description'] ?? '—') ?></textarea>
                </div>

                <div>
                    <label class="label mb-1 block">Statut</label>
                    <select name="statut" class="input">
                        <option value="à valider" <?= ($profil['statut'] ?? '') == 'à valider' ? 'selected' : '' ?>>À valider</option>
                        <option value="validé" <?= ($profil['statut'] ?? '') == 'validé' ? 'selected' : '' ?>>Validé</option>
                    </select>
                </div>

                <button type="submit" class="btn-default w-full">Enregistrer et Valider</button>
            </form>

            <!-- ===================== EXPERIENCES ===================== -->
            <div class="mt-6" x-data="{ addOpen: false }">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/briefcase.svg" class="h-4 w-4" alt="">
                        Expériences
                    </h2>
                    <button class="btn-outline btn-sm" type="button" @click="addOpen = !addOpen">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/plus.svg" class="h-4 w-4" alt="">
                        Ajouter
                    </button>
                </div>

                <div x-show="addOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <form action="/experience/add" method="POST" class="space-y-2">
                        <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                        <div>
                            <label class="label mb-1 block text-xs">Poste</label>
                            <input type="text" name="poste" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Entreprise</label>
                            <input type="text" name="entreprise" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Secteur</label>
                            <input type="text" name="secteur" class="input">
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="label mb-1 block text-xs">Début</label>
                                <input type="text" name="date_debut" class="input" placeholder="ex. 2019 ou 2019-03">
                            </div>
                            <div>
                                <label class="label mb-1 block text-xs">Fin</label>
                                <input type="text" name="date_fin" class="input" placeholder="ex. 2022 ou en cours">
                            </div>
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Description</label>
                            <textarea name="description" class="input" rows="2"></textarea>
                        </div>
                        <button type="submit" class="btn-default btn-sm w-full">Ajouter l'expérience</button>
                    </form>
                </div>
            </div>

            <div class="mt-3 space-y-3">
                <?php if (!empty($profil['experiences'])): ?>
                    <?php foreach ($profil['experiences'] as $exp): ?>
                        <div class="relative rounded-md border border-border bg-muted p-3" x-data="{ editOpen: false }">
                            <div class="absolute right-2 top-2 flex gap-1">
                                <button class="btn-outline btn-sm" type="button" @click="editOpen = !editOpen">
                                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/pencil.svg" class="h-4 w-4" alt="">
                                </button>
                                <form action="/experience/delete/<?= $exp['id'] ?>" method="POST" onsubmit="return confirm('Supprimer cette expérience ?');">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <button class="btn-outline btn-sm" type="submit">
                                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/trash-2.svg" class="h-4 w-4" alt="">
                                    </button>
                                </form>
                            </div>

                            <div class="font-semibold"><?= htmlspecialchars($exp['poste'] ?? '') ?></div>
                            <div class="text-sm text-muted-foreground">
                                <?= htmlspecialchars($exp['entreprise'] ?? '') ?>
                                <?php if (!empty($exp['secteur'])): ?> — <?= htmlspecialchars($exp['secteur']) ?><?php endif; ?>
                            </div>
                            <div class="mb-2 text-sm text-muted-foreground">
                                <?= $exp['date_debut'] ? htmlspecialchars($exp['date_debut']) : '?' ?>
                                —
                                <?= $exp['date_fin'] ? htmlspecialchars($exp['date_fin']) : 'Présent' ?>
                            </div>
                            <?php if (!empty($exp['description'])): ?>
                                <p class="mb-2 text-sm"><?= nl2br(htmlspecialchars($exp['description'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($exp['competences'])): ?>
                                <div class="flex flex-wrap gap-1">
                                    <?php foreach ($exp['competences'] as $comp): ?>
                                        <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <div x-show="editOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-background p-4">
                                <form action="/experience/update/<?= $exp['id'] ?>" method="POST" class="space-y-2">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <div>
                                        <label class="label mb-1 block text-xs">Poste</label>
                                        <input type="text" name="poste" class="input" value="<?= htmlspecialchars($exp['poste'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Entreprise</label>
                                        <input type="text" name="entreprise" class="input" value="<?= htmlspecialchars($exp['entreprise'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Secteur</label>
                                        <input type="text" name="secteur" class="input" value="<?= htmlspecialchars($exp['secteur'] ?? '') ?>">
                                    </div>
                                    <div class="grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="label mb-1 block text-xs">Début</label>
                                            <input type="text" name="date_debut" class="input" placeholder="ex. 2019 ou 2019-03" value="<?= htmlspecialchars($exp['date_debut'] ?? '') ?>">
                                        </div>
                                        <div>
                                            <label class="label mb-1 block text-xs">Fin</label>
                                            <input type="text" name="date_fin" class="input" placeholder="ex. 2022 ou en cours" value="<?= htmlspecialchars($exp['date_fin'] ?? '') ?>">
                                        </div>
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Description</label>
                                        <textarea name="description" class="input" rows="2"><?= htmlspecialchars($exp['description'] ?? '') ?></textarea>
                                    </div>
                                    <button type="submit" class="btn-default btn-sm w-full">Enregistrer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted-foreground">Aucune expérience renseignée.</p>
                <?php endif; ?>
            </div>

            <!-- ===================== FORMATIONS ===================== -->
            <div class="mt-6" x-data="{ addOpen: false }">
                <div class="flex items-center justify-between">
                    <h2 class="flex items-center gap-2 text-sm font-semibold text-primary">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/graduation-cap.svg" class="h-4 w-4" alt="">
                        Formations
                    </h2>
                    <button class="btn-outline btn-sm" type="button" @click="addOpen = !addOpen">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/plus.svg" class="h-4 w-4" alt="">
                        Ajouter
                    </button>
                </div>

                <div x-show="addOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <form action="/formation/add" method="POST" class="space-y-2">
                        <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                        <div>
                            <label class="label mb-1 block text-xs">Intitulé</label>
                            <input type="text" name="intitule" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Établissement</label>
                            <input type="text" name="etablissement" class="input">
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Type</label>
                            <select name="type" class="input">
                                <option value="">—</option>
                                <option value="diplôme">Diplôme</option>
                                <option value="certification">Certification</option>
                            </select>
                        </div>
                        <div>
                            <label class="label mb-1 block text-xs">Année</label>
                            <input type="number" name="annee" class="input">
                        </div>
                        <button type="submit" class="btn-default btn-sm w-full">Ajouter la formation</button>
                    </form>
                </div>
            </div>

            <div class="mt-3 space-y-3">
                <?php if (!empty($profil['formations'])): ?>
                    <?php foreach ($profil['formations'] as $form): ?>
                        <div class="relative rounded-md border border-border bg-muted p-3" x-data="{ editOpen: false }">
                            <div class="absolute right-2 top-2 flex gap-1">
                                <button class="btn-outline btn-sm" type="button" @click="editOpen = !editOpen">
                                    <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/pencil.svg" class="h-4 w-4" alt="">
                                </button>
                                <form action="/formation/delete/<?= $form['id'] ?>" method="POST" onsubmit="return confirm('Supprimer cette formation ?');">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <button class="btn-outline btn-sm" type="submit">
                                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@latest/icons/trash-2.svg" class="h-4 w-4" alt="">
                                    </button>
                                </form>
                            </div>

                            <div class="font-semibold"><?= htmlspecialchars($form['intitule'] ?? '') ?></div>
                            <div class="text-sm text-muted-foreground">
                                <?= htmlspecialchars($form['etablissement'] ?? '') ?>
                                <?php if (!empty($form['type'])): ?> — <?= htmlspecialchars($form['type']) ?><?php endif; ?>
                                <?php if (!empty($form['annee'])): ?> (<?= htmlspecialchars($form['annee']) ?>)<?php endif; ?>
                            </div>

                            <div x-show="editOpen" x-cloak class="mt-3 rounded-md border border-dashed border-border bg-background p-4">
                                <form action="/formation/update/<?= $form['id'] ?>" method="POST" class="space-y-2">
                                    <input type="hidden" name="profil_id" value="<?= $profil['id'] ?>">
                                    <div>
                                        <label class="label mb-1 block text-xs">Intitulé</label>
                                        <input type="text" name="intitule" class="input" value="<?= htmlspecialchars($form['intitule'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Établissement</label>
                                        <input type="text" name="etablissement" class="input" value="<?= htmlspecialchars($form['etablissement'] ?? '') ?>">
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Type</label>
                                        <select name="type" class="input">
                                            <option value="">—</option>
                                            <option value="diplôme" <?= ($form['type'] ?? '') == 'diplôme' ? 'selected' : '' ?>>Diplôme</option>
                                            <option value="certification" <?= ($form['type'] ?? '') == 'certification' ? 'selected' : '' ?>>Certification</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="label mb-1 block text-xs">Année</label>
                                        <input type="number" name="annee" class="input" value="<?= htmlspecialchars($form['annee'] ?? '') ?>">
                                    </div>
                                    <button type="submit" class="btn-default btn-sm w-full">Enregistrer</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-muted-foreground">Aucune formation renseignée.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p class="text-destructive">Profil introuvable.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
```

Note : le bloc manuel "Retour" + "Déconnexion" de l'original est remplacé par le partial
`nav.php` (onglet "Revue" actif = équivalent fonctionnel du bouton Retour, dropdown compte =
équivalent du bouton Déconnexion), conformément à la spec.

- [ ] **Step 3: Vérifier qu'aucune classe Bootstrap ni `data-bs-toggle` ne subsiste**

Run: `grep -n "data-bs-toggle\|class=\"form-control\|btn btn-\|collapse" web/public/Views/revue.php`
Expected: aucune sortie.

- [ ] **Step 4: Reconstruire et tester manuellement**

Run: `docker compose up -d --build web`

Ouvrir `http://localhost:8080/revue/<id>` pour un profil existant : vérifier le layout 2 colonnes
(PDF à gauche, formulaire à droite), cliquer sur "Ajouter" (expériences) → le formulaire doit
apparaître/disparaître (Alpine `x-show`), cliquer sur l'icône crayon d'une expérience existante →
le formulaire d'édition doit se déplier indépendamment des autres cartes. Tester la suppression
(confirmation JS toujours présente).

- [ ] **Step 5: Lancer la suite de tests existante**

Run: `tests/.venv/bin/pytest tests/web/ -v`
Expected: tous les tests passent (aucune route touchée).

- [ ] **Step 6: Commit**

```bash
git add web/public/Views/revue.php
git commit -m "style(web): restyle revue.php en shadcn avec collapsibles Alpine"
```

---

### Task 6: Restyle du body de `admin_utilisateurs.php`

**Files:**
- Modify: `web/public/Views/admin_utilisateurs.php`

**Interfaces:**
- Consumes: `.card`, `.card-content`, `.input`, `.label`, `.btn-default`, `.btn-outline`,
  `.badge-default`, `.badge-secondary`, `.alert-destructive`, `.table-shadcn` (Task 1) ; partial
  `partials/nav.php` déjà inclus (Task 2).

- [ ] **Step 1: Vérifier l'état actuel**

Run: `grep -c "form-control\|form-select\|class=\"alert " web/public/Views/admin_utilisateurs.php`
Expected: un nombre > 0.

- [ ] **Step 2: Remplacer tout le contenu de `web/public/Views/admin_utilisateurs.php`**

```php
<?php
/** @var array $utilisateurs */
/** @var string|null $erreur */
$activeNav = 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Administration des comptes</title>
    <link rel="stylesheet" href="/css/app.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="card mb-6">
            <div class="card-content">
                <h1 class="mb-4 text-xl font-bold">Créer un compte</h1>

                <?php if (!empty($erreur)): ?>
                    <div class="alert-destructive mb-4"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="/admin/utilisateurs/create" class="grid gap-4 md:grid-cols-12">
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Email</label>
                        <input type="email" name="email" class="input" required>
                    </div>
                    <div class="md:col-span-4">
                        <label class="label mb-1 block">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="input" required>
                    </div>
                    <div class="md:col-span-2">
                        <label class="label mb-1 block">Rôle</label>
                        <select name="role" class="input">
                            <option value="utilisateur">Utilisateur</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="flex items-end md:col-span-2">
                        <button type="submit" class="btn-default w-full">Créer</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-content overflow-x-auto">
                <h2 class="mb-3 text-base font-semibold">Comptes existants</h2>
                <table class="table-shadcn">
                    <thead>
                        <tr>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($utilisateurs as $u): ?>
                            <tr>
                                <td><?= htmlspecialchars($u['email']) ?></td>
                                <td><?= htmlspecialchars($u['role']) ?></td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <span class="badge-default">Actif</span>
                                    <?php else: ?>
                                        <span class="badge-secondary">Désactivé</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($u['actif']): ?>
                                        <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/desactiver"
                                              onsubmit="return confirm('Désactiver ce compte ?');">
                                            <button type="submit" class="btn-outline btn-sm">Désactiver</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 3: Vérifier qu'aucune classe Bootstrap ne subsiste**

Run: `grep -n "form-control\|form-select\|class=\"alert \|btn btn-" web/public/Views/admin_utilisateurs.php`
Expected: aucune sortie.

- [ ] **Step 4: Reconstruire et tester manuellement**

Run: `docker compose up -d --build web`

Se connecter avec un compte admin, ouvrir `http://localhost:8080/admin/utilisateurs` : créer un
compte de test, vérifier son apparition dans le tableau avec le badge "Actif" vert, le désactiver,
vérifier le badge "Désactivé" gris et la disparition du bouton "Désactiver".

- [ ] **Step 5: Lancer la suite de tests existante**

Run: `tests/.venv/bin/pytest tests/web/ -v`
Expected: tous les tests passent, y compris `test_admin_can_create_and_deactivate_user`.

- [ ] **Step 6: Commit**

```bash
git add web/public/Views/admin_utilisateurs.php
git commit -m "style(web): restyle admin_utilisateurs.php en shadcn (form, table)"
```

---

### Task 7: Vérification finale de non-régression

**Files:** aucun fichier modifié — tâche de vérification uniquement.

**Interfaces:**
- Consumes : l'ensemble des tâches précédentes.

- [ ] **Step 1: Rebuild complet de la stack**

Run: `docker compose down && docker compose up -d --build`
Expected: tous les services (`db`, `migrate`, `web`, `import`) démarrent sans erreur.

- [ ] **Step 2: Lancer toute la suite de tests web et import**

Run: `tests/.venv/bin/pytest tests/web/ tests/import/ -v`
Expected: tous les tests passent (aucune route, aucun Controller, aucun Model n'a été modifié
dans ce plan — seul le rendu des vues a changé).

- [ ] **Step 3: Checklist de vérification manuelle dans le navigateur**

Se connecter sur `http://localhost:8080/login`, puis pour chaque page suivante, vérifier
visuellement l'absence de classes Bootstrap résiduelles (pas de coins très arrondis façon
Bootstrap, icônes Lucide au lieu de Bootstrap Icons, accent vert `#16A34A`) et l'absence d'erreur
dans la console navigateur (F12) liée à Bootstrap manquant :

- [ ] `/` (import) : upload d'un CV, historique affiché
- [ ] `/revue` : filtres, liste de profils
- [ ] `/revue/<id>` : édition d'un profil, ajout/édition/suppression d'une expérience et d'une
  formation (collapsibles Alpine)
- [ ] `/admin/utilisateurs` (avec un compte admin) : création et désactivation d'un compte
- [ ] Sur chacune des 4 pages : dropdown "Compte" (Alpine) → bouton "Déconnexion" fonctionnel

- [ ] **Step 4: Nettoyage des comptes de test créés pendant la vérification manuelle**

Si des comptes de test ont été créés à l'étape précédente, les désactiver depuis
`/admin/utilisateurs` (pas de suppression physique disponible, cohérent avec la conception
existante).

Aucun commit pour cette tâche (vérification uniquement, pas de changement de fichier).
