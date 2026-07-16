# Extension de la palette : couleurs de statut (warning/info/success)

Date : 2026-07-16
Statut : approuvé

## Contexte

Depuis la migration shadcn (`docs/superpowers/specs/2026-07-14-dashboard-shadcn-design.md`), la
palette ne compte que deux couleurs sémantiques : `--primary` (vert Novity `#16A34A`) et
`--destructive` (rouge `#dc2626`). Tous les autres badges de statut retombent sur
`--muted`/`badge-secondary` (gris neutre), y compris pour des statuts qui mériteraient une couleur
distincte :

- `revue_liste.php` / `revue.php` : statut `à valider` (gris) vs `validé` (vert).
- `import.php` : statut `réussi` (vert) vs `échoué` (gris) — un import raté n'est aujourd'hui pas
  visuellement distingué d'un statut neutre, ce qui est trompeur.

L'utilisateur souhaite « ajouter d'autres codes couleur qui représentent bien Novity » pour
finaliser (paufiner) le mini-projet. Aucune charte graphique Novity officielle n'est disponible
(déjà noté dans `docs/superpowers/specs/2026-07-12-login-admin-design.md`) : la palette est donc
étendue par complémentarité avec le vert existant plutôt qu'extraite d'un asset de marque.

## Objectif

Ajouter des tokens de couleur **sémantiques de statut** (warning, info, success) et les appliquer
aux badges existants pour que chaque statut réel de l'application soit visuellement distinct et
correct (en particulier corriger le cas `échoué` actuellement gris).

## Hors scope

- Pas de couleur d'accent secondaire de marque (deuxième teinte verte, etc.) — uniquement des
  couleurs de statut, comme validé avec l'utilisateur.
- Pas de nouveau statut métier ajouté (pas de `rejeté` en base, par ex.) — uniquement le rendu des
  statuts déjà existants.
- `login.php` et `import_status.php` restent en Bootstrap CDN, hors scope (déjà acté dans la spec
  du 2026-07-14) — non retouchés ici.
- Pas de mode sombre.

## Tokens ajoutés

Dans `web/assets/css/input.css`, à la suite des tokens existants dans `:root` :

```css
--success: 22 163 74;         /* #16a34a — alias sémantique de --primary */
--success-foreground: 255 255 255;
--warning: 217 119 6;         /* #d97706 */
--warning-foreground: 255 255 255;
--info: 37 99 235;            /* #2563eb */
--info-foreground: 255 255 255;
```

`--success` reprend intentionnellement la même valeur que `--primary` (vert Novity) : c'est un nom
sémantique dédié aux badges/alertes de statut « positif », indépendant de `--primary` si celui-ci
venait à changer un jour pour une autre raison (branding, thème).

Palette retenue : teintes shadcn/Tailwind standards (ambre `amber-600`, bleu `blue-600`), plutôt
qu'une variante « tons chauds » calibrée sur le vert — choix validé avec l'utilisateur pour rester
lisible et cohérent avec le reste de la palette shadcn déjà en place.

## Wiring Tailwind

Dans `web/assets/tailwind.config.js`, `theme.extend.colors`, ajouter `success`, `warning`, `info`
suivant le même pattern que les couleurs existantes :

```js
success: 'rgb(var(--success) / <alpha-value>)',
'success-foreground': 'rgb(var(--success-foreground) / <alpha-value>)',
warning: 'rgb(var(--warning) / <alpha-value>)',
'warning-foreground': 'rgb(var(--warning-foreground) / <alpha-value>)',
info: 'rgb(var(--info) / <alpha-value>)',
'info-foreground': 'rgb(var(--info-foreground) / <alpha-value>)',
```

## Classes composants ajoutées

Dans `web/assets/css/input.css`, `@layer components`, en miroir des classes `badge-*`/`alert-*`
existantes :

```css
.badge-success {
  @apply inline-flex items-center rounded-full border border-transparent bg-success px-2.5 py-0.5 text-xs font-semibold text-success-foreground;
}
.badge-warning {
  @apply inline-flex items-center rounded-full border border-transparent bg-warning px-2.5 py-0.5 text-xs font-semibold text-warning-foreground;
}
.badge-info {
  @apply inline-flex items-center rounded-full border border-transparent bg-info px-2.5 py-0.5 text-xs font-semibold text-info-foreground;
}

.alert-warning {
  @apply rounded-md border border-warning/50 bg-warning/10 px-4 py-3 text-sm text-warning;
}
.alert-info {
  @apply rounded-md border border-info/50 bg-info/10 px-4 py-3 text-sm text-info;
}
```

`badge-default`/`badge-secondary`/`badge-destructive` et `alert-destructive` restent inchangés (pas
de renommage, pour ne pas casser d'usages existants non listés ci-dessous).

## Pages impactées

- **`web/public/Views/revue_liste.php`** (~ligne 81) : le badge de statut du profil passe de
  `$profil['statut'] === 'validé' ? 'badge-default' : 'badge-secondary'` à
  `$profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning'`.
- **`web/public/Views/revue.php`** : pas de badge de statut à modifier — seul un `<select name="statut">`
  y affiche le statut (à valider/validé), non concerné par ce changement. Le `badge-secondary`
  utilisé pour les compétences (ligne ~147) est conservé tel quel (ce n'est pas un statut).
- **`web/public/Views/import.php`** (~ligne 97) : le badge de statut d'import passe de
  `$item['statut'] == 'réussi' ? 'badge-default' : 'badge-secondary'` à une correspondance
  explicite à trois branches : `réussi` → `badge-success`, `échoué` → `badge-destructive`, tout
  autre statut (ex. `en cours`) → `badge-warning`.

Aucun changement de Controller, Model, route ou schéma BDD.

## Vérification

- `docker compose up -d --build` (le CSS est recompilé au build de l'image, pas de watch en dev).
- Test manuel dans le navigateur :
  - `revue_liste.php` : un profil `à valider` affiche un badge ambre, un profil `validé` affiche un
    badge vert.
  - `import.php` : historique d'imports — un import `réussi` en vert, un import `échoué` en rouge
    (vérifier avec un import qui échoue réellement, ex. fichier corrompu).
- Vérifier le contraste texte/fond des nouveaux badges (`warning`/`info`) à l'œil pour l'accessibilité
  de base (pas d'outil de contraste automatisé dans le projet).
- Les tests d'intégration existants (`tests/web/`) ne sont pas affectés (aucune route/Controller/Model
  modifié) — les faire tourner pour confirmer l'absence de régression.
