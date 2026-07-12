# Authentification, rôle admin et page de login

Date : 2026-07-12
Statut : approuvé

## Contexte

Aucune authentification n'existe dans le projet : `web/public/index.php` appelle `session_start()`
sans jamais l'utiliser, et `importe_par_user_id` est codé en dur à `1` dans
`ImportController::upload()`. La table `utilisateurs` ne contient que `id`, `email`,
`mot_de_passe`, sans notion de rôle.

`docs/spec.md` §7 prévoyait déjà : « Authentification par login email/password. Pas
d'inscription publique en v1 : les comptes sont créés manuellement (seed ou script
administrateur). » Ce document implémente cette partie, en ajoutant une distinction
admin / utilisateur standard (non prévue dans la spec initiale, ajoutée à la demande).

## Périmètre

- Page de login (email + mot de passe), sessions PHP natives (déjà le choix documenté dans
  `docs/adr/0001-stack-technique-v1.md`).
- Rôle `admin` / `utilisateur` sur chaque compte.
- Page d'administration (accessible aux seuls admins) : lister les comptes, en créer un
  (email + mot de passe saisi directement par l'admin + rôle), désactiver un compte existant.
- Après connexion, redirection vers la page d'import CV (`/`), quel que soit le rôle.
- Toutes les routes existantes (sauf `/login`) exigent une session active.
- `ImportController::upload()` utilise l'utilisateur réellement connecté au lieu du `1` en dur.
- Design visuel de la page de login inspiré de l'identité Novity (novity.io) : dominante
  noir/blanc, vert en accent ; mise en page en deux colonnes avec une photo de personnes à
  gauche (traitement visuel adouci/sombre) et le formulaire à droite.

## Hors scope (explicitement exclu, décisions déjà actées avec l'utilisateur)

- Pas de « mot de passe oublié » : aucun système d'email dans le projet, un envoi de lien de
  réinitialisation n'est pas réalisable en v1.
- Pas d'inscription publique.
- Pas de changement de mot de passe en libre-service par l'utilisateur connecté (seul l'admin
  définit/modifie les mots de passe pour l'instant).
- Pas de suppression physique des comptes (désactivation uniquement), pour ne pas casser les
  références `importe_par_user_id` / `valide_par_user_id` déjà en base sur des profils existants.
- Les couleurs exactes de Novity n'ont pas pu être extraites automatiquement (pas d'accès aux
  CSS du site) : une palette noir/blanc/vert plausible est utilisée en premier jet, à ajuster
  après revue visuelle.

## Modèle de données

Nouvelle migration `migrations/004_ajout_role_utilisateurs.sql` :

```sql
ALTER TABLE utilisateurs
    ADD COLUMN role TEXT NOT NULL DEFAULT 'utilisateur'
        CHECK (role IN ('admin', 'utilisateur')),
    ADD COLUMN actif BOOLEAN NOT NULL DEFAULT true;
```

`actif` permet la désactivation d'un compte sans suppression physique (un utilisateur désactivé
ne peut plus se connecter, mais ses références historiques restent valides).

Un compte admin initial doit exister pour pouvoir créer les suivants depuis l'UI — fourni par un
script de seed (`scripts/seed-admin.sh` ou équivalent), pas par une UI d'inscription (cohérent
avec « pas d'inscription publique »).

## Composants

### PHP — Authentification

- **`web/public/Models/UtilisateurModel.php`** (nouveau) :
  - `verifierIdentifiants(string $email, string $motDePasse): ?array` — cherche l'utilisateur actif
    par email, vérifie le mot de passe avec `password_verify()`, retourne
    `['id' => int, 'email' => string, 'role' => string]` ou `null`.
  - `creerUtilisateur(string $email, string $motDePasse, string $role): int` — insère avec
    `password_hash($motDePasse, PASSWORD_BCRYPT)`, retourne l'id créé.
  - `listerUtilisateurs(): array` — tous les comptes (id, email, rôle, actif), pour la page admin.
  - `desactiverUtilisateur(int $id): void` — passe `actif` à `false`.

- **`web/public/Controllers/AuthController.php`** (nouveau) :
  - `showLogin()` : rend `Views/login.php` (si une session existe déjà, redirige directement
    vers `/`).
  - `login()` : lit `$_POST['email']`/`$_POST['mot_de_passe']`, appelle
    `UtilisateurModel::verifierIdentifiants`. Si succès, régénère l'id de session
    (`session_regenerate_id(true)`), stocke `$_SESSION['user_id']` et `$_SESSION['role']`,
    redirige vers `/`. Si échec, réaffiche `Views/login.php` avec un message d'erreur générique
    (« Email ou mot de passe incorrect » — ne jamais préciser lequel des deux, pour ne pas
    faciliter l'énumération de comptes).
  - `logout()` : `session_destroy()`, redirige vers `/login`.

- **`web/public/Views/login.php`** (nouveau) : mise en page en deux colonnes (photo à gauche,
  formulaire à droite), champs email + mot de passe, affichage du message d'erreur le cas échéant.

### PHP — Garde d'accès

- **`web/public/index.php`** : avant le routage existant, si l'URI n'est ni `/login` ni la route
  de connexion POST, et qu'aucune session valide n'existe (`$_SESSION['user_id']` absent),
  redirection vers `/login`. Les routes `/import/job/{id}/status` (JSON, appelée par le
  JavaScript de polling) doivent aussi être protégées — un utilisateur non connecté ne doit pas
  pouvoir consulter l'état d'un job.
- Nouvelles routes : `GET /login`, `POST /login`, `POST /logout` (formulaire avec bouton, pas un
  lien `<a href>`, pour éviter qu'un préchargement de page ou un clic accidentel ne déconnecte —
  cohérent avec le pattern déjà utilisé pour les actions de suppression dans `revue.php`),
  `GET /admin/utilisateurs`, `POST /admin/utilisateurs/create`,
  `POST /admin/utilisateurs/{id}/desactiver`.

### PHP — Administration des comptes

- **`web/public/Controllers/AdminController.php`** (nouveau) :
  - `listerUtilisateurs()` : vérifie `$_SESSION['role'] === 'admin'` (sinon 403 / redirection),
    rend `Views/admin_utilisateurs.php` avec la liste des comptes.
  - `creerUtilisateur()` : même garde admin, valide les champs, appelle
    `UtilisateurModel::creerUtilisateur`, redirige vers la liste.
  - `desactiverUtilisateur($id)` : même garde admin, appelle
    `UtilisateurModel::desactiverUtilisateur`, redirige vers la liste.

- **`web/public/Views/admin_utilisateurs.php`** (nouveau) : tableau des comptes (email, rôle,
  statut actif/inactif), formulaire de création, bouton de désactivation par ligne.

### Navigation

- Ajout d'un onglet « Admin » dans la nav (visible uniquement si `$_SESSION['role'] === 'admin'`)
  et d'un lien « Déconnexion », dans `Views/import.php`, `Views/revue_liste.php`, `Views/revue.php`.
- `ImportController::upload()` : remplace `'importe_par_user_id' => 1` par
  `'importe_par_user_id' => $_SESSION['user_id']`.

### Design visuel

- Palette de départ (à ajuster) : noir `#0A0A0A` (texte, éléments dominants), blanc `#FFFFFF`
  (fonds), vert `#16A34A` (accent : bouton de connexion, focus des champs, liens actifs).
- Colonne gauche (≈50% de la largeur sur desktop, masquée sur mobile) : photo libre de droits
  (Unsplash) représentant des personnes en contexte professionnel, avec un voile sombre
  semi-transparent superposé (traitement « dull » demandé) et le logo/wordmark Novity en
  surimpression.
- Colonne droite : formulaire de connexion centré verticalement, fond blanc, champs avec bordure
  fine et focus vert, bouton de connexion plein vert.

## Sécurité

- Mots de passe : `password_hash`/`password_verify` (bcrypt), jamais stockés ni loggés en clair.
- Message d'erreur de connexion générique (pas de distinction email inconnu / mot de passe
  incorrect).
- Régénération de l'id de session à la connexion (protection contre la fixation de session).
- Toutes les routes protégées vérifiées côté serveur (pas seulement un lien caché côté vue) —
  en particulier `/admin/*` et l'endpoint JSON de statut de job.
- Cookie de session : ajouter explicitement `session.cookie_httponly = 1` et
  `session.cookie_samesite = Lax` dans le `php.ini` déjà ajouté dans `web/Dockerfile`
  (`uploads.ini` → renommer en `app.ini` ou ajouter un second fichier `session.ini` dans le même
  `conf.d`), plutôt que de compter sur les valeurs par défaut de l'image.

## Tests

- Tests d'intégration httpx (cohérent avec `tests/web/`) : connexion avec identifiants valides
  redirige et donne accès à `/` ; identifiants invalides réaffichent le formulaire avec erreur ;
  accès à une route protégée sans session redirige vers `/login` ; accès à `/admin/utilisateurs`
  par un compte non-admin est refusé ; création puis désactivation d'un compte par un admin.
- Vérification manuelle du rendu visuel de la page de login (captures ou description, pas
  d'outil de navigateur headless disponible dans cet environnement).
