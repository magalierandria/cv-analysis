# Refonte layout : menu vertical (sidebar) façon AdminLTE

Date : 2026-07-16
Statut : approuvé

## Contexte

Les 4 pages post-login (`import.php`, `revue_liste.php`, `revue.php`, `admin_utilisateurs.php`)
utilisent depuis la migration shadcn (`docs/superpowers/specs/2026-07-14-dashboard-shadcn-design.md`)
une topbar horizontale (`partials/nav.php`) et un contenu centré dans un conteneur `max-w-*`, avec
des marges vides importantes sur les écrans larges. L'utilisateur souhaite « paufiner » le mini-projet
en passant à un layout plein écran avec un menu vertical façon AdminLTE, dans les couleurs Novity.

## Décisions validées avec l'utilisateur

- **Position du menu** : à gauche (convention AdminLTE classique), pas à droite comme suggéré
  initialement dans la demande — clarifié explicitement.
- **Couleurs du menu** : fond quasi-noir `#0A0A0A` (« noir Novity », déjà utilisé comme
  `--novity-noir` dans `login.php`), item actif en vert Novity `#16A34A` plein avec texte blanc,
  items inactifs en gris clair doux plutôt que blanc pur.
- **Largeur du contenu** : pleine largeur partout (tableaux, grilles de profils) — pas de conteneur
  `max-w-*` artificiel comme aujourd'hui.
- **Mobile** : menu caché par défaut, bouton hamburger qui ouvre un tiroir en superposition
  (overlay + drawer glissant depuis la gauche), pattern standard réutilisant Alpine.js déjà en
  place dans le projet.

## Hors scope

- `login.php` et `import_status.php` : restent en Bootstrap CDN, non touchés (déjà acté dans les
  specs précédentes — ce sont des pages sans menu de navigation).
- Aucun changement de Controller, Model, route ou schéma BDD — uniquement structure HTML et CSS
  des vues.
- Pas de repli « icônes seules » du sidebar sur desktop (toggle mini-sidebar façon AdminLTE) — non
  demandé, ajouterait de la complexité (état persistant, largeur variable) sans besoin exprimé.
- Pas de sous-menus / nav à plusieurs niveaux — les 3 items existants (Import, Revue, Admin) restent
  plats.

## Simplification actée

Le menu « Compte » actuel est un dropdown Alpine (`x-data="{ open: false }"`) qui ne contient qu'un
seul item, Déconnexion. Dans le nouveau pied de sidebar, il est remplacé par un bouton de
déconnexion direct (icône + label, `POST /logout`) — un dropdown pour une seule action est une
complexité inutile.

## Structure

### Renommage `partials/nav.php` → `partials/sidebar.php`

Le rôle du partial change fondamentalement (topbar horizontale → chrome de navigation vertical +
tiroir mobile), justifiant un renommage. Le nouveau partial rend, dans l'ordre :

1. Un bouton hamburger mobile (`lg:hidden`, position fixe en haut à gauche du contenu).
2. Un overlay de fond mobile (`fixed inset-0 bg-black/50 lg:hidden`, visible seulement quand le
   tiroir est ouvert, ferme le tiroir au clic).
3. L'`<aside>` du menu : `fixed inset-y-0 left-0 z-40 w-64` sur toutes tailles d'écran, avec
   `-translate-x-full lg:translate-x-0` par défaut (caché hors-écran sur mobile, toujours visible à
   partir du breakpoint `lg`), et `translate-x-0` quand `sidebarOpen` est vrai.

Contenu de l'`<aside>` :
- En-tête : logo Novity (voir section Logo ci-dessous).
- Liste des items de nav (même logique `$navItems` que l'actuel `nav.php` : Import, Revue, et Admin
  si `$_SESSION['role'] === 'admin'`), affichés verticalement avec icône + label.
- Pied : séparateur, badge du rôle courant (`$_SESSION['role']`, déjà disponible, aucune requête
  supplémentaire), bouton de déconnexion direct.

### État partagé Alpine

`x-data="{ sidebarOpen: false }"` est posé sur la balise `<body>` de chaque vue (et non sur un
`<div>` interne) — le bouton hamburger, l'overlay et l'aside sont tous des enfants directs ou
indirects du `<body>` et partagent donc cet état sans mécanisme supplémentaire (pas de store Alpine
global nécessaire, la portée `x-data` suffit).

### Wrapper de contenu

Chaque vue remplace son wrapper actuel :
```html
<div class="mx-auto max-w-Nxl px-4">
    <?php include __DIR__ . '/partials/nav.php'; ?>
    ... contenu ...
</div>
```
par :
```html
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<main class="lg:pl-64">
    ... contenu, sans contrainte max-w ...
</main>
```

### Cas particulier : `revue.php`

`revue.php` a aujourd'hui une structure différente : `<body>` est directement un conteneur
`flex h-screen gap-4 p-4` à 2 colonnes (visionneuse PDF + carte formulaire), et `nav.php` est inclus
*à l'intérieur* de la colonne formulaire (avant le `<h1>`), pas au niveau racine de la page — un
positionnement historique incohérent avec les 3 autres vues.

Cette page passe à la même structure que les autres : `sidebar.php` inclus au niveau racine, puis
`<main class="lg:pl-64 flex h-screen gap-4 p-4">` englobant le layout 2 colonnes existant (PDF +
formulaire), qui reste inchangé par ailleurs. Le `<?php include .../nav.php; ?>` actuellement dans
la colonne formulaire est supprimé de cet emplacement.

## Design tokens ajoutés

Dans `web/assets/css/input.css`, à la suite des tokens existants dans `:root` :

```css
--sidebar: 10 10 10;              /* #0a0a0a — noir Novity, même valeur que --foreground */
--sidebar-foreground: 212 212 216; /* #d4d4d8 — gris clair doux (zinc-300), pas blanc pur */
--sidebar-border: 39 39 42;         /* #27272a — séparateur discret sur fond sombre (zinc-800) */
```

L'item de nav actif réutilise `--primary`/`--primary-foreground` (vert Novity / blanc) déjà
existants — pas de nouveau token pour l'état actif.

Wiring dans `tailwind.config.js` (`theme.extend.colors`), même pattern `{ DEFAULT, foreground }`
que `primary`/`muted`, plus `sidebar-border` en couleur plate :
```js
sidebar: {
  DEFAULT: "rgb(var(--sidebar) / <alpha-value>)",
  foreground: "rgb(var(--sidebar-foreground) / <alpha-value>)",
},
"sidebar-border": "rgb(var(--sidebar-border) / <alpha-value>)",
```

## Logo sur fond sombre

Le SVG existant `web/public/images/novity-logo-noir.svg` a un remplissage noir (`fill="black"`),
invisible sur le fond `#0A0A0A` du sidebar. Plutôt que de créer un nouvel asset (aucune charte
graphique Novity disponible, cf. specs précédentes), le logo est inversé en blanc via un filtre CSS
Tailwind sur la balise `<img>` : classes `brightness-0 invert` (transforme le noir en blanc,
fonctionne nativement en Tailwind v3 sans classe `filter` séparée). À vérifier visuellement pendant
l'implémentation : si le rendu du filtre est imparfait (halo, anti-aliasing dégradé), replier sur un
texte simple « NOVITY » en blanc (`font-semibold text-white`) à la place de l'image — décision
laissée à l'implémentation selon le rendu réel, pas un aller-retour de conception.

## Pages impactées

- **`web/public/Views/partials/sidebar.php`** (nouveau, remplace `nav.php`) : hamburger mobile +
  overlay + aside (logo, nav, pied avec rôle + déconnexion).
- **`web/public/Views/import.php`** : wrapper `<body>`/`<div class="mx-auto max-w-3xl">` remplacé
  par `<body x-data="{ sidebarOpen: false }">` + `sidebar.php` + `<main class="lg:pl-64">`.
- **`web/public/Views/revue_liste.php`** : idem (actuellement `max-w-4xl`).
- **`web/public/Views/admin_utilisateurs.php`** : idem (actuellement `max-w-4xl`).
- **`web/public/Views/revue.php`** : cas particulier décrit ci-dessus (nav déplacé au niveau
  racine, layout 2 colonnes existant englobé dans `<main>`).

Aucun fichier PHP `Controllers`/`Models`, aucune route, aucune migration SQL modifiée.

## Vérification

- `docker compose up -d --build` (CSS recompilé au build de l'image, cf. contrainte déjà connue).
- Suite d'intégration existante : `tests/.venv/bin/pytest tests/web/ -v` — aucune route touchée,
  aucune régression attendue, sert de garde-fou de non-régression fonctionnelle.
- Vérification manuelle dans le navigateur sur les 4 pages impactées :
  - Desktop (≥ breakpoint `lg`) : sidebar toujours visible à gauche, contenu pleine largeur à
    droite, item de nav actif correctement mis en évidence en vert selon la page courante.
  - Mobile (redimensionnement navigateur sous `lg`) : sidebar cachée par défaut, bouton hamburger
    visible, clic ouvre le tiroir avec overlay, clic sur l'overlay ou sur un item de nav referme le
    tiroir.
  - `revue.php` : le layout 2 colonnes (PDF + formulaire) fonctionne toujours correctement à
    l'intérieur du nouveau `<main>`.
  - Déconnexion directe depuis le pied de sidebar fonctionne (redirection vers `/login`).
  - Lisibilité du logo inversé en blanc sur le fond sombre du sidebar.
