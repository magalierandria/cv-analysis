# Matching CV / offres d'emploi Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une entité "offre d'emploi" (CRUD admin) et un matching par recoupement de compétences entre profils et offres, dans les deux sens (page offre → candidats classés avec anneau de progression ; fiche profil → offres correspondantes).

**Architecture:** Extension classique du MVC PHP existant (`OffreModel`/`OffreController`, 2 nouvelles vues, 1 migration). Le score de matching est calculé à la volée en PHP (pas stocké, pas de dépendance au service Python/LLM). Aucun changement au modèle d'authentification ou aux entités existantes au-delà de `RevueController::show()` qui gagne un calcul supplémentaire.

**Tech Stack:** PHP 8.3, PostgreSQL 16 (migration), Alpine.js (déjà chargé), SVG inline (anneau de progression, sans dépendance), pytest + httpx + psycopg (tests d'intégration existants).

## Global Constraints

- Spec de référence : `docs/superpowers/specs/2026-07-17-matching-offres-design.md`.
- Branche : ce plan s'exécute sur `feature-matching-offres` (déjà créée depuis `feature_import_final`).
- **Voir** les offres/matchings : tout utilisateur connecté. **Créer/modifier/supprimer** une
  offre : admin uniquement (`verifierAdmin()`, même pattern que `AdminController`).
- Toute route de mutation (`/offres/create`, `/offres/{id}/modifier`, `/offres/{id}/delete`) doit
  exiger `$_SERVER['REQUEST_METHOD'] === 'POST'` explicitement — précédent de sécurité déjà établi
  dans ce projet (corrige une classe de bypass GET déjà rencontrée ailleurs). Ne pas omettre cette
  garde.
- Score = (compétences requises possédées par le profil) / (total compétences requises) × 100,
  comparaison **insensible à la casse** (`mb_strtolower(trim(...))`), mais l'affichage des badges
  garde la casse originale telle que saisie dans l'offre.
- `offre_competences.offre_id` a `ON DELETE CASCADE` — supprimer une offre supprime ses
  compétences requises automatiquement, ne pas gérer ça manuellement en PHP.
- Pas de nouveaux tokens CSS — réutilise `badge-success`/`badge-warning`/`badge-secondary`,
  `text-success`/`text-warning`/`text-muted-foreground` (pour l'anneau SVG), `btn-*`, `input`,
  `label`, `card` existants.
- Le CSS n'est compilé qu'au build de l'image Docker — chaque tâche impliquant une vue nécessite
  un rebuild (`docker compose up -d --build web`) avant vérification.

---

### Task 1: Migration — tables `offres` et `offre_competences`

**Files:**
- Create: `migrations/006_offres.sql`

**Interfaces:**
- Produces : tables `offres`, `offre_competences`, consommées par toutes les tâches suivantes.

- [ ] **Step 1: Créer le fichier de migration**

Create `migrations/006_offres.sql` :

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

- [ ] **Step 2: Rebuild complet et vérifier que la migration s'applique**

Run:
```bash
docker compose up -d --build
docker compose logs migrate --tail=20
```
Expected : le service `migrate` termine sans erreur.

- [ ] **Step 3: Vérifier les tables directement en base**

Run:
```bash
docker compose exec -T db psql -U cv -d cv_analysis -c "\d offres"
docker compose exec -T db psql -U cv -d cv_analysis -c "\d offre_competences"
```
Expected : `offres` a les 5 colonnes attendues ; `offre_competences` montre
`FOREIGN KEY (offre_id) REFERENCES offres(id) ON DELETE CASCADE`.

- [ ] **Step 4: Commit**

```bash
git add migrations/006_offres.sql
git commit -m "feat(db): ajoute les tables offres et offre_competences"
```

---

### Task 2: Backend — CRUD des offres (TDD)

**Files:**
- Create: `web/public/Models/OffreModel.php`
- Create: `web/public/Controllers/OffreController.php`
- Modify: `web/public/index.php` (require + 3 routes + `use`)
- Create: `tests/web/test_offres_crud.py`

**Interfaces:**
- Produces : `OffreModel::creerOffre/modifierOffre/deleteOffre/listerOffres/getOffreById`,
  `OffreController::create/update/delete` (+ `verifierAdmin()` privé), routes
  `POST /offres/create`, `POST /offres/{id}/modifier`, `POST /offres/{id}/delete`. Consommé par
  Task 3 (matching) et Task 4 (vue liste).
- **Ne pas encore ajouter `OffreController::index()`/`show()` ni leurs routes** — les vues
  correspondantes n'existent pas avant Tasks 3/4 ; les ajouter maintenant casserait un `include`
  vers un fichier inexistant.

- [ ] **Step 1: Écrire les tests (encore en échec — les routes n'existent pas)**

Create `tests/web/test_offres_crud.py` :

```python
import os

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> int:
    hash_ = bcrypt.hashpw(mot_de_passe.encode(), bcrypt.gensalt()).decode()
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO utilisateurs (email, mot_de_passe, role)
                VALUES (%s, %s, %s)
                ON CONFLICT (email) DO UPDATE SET
                    mot_de_passe = EXCLUDED.mot_de_passe,
                    role = EXCLUDED.role,
                    actif = true
                RETURNING id
                """,
                (email, hash_, role),
            )
            user_id = cur.fetchone()[0]
        conn.commit()
    return user_id


def _creer_offre_directe(titre: str, user_id: int, competences: list[str]) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO offres (titre, cree_par_user_id) VALUES (%s, %s) RETURNING id",
                (titre, user_id),
            )
            offre_id = cur.fetchone()[0]
            for comp in competences:
                cur.execute(
                    "INSERT INTO offre_competences (offre_id, libelle) VALUES (%s, %s)",
                    (offre_id, comp),
                )
        conn.commit()
    return offre_id


@pytest.fixture(scope="module")
def admin_id() -> int:
    return _creer_utilisateur("test-offres-admin@example.com", "bonmotdepasse", "admin")


@pytest.fixture(scope="module")
def admin_client(admin_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-offres-admin@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        yield client


@pytest.fixture(scope="module")
def non_admin_client() -> httpx.Client:
    _creer_utilisateur("test-offres-nonadmin@example.com", "bonmotdepasse", "utilisateur")
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-offres-nonadmin@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        yield client


def test_admin_can_create_offer_with_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    response = admin_client.post(
        "/offres/create",
        data={
            "titre": "Dev Frontend Senior",
            "description": "Poste basé à Paris",
            "competences": "React, TypeScript , GraphQL",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM offres WHERE titre = %s", ("Dev Frontend Senior",)
            )
            offre_id = cur.fetchone()[0]
            cur.execute(
                "SELECT libelle FROM offre_competences WHERE offre_id = %s ORDER BY id",
                (offre_id,),
            )
            libelles = [row[0] for row in cur.fetchall()]
            assert libelles == ["React", "TypeScript", "GraphQL"]


def test_non_admin_cannot_create_offer(non_admin_client: httpx.Client) -> None:
    response = non_admin_client.post(
        "/offres/create",
        data={"titre": "Offre Non Autorisée", "competences": "PHP"},
    )
    assert response.status_code == 403

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT id FROM offres WHERE titre = %s", ("Offre Non Autorisée",)
            )
            assert cur.fetchone() is None


def test_create_offer_via_get_is_rejected(admin_client: httpx.Client) -> None:
    response = admin_client.get(
        "/offres/create", params={"titre": "Offre Via Get", "competences": "PHP"}
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE titre = %s", ("Offre Via Get",))
            assert cur.fetchone() is None


def test_admin_can_update_offer_replacing_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    offre_id = _creer_offre_directe("Offre A Modifier", admin_id, ["PHP", "MySQL"])

    response = admin_client.post(
        f"/offres/{offre_id}/modifier",
        data={
            "titre": "Offre Modifiée",
            "description": "Nouvelle description",
            "competences": "Python, Django",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT titre, description FROM offres WHERE id = %s", (offre_id,)
            )
            titre, description = cur.fetchone()
            assert titre == "Offre Modifiée"
            assert description == "Nouvelle description"

            cur.execute(
                "SELECT libelle FROM offre_competences WHERE offre_id = %s ORDER BY id",
                (offre_id,),
            )
            libelles = [row[0] for row in cur.fetchall()]
            assert libelles == ["Python", "Django"]


def test_modifier_via_get_is_rejected(admin_client: httpx.Client, admin_id: int) -> None:
    offre_id = _creer_offre_directe("Offre Get Modifier", admin_id, ["Java"])

    response = admin_client.get(
        f"/offres/{offre_id}/modifier", params={"titre": "Piraté"}
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT titre FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone()[0] == "Offre Get Modifier"


def test_admin_can_delete_offer_cascades_competences(
    admin_client: httpx.Client, admin_id: int
) -> None:
    offre_id = _creer_offre_directe("Offre A Supprimer", admin_id, ["Ruby", "Rails"])

    response = admin_client.post(f"/offres/{offre_id}/delete", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/offres"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone() is None
            cur.execute(
                "SELECT id FROM offre_competences WHERE offre_id = %s", (offre_id,)
            )
            assert cur.fetchone() is None


def test_delete_via_get_is_rejected(admin_client: httpx.Client, admin_id: int) -> None:
    offre_id = _creer_offre_directe("Offre Get Delete", admin_id, ["Go"])

    response = admin_client.get(f"/offres/{offre_id}/delete")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM offres WHERE id = %s", (offre_id,))
            assert cur.fetchone() is not None
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run:
```bash
tests/.venv/bin/pytest tests/web/test_offres_crud.py -v
```
Expected : tous échouent (aucune route `/offres/*` n'existe encore, `OffreModel`/`OffreController`
n'existent pas — l'autoloader ne trouve pas la classe et PHP fatal-error, ce qui devrait se
traduire par une réponse HTTP en erreur ou un fallback inattendu selon la route ; dans tous les
cas, `test_admin_can_create_offer_with_competences` échoue car aucune ligne n'est créée).

- [ ] **Step 3: Créer `OffreModel`**

Create `web/public/Models/OffreModel.php` :

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
}
```

- [ ] **Step 4: Créer `OffreController` (sans `index()`/`show()` pour l'instant)**

Create `web/public/Controllers/OffreController.php` :

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

- [ ] **Step 5: Ajouter le require + les 3 routes dans `index.php`**

Remplacer :
```php
require_once __DIR__ . '/Controllers/AdminController.php';

use Controllers\ImportController;
use Controllers\RevueController;
use Controllers\AuthController;
use Controllers\AdminController;
```
par :
```php
require_once __DIR__ . '/Controllers/AdminController.php';
require_once __DIR__ . '/Models/OffreModel.php';
require_once __DIR__ . '/Controllers/OffreController.php';

use Controllers\ImportController;
use Controllers\RevueController;
use Controllers\AuthController;
use Controllers\AdminController;
use Controllers\OffreController;
```

Puis remplacer :
```php
elseif (preg_match('/\/profil\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new RevueController($pdo))->deleteProfil($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```
par :
```php
elseif (preg_match('/\/profil\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new RevueController($pdo))->deleteProfil($matches[1]);
}
elseif (preg_match('/^\/offres\/create$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->create();
}
elseif (preg_match('/^\/offres\/(\d+)\/modifier$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->update($matches[1]);
}
elseif (preg_match('/^\/offres\/(\d+)\/delete$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->delete($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```

- [ ] **Step 6: Rebuild et vérifier que les tests passent**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_offres_crud.py -v
```
Expected : les 7 tests passent.

- [ ] **Step 7: Suite complète et commit**

Run:
```bash
tests/.venv/bin/pytest tests/web/ -v
```
Expected : aucune régression.

```bash
git add web/public/Models/OffreModel.php web/public/Controllers/OffreController.php web/public/index.php tests/web/test_offres_crud.py
git commit -m "feat(web): CRUD des offres (backend + routes, admin uniquement)"
```

---

### Task 3: Backend + frontend — matching offre → profils (TDD)

**Files:**
- Modify: `web/public/Models/OffreModel.php` (+ `calculerMatchingProfils`, + `getCompetencesProfil` privé)
- Modify: `web/public/Controllers/OffreController.php` (+ `show($id)`)
- Modify: `web/public/index.php` (+ 1 route)
- Create: `web/public/Views/offre_detail.php`
- Create: `tests/web/test_offres_matching.py`

**Interfaces:**
- Produces : `OffreModel::calculerMatchingProfils($offreId)` → tableau trié décroissant de
  `['profil' => [...], 'score' => int, 'matched' => [...], 'missing' => [...]]`. Consommé par
  Task 5 (`calculerMatchingOffres`, sens inverse, sera ajouté séparément dans ce même fichier).
  Route `GET /offres/{id}` → page de matching.

- [ ] **Step 1: Écrire les tests (encore en échec — la route n'existe pas)**

Create `tests/web/test_offres_matching.py` :

```python
import os

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> int:
    hash_ = bcrypt.hashpw(mot_de_passe.encode(), bcrypt.gensalt()).decode()
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO utilisateurs (email, mot_de_passe, role)
                VALUES (%s, %s, %s)
                ON CONFLICT (email) DO UPDATE SET
                    mot_de_passe = EXCLUDED.mot_de_passe,
                    role = EXCLUDED.role,
                    actif = true
                RETURNING id
                """,
                (email, hash_, role),
            )
            user_id = cur.fetchone()[0]
        conn.commit()
    return user_id


def _creer_profil_avec_competences(
    nom: str, email: str, user_id: int, competences: list[str]
) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES (%s, %s, 'validé', %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET nom = EXCLUDED.nom
                RETURNING id
                """,
                (nom, email, user_id),
            )
            profil_id = cur.fetchone()[0]
            cur.execute(
                "INSERT INTO experiences (profil_id, entreprise, poste) VALUES (%s, %s, %s) RETURNING id",
                (profil_id, "ACME", "Dev"),
            )
            experience_id = cur.fetchone()[0]
            for comp in competences:
                cur.execute(
                    "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                    (experience_id, comp),
                )
        conn.commit()
    return profil_id


def _creer_offre_directe(titre: str, user_id: int, competences: list[str]) -> int:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "INSERT INTO offres (titre, cree_par_user_id) VALUES (%s, %s) RETURNING id",
                (titre, user_id),
            )
            offre_id = cur.fetchone()[0]
            for comp in competences:
                cur.execute(
                    "INSERT INTO offre_competences (offre_id, libelle) VALUES (%s, %s)",
                    (offre_id, comp),
                )
        conn.commit()
    return offre_id


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-offres-matching@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-offres-matching@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_matching_scores_and_ordering(web_client: httpx.Client, user_id: int) -> None:
    profil_bas = _creer_profil_avec_competences(
        "Profil Match Bas", "test-match-bas@example.com", user_id, ["React", "TypeScript"]
    )
    profil_haut = _creer_profil_avec_competences(
        "Profil Match Haut",
        "test-match-haut@example.com",
        user_id,
        ["React", "TypeScript", "GraphQL", "Jest"],
    )
    offre_id = _creer_offre_directe(
        "Offre Matching Test", user_id, ["React", "TypeScript", "GraphQL", "Jest"]
    )

    response = web_client.get(f"/offres/{offre_id}")
    assert response.status_code == 200

    texte = response.text
    assert "100%" in texte
    assert "50%" in texte
    # Le profil à 100% doit apparaître avant celui à 50% (ordre décroissant).
    assert texte.index("Profil Match Haut") < texte.index("Profil Match Bas")


def test_offre_detail_unknown_id_returns_404(web_client: httpx.Client) -> None:
    response = web_client.get("/offres/999999999")
    assert response.status_code == 404
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run:
```bash
tests/.venv/bin/pytest tests/web/test_offres_matching.py -v
```
Expected : les deux tests échouent (route `/offres/{id}` inexistante, tombe sur le catch-all).

- [ ] **Step 3: Ajouter les méthodes de matching à `OffreModel`**

Dans `web/public/Models/OffreModel.php`, remplacer :
```php
    private function getCompetencesOffre($offreId) {
        $stmt = $this->db->prepare(
            "SELECT libelle FROM offre_competences WHERE offre_id = ? ORDER BY id ASC"
        );
        $stmt->execute([$offreId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
```
par :
```php
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
}
```

- [ ] **Step 4: Ajouter `OffreController::show()`**

Dans `web/public/Controllers/OffreController.php`, ajouter avant `create()` :

```php
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
```

- [ ] **Step 5: Ajouter la route**

Dans `web/public/index.php`, remplacer :
```php
elseif (preg_match('/^\/offres\/(\d+)\/delete$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->delete($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```
par :
```php
elseif (preg_match('/^\/offres\/(\d+)\/delete$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new OffreController($pdo))->delete($matches[1]);
}
elseif (preg_match('/^\/offres\/(\d+)$/', $uri, $matches)) {
    (new OffreController($pdo))->show($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```

- [ ] **Step 6: Créer la vue `offre_detail.php` (la page "superbe")**

Create `web/public/Views/offre_detail.php` :

```php
<?php
/** @var array $offre */
/** @var array $matchings */
$activeNav = 'offres';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | <?= htmlspecialchars($offre['titre']) ?></title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-72 lg:pt-10">
        <div class="card mb-6">
            <div class="card-content">
                <h1 class="text-xl font-bold"><?= htmlspecialchars($offre['titre']) ?></h1>
                <?php if (!empty($offre['description'])): ?>
                    <p class="mt-2 text-sm text-muted-foreground"><?= htmlspecialchars($offre['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($offre['competences'])): ?>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <?php foreach ($offre['competences'] as $comp): ?>
                            <span class="badge-success"><?= htmlspecialchars($comp) ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <h2 class="mb-3 text-lg font-semibold">Candidats classés par correspondance</h2>
        <?php if (empty($matchings)): ?>
            <div class="card p-6 text-center text-muted-foreground">Aucun profil à comparer.</div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
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
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
```

- [ ] **Step 7: Rebuild et vérifier que les tests passent**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_offres_matching.py -v
```
Expected : les 2 tests passent.

- [ ] **Step 8: Suite complète et commit**

Run:
```bash
tests/.venv/bin/pytest tests/web/ -v
```
Expected : aucune régression.

```bash
git add web/public/Models/OffreModel.php web/public/Controllers/OffreController.php web/public/index.php web/public/Views/offre_detail.php tests/web/test_offres_matching.py
git commit -m "feat(web): matching offre -> profils avec anneau de progression"
```

---

### Task 4: Frontend — liste des offres (`offres_liste.php`) + `index()`

**Files:**
- Modify: `web/public/Controllers/OffreController.php` (+ `index()`)
- Modify: `web/public/index.php` (+ 1 route)
- Create: `web/public/Views/offres_liste.php`

**Interfaces:**
- Consumes : `OffreModel::listerOffres()` (Task 2), routes `/offres/create`, `/offres/{id}/modifier`,
  `/offres/{id}/delete` (Task 2), `/offres/{id}` (Task 3, pour le lien étiré vers le détail).
- Produces : route `GET /offres` (liste), consommée par la navigation du sidebar (Task 5).

- [ ] **Step 1: Ajouter `OffreController::index()`**

Dans `web/public/Controllers/OffreController.php`, ajouter avant `show()` :

```php
    public function index() {
        $model = new OffreModel($this->db);
        $offres = $model->listerOffres();
        include __DIR__ . '/../Views/offres_liste.php';
    }
```

- [ ] **Step 2: Ajouter la route**

Dans `web/public/index.php`, remplacer :
```php
elseif (preg_match('/^\/offres\/(\d+)$/', $uri, $matches)) {
    (new OffreController($pdo))->show($matches[1]);
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```
par :
```php
elseif (preg_match('/^\/offres\/(\d+)$/', $uri, $matches)) {
    (new OffreController($pdo))->show($matches[1]);
}
elseif (preg_match('/^\/offres\/?$/', $uri)) {
    (new OffreController($pdo))->index();
}
elseif (preg_match('/\/revue\/?$/', $uri)) {
```

- [ ] **Step 3: Créer la vue `offres_liste.php`**

Create `web/public/Views/offres_liste.php` :

```php
<?php
/** @var array $offres */
$activeNav = 'offres';
$estAdmin = ($_SESSION['role'] ?? null) === 'admin';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Offres</title>
    <link rel="stylesheet" href="/css/app.css?v=<?= @filemtime(__DIR__ . '/../css/app.css') ?: time() ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
</head>
<body class="bg-background font-sans text-foreground" x-data="{ sidebarOpen: false }">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>
    <main class="min-h-screen px-4 pb-10 pt-16 lg:pl-72 lg:pt-10">
        <?php if ($estAdmin): ?>
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
        <?php endif; ?>

        <?php if (empty($offres)): ?>
            <div class="card p-6 text-center text-muted-foreground">Aucune offre pour le moment.</div>
        <?php else: ?>
            <div class="flex flex-col gap-3">
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
                            <?php if ($estAdmin): ?>
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
                        <?php if ($estAdmin): ?>
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
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
```

- [ ] **Step 4: Vérifier la syntaxe PHP et la suite de tests**

Run:
```bash
docker compose up -d --build web
docker compose exec -T web php -l /var/www/html/Controllers/OffreController.php
docker compose exec -T web php -l /var/www/html/Views/offres_liste.php
tests/.venv/bin/pytest tests/web/ -v
```
Expected : aucune erreur de syntaxe, aucune régression (aucun test n'exerce encore `GET /offres`
directement, mais la suite complète doit rester verte).

- [ ] **Step 5: Vérification manuelle**

Avec `scripts/seed-admin.sql` : sur `/offres`, le formulaire de création est visible (admin),
créer une offre avec des compétences fonctionne, "Modifier" déplie un formulaire pré-rempli
indépendant par carte, "Supprimer" fonctionne avec confirmation, cliquer sur une carte (hors
boutons) navigue vers `/offres/{id}`.

- [ ] **Step 6: Commit**

```bash
git add web/public/Controllers/OffreController.php web/public/index.php web/public/Views/offres_liste.php
git commit -m "feat(web): liste des offres (création, modification, suppression)"
```

---

### Task 5: Matching profil → offres (fiche profil) + navigation

**Files:**
- Modify: `web/public/Models/OffreModel.php` (+ `calculerMatchingOffres`)
- Modify: `web/public/Controllers/RevueController.php` (`show()` calcule le matching)
- Modify: `web/public/Views/revue.php` (+ section "Offres correspondantes")
- Modify: `web/public/Views/partials/sidebar.php` (+ item de nav "Offres")

**Interfaces:**
- Consumes : `OffreModel::listerOffres()`/`getCompetencesProfil()` (déjà présents), route `/offres/{id}`
  (Task 3) pour les liens de la nouvelle section.
- Produces : `OffreModel::calculerMatchingOffres($profilId)`, variable `$matchingOffres` passée à
  `revue.php` par `RevueController::show()`.

- [ ] **Step 1: Ajouter `calculerMatchingOffres` à `OffreModel`**

Dans `web/public/Models/OffreModel.php`, remplacer la toute fin du fichier :
```php
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        return $results;
    }
}
```
(la fermeture de `calculerMatchingProfils` suivie de la fermeture de la classe, ajoutées à la
Task 3) par :
```php
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

- [ ] **Step 2: Faire calculer le matching dans `RevueController::show()`**

Remplacer :
```php
    public function show($id) {
        $model = new ProfilModel($this->db);
        $profil = $model->getProfilById($id);
        include __DIR__ . '/../Views/revue.php';
    }
```
par :
```php
    public function show($id) {
        $model = new ProfilModel($this->db);
        $profil = $model->getProfilById($id);

        $offreModel = new \Models\OffreModel($this->db);
        $matchingOffres = $profil ? $offreModel->calculerMatchingOffres($id) : [];

        include __DIR__ . '/../Views/revue.php';
    }
```

- [ ] **Step 3: Ajouter la section "Offres correspondantes" dans `revue.php`**

Remplacer :
```php
                <button type="submit" class="btn-default w-full">Enregistrer et Valider</button>
            </form>

            <!-- ===================== EXPERIENCES ===================== -->
```
par :
```php
                <button type="submit" class="btn-default w-full">Enregistrer et Valider</button>
            </form>

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

            <!-- ===================== EXPERIENCES ===================== -->
```

- [ ] **Step 4: Ajouter l'item de navigation "Offres" au sidebar**

Dans `web/public/Views/partials/sidebar.php`, remplacer :
```php
$navItems = [
    'import' => ['href' => '/', 'label' => 'Import', 'icon' => 'upload-cloud'],
    'revue' => ['href' => '/revue', 'label' => 'Revue', 'icon' => 'list-checks'],
];
```
par :
```php
$navItems = [
    'import' => ['href' => '/', 'label' => 'Import', 'icon' => 'upload-cloud'],
    'revue' => ['href' => '/revue', 'label' => 'Revue', 'icon' => 'list-checks'],
    'offres' => ['href' => '/offres', 'label' => 'Offres', 'icon' => 'briefcase'],
];
```

- [ ] **Step 5: Rebuild, test manuel et suite complète**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/ -v
```
Expected : aucune régression (18+ tests existants, plus les nouveaux des Tasks 2-3).

Vérification manuelle : ouvrir `/revue/{id}` d'un profil ayant des compétences qui recoupent au
moins une offre créée précédemment — la section "Offres correspondantes" apparaît avec le bon
score ; ouvrir `/revue/{id}` d'un profil sans aucune compétence recoupant une offre — la section
n'apparaît pas (ou affiche des offres à 0%, selon ce que montre `array_slice`, cohérent avec le
calcul). Confirmer que l'item "Offres" apparaît dans le sidebar sur toutes les pages et se met en
surbrillance verte sur `/offres` et `/offres/{id}`.

- [ ] **Step 6: Commit**

```bash
git add web/public/Models/OffreModel.php web/public/Controllers/RevueController.php web/public/Views/revue.php web/public/Views/partials/sidebar.php
git commit -m "feat(web): matching profil -> offres sur la fiche profil + navigation"
```

---

### Task 6: Vérification finale

**Files:** aucun — vérification uniquement.

- [ ] **Step 1: Rebuild complet et suite de tests au complet**

Run:
```bash
docker compose up -d --build
tests/.venv/bin/pytest tests/web/ -v
```
Expected : tous les tests passent (existants + les nouveaux de ce plan).

- [ ] **Step 2: Vérification manuelle / capture d'écran de bout en bout**

- Créer 2-3 offres avec des compétences qui se recoupent partiellement avec des profils
  existants (via `/offres`, en tant qu'admin).
- Ouvrir `/offres/{id}` : vérifier l'anneau de progression, le classement décroissant, les badges
  compétences possédées (vert) / manquantes (gris).
- Ouvrir `/revue/{id}` d'un profil : vérifier la section "Offres correspondantes".
- Vérifier la navigation (item "Offres" dans le sidebar, surbrillance correcte) sur les 4+1 pages.
- Confirmer qu'un utilisateur non-admin voit les offres et le matching mais pas les boutons
  Créer/Modifier/Supprimer.

Pas de commit pour cette tâche (vérification uniquement).
