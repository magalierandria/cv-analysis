# Modification de compte (Admin), suppression de profil (Revue), cartes plus jolies

Date : 2026-07-16
Statut : approuvé

## Contexte

L'utilisateur souhaite trois améliorations liées à la gestion des données :

1. Dans `admin_utilisateurs.php`, pouvoir **modifier** un compte (en plus de la simple
   désactivation déjà existante) et clarifier ce que fait la "suppression".
2. Dans `revue_liste.php`, pouvoir **supprimer** un profil directement depuis la liste, via une
   icône poubelle.
3. Rendre les cartes de profils de `revue_liste.php` visuellement plus soignées.

## Décisions validées avec l'utilisateur

- **Pas de suppression définitive de compte utilisateur.** `profils.importe_par_user_id` est une
  clé étrangère **obligatoire** (`NOT NULL`) vers `utilisateurs`, sans cascade — un vrai `DELETE`
  échouerait pour tout compte ayant déjà importé ou validé un profil (soit la quasi-totalité des
  comptes actifs). Le mécanisme de suppression douce existant (`actif = false`, bouton
  "Désactiver") est conservé tel quel. À la place, un bouton **"Modifier"** est ajouté.
- **Le formulaire "Modifier" édite tout sauf le mot de passe** : email, rôle, statut actif/inactif
  (ce dernier permet aussi de *réactiver* un compte désactivé, ce qui n'existait pas avant).
- **Suppression de profil** : suppression réelle (`DELETE FROM profils`), avec confirmation
  (`confirm()` JS, même pattern que les suppressions d'expérience/formation existantes). Les
  `experiences`/`formations`/`competences` associées suivent automatiquement (`ON DELETE CASCADE`
  déjà en place). `import_jobs.profile_id` référence `profils` **sans** cascade actuellement — une
  petite migration change ça en `ON DELETE SET NULL` pour que l'historique d'import reste visible
  (juste sans lien vers un profil supprimé) au lieu de bloquer la suppression.
- **Cartes de profils** : ajout d'un avatar circulaire avec les initiales du nom, meilleure
  hiérarchie visuelle (nom en évidence, titre en dessous), icône poubelle à côté du badge de
  statut. La liste reste verticale (pas de passage en grille) ; les tags de compétences gardent
  le style `badge-secondary` existant.

## Hors scope

- Pas de suppression définitive de compte utilisateur (voir décisions ci-dessus).
- Pas de modification du mot de passe depuis le formulaire "Modifier".
- Pas de passage en grille/galerie pour la liste de profils — reste une liste verticale.
- Pas de nouvelle couleur pour les tags de compétences — `badge-secondary` existant conservé.
- Pas de confirmation par email ni de notification lors d'une suppression.

## Base de données

Nouvelle migration `migrations/005_import_jobs_profile_id_set_null.sql` :

```sql
ALTER TABLE import_jobs DROP CONSTRAINT import_jobs_profile_id_fkey;
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_profile_id_fkey
    FOREIGN KEY (profile_id) REFERENCES profils (id) ON DELETE SET NULL;
```

(Le nom de contrainte `import_jobs_profile_id_fkey` est celui auto-généré par Postgres pour la
`REFERENCES` inline de `migrations/001_initial_schema.sql` — confirmé via `\d import_jobs` sur la
base actuelle.)

## Backend : suppression de profil

- **`web/public/Models/ProfilModel.php`** — nouvelle méthode :
  ```php
  public function deleteProfil($id) {
      $stmt = $this->db->prepare("DELETE FROM profils WHERE id = ?");
      return $stmt->execute([$id]);
  }
  ```
- **`web/public/Controllers/RevueController.php`** — nouvelle méthode :
  ```php
  public function deleteProfil($id) {
      $model = new ProfilModel($this->db);
      $model->deleteProfil($id);
      header('Location: /revue');
      exit;
  }
  ```
- **`web/public/index.php`** — nouvelle route, placée parmi les autres routes de mutation de
  `RevueController` (avant la route générique `/revue/(\d+)$`), avec garde `POST` explicite
  (même précaution de sécurité que les routes de mutation admin existantes) :
  ```php
  elseif (preg_match('/\/profil\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
      (new RevueController($pdo))->deleteProfil($matches[1]);
  }
  ```

## Backend : modification de compte

- **`web/public/Models/UtilisateurModel.php`** — nouvelle méthode :
  ```php
  public function modifierUtilisateur($id, $email, $role, $actif) {
      $stmt = $this->db->prepare(
          "UPDATE utilisateurs SET email = ?, role = ?, actif = ? WHERE id = ?"
      );
      return $stmt->execute([$email, $role, $actif, $id]);
  }
  ```
- **`web/public/Controllers/AdminController.php`** — nouvelle méthode, même structure de
  validation/gestion d'erreur que `creerUtilisateur()` existant :
  ```php
  public function modifierUtilisateur($id) {
      $this->verifierAdmin();

      $email = trim($_POST['email'] ?? '');
      $role = $_POST['role'] ?? 'utilisateur';
      $actif = isset($_POST['actif']);

      if ($email === '' || !in_array($role, ['admin', 'utilisateur'], true)) {
          $_SESSION['erreur_admin'] = 'Champs invalides.';
          header('Location: /admin/utilisateurs');
          exit;
      }

      $model = new UtilisateurModel($this->db);
      try {
          $model->modifierUtilisateur((int) $id, $email, $role, $actif);
      } catch (\PDOException $e) {
          $_SESSION['erreur_admin'] = 'Impossible de modifier ce compte (email déjà utilisé ?).';
      }

      header('Location: /admin/utilisateurs');
      exit;
  }
  ```
- **`web/public/index.php`** — nouvelle route, avec la même garde `POST` explicite que
  `/admin/utilisateurs/(\d+)/desactiver` existant :
  ```php
  elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/modifier$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
      (new AdminController($pdo))->modifierUtilisateur($matches[1]);
  }
  ```
  Placée parmi les autres routes `/admin/utilisateurs/...` spécifiques, avant la route générique
  `/^\/admin\/utilisateurs\/?$/`.

## Frontend : formulaire "Modifier" inline (admin_utilisateurs.php)

Chaque ligne du tableau des comptes a besoin d'un état Alpine indépendant (`editOpen`) pour
afficher/masquer son propre formulaire d'édition, sans affecter les autres lignes. Comme `x-data`
ne s'étend qu'aux **descendants** d'un élément (pas à ses frères), et que deux `<tr>` frères ne
peuvent pas partager un scope Alpine posé sur l'un des deux, la solution est d'utiliser **un
`<tbody>` par utilisateur** (un `<table>` HTML peut contenir plusieurs `<tbody>`, chacun devient
naturellement son propre scope Alpine) :

```php
<?php foreach ($utilisateurs as $u): ?>
    <tbody x-data="{ editOpen: false }">
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
            <td class="flex gap-1">
                <?php if ($u['actif']): ?>
                    <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/desactiver"
                          onsubmit="return confirm('Désactiver ce compte ?');">
                        <button type="submit" class="btn-outline btn-sm">Désactiver</button>
                    </form>
                <?php endif; ?>
                <button type="button" class="btn-outline btn-sm" @click="editOpen = !editOpen">Modifier</button>
            </td>
        </tr>
        <tr x-show="editOpen" x-cloak>
            <td colspan="4">
                <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/modifier"
                      class="flex flex-wrap items-end gap-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <div>
                        <label class="label mb-1 block text-xs">Email</label>
                        <input type="email" name="email" class="input" value="<?= htmlspecialchars($u['email']) ?>" required>
                    </div>
                    <div>
                        <label class="label mb-1 block text-xs">Rôle</label>
                        <select name="role" class="input">
                            <option value="utilisateur" <?= $u['role'] === 'utilisateur' ? 'selected' : '' ?>>Utilisateur</option>
                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                        </select>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="actif" value="1" <?= $u['actif'] ? 'checked' : '' ?>
                               class="h-4 w-4 rounded border-border text-primary focus:ring-primary">
                        Actif
                    </label>
                    <button type="submit" class="btn-default btn-sm">Enregistrer</button>
                </form>
            </td>
        </tr>
    </tbody>
<?php endforeach; ?>
```

Le `<thead>` existant reste inchangé au-dessus de ces `<tbody>` répétés.

## Frontend : suppression + restyle des cartes (revue_liste.php)

La carte de profil est aujourd'hui un unique `<a href="/revue/{id}" class="card ...">` englobant
tout son contenu. Un `<form>` (bouton de suppression) ne peut pas être imbriqué dans un `<a>` en
HTML valide, et ferait de toute façon déclencher la navigation au clic. La carte passe donc à un
`<div>` avec un lien "étiré" (*stretched link*) : un `<a>` invisible positionné en
`absolute inset-0` capture les clics sur toute la carte pour la navigation, et les éléments
réellement interactifs (bouton de suppression) reçoivent `position: relative` + un `z-index`
supérieur pour rester cliquables au-dessus du lien étiré :

```php
<?php foreach ($profils as $profil): ?>
    <?php
        $motsNom = preg_split('/\s+/', trim($profil['nom'] ?? ''));
        $initiales = '';
        if (!empty($motsNom[0])) {
            $initiales .= mb_strtoupper(mb_substr($motsNom[0], 0, 1));
        }
        if (count($motsNom) > 1 && !empty(end($motsNom))) {
            $initiales .= mb_strtoupper(mb_substr(end($motsNom), 0, 1));
        }
        if ($initiales === '') {
            $initiales = '?';
        }
    ?>
    <div class="card relative p-4 transition-shadow hover:shadow-md">
        <a href="/revue/<?= $profil['id'] ?>" class="absolute inset-0" aria-label="Voir le profil de <?= htmlspecialchars($profil['nom'] ?? 'ce profil') ?>"></a>

        <div class="flex items-start justify-between gap-3">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                    <?= htmlspecialchars($initiales) ?>
                </div>
                <div>
                    <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                    <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                </div>
            </div>
            <div class="relative z-10 flex shrink-0 items-center gap-2">
                <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
                    <?= htmlspecialchars($profil['statut']) ?>
                </span>
                <form method="POST" action="/profil/delete/<?= $profil['id'] ?>" onsubmit="return confirm('Supprimer ce profil ?');">
                    <button type="submit" class="btn-outline btn-sm" aria-label="Supprimer le profil">
                        <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/trash-2.svg" class="h-4 w-4" alt="">
                    </button>
                </form>
            </div>
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
            <div class="relative z-10 flex flex-wrap gap-1">
                <?php foreach (array_keys($competencesProfil) as $comp): ?>
                    <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
```

Note : la description et les tags de compétences n'ont pas besoin de `relative z-10` pour rester
lisibles (ils n'ont pas d'action cliquable propre), seul le conteneur badge+poubelle en a besoin
puisqu'il contient un élément interactif (le bouton de suppression).

## Pages/fichiers impactés

- `migrations/005_import_jobs_profile_id_set_null.sql` (nouveau)
- `web/public/Models/ProfilModel.php` (+ `deleteProfil`)
- `web/public/Models/UtilisateurModel.php` (+ `modifierUtilisateur`)
- `web/public/Controllers/RevueController.php` (+ `deleteProfil`)
- `web/public/Controllers/AdminController.php` (+ `modifierUtilisateur`)
- `web/public/index.php` (+ 2 routes, gardées `POST`)
- `web/public/Views/admin_utilisateurs.php` (tableau restructuré en `<tbody>` par utilisateur + formulaire "Modifier")
- `web/public/Views/revue_liste.php` (carte de profil restylée + suppression)

## Vérification

- `docker compose up -d --build` (migration appliquée automatiquement par le service `migrate` au
  démarrage, comme les précédentes).
- Suite d'intégration existante : `tests/.venv/bin/pytest tests/web/ -v` — aucune route existante
  modifiée, sert de garde-fou de non-régression.
- Nouveaux scénarios à vérifier manuellement / via un script Playwright ou pytest :
  - Modifier l'email et le rôle d'un compte depuis l'admin, vérifier la persistance.
  - Réactiver un compte désactivé via "Modifier" (case "Actif" recochée).
  - Tenter de modifier l'email d'un compte vers un email déjà pris par un autre compte → message
    d'erreur, pas de modification appliquée.
  - Supprimer un profil qui a un `import_jobs` associé → suppression réussie, la ligne
    correspondante dans l'historique d'import (`import.php`) reste visible mais sans lien cliquable
    (`profile_id` passé à `NULL`).
  - Vérifier que le bouton de suppression du profil ne déclenche pas aussi la navigation vers
    `/revue/{id}` (lien étiré ne doit pas intercepter le clic sur le bouton).
  - Rendu visuel des cartes (avatar/initiales, hiérarchie, icône poubelle) sur `/revue`.
