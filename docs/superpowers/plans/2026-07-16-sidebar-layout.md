# Layout sidebar verticale façon AdminLTE Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remplacer la topbar horizontale des 4 pages post-login par un menu vertical fixe à gauche (façon AdminLTE), en noir Novity avec accents verts, contenu pleine largeur, et un tiroir mobile avec bouton hamburger.

**Architecture:** Renommage/refonte du partial `partials/nav.php` → `partials/sidebar.php` (aside fixe + tiroir mobile Alpine.js), et changement du wrapper `<body>` des 4 vues (`<div class="mx-auto max-w-Nxl">` → `<main class="lg:pl-64">`). Extension des tokens CSS existants (`web/assets/css/input.css`) avec une palette `sidebar`/`sidebar-foreground`/`sidebar-border`. Aucun changement de Controller/Model/route/BDD.

**Tech Stack:** Tailwind CSS (CLI standalone, compilé au build Docker), Alpine.js (déjà chargé via CDN dans les 4 vues), PHP 8.3.

## Global Constraints

- Spec de référence : `docs/superpowers/specs/2026-07-16-sidebar-layout-design.md`.
- Position du menu : **à gauche**, fixe, largeur `w-64` (16rem) sur desktop (`lg:` et plus).
- Couleurs (à ajouter dans `input.css`, valeurs exactes) : `--sidebar: 10 10 10` (#0a0a0a), `--sidebar-foreground: 212 212 216` (#d4d4d8), `--sidebar-border: 39 39 42` (#27272a). L'item actif réutilise `--primary`/`--primary-foreground` existants (vert Novity, pas de nouveau token).
- Contenu : **pleine largeur** partout — aucun `max-w-*` sur le conteneur de contenu (seuls des éléments internes, ex. formulaires courts, peuvent naturellement rester compacts).
- Mobile : menu caché par défaut sous le breakpoint `lg` (1024px), bouton hamburger fixe en haut à gauche du contenu, tiroir en overlay avec fond semi-transparent au clic pour fermer.
- Logo : `web/public/images/novity-logo-noir.svg` (fill noir) est réutilisé tel quel, inversé en blanc via les classes Tailwind `brightness-0 invert` — pas de nouvel asset. Toutes les icônes `<img src=".../icons/*.svg">` affichées à l'intérieur du sidebar sombre doivent recevoir le même traitement (`brightness-0 invert`, éventuellement `opacity-60` pour les items inactifs) — un `<img>` chargé comme image ne peut pas être recoloré par `text-*`/`currentColor`, c'est le seul mécanisme qui fonctionne ici.
- Hors scope : `login.php`, `import_status.php` (non touchés), aucun sous-menu, aucun repli "icônes seules" du sidebar sur desktop.
- **Pas de nouveaux tests automatisés requis pour ce plan.** C'est un changement de markup/CSS pur, sans nouvelle logique métier — la suite `tests/web/` existante (comportement, auth, statuts) sert de garde-fou de non-régression et doit continuer à passer intégralement après chaque tâche. La vérification structurelle (présence du sidebar, absence de l'ancien markup) se fait via rebuild Docker + `curl` authentifié, pas via de nouveaux tests pytest — écrire des assertions pytest fragiles sur des classes CSS exactes n'apporterait pas de valeur de régression réelle pour un changement purement visuel.
- Le CSS n'est compilé qu'au build de l'image Docker (`docker compose up -d --build web`) — chaque tâche impliquant du CSS ou une vue PHP nécessite un rebuild avant vérification.

---

### Task 1: Tokens CSS sidebar (fondation)

**Files:**
- Modify: `web/assets/css/input.css:5-25` (bloc `:root`)
- Modify: `web/assets/tailwind.config.js:33-70` (`theme.extend.colors`)

**Interfaces:**
- Produces : couleurs Tailwind `sidebar` (`DEFAULT`/`foreground`) et `sidebar-border`, consommées par Task 2.

- [ ] **Step 1: Ajouter les tokens dans `:root`**

Dans `web/assets/css/input.css`, remplacer :
```css
  --info: 37 99 235; /* #2563eb */
  --info-foreground: 255 255 255;
  --radius: 0.5rem;
}
```
par :
```css
  --info: 37 99 235; /* #2563eb */
  --info-foreground: 255 255 255;
  --sidebar: 10 10 10; /* #0a0a0a — noir Novity, même valeur que --foreground */
  --sidebar-foreground: 212 212 216; /* #d4d4d8 */
  --sidebar-border: 39 39 42; /* #27272a */
  --radius: 0.5rem;
}
```

- [ ] **Step 2: Wiring dans `tailwind.config.js`**

Dans `web/assets/tailwind.config.js`, remplacer :
```js
        info: {
          DEFAULT: "rgb(var(--info) / <alpha-value>)",
          foreground: "rgb(var(--info-foreground) / <alpha-value>)",
        },
      },
```
par :
```js
        info: {
          DEFAULT: "rgb(var(--info) / <alpha-value>)",
          foreground: "rgb(var(--info-foreground) / <alpha-value>)",
        },
        sidebar: {
          DEFAULT: "rgb(var(--sidebar) / <alpha-value>)",
          foreground: "rgb(var(--sidebar-foreground) / <alpha-value>)",
        },
        "sidebar-border": "rgb(var(--sidebar-border) / <alpha-value>)",
      },
```

Ne pas ajouter d'entrée au `safelist` — `bg-sidebar`/`text-sidebar-foreground`/`border-sidebar-border`
sont des utilitaires Tailwind classiques, pas des classes `@apply` de `@layer components` ; ils
seront générés automatiquement une fois utilisés littéralement dans `sidebar.php` (Task 2), inutile
de les forcer ici.

- [ ] **Step 3: Rebuild et vérifier que les variables sont compilées**

Run:
```bash
docker compose up -d --build web
docker compose exec web grep -o ':root{[^}]*}' /var/www/html/css/app.css
```
Expected: la sortie contient `--sidebar:10 10 10;--sidebar-foreground:212 212 216;--sidebar-border:39 39 42` (dans le bloc `:root`, aux côtés des variables existantes).

- [ ] **Step 4: Commit**

```bash
git add web/assets/css/input.css web/assets/tailwind.config.js
git commit -m "feat(web): ajoute les tokens de couleur du sidebar (noir Novity)"
```

---

### Task 2: Créer `partials/sidebar.php`

**Files:**
- Create: `web/public/Views/partials/sidebar.php`

**Interfaces:**
- Consumes : couleurs `sidebar`/`sidebar-foreground`/`sidebar-border` (Task 1), `$_SESSION['role']`, variable `$activeNav` définie par la vue appelante avant l'include (déjà le contrat existant de l'ancien `nav.php`).
- Produces : partial incluable via `<?php include __DIR__ . '/partials/sidebar.php'; ?>`, qui attend que son appelant ait posé `x-data="{ sidebarOpen: false }"` sur un ancêtre (le `<body>` de la vue) — consommé par Tasks 3-6.

- [ ] **Step 1: Créer le fichier**

Create `web/public/Views/partials/sidebar.php` :

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
<button type="button" x-show="!sidebarOpen" @click="sidebarOpen = true"
        class="fixed left-4 top-4 z-30 inline-flex items-center justify-center rounded-md border border-border bg-card p-2 shadow-sm lg:hidden">
    <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/menu.svg" class="h-5 w-5" alt="Ouvrir le menu">
</button>

<div x-show="sidebarOpen" x-transition.opacity @click="sidebarOpen = false"
     class="fixed inset-0 z-40 bg-black/50 lg:hidden"></div>

<aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
       class="fixed inset-y-0 left-0 z-50 flex w-64 flex-col bg-sidebar text-sidebar-foreground transition-transform duration-200 lg:translate-x-0">
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

    <div class="border-t border-sidebar-border px-3 py-4">
        <div class="mb-2 px-3 text-xs uppercase tracking-wide text-sidebar-foreground/60">
            <?= $role === 'admin' ? 'Admin' : 'Utilisateur' ?>
        </div>
        <form method="POST" action="/logout">
            <button type="submit"
                    class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm font-medium hover:bg-white/10">
                <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/log-out.svg" class="h-4 w-4 brightness-0 invert opacity-60" alt="">
                Déconnexion
            </button>
        </form>
    </div>
</aside>
```

Notes d'implémentation (à ne pas re-discuter, déjà tranchées) :
- Le bouton hamburger et l'overlay n'ont **pas** besoin de fermer explicitement le tiroir au clic
  sur un lien de nav (`@click="sidebarOpen = false"` sur les `<a>`) : ce n'est pas une SPA, chaque
  clic sur un lien de nav recharge la page entière, ce qui réinitialise `sidebarOpen` à `false` via
  le `x-data` du `<body>` de la page suivante. Ajouter ce handler serait du code mort.
- `:class` (Alpine, bascule `translate-x-0`/`-translate-x-full`) et la classe statique
  `lg:translate-x-0` ne se marchent pas dessus : Tailwind émet les variantes responsives
  (`lg:*`) après les utilitaires de base dans la feuille de style compilée, donc `lg:translate-x-0`
  gagne toujours à partir du breakpoint `lg`, quelle que soit la classe non-préfixée posée par
  Alpine à un instant donné.
- Léger flash possible sur mobile avant l'hydratation d'Alpine (le tiroir peut apparaître ouvert
  une fraction de seconde avant que `-translate-x-full` par défaut ne s'applique) : accepté comme
  compromis mineur pour un outil interne, cohérent avec l'absence de mécanisme SSR/hydratation dans
  ce projet.

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run:
```bash
php -l web/public/Views/partials/sidebar.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Rebuild et vérifier que les nouvelles classes Tailwind sont générées**

Run:
```bash
docker compose up -d --build web
docker compose exec web sh -c "grep -c '\.bg-sidebar{\|\.text-sidebar-foreground{\|\.border-sidebar-border{\|\.translate-x-0{\|\.-translate-x-full' /var/www/html/css/app.css"
```
Expected: un nombre `> 0` pour chaque motif (les utilitaires apparaissent maintenant dans le CSS
compilé car `sidebar.php` les référence littéralement, même si aucune vue ne l'inclut encore).

- [ ] **Step 4: Commit**

```bash
git add web/public/Views/partials/sidebar.php
git commit -m "feat(web): crée le partial sidebar verticale (non encore utilisé par les vues)"
```

---

### Task 3: Migrer `import.php` vers le sidebar

**Files:**
- Modify: `web/public/Views/import.php:14-16` (ouverture body/wrapper) et `:113-115` (fermeture)

**Interfaces:**
- Consumes : `partials/sidebar.php` (Task 2), qui exige `x-data="{ sidebarOpen: false }"` sur `<body>`.

- [ ] **Step 1: Modifier l'ouverture**

Dans `web/public/Views/import.php`, remplacer :
```php
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-3xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>

        <div class="card mb-6">
```
par :
```php
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-64 lg:pt-10">
        <div class="card mb-6">
```
(`pt-16` sur mobile laisse la place au bouton hamburger fixe en haut à gauche ; `lg:pt-10` restaure
le padding d'origine à partir du breakpoint où le hamburger disparaît.)

- [ ] **Step 2: Modifier la fermeture**

Remplacer :
```php
        </div>
    </div>

    <script>
```
par :
```php
        </div>
    </main>

    <script>
```

- [ ] **Step 3: Rebuild, vérifier structurellement et faire tourner la suite existante**

Le dépôt contient déjà `scripts/seed-admin.sql`, qui crée/met à jour un compte admin connu
(`admin@novity.io` / `changeme123`) — l'utiliser pour l'authentification manuelle plutôt que de
recréer un mécanisme séparé.

Run:
```bash
docker compose up -d --build web
docker compose exec -T db psql -U cv -d cv_analysis -f - < scripts/seed-admin.sql
COOKIE=$(mktemp)
curl -s -c "$COOKIE" -b "$COOKIE" -X POST http://localhost:8080/login \
  -d "email=admin@novity.io&mot_de_passe=changeme123" -o /dev/null
curl -s -b "$COOKIE" http://localhost:8080/ | grep -o '<aside\|bg-sidebar\|mx-auto max-w-3xl' | sort | uniq -c
tests/.venv/bin/pytest tests/web/ -v
```
Expected : le grep montre `<aside` présent et `mx-auto max-w-3xl` **absent** (l'ancien wrapper a
bien disparu) ; les 18 tests de `tests/web/` passent toujours (aucune route/comportement modifié).

- [ ] **Step 4: Commit**

```bash
git add web/public/Views/import.php
git commit -m "feat(web): migre import.php vers le layout sidebar"
```

---

### Task 4: Migrer `revue_liste.php` vers le sidebar

**Files:**
- Modify: `web/public/Views/revue_liste.php:18-20` (ouverture) et `:107-108` (fermeture)

**Interfaces:**
- Consumes : `partials/sidebar.php` (Task 2), même contrat que Task 3.

- [ ] **Step 1: Modifier l'ouverture**

Remplacer :
```php
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>
```
par :
```php
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-64 lg:pt-10">
```

- [ ] **Step 2: Modifier la fermeture**

Remplacer :
```php
    </div>
</body>
</html>
```
par :
```php
    </main>
</body>
</html>
```

- [ ] **Step 3: Rebuild, vérifier structurellement et faire tourner la suite existante**

Réutiliser le même compte de test que Task 3 (`admin@novity.io` / `changeme123`, via
`scripts/seed-admin.sql`) et la même variable `$COOKIE` (relancer le login POST si la session a
expiré ou si `$COOKIE` ne provient pas du même shell) :

```bash
docker compose up -d --build web
curl -s -b "$COOKIE" http://localhost:8080/revue | grep -o '<aside\|bg-sidebar\|mx-auto max-w-4xl' | sort | uniq -c
tests/.venv/bin/pytest tests/web/ -v
```
Expected : `<aside` présent, `mx-auto max-w-4xl` absent, 18 tests toujours passants.

- [ ] **Step 4: Commit**

```bash
git add web/public/Views/revue_liste.php
git commit -m "feat(web): migre revue_liste.php vers le layout sidebar"
```

---

### Task 5: Migrer `admin_utilisateurs.php` vers le sidebar

**Files:**
- Modify: `web/public/Views/admin_utilisateurs.php:15-17` (ouverture) et `:88-89` (fermeture)

**Interfaces:**
- Consumes : `partials/sidebar.php` (Task 2), même contrat.

- [ ] **Step 1: Modifier l'ouverture**

Remplacer :
```php
<body class="min-h-screen bg-background py-10 font-sans text-foreground">
    <div class="mx-auto max-w-4xl px-4">
        <?php include __DIR__ . '/partials/nav.php'; ?>
```
par :
```php
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-64 lg:pt-10">
```

- [ ] **Step 2: Modifier la fermeture**

Remplacer :
```php
    </div>
</body>
</html>
```
par :
```php
    </main>
</body>
</html>
```

- [ ] **Step 3: Rebuild, vérifier structurellement et faire tourner la suite existante**

`admin@novity.io` (via `scripts/seed-admin.sql`, Task 3) a déjà le rôle `admin`, donc le même
`$COOKIE` donne directement accès à cette page :

```bash
docker compose up -d --build web
curl -s -b "$COOKIE" http://localhost:8080/admin/utilisateurs | grep -o '<aside\|bg-sidebar\|mx-auto max-w-4xl' | sort | uniq -c
tests/.venv/bin/pytest tests/web/ -v
```
Expected : `<aside` présent, `mx-auto max-w-4xl` absent, 18 tests toujours passants.

- [ ] **Step 4: Commit**

```bash
git add web/public/Views/admin_utilisateurs.php
git commit -m "feat(web): migre admin_utilisateurs.php vers le layout sidebar"
```

---

### Task 6: Migrer `revue.php` vers le sidebar (cas particulier)

**Files:**
- Modify: `web/public/Views/revue.php:7-13` (`<head>`, ajout viewport), `:13-25` (ouverture body + 2 colonnes + retrait du nav imbriqué), `:292` (fermeture)

**Interfaces:**
- Consumes : `partials/sidebar.php` (Task 2), même contrat. Contrairement aux 3 autres vues,
  `revue.php` n'a aujourd'hui aucun `<meta name="viewport">` dans son `<head>` — à ajouter, car le
  tiroir mobile n'a de sens qu'avec un viewport mobile correctement configuré.

- [ ] **Step 1: Ajouter le viewport meta manquant**

Remplacer :
```php
<head>
    <meta charset="UTF-8">
    <title>Revue de Profil - Novity</title>
```
par :
```php
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Revue de Profil - Novity</title>
```

- [ ] **Step 2: Modifier l'ouverture du body et déplacer le nav au niveau racine**

Remplacer :
```php
<body class="bg-background font-sans text-foreground">

<div class="flex h-screen gap-4 p-4">
    <div class="flex-[2] overflow-hidden rounded-lg border border-border bg-muted">
```
par :
```php
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="flex h-screen gap-4 p-4 pt-16 lg:pl-64 lg:pt-4">
    <div class="flex-[2] overflow-hidden rounded-lg border border-border bg-muted">
```
(même logique `pt-16`/`lg:pt-4` que les autres vues pour la place du hamburger mobile.)

- [ ] **Step 3: Retirer l'ancien include imbriqué dans la colonne formulaire**

Remplacer :
```php
    <div class="card flex-1 overflow-y-auto p-6">
        <?php include __DIR__ . '/partials/nav.php'; ?>
        <h1 class="mb-4 text-xl font-bold">Validation du profil</h1>
```
par :
```php
    <div class="card flex-1 overflow-y-auto p-6">
        <h1 class="mb-4 text-xl font-bold">Validation du profil</h1>
```

- [ ] **Step 4: Modifier la fermeture**

Remplacer (fin de fichier) :
```php
</div>

</body>
</html>
```
par :
```php
</main>

</body>
</html>
```

- [ ] **Step 5: Rebuild, vérifier structurellement (y compris le layout 2 colonnes) et faire tourner la suite existante**

Un id de profil inexistant suffit pour vérifier la structure : `revue.php` affiche alors "Profil
introuvable" dans la colonne formulaire, mais le sidebar (rendu sans condition sur `$profil`) et la
colonne PDF restent affichés normalement :
```bash
docker compose up -d --build web
curl -s -b "$COOKIE" "http://localhost:8080/revue/999999" | grep -o '<aside\|bg-sidebar\|flex-\[2\]\|partials/nav' | sort | uniq -c
tests/.venv/bin/pytest tests/web/ -v
```
Expected : `<aside` présent, `flex-[2]` (colonne PDF) toujours présent et inchangé, aucune trace de
`partials/nav`, 18 tests toujours passants.

- [ ] **Step 6: Commit**

```bash
git add web/public/Views/revue.php
git commit -m "feat(web): migre revue.php vers le layout sidebar (nav déplacé au niveau racine)"
```

---

### Task 7: Supprimer l'ancien partial et vérification finale

**Files:**
- Delete: `web/public/Views/partials/nav.php`

**Interfaces:** aucune — cette tâche ne fait que retirer du code mort et vérifier l'ensemble.

- [ ] **Step 1: Vérifier qu'aucune vue ne référence plus `nav.php`**

Run:
```bash
grep -rn "partials/nav" web/public/Views/
```
Expected: aucune sortie (0 résultat) — confirmation que les Tasks 3-6 ont bien migré les 4 vues.

- [ ] **Step 2: Supprimer le fichier**

```bash
git rm web/public/Views/partials/nav.php
```

- [ ] **Step 3: Rebuild complet et suite de tests au complet**

Run:
```bash
docker compose up -d --build
tests/.venv/bin/pytest tests/web/ -v
```
Expected: tous les tests passent (aucune régression — aucune route/Controller/Model modifié dans
tout ce plan).

- [ ] **Step 4: Vérification manuelle dans le navigateur**

- Se connecter sur `http://localhost:8080`.
- Sur chacune des 4 pages (`/`, `/revue`, `/revue/{id}`, `/admin/utilisateurs`, ce dernier avec un
  compte admin) : le sidebar noir est visible à gauche, l'item correspondant à la page courante est
  en surbrillance verte, le contenu remplit toute la largeur restante.
- Réduire la largeur de la fenêtre sous ~1024px : le sidebar disparaît, un bouton hamburger apparaît
  en haut à gauche ; cliquer l'ouvre en tiroir avec un fond assombri derrière ; cliquer sur le fond
  assombri le referme.
- Lisibilité du logo Novity (inversé en blanc) et des icônes dans le sidebar sombre — si le rendu du
  filtre `brightness-0 invert` est visuellement imparfait, remplacer l'image du logo par un texte
  "NOVITY" en blanc (`font-semibold text-white`) directement dans `sidebar.php`, en gardant tout le
  reste du fichier inchangé.
- Déconnexion depuis le pied du sidebar redirige bien vers `/login`.
- `/revue/{id}` : le layout 2 colonnes (PDF + formulaire) fonctionne toujours normalement à
  l'intérieur du nouveau `<main>`.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "chore(web): supprime l'ancien partial nav.php, remplacé par sidebar.php"
```
