# Matching CV / offres d'emploi

Date : 2026-07-17
Statut : approuvé

## Contexte

L'utilisateur souhaite une nouvelle fonctionnalité de matching entre les profils (CV) déjà
importés/validés et une liste d'offres d'emploi, avec une page "superbe" pour la présenter.
Aucune notion d'offre n'existe actuellement dans l'application (vérifié : aucune table, aucun
fichier ne mentionne "offre"). Cette fonctionnalité part sur une **nouvelle branche depuis
`feature_import_final`** (la seule base qui a le dashboard, les profils/compétences et le design
actuel — `main` ne contient qu'un `health.php` minimal, sans aucun des Views/Controllers/Models
existants).

## Décisions validées avec l'utilisateur

- **Offres** : vraie entité gérée dans l'app (nouvelles tables `offres` + `offre_competences`),
  pas un simple import de texte volatil.
- **Droits** : voir les offres et les matchings → tout utilisateur connecté. Créer/modifier/
  supprimer une offre → **admin uniquement**.
- **Algorithme de matching** : score = (nombre de compétences requises possédées par le profil) /
  (nombre total de compétences requises) × 100, comparaison insensible à la casse. Calculé à la
  volée en PHP (pas de dépendance au service LLM Python existant, pas de nouvelle dépendance).
- **Sens du matching** : les deux — une page par offre listant les profils classés, et une section
  sur la fiche profil listant les offres correspondantes.
- **Saisie des compétences requises** : un champ texte unique séparé par des virgules
  (`"React, TypeScript, GraphQL"`), découpé côté serveur — pas de widget de tags interactif.

## Hors scope

- Pas d'analyse sémantique/LLM pour le matching — uniquement le score par recoupement de
  compétences exact (après normalisation casse/espaces).
- Pas de widget de saisie de tags interactif pour les compétences requises.
- Pas de filtrage des profils par statut dans le matching — tous les profils apparaissent, avec
  leur badge de statut visible (l'utilisateur voit qui est "validé" vs "à valider" directement
  dans le classement, pas de profils masqués).
- Pas de pagination sur la liste des offres ni sur le classement des candidats (cohérent avec le
  reste de l'application, qui n'a pas de pagination actuellement).

## Base de données

Nouvelle migration `migrations/006_offres.sql` :

```sql
CREATE TABLE offres (
    id SERIAL PRIMARY KEY,
    titre TEXT NOT NULL,
    description TEXT,
    cree_par_user_id INTEGER NOT NULL REFERENCES utilisateurs (id),
    date_creation TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE offre_competences (
    id SERIAL PRIMARY KEY,
    offre_id INTEGER NOT NULL REFERENCES offres (id) ON DELETE CASCADE,
    libelle TEXT NOT NULL
);

CREATE INDEX idx_offre_competences_offre_id ON offre_competences (offre_id);
```

`offre_competences.offre_id` a une cascade `ON DELETE CASCADE` (mêmes conventions que
`experiences`/`formations`/`competences` vis-à-vis de `profils`) : supprimer une offre supprime
automatiquement ses compétences requises associées.

## Backend

### `web/public/Models/OffreModel.php` (nouveau)

```php
<?php
namespace Models;

use PDO;

class OffreModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function creerOffre($titre, $description, $userId, array $competences) {
        $stmt = $this->db->prepare(
            "INSERT INTO offres (titre, description, cree_par_user_id) VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$titre, $description, $userId]);
        $offreId = (int) $stmt->fetchColumn();
        $this->remplacerCompetencesOffre($offreId, $competences);
        return $offreId;
    }

    public function modifierOffre($id, $titre, $description, array $competences) {
        $stmt = $this->db->prepare("UPDATE offres SET titre = ?, description = ? WHERE id = ?");
        $stmt->execute([$titre, $description, $id]);
        $this->remplacerCompetencesOffre($id, $competences);
    }

    public function deleteOffre($id) {
        $stmt = $this->db->prepare("DELETE FROM offres WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function listerOffres() {
        $stmt = $this->db->query(
            "SELECT id, titre, description, date_creation FROM offres ORDER BY date_creation DESC"
        );
        $offres = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($offres as &$offre) {
            $offre['competences'] = $this->getCompetencesOffre($offre['id']);
        }
        return $offres;
    }

    public function getOffreById($id) {
        $stmt = $this->db->prepare(
            "SELECT id, titre, description, date_creation FROM offres WHERE id = ?"
        );
        $stmt->execute([$id]);
        $offre = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$offre) {
            return null;
        }
        $offre['competences'] = $this->getCompetencesOffre($id);
        return $offre;
    }

    private function remplacerCompetencesOffre($offreId, array $competences) {
        $stmt = $this->db->prepare("DELETE FROM offre_competences WHERE offre_id = ?");
        $stmt->execute([$offreId]);
        $stmt = $this->db->prepare("INSERT INTO offre_competences (offre_id, libelle) VALUES (?, ?)");
        foreach ($competences as $comp) {
            $comp = trim($comp);
            if ($comp !== '') {
                $stmt->execute([$offreId, $comp]);
            }
        }
    }

    private function getCompetencesOffre($offreId) {
        $stmt = $this->db->prepare(
            "SELECT libelle FROM offre_competences WHERE offre_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$offreId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getCompetencesProfil($profilId) {
        $stmt = $this->db->prepare(
            "SELECT DISTINCT c.libelle FROM competences c
             JOIN experiences e ON e.id = c.experience_id
             WHERE e.profil_id = ?"
        );
        $stmt->execute([$profilId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Score de chaque profil pour une offre donnée, trié décroissant. */
    public function calculerMatchingProfils($offreId) {
        $competencesRequises = $this->getCompetencesOffre($offreId);

        $stmt = $this->db->query("SELECT id, nom, titre, statut FROM profils ORDER BY nom ASC");
        $profils = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($profils as $profil) {
            $competencesProfilLower = array_map(
                fn($c) => mb_strtolower(trim($c)),
                $this->getCompetencesProfil($profil['id'])
            );

            $matched = [];
            $missing = [];
            foreach ($competencesRequises as $comp) {
                if (in_array(mb_strtolower(trim($comp)), $competencesProfilLower, true)) {
                    $matched[] = $comp;
                } else {
                    $missing[] = $comp;
                }
            }

            $score = count($competencesRequises) > 0
                ? (int) round(count($matched) / count($competencesRequises) * 100)
                : 0;

            $results[] = [
                'profil' => $profil,
                'score' => $score,
                'matched' => $matched,
                'missing' => $missing,
            ];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }

    /** Score de chaque offre pour un profil donné, trié décroissant. */
    public function calculerMatchingOffres($profilId) {
        $competencesProfilLower = array_map(
            fn($c) => mb_strtolower(trim($c)),
            $this->getCompetencesProfil($profilId)
        );

        $offres = $this->listerOffres();
        $results = [];
        foreach ($offres as $offre) {
            $matched = [];
            foreach ($offre['competences'] as $comp) {
                if (in_array(mb_strtolower(trim($comp)), $competencesProfilLower, true)) {
                    $matched[] = $comp;
                }
            }
            $score = count($offre['competences']) > 0
                ? (int) round(count($matched) / count($offre['competences']) * 100)
                : 0;

            $results[] = ['offre' => $offre, 'score' => $score];
        }

        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }
}
```

### `web/public/Controllers/OffreController.php` (nouveau)

```php
<?php
namespace Controllers;

use Models\OffreModel;

class OffreController {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    private function verifierAdmin() {
        if (($_SESSION['role'] ?? null) !== 'admin') {
            http_response_code(403);
            die("Accès réservé aux administrateurs.");
        }
    }

    public function index() {
        $model = new OffreModel($this->db);
        $offres = $model->listerOffres();
        include __DIR__ . '/../Views/offres_liste.php';
    }

    public function show($id) {
        $model = new OffreModel($this->db);
        $offre = $model->getOffreById($id);
        if (!$offre) {
            http_response_code(404);
            die("Offre introuvable.");
        }
        $matchings = $model->calculerMatchingProfils($id);
        include __DIR__ . '/../Views/offre_detail.php';
    }

    public function create() {
        $this->verifierAdmin();
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $competences = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));

        if ($titre !== '') {
            $model = new OffreModel($this->db);
            $model->creerOffre($titre, $description, $_SESSION['user_id'], $competences);
        }

        header('Location: /offres');
        exit;
    }

    public function update($id) {
        $this->verifierAdmin();
        $titre = trim($_POST['titre'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $competences = array_filter(array_map('trim', explode(',', $_POST['competences'] ?? '')));

        if ($titre !== '') {
            $model = new OffreModel($this->db);
            $model->modifierOffre($id, $titre, $description, $competences);
        }

        header('Location: /offres');
        exit;
    }

    public function delete($id) {
        $this->verifierAdmin();
        $model = new OffreModel($this->db);
        $model->deleteOffre($id);
        header('Location: /offres');
        exit;
    }
}
```

### `web/public/Controllers/RevueController.php` (modifié)

`show($id)` calcule aussi les offres correspondantes pour ce profil :

```php
public function show($id) {
    $model = new ProfilModel($this->db);
    $profil = $model->getProfilById($id);

    $offreModel = new \Models\OffreModel($this->db);
    $matchingOffres = $profil ? $offreModel->calculerMatchingOffres($id) : [];

    include __DIR__ . '/../Views/revue.php';
}
```

### `web/public/index.php` (routes)

Ajout de `require_once` pour `OffreModel`/`OffreController` (même bloc que les autres) et des
routes suivantes, placées avant le `else` final, mutations gardées `POST` (précédent de sécurité
déjà établi dans ce projet) :

```php
elseif (preg_match('/^\/offres\/create$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->create();
}
elseif (preg_match('/^\/offres\/(\d+)\/modifier$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->update($matches[1]);
}
elseif (preg_match('/^\/offres\/(\d+)\/delete$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->delete($matches[1]);
}
elseif (preg_match('/^\/offres\/(\d+)$/', $uri, $matches)) {
    (new OffreController($pdo))->show($matches[1]);
}
elseif (preg_match('/^\/offres\/?$/', $uri)) {
    (new OffreController($pdo))->index();
}
```

(Placer ce bloc avant les routes génériques `/revue` existantes, dans le même esprit que
l'organisation actuelle du fichier.)

## Frontend

### Sidebar (`partials/sidebar.php`)

Ajout d'un item de nav, visible pour tous (pas seulement l'admin — seule la création/édition/
suppression est admin-only) :

```php
$navItems = [
    'import' => ['href' => '/', 'label' => 'Import', 'icon' => 'upload-cloud'],
    'revue' => ['href' => '/revue', 'label' => 'Revue', 'icon' => 'list-checks'],
    'offres' => ['href' => '/offres', 'label' => 'Offres', 'icon' => 'briefcase'],
];
```
(inséré entre `revue` et le bloc conditionnel `admin` existant.)

### `web/public/Views/offres_liste.php` (nouveau)

Structure identique aux autres vues (head, sidebar, `<main>` avec `lg:pl-72`). Si admin, un
formulaire "Créer une offre" toujours visible en haut (même pattern que
`admin_utilisateurs.php`) :

```php
<div class="card mb-6">
    <div class="card-content">
        <h1 class="mb-4 text-xl font-bold">Créer une offre</h1>
        <form method="POST" action="/offres/create" class="grid gap-4 md:grid-cols-12">
            <div class="md:col-span-4">
                <label class="label mb-1 block">Titre</label>
                <input type="text" name="titre" class="input" required>
            </div>
            <div class="md:col-span-8">
                <label class="label mb-1 block">Description</label>
                <input type="text" name="description" class="input">
            </div>
            <div class="md:col-span-12">
                <label class="label mb-1 block">Compétences requises</label>
                <input type="text" name="competences" class="input" placeholder="React, TypeScript, GraphQL">
                <p class="mt-1 text-xs text-muted-foreground">Séparez les compétences par des virgules.</p>
            </div>
            <div class="md:col-span-12">
                <button type="submit" class="btn-default">Créer l'offre</button>
            </div>
        </form>
    </div>
</div>
```

Puis la liste des offres en cartes, avec lien étiré (même pattern que les cartes de profils) et,
si admin, un bouton "Modifier" (déplie un formulaire inline, `x-data` posé directement sur la
`<div class="card ...">`, pas besoin du pattern multi-`<tbody>` ici car ce n'est pas un tableau) et
un bouton supprimer :

```php
<?php foreach ($offres as $offre): ?>
    <div class="card relative p-4 transition-shadow hover:shadow-md" x-data="{ editOpen: false }">
        <a href="/offres/<?= $offre['id'] ?>" class="absolute inset-0" aria-label="Voir l'offre <?= htmlspecialchars($offre['titre']) ?>"></a>
        <div class="flex items-start justify-between gap-3">
            <div>
                <div class="text-base font-semibold"><?= htmlspecialchars($offre['titre']) ?></div>
                <?php if (!empty($offre['description'])): ?>
                    <div class="text-sm text-muted-foreground"><?= htmlspecialchars($offre['description']) ?></div>
                <?php endif; ?>
                <?php if (!empty($offre['competences'])): ?>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <?php foreach ($offre['competences'] as $comp): ?>
                            <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
                <div class="relative z-10 flex shrink-0 items-center gap-2">
                    <button type="button" class="btn-outline btn-sm" @click="editOpen = !editOpen">Modifier</button>
                    <form method="POST" action="/offres/<?= $offre['id'] ?>/delete" onsubmit="return confirm('Supprimer cette offre ?');">
                        <button type="submit" class="btn-outline btn-sm" aria-label="Supprimer l'offre">
                            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/trash-2.svg" class="h-4 w-4" alt="">
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
            <div class="relative z-10 mt-3" x-show="editOpen" x-cloak>
                <form method="POST" action="/offres/<?= $offre['id'] ?>/modifier" class="flex flex-wrap items-end gap-3 rounded-md border border-dashed border-border bg-muted p-4">
                    <div>
                        <label class="label mb-1 block text-xs">Titre</label>
                        <input type="text" name="titre" class="input" value="<?= htmlspecialchars($offre['titre']) ?>" required>
                    </div>
                    <div>
                        <label class="label mb-1 block text-xs">Description</label>
                        <input type="text" name="description" class="input" value="<?= htmlspecialchars($offre['description'] ?? '') ?>">
                    </div>
                    <div>
                        <label class="label mb-1 block text-xs">Compétences requises</label>
                        <input type="text" name="competences" class="input" value="<?= htmlspecialchars(implode(', ', $offre['competences'])) ?>">
                    </div>
                    <button type="submit" class="btn-default btn-sm">Enregistrer</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
```

Le lien étiré + boutons en `relative z-10` reprend exactement le pattern déjà validé sur les
cartes de profils (`revue_liste.php`).

### `web/public/Views/offre_detail.php` (nouveau — la page "superbe")

En-tête offre (titre, description, compétences requises en badges verts), puis la liste des
candidats classés, chacun avec un **anneau de progression circulaire SVG** (sans dépendance,
couleur selon le score via les tokens Tailwind existants `text-success`/`text-warning`/
`text-muted-foreground` appliqués en `stroke="currentColor"`) :

```php
<?php foreach ($matchings as $m): ?>
    <?php
        $profil = $m['profil'];
        $score = $m['score'];
        $ringColor = $score >= 75 ? 'text-success' : ($score >= 40 ? 'text-warning' : 'text-muted-foreground');
        $rayon = 16;
        $circonference = 2 * M_PI * $rayon;
        $dashoffset = $circonference * (1 - $score / 100);

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
    <a href="/revue/<?= $profil['id'] ?>" class="card flex items-center gap-4 p-4 transition-shadow hover:shadow-md">
        <div class="relative h-12 w-12 shrink-0">
            <svg viewBox="0 0 36 36" class="h-12 w-12 -rotate-90">
                <circle cx="18" cy="18" r="<?= $rayon ?>" fill="none" stroke="currentColor" stroke-width="3" class="text-muted"></circle>
                <circle cx="18" cy="18" r="<?= $rayon ?>" fill="none" stroke="currentColor" stroke-width="3"
                        stroke-dasharray="<?= round($circonference, 2) ?>"
                        stroke-dashoffset="<?= round($dashoffset, 2) ?>"
                        stroke-linecap="round"
                        class="<?= $ringColor ?>"></circle>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center text-xs font-semibold"><?= $score ?>%</div>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
                    <?= htmlspecialchars($profil['statut']) ?>
                </span>
            </div>
            <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
            <div class="mt-2 flex flex-wrap gap-1">
                <?php foreach ($m['matched'] as $comp): ?>
                    <span class="badge-success"><?= htmlspecialchars($comp) ?></span>
                <?php endforeach; ?>
                <?php foreach ($m['missing'] as $comp): ?>
                    <span class="badge-secondary"><?= htmlspecialchars($comp) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </a>
<?php endforeach; ?>
```

`-rotate-90` fait démarrer l'anneau à midi plutôt qu'à 3h (convention standard des anneaux de
progression). Les compétences manquantes réutilisent `badge-secondary` (gris) pour bien les
distinguer visuellement des compétences possédées (`badge-success`, vert).

### `web/public/Views/revue.php` (section ajoutée)

Nouvelle section "Offres correspondantes", insérée après le formulaire de validation du profil
existant (avant la section "Expériences") :

```php
<?php if (!empty($matchingOffres)): ?>
    <div class="mt-6">
        <h2 class="mb-2 flex items-center gap-2 text-sm font-semibold text-primary">
            <img src="https://cdn.jsdelivr.net/npm/lucide-static@1.24.0/icons/briefcase.svg" class="h-4 w-4" alt="">
            Offres correspondantes
        </h2>
        <div class="space-y-2">
            <?php foreach (array_slice($matchingOffres, 0, 5) as $m): ?>
                <a href="/offres/<?= $m['offre']['id'] ?>" class="flex items-center justify-between rounded-md border border-border bg-muted p-3 text-sm hover:bg-muted/70">
                    <span class="font-medium"><?= htmlspecialchars($m['offre']['titre']) ?></span>
                    <span class="<?= $m['score'] >= 75 ? 'badge-success' : ($m['score'] >= 40 ? 'badge-warning' : 'badge-secondary') ?>">
                        <?= $m['score'] ?>%
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
```

Limité aux 5 meilleures offres (`array_slice`) pour ne pas surcharger la fiche profil.

## Pages/fichiers impactés

- `migrations/006_offres.sql` (nouveau)
- `web/public/Models/OffreModel.php` (nouveau)
- `web/public/Controllers/OffreController.php` (nouveau)
- `web/public/Views/offres_liste.php` (nouveau)
- `web/public/Views/offre_detail.php` (nouveau)
- `web/public/Views/partials/sidebar.php` (+ item de nav)
- `web/public/Views/revue.php` (+ section "Offres correspondantes")
- `web/public/Controllers/RevueController.php` (`show()` calcule aussi le matching)
- `web/public/index.php` (+ require + 5 routes)

## Vérification

- `docker compose up -d --build` (migration appliquée automatiquement).
- Suite d'intégration existante : `tests/.venv/bin/pytest tests/web/ -v` — aucune route existante
  modifiée en profondeur (seul `RevueController::show()` gagne un calcul supplémentaire, sans
  changer sa signature ni son comportement pour l'appelant), sert de garde-fou de non-régression.
- Nouveaux scénarios à couvrir par des tests d'intégration (mêmes conventions `httpx`/`psycopg`
  que le reste de `tests/web/`) :
  - Création d'une offre par un admin, avec compétences séparées par virgules → vérifier les
    lignes `offres`/`offre_competences` en base.
  - Un non-admin qui tente `POST /offres/create` (ou `/modifier`, `/delete`) → refusé (403, comme
    `verifierAdmin()` existant).
  - Une requête `GET` sur les routes de mutation → rejetée (garde POST).
  - Score de matching calculé correctement pour un cas simple (profil avec 2 compétences sur 4
    requises → 50%).
  - Suppression d'une offre supprime bien ses `offre_competences` (cascade).
  - La fiche profil affiche bien la section "Offres correspondantes" quand au moins une offre
    existe, et ne l'affiche pas sinon.
- Vérification manuelle : créer 2-3 offres avec des compétences qui se recoupent partiellement
  avec les profils de test existants, confirmer visuellement le classement et l'anneau de
  progression sur `/offres/{id}`, et la section sur `/revue/{id}`.
