# Refonte visuelle shadcn/ui du dashboard post-login

Date : 2026-07-14
Statut : approuvé

## Contexte

Les pages accessibles après connexion (`import.php`, `revue_liste.php`, `revue.php`,
`admin_utilisateurs.php`) utilisent Bootstrap 5 + Bootstrap Icons chargés via CDN, avec un bloc de
navigation dupliqué à l'identique dans chacune des 4 vues. L'utilisateur souhaite adopter le style
visuel de shadcn/ui pour ce dashboard.

Point d'architecture clé : shadcn/ui est une collection de composants **React** (Radix UI +
Tailwind CSS), pas une librairie CSS installable. Le projet est un **MVC PHP maison sans
framework, sans Node/npm** (`docs/adr/0001-stack-technique-v1.md` : « zéro dépendance Composer »,
Apache + mod_php). Introduire React signifierait une refonte d'architecture majeure contredisant
l'ADR. Après clarification avec l'utilisateur, l'objectif retenu est de **reproduire le langage
visuel de shadcn/ui** (design tokens, composants Card/Badge/Button/Table/Nav) **en PHP + Tailwind
CSS + Alpine.js**, sans React, sans dépendance Node à l'exécution.

## Périmètre

- Les 4 pages post-login : `import.php`, `revue_liste.php`, `revue.php`, `admin_utilisateurs.php`.
- Extraction du bloc nav commun (aujourd'hui dupliqué dans les 4 vues) en un partial PHP unique.
- Remplacement de Bootstrap CSS/JS et Bootstrap Icons par Tailwind CSS, Alpine.js et Lucide
  (icônes SVG statiques), avec une palette de tokens façon shadcn (style « new-york », base
  neutral) et le vert Novity `#16A34A` comme couleur d'accent primaire (cohérence avec
  `login.php`, qui utilise déjà cette couleur).
- Migration en une seule PR : les 4 pages partagent le même nav ; les migrer une par une créerait
  un mélange visuel incohérent tant que le nav n'est pas factorisé.

## Hors scope (décisions déjà actées avec l'utilisateur)

- **`login.php`** : reste en Bootstrap, non touché par cette migration.
- **Aucune introduction de React/Next.js/Vite** : rejeté explicitement, contredit l'ADR 0001.
- **Aucun changement de logique métier** : Controllers, Models, routes, schéma BDD, service
  d'import Python — non modifiés. Seul le rendu HTML/CSS/JS des vues change.
- **Pas de composants PHP réutilisables génériques** (pas de fonction `card()`, `button()`, etc.)
  dans ce premier jet : on documente des conventions de markup HTML+Tailwind cohérentes entre les
  4 pages, appliquées directement dans chaque vue. Une éventuelle extraction en partials par
  composant pourra être envisagée plus tard si la duplication devient un problème réel.
- **Pas de mode sombre** : non demandé, non traité.
- **Pas de tests visuels automatisés** : vérification manuelle uniquement (aucun outil de ce type
  dans le projet aujourd'hui).

## Stack ajoutée

- **Tailwind CSS**, compilé via le binaire CLI standalone (`tailwindcss-linux-x64`), téléchargé au
  build de l'image Docker `web` — pas de dépendance npm/Node à l'exécution ni au développement,
  cohérent avec la philosophie « zéro dépendance » de l'ADR 0001.
- **Alpine.js** via CDN (`<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer>`) :
  remplace le JS de Bootstrap (`data-bs-toggle="collapse"` dans `revue.php`, futur dropdown menu
  utilisateur) par des attributs déclaratifs (`x-data`, `x-show`, `@click`).
- **Lucide** (icônes) via `lucide-static` (SVG statiques servis en fichiers locaux ou CDN
  jsdelivr), remplace Bootstrap Icons — c'est le set d'icônes standard d'un projet shadcn/ui.

## Structure de fichiers

```
web/
  assets/
    css/input.css          # directives @tailwind + variables CSS façon shadcn (tokens)
    tailwind.config.js     # content: ["../public/Views/**/*.php"], theme.extend avec les tokens
  public/
    css/app.css            # généré par le build Tailwind (gitignored)
    Views/
      partials/
        nav.php            # topbar commune (liens Import/Revue/Admin + dropdown utilisateur Alpine)
      import.php
      revue_liste.php
      revue.php
      admin_utilisateurs.php
```

`nav.php` est inclus via `<?php include __DIR__ . '/partials/nav.php'; ?>` dans les 4 vues, et
reçoit les mêmes variables déjà disponibles dans le scope de la vue appelante (`$_SESSION['role']`
pour l'affichage conditionnel de l'onglet Admin).

## Build Docker

`web/Dockerfile` passe en build multi-stage :

```dockerfile
FROM alpine:3.20 AS cssbuild
RUN apk add --no-cache curl && \
    curl -sLo /usr/local/bin/tailwindcss \
      https://github.com/tailwindlabs/tailwindcss/releases/latest/download/tailwindcss-linux-x64 && \
    chmod +x /usr/local/bin/tailwindcss
WORKDIR /build
COPY assets/ assets/
COPY public/Views/ public/Views/
RUN tailwindcss -i assets/css/input.css -o public/css/app.css --minify

FROM php:8.3-apache
# ... étapes existantes (ext pdo_pgsql, KeepAlive Off, uploads.ini, session.ini) inchangées ...
COPY public/ /var/www/html/
COPY --from=cssbuild /build/public/css/app.css /var/www/html/css/app.css
```

Le stage `cssbuild` n'est présent que pendant le build de l'image ; l'image finale ne contient que
le CSS compilé, aucun binaire ni dépendance supplémentaire à l'exécution.

## Design tokens

Variables CSS dans `assets/css/input.css` (style shadcn « new-york », base neutral) :

| Token | Valeur |
| --- | --- |
| `--background` | `#ffffff` |
| `--foreground` | `#0a0a0a` |
| `--card` | `#ffffff` |
| `--border` | `#e5e7eb` |
| `--muted` | `#f4f4f5` / `--muted-foreground` `#71717a` |
| `--primary` | `#16A34A` (vert Novity) |
| `--primary-foreground` | `#ffffff` |
| `--destructive` | `#dc2626` |
| `--radius` | `0.5rem` |

## Mapping composants (Bootstrap → shadcn-style)

| Actuel | Nouveau | Pages concernées |
| --- | --- | --- |
| `navbar-custom` + `nav-pills`, bouton déconnexion visible | Topbar (`nav.php`) : liens Import/Revue/Admin actifs + dropdown menu utilisateur (Alpine `x-data="{ open: false }"`) contenant le bouton Déconnexion | toutes |
| `.card` | Card / CardHeader / CardTitle / CardContent (div + classes Tailwind reproduisant le markup shadcn) | toutes |
| `.badge-status`, `.badge-competence`, `.badge` | Badge (variants `default` vert, `secondary` gris, `destructive` rouge, `outline`) | import, revue_liste, revue, admin |
| `.btn btn-primary/outline-*/danger` | Button (variants `default`, `outline`, `destructive`, `ghost` ; tailles `default`/`sm`) | toutes |
| `.form-control`, `.form-select`, `.form-check` | Input / Select / Checkbox / Label shadcn | import (aucun), revue_liste (filtres), revue (form validation), admin (form création) |
| `.table`, `.table-hover` | Table shadcn (thead bordure basse, tbody avec hover `bg-muted`) | import (historique), admin (comptes) |
| `.alert-danger` | Alert (variant `destructive`) | login (hors scope), admin, revue |
| Bootstrap `collapse` (`data-bs-toggle`) | Alpine `x-show` avec transition (`x-transition`) | revue.php (formulaires ajout/édition expérience/formation) |
| `.drop-zone` | Conservé tel quel (bordure pointillée, hover), juste restylé avec les tokens Tailwind | import |

## Pages impactées

- **`import.php`** : nav → partial, card d'upload restylée, table historique en Table shadcn. Le
  JS existant (`showFile`/`resetFile`) est conservé sans modification (n'a pas de dépendance
  Bootstrap).
- **`revue_liste.php`** : nav → partial, formulaire de filtres (recherche/statut/checkboxes
  compétences) en Input/Select/Checkbox shadcn, cards de profils restylées.
- **`revue.php`** : nav → partial (dans la colonne form-validation), layout 2 colonnes inchangé
  (PDF viewer + form), formulaires ajout/édition d'expérience et de formation convertis de
  Bootstrap `collapse` vers Alpine `x-show`.
- **`admin_utilisateurs.php`** : nav → partial, formulaire de création de compte et table des
  comptes existants restylés.

## Vérification

- `docker compose up -d --build` puis test manuel dans le navigateur des 4 pages : upload d'un CV,
  filtres de la liste de profils, ajout/édition/suppression d'une expérience et d'une formation
  dans `revue.php`, création et désactivation d'un compte dans l'admin.
- Vérifier le rendu responsive (mobile) sur `revue_liste.php` et `import.php`.
- Vérifier que le dropdown utilisateur (Alpine) et les collapses de formulaires fonctionnent
  sans JS Bootstrap chargé.
- Les tests d'intégration existants (`tests/web/`) ne sont pas affectés (aucune route, Controller
  ou Model modifié) — les faire tourner pour confirmer l'absence de régression fonctionnelle.
