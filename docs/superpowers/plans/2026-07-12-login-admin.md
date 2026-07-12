# Authentification, rôle admin et page de login — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter une authentification email/mot de passe (déjà prévue dans `docs/spec.md` §7 mais jamais implémentée), un rôle admin capable de gérer les comptes utilisateurs, et une page de connexion au design inspiré de l'identité Novity.

**Architecture:** Sessions PHP natives (`password_hash`/`password_verify`, bcrypt) ; une garde d'authentification globale dans `index.php` redirige vers `/login` toute requête sans session active ; le rôle (`admin`/`utilisateur`) est vérifié au niveau du contrôleur pour les routes `/admin/*`.

**Tech Stack:** PHP 8.3 natif (sessions, PDO), PostgreSQL (migration SQL versionnée), pytest + httpx pour les tests d'intégration (cohérent avec `tests/web/` existant), `bcrypt` (Python) pour créer des utilisateurs de test compatibles avec `password_verify()` PHP.

## Global Constraints

- Mots de passe : `password_hash($motDePasse, PASSWORD_BCRYPT)` / `password_verify()` — jamais de
  hash maison, jamais de mot de passe en clair loggé ou affiché après création.
- Message d'erreur de connexion générique : ne jamais indiquer si c'est l'email ou le mot de
  passe qui est incorrect.
- Aucune route existante ne doit rester accessible sans session active, **sauf** `/login`
  (GET et POST) — y compris `/import/job/{id}/status` (JSON de polling).
- `/admin/*` : accès refusé (HTTP 403) à tout utilisateur dont `$_SESSION['role'] !== 'admin'`.
- Désactivation de compte uniquement (`actif = false`) — jamais de suppression physique de ligne
  `utilisateurs` (des `profils`/`import_jobs` référencent ces lignes par clé étrangère).
- Pas de « mot de passe oublié », pas d'inscription publique, pas de changement de mot de passe
  en libre-service — hors scope de ce plan.
- Après connexion réussie, redirection vers `/` (page d'import CV), quel que soit le rôle.

---

### Task 1: Migration `role`/`actif` + script de seed du premier admin

**Files:**
- Create: `migrations/004_ajout_role_utilisateurs.sql`
- Create: `scripts/seed-admin.sh`

**Interfaces:**
- Produces: colonnes `utilisateurs.role` (`'admin'` | `'utilisateur'`, défaut `'utilisateur'`) et
  `utilisateurs.actif` (boolean, défaut `true`). Consommées par toutes les tâches suivantes.

- [ ] **Step 1: Créer la migration**

```sql
ALTER TABLE utilisateurs
    ADD COLUMN role TEXT NOT NULL DEFAULT 'utilisateur'
        CHECK (role IN ('admin', 'utilisateur')),
    ADD COLUMN actif BOOLEAN NOT NULL DEFAULT true;
```

- [ ] **Step 2: Appliquer la migration et vérifier**

Run:
```bash
docker compose up -d migrate
docker compose exec -T db psql -U cv -d cv_analysis -c "\d utilisateurs"
```
Expected : les colonnes `role` (text, not null, default `'utilisateur'::text`) et `actif`
(boolean, not null, default `true`) apparaissent, et
`docker compose exec -T db psql -U cv -d cv_analysis -c "SELECT * FROM schema_migrations;"`
liste `004_ajout_role_utilisateurs`.

- [ ] **Step 3: Créer le script de seed**

```bash
#!/bin/sh
set -e

if [ $# -ne 2 ]; then
    echo "Usage: $0 <email> <mot_de_passe>" >&2
    exit 1
fi

EMAIL="$1"
MOT_DE_PASSE="$2"

HASH=$(docker compose exec -T web php -r "echo password_hash('$MOT_DE_PASSE', PASSWORD_BCRYPT);")

docker compose exec -T db psql -U cv -d cv_analysis -v ON_ERROR_STOP=1 -c \
    "INSERT INTO utilisateurs (email, mot_de_passe, role) VALUES ('$EMAIL', '$HASH', 'admin')
     ON CONFLICT (email) DO UPDATE SET mot_de_passe = EXCLUDED.mot_de_passe, role = 'admin';"

echo "Compte admin créé/mis à jour : $EMAIL"
```

Rendre le script exécutable :
```bash
chmod +x scripts/seed-admin.sh
```

- [ ] **Step 4: Tester le script**

Run:
```bash
./scripts/seed-admin.sh admin@novity.io changeme123
docker compose exec -T db psql -U cv -d cv_analysis -c "SELECT email, role, actif FROM utilisateurs WHERE email = 'admin@novity.io';"
```
Expected : une ligne `admin@novity.io | admin | t`.

- [ ] **Step 5: Commit**

```bash
git add migrations/004_ajout_role_utilisateurs.sql scripts/seed-admin.sh
git commit -m "feat(db): ajoute role/actif sur utilisateurs + script de seed du premier admin"
```

---

### Task 2: Modèle utilisateur, contrôleur d'authentification, page de login (fonctionnelle, sans garde globale)

**Files:**
- Create: `web/public/Models/UtilisateurModel.php`
- Create: `web/public/Controllers/AuthController.php`
- Create: `web/public/Views/login.php`
- Modify: `web/public/index.php`
- Test: `tests/web/test_auth.py` (nouveau)

**Interfaces:**
- Consumes: colonnes `utilisateurs.role`/`.actif` (Task 1)
- Produces: `UtilisateurModel::verifierIdentifiants(string $email, string $motDePasse): ?array`
  (retourne `['id' => int, 'email' => string, 'role' => string]` ou `null`),
  `UtilisateurModel::creerUtilisateur(string $email, string $motDePasse, string $role): int`,
  `UtilisateurModel::listerUtilisateurs(): array`,
  `UtilisateurModel::desactiverUtilisateur(int $id): void`. Ces quatre méthodes sont consommées
  par la Task 4 (`AdminController`). Sessions : `$_SESSION['user_id']` (int),
  `$_SESSION['role']` (string) — consommés par la garde d'authentification de la Task 3 et par
  les vues de la Task 4.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/web/test_auth.py` :

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


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> None:
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
                """,
                (email, hash_, role),
            )
        conn.commit()


@pytest.fixture(scope="module")
def web_client() -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        yield client


def test_login_with_invalid_credentials_shows_error(web_client: httpx.Client) -> None:
    _creer_utilisateur("test-auth-invalide@example.com", "bonmotdepasse", "utilisateur")
    response = web_client.post(
        "/login",
        data={"email": "test-auth-invalide@example.com", "mot_de_passe": "mauvais"},
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/login"


def test_login_with_valid_credentials_grants_access(web_client: httpx.Client) -> None:
    _creer_utilisateur("test-auth-valide@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        response = client.post(
            "/login",
            data={"email": "test-auth-valide@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        assert response.status_code == 302
        assert response.headers["location"] == "/"
```

Ajouter `bcrypt` à `tests/requirements.txt` (nouvelle ligne, ne pas retirer les lignes
existantes) :
```
bcrypt==4.2.1
```

- [ ] **Step 2: Lancer le test pour vérifier qu'il échoue**

Run:
```bash
tests/.venv/bin/pip install -r tests/requirements.txt
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/test_auth.py -v
```
Expected : échec — la route `/login` n'existe pas encore (404), ou `psycopg` échoue si les
colonnes `role`/`actif` n'existent pas encore (auquel cas vérifier que la Task 1 est bien
appliquée avant de continuer).

- [ ] **Step 3: Créer `web/public/Models/UtilisateurModel.php`**

```php
<?php
namespace Models;

use PDO;

class UtilisateurModel {
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function verifierIdentifiants($email, $motDePasse) {
        $stmt = $this->db->prepare(
            "SELECT id, email, mot_de_passe, role FROM utilisateurs WHERE email = ? AND actif = true"
        );
        $stmt->execute([$email]);
        $utilisateur = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$utilisateur || !password_verify($motDePasse, $utilisateur['mot_de_passe'])) {
            return null;
        }

        return [
            'id' => (int) $utilisateur['id'],
            'email' => $utilisateur['email'],
            'role' => $utilisateur['role'],
        ];
    }

    public function creerUtilisateur($email, $motDePasse, $role) {
        $hash = password_hash($motDePasse, PASSWORD_BCRYPT);
        $stmt = $this->db->prepare(
            "INSERT INTO utilisateurs (email, mot_de_passe, role) VALUES (?, ?, ?) RETURNING id"
        );
        $stmt->execute([$email, $hash, $role]);
        return (int) $stmt->fetchColumn();
    }

    public function listerUtilisateurs() {
        $stmt = $this->db->query(
            "SELECT id, email, role, actif FROM utilisateurs ORDER BY email ASC"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function desactiverUtilisateur($id) {
        $stmt = $this->db->prepare("UPDATE utilisateurs SET actif = false WHERE id = ?");
        $stmt->execute([$id]);
    }
}
```

- [ ] **Step 4: Créer `web/public/Controllers/AuthController.php`**

```php
<?php
namespace Controllers;

use Models\UtilisateurModel;

class AuthController {
    private $db;

    public function __construct($pdo) {
        $this->db = $pdo;
    }

    public function showLogin() {
        if (isset($_SESSION['user_id'])) {
            header('Location: /');
            exit;
        }
        $erreur = $_SESSION['erreur_login'] ?? null;
        unset($_SESSION['erreur_login']);
        require_once __DIR__ . '/../Views/login.php';
    }

    public function login() {
        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';

        $model = new UtilisateurModel($this->db);
        $utilisateur = $model->verifierIdentifiants($email, $motDePasse);

        if (!$utilisateur) {
            $_SESSION['erreur_login'] = 'Email ou mot de passe incorrect.';
            header('Location: /login');
            exit;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $utilisateur['id'];
        $_SESSION['role'] = $utilisateur['role'];

        header('Location: /');
        exit;
    }

    public function logout() {
        $_SESSION = [];
        session_destroy();
        header('Location: /login');
        exit;
    }
}
```

- [ ] **Step 5: Créer `web/public/Views/login.php`** (version fonctionnelle simple — le design
  deux colonnes/Novity vient en Task 5)

```php
<?php
/** @var string|null $erreur */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <h3 class="fw-bold mb-4">Connexion</h3>

                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 6: Ajouter les routes dans `web/public/index.php`**

Modifier la section des dépendances pour ajouter :
```php
require_once __DIR__ . '/Models/UtilisateurModel.php';
require_once __DIR__ . '/Controllers/AuthController.php';
```
(à ajouter juste après la ligne existante `require_once __DIR__ . '/Models/ProfilModel.php';`)

Ajouter l'import en haut du fichier, avec les `use` existants :
```php
use Controllers\AuthController;
```

Ajouter ces deux routes, juste avant le `elseif (strpos($uri, '/import/upload') !== false)`
existant :
```php
if (preg_match('/^\/login\/?$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AuthController($pdo))->login();
}
elseif (preg_match('/^\/login\/?$/', $uri)) {
    (new AuthController($pdo))->showLogin();
}
elseif (strpos($uri, '/import/upload') !== false) {
```
(la ligne `elseif (strpos($uri, '/import/upload') !== false)` remplace le `if` d'origine — il ne
doit rester qu'un seul `if` en tête de la chaîne, celui de `/login`)

- [ ] **Step 7: Lancer le test pour vérifier qu'il passe**

Run:
```bash
docker compose up -d --build web
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/test_auth.py -v
```
Expected: `2 passed`.

- [ ] **Step 8: Commit**

```bash
git add web/public/Models/UtilisateurModel.php web/public/Controllers/AuthController.php \
        web/public/Views/login.php web/public/index.php \
        tests/web/test_auth.py tests/requirements.txt
git commit -m "feat(web): authentification email/mot de passe (login fonctionnel, sans garde globale)"
```

---

### Task 3: Garde d'authentification globale + utilisateur réel sur les imports

**Files:**
- Modify: `web/public/index.php`
- Modify: `web/public/Controllers/ImportController.php:52`
- Modify: `tests/web/test_import_flow.py` (les tests existants doivent maintenant s'authentifier)
- Modify: `tests/web/test_auth.py`

**Interfaces:**
- Consumes: `$_SESSION['user_id']`, `AuthController` (Task 2)
- Produces: toute route non listée dans `/login` redirige `302` vers `/login` si
  `$_SESSION['user_id']` est absent — comportement consommé implicitement par toutes les pages
  existantes et par la Task 4.

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `tests/web/test_auth.py` :

```python
def test_protected_route_redirects_to_login_when_not_authenticated() -> None:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        response = client.get("/")
        assert response.status_code == 302
        assert response.headers["location"] == "/login"


def test_logout_revokes_access() -> None:
    _creer_utilisateur("test-auth-logout@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-logout@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        assert client.get("/").status_code == 200

        client.post("/logout")

        response = client.get("/")
        assert response.status_code == 302
        assert response.headers["location"] == "/login"
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run:
```bash
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/test_auth.py -v -k "protected_route or logout"
```
Expected: `test_protected_route_redirects_to_login_when_not_authenticated` échoue (reçoit un 200,
pas de garde en place) ; `test_logout_revokes_access` échoue (`/logout` n'existe pas encore).

- [ ] **Step 3: Ajouter la garde et la route `/logout` dans `web/public/index.php`**

Juste après la ligne `$uri = str_replace('/cv-analysis/web/public', '', $uri);` et avant le
commentaire `// Déclaration de la variable pour stocker les résultats...`, ajouter :
```php
// Garde d'authentification : tout sauf /login exige une session active
if (strpos($uri, '/login') !== 0 && !isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}
```

Ajouter la route de déconnexion. Le routage doit ressembler à ceci (seul le bloc `/logout` est
nouveau ; les deux blocs `/login` existent déjà depuis la Task 2) :
```php
if (preg_match('/^\/login\/?$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AuthController($pdo))->login();
}
elseif (preg_match('/^\/login\/?$/', $uri)) {
    (new AuthController($pdo))->showLogin();
}
elseif (preg_match('/^\/logout\/?$/', $uri) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    (new AuthController($pdo))->logout();
}
elseif (strpos($uri, '/import/upload') !== false) {
```
(insérer le nouveau bloc `elseif (preg_match('/^\/logout\/?$/', ...))` entre le second bloc
`/login` et le `elseif (strpos($uri, '/import/upload') !== false)` existant)

- [ ] **Step 4: Remplacer l'utilisateur en dur dans `web/public/Controllers/ImportController.php`**

```php
// Avant (ligne 52)
'importe_par_user_id' => 1,

// Après
'importe_par_user_id' => $_SESSION['user_id'],
```

- [ ] **Step 5: Mettre à jour `tests/web/test_import_flow.py` pour s'authentifier**

Ces tests appellent désormais des routes protégées ; il faut créer un utilisateur de test et se
connecter avant chaque scénario. Remplacer le contenu du fichier par :

```python
import os
import time

import bcrypt
import httpx
import psycopg
import pytest

WEB_URL = os.environ.get("WEB_URL", "http://localhost:8080")
DATABASE_URL = os.environ.get(
    "DATABASE_URL", "postgresql://cv:cv@localhost:5433/cv_analysis"
)

BLANK_PDF = b"""%PDF-1.1
1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj
2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj
3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 200 200] >> endobj
trailer << /Root 1 0 R /Size 4 >>
%%EOF
"""


def _creer_utilisateur(email: str, mot_de_passe: str, role: str) -> None:
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
                """,
                (email, hash_, role),
            )
        conn.commit()


@pytest.fixture(scope="module")
def web_client() -> httpx.Client:
    _creer_utilisateur("test-import-flow@example.com", "bonmotdepasse", "utilisateur")
    with httpx.Client(base_url=WEB_URL, timeout=15.0) as client:
        client.post(
            "/login",
            data={"email": "test-import-flow@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        yield client


def _upload(web_client: httpx.Client) -> int:
    response = web_client.post(
        "/import/upload",
        files={"cv_file": ("cv-vide.pdf", BLANK_PDF, "application/pdf")},
    )
    assert response.status_code in (302, 303)
    location = response.headers["location"]
    assert location.startswith("/import/status/")
    return int(location.rsplit("/", 1)[-1])


def test_upload_redirects_to_status_page(web_client: httpx.Client) -> None:
    job_id = _upload(web_client)
    assert job_id > 0


def test_job_status_endpoint_reports_echec_for_empty_pdf(
    web_client: httpx.Client,
) -> None:
    job_id = _upload(web_client)

    status = None
    for _ in range(20):
        response = web_client.get(f"/import/job/{job_id}/status")
        assert response.status_code == 200
        status = response.json()
        if status["statut"] != "en cours":
            break
        time.sleep(0.5)

    assert status["statut"] == "échoué"
    assert status["profile_id"] is None


def test_job_status_unknown_id_returns_404(web_client: httpx.Client) -> None:
    response = web_client.get("/import/job/999999999/status")
    assert response.status_code == 404
```

- [ ] **Step 6: Rebuild, lancer les tests d'auth puis toute la suite**

Run:
```bash
docker compose up -d --build web
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/ -v
```
Expected: tous les tests de `tests/web/test_auth.py` et `tests/web/test_import_flow.py` passent
(`test_upload_redirects_to_status_page` etc. passent désormais authentifiés).

Puis toute la suite pour vérifier l'absence de régression ailleurs :
```bash
WEB_URL=http://localhost:8080 IMPORT_URL=http://localhost:8001 \
DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/ -v
```
Expected : tous les tests passent sauf `test_web_container_starts` dans
`tests/bootstrap/test_infra.py` (échec préexistant sans rapport, attend `"OK"` littéral sur `/`)
et `test_pdf_volume_mounted_on_web_and_import` — **attention** : ce dernier appelle
`web_client.get("/health.php")`, qui est servi directement par Apache (fichier statique, hors du
routage `index.php`) donc **non concerné par la garde d'authentification** ; s'il échoue,
investiguer avant de continuer, ce serait une régression réelle.

- [ ] **Step 7: Commit**

```bash
git add web/public/index.php web/public/Controllers/ImportController.php \
        tests/web/test_import_flow.py tests/web/test_auth.py
git commit -m "feat(web): garde d'authentification globale, déconnexion, utilisateur réel sur les imports"
```

---

### Task 4: Administration des comptes utilisateurs

**Files:**
- Create: `web/public/Controllers/AdminController.php`
- Create: `web/public/Views/admin_utilisateurs.php`
- Modify: `web/public/index.php`
- Modify: `web/public/Views/import.php`
- Modify: `web/public/Views/revue_liste.php`
- Modify: `web/public/Views/revue.php`
- Test: `tests/web/test_auth.py`

**Interfaces:**
- Consumes: `UtilisateurModel::listerUtilisateurs()/creerUtilisateur()/desactiverUtilisateur()`
  (Task 2), `$_SESSION['role']` (Task 2/3)
- Produces: routes `GET /admin/utilisateurs`, `POST /admin/utilisateurs/create`,
  `POST /admin/utilisateurs/(\d+)/desactiver`, toutes protégées (403 si non-admin).

- [ ] **Step 1: Écrire les tests qui échouent**

Ajouter à la fin de `tests/web/test_auth.py` :

```python
def test_non_admin_cannot_access_admin_page() -> None:
    _creer_utilisateur("test-auth-standard@example.com", "bonmotdepasse", "utilisateur")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-standard@example.com", "mot_de_passe": "bonmotdepasse"},
        )
        response = client.get("/admin/utilisateurs")
        assert response.status_code == 403


def test_admin_can_create_and_deactivate_user() -> None:
    _creer_utilisateur("test-auth-admin@example.com", "bonmotdepasse", "admin")

    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={"email": "test-auth-admin@example.com", "mot_de_passe": "bonmotdepasse"},
        )

        response = client.get("/admin/utilisateurs")
        assert response.status_code == 200

        response = client.post(
            "/admin/utilisateurs/create",
            data={
                "email": "test-auth-cree-par-admin@example.com",
                "mot_de_passe": "unautremotdepasse",
                "role": "utilisateur",
            },
        )
        assert response.status_code == 302

        with psycopg.connect(DATABASE_URL) as conn:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT id, actif FROM utilisateurs WHERE email = %s",
                    ("test-auth-cree-par-admin@example.com",),
                )
                user_id, actif = cur.fetchone()
                assert actif is True

        response = client.post(f"/admin/utilisateurs/{user_id}/desactiver")
        assert response.status_code == 302

        with psycopg.connect(DATABASE_URL) as conn:
            with conn.cursor() as cur:
                cur.execute("SELECT actif FROM utilisateurs WHERE id = %s", (user_id,))
                assert cur.fetchone()[0] is False
```

- [ ] **Step 2: Lancer les tests pour vérifier qu'ils échouent**

Run:
```bash
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/test_auth.py -v -k admin
```
Expected: échec — la route `/admin/utilisateurs` n'existe pas encore (redirection ou 404 selon
le routage actuel).

- [ ] **Step 3: Créer `web/public/Controllers/AdminController.php`**

```php
<?php
namespace Controllers;

use Models\UtilisateurModel;

class AdminController {
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

    public function listerUtilisateurs() {
        $this->verifierAdmin();
        $model = new UtilisateurModel($this->db);
        $utilisateurs = $model->listerUtilisateurs();
        $erreur = $_SESSION['erreur_admin'] ?? null;
        unset($_SESSION['erreur_admin']);
        require_once __DIR__ . '/../Views/admin_utilisateurs.php';
    }

    public function creerUtilisateur() {
        $this->verifierAdmin();

        $email = trim($_POST['email'] ?? '');
        $motDePasse = $_POST['mot_de_passe'] ?? '';
        $role = $_POST['role'] ?? 'utilisateur';

        if ($email === '' || $motDePasse === '' || !in_array($role, ['admin', 'utilisateur'], true)) {
            $_SESSION['erreur_admin'] = 'Champs invalides.';
            header('Location: /admin/utilisateurs');
            exit;
        }

        $model = new UtilisateurModel($this->db);
        try {
            $model->creerUtilisateur($email, $motDePasse, $role);
        } catch (\PDOException $e) {
            $_SESSION['erreur_admin'] = 'Impossible de créer ce compte (email déjà utilisé ?).';
        }

        header('Location: /admin/utilisateurs');
        exit;
    }

    public function desactiverUtilisateur($id) {
        $this->verifierAdmin();
        $model = new UtilisateurModel($this->db);
        $model->desactiverUtilisateur((int) $id);
        header('Location: /admin/utilisateurs');
        exit;
    }
}
```

- [ ] **Step 4: Créer `web/public/Views/admin_utilisateurs.php`**

```php
<?php
/** @var array $utilisateurs */
/** @var string|null $erreur */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Administration des comptes</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root { --primary: #2563eb; --bg: #f8fafc; }
        body { background-color: var(--bg); font-family: 'Inter', system-ui, sans-serif; }
        .navbar-custom { background: white; border-radius: 12px; padding: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .nav-pills .nav-link { color: #64748b; font-weight: 500; }
        .nav-pills .nav-link.active { background-color: var(--primary); }
        .card { border: none; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center mb-4">
            <div class="col-md-8">
                <nav class="navbar-custom d-flex justify-content-between align-items-center px-2">
                    <div class="nav nav-pills nav-fill flex-grow-1 me-2">
                        <a class="nav-link" href="/"><i class="bi bi-upload"></i> Import</a>
                        <a class="nav-link" href="/revue"><i class="bi bi-list-check"></i> Revue</a>
                        <a class="nav-link active" href="/admin/utilisateurs"><i class="bi bi-people"></i> Admin</a>
                    </div>
                    <form method="POST" action="/logout" class="m-0">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
                    </form>
                </nav>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card p-4 mb-4">
                    <h3 class="fw-bold mb-4">Créer un compte</h3>

                    <?php if (!empty($erreur)): ?>
                        <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
                    <?php endif; ?>

                    <form method="POST" action="/admin/utilisateurs/create" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label small">Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Mot de passe</label>
                            <input type="password" name="mot_de_passe" class="form-control" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Rôle</label>
                            <select name="role" class="form-select">
                                <option value="utilisateur">Utilisateur</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Créer</button>
                        </div>
                    </form>
                </div>

                <div class="card p-4">
                    <h5 class="mb-3">Comptes existants</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
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
                                                <span class="badge bg-success">Actif</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Désactivé</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($u['actif']): ?>
                                                <form method="POST" action="/admin/utilisateurs/<?= $u['id'] ?>/desactiver"
                                                      onsubmit="return confirm('Désactiver ce compte ?');">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Désactiver</button>
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
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 5: Ajouter les routes dans `web/public/index.php`**

Ajouter la dépendance, avec les autres `require_once` :
```php
require_once __DIR__ . '/Controllers/AdminController.php';
```
Ajouter l'import :
```php
use Controllers\AdminController;
```
Ajouter les routes, juste avant le `else { (new ImportController($pdo))->index(); }` final :
```php
elseif (preg_match('/^\/admin\/utilisateurs\/create$/', $uri)) {
    (new AdminController($pdo))->creerUtilisateur();
}
elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/desactiver$/', $uri, $matches)) {
    (new AdminController($pdo))->desactiverUtilisateur($matches[1]);
}
elseif (preg_match('/^\/admin\/utilisateurs\/?$/', $uri)) {
    (new AdminController($pdo))->listerUtilisateurs();
}
```

- [ ] **Step 6: Ajouter le lien Admin (conditionnel) et la déconnexion dans `web/public/Views/import.php`**

```php
<!-- Avant -->
<nav class="navbar-custom">
    <div class="nav nav-pills nav-fill">
        <a class="nav-link active" href="/cv-analysis/web/public/"><i class="bi bi-upload"></i> Import</a>
        <a class="nav-link" href="/cv-analysis/web/public/revue"><i class="bi bi-list-check"></i> Revue</a>
    </div>
</nav>

<!-- Après -->
<nav class="navbar-custom d-flex justify-content-between align-items-center px-2">
    <div class="nav nav-pills nav-fill flex-grow-1 me-2">
        <a class="nav-link active" href="/cv-analysis/web/public/"><i class="bi bi-upload"></i> Import</a>
        <a class="nav-link" href="/cv-analysis/web/public/revue"><i class="bi bi-list-check"></i> Revue</a>
        <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
            <a class="nav-link" href="/admin/utilisateurs"><i class="bi bi-people"></i> Admin</a>
        <?php endif; ?>
    </div>
    <form method="POST" action="/logout" class="m-0">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
    </form>
</nav>
```

- [ ] **Step 7: Même changement dans `web/public/Views/revue_liste.php`**

```php
<!-- Avant -->
<nav class="navbar-custom">
    <div class="nav nav-pills nav-fill">
        <a class="nav-link" href="/"><i class="bi bi-upload"></i> Import</a>
        <a class="nav-link active" href="/revue"><i class="bi bi-list-check"></i> Revue</a>
    </div>
</nav>

<!-- Après -->
<nav class="navbar-custom d-flex justify-content-between align-items-center px-2">
    <div class="nav nav-pills nav-fill flex-grow-1 me-2">
        <a class="nav-link" href="/"><i class="bi bi-upload"></i> Import</a>
        <a class="nav-link active" href="/revue"><i class="bi bi-list-check"></i> Revue</a>
        <?php if (($_SESSION['role'] ?? null) === 'admin'): ?>
            <a class="nav-link" href="/admin/utilisateurs"><i class="bi bi-people"></i> Admin</a>
        <?php endif; ?>
    </div>
    <form method="POST" action="/logout" class="m-0">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
    </form>
</nav>
```

- [ ] **Step 8: Ajouter la déconnexion dans `web/public/Views/revue.php`**

```php
<!-- Avant -->
<div class="mb-3">
    <a href="/cv-analysis/web/public/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
</div>

<!-- Après -->
<div class="mb-3 d-flex justify-content-between align-items-center">
    <a href="/cv-analysis/web/public/" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left"></i> Retour
    </a>
    <form method="POST" action="/logout" class="m-0">
        <button type="submit" class="btn btn-sm btn-outline-secondary">Déconnexion</button>
    </form>
</div>
```

- [ ] **Step 9: Rebuild et lancer les tests**

Run:
```bash
docker compose up -d --build web
WEB_URL=http://localhost:8080 DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/web/test_auth.py -v
```
Expected: `6 passed`.

- [ ] **Step 10: Commit**

```bash
git add web/public/Controllers/AdminController.php web/public/Views/admin_utilisateurs.php \
        web/public/index.php web/public/Views/import.php web/public/Views/revue_liste.php \
        web/public/Views/revue.php tests/web/test_auth.py
git commit -m "feat(web): page d'administration des comptes utilisateurs (rôle admin)"
```

---

### Task 5: Design visuel de la page de login (identité Novity) + durcissement des sessions

**Files:**
- Modify: `web/public/Views/login.php`
- Modify: `web/Dockerfile`

**Interfaces:** aucune (design visuel + configuration, pas de nouveau contrat)

- [ ] **Step 1: Réécrire `web/public/Views/login.php` en deux colonnes**

```php
<?php
/** @var string|null $erreur */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novity | Connexion</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --novity-noir: #0A0A0A; --novity-vert: #16A34A; }
        body { margin: 0; font-family: 'Inter', system-ui, sans-serif; }
        .login-split { display: flex; min-height: 100vh; }
        .login-image {
            flex: 1;
            background-image: linear-gradient(rgba(10, 10, 10, 0.55), rgba(10, 10, 10, 0.55)),
                url('https://images.unsplash.com/photo-1600880292203-943bd5ac4dcb?auto=format&fit=crop&w=1200&q=80');
            background-size: cover;
            background-position: center;
            display: flex;
            align-items: flex-end;
            padding: 40px;
        }
        .login-image h2 { color: #fff; font-weight: 700; }
        .login-form-side {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #fff;
            padding: 40px;
        }
        .login-card { width: 100%; max-width: 380px; }
        .btn-novity { background-color: var(--novity-vert); border-color: var(--novity-vert); }
        .btn-novity:hover { background-color: #128a3e; border-color: #128a3e; }
        .form-control:focus { border-color: var(--novity-vert); box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.25); }
        @media (max-width: 768px) { .login-image { display: none; } }
    </style>
</head>
<body>
    <div class="login-split">
        <div class="login-image">
            <h2>Novity CV Analyser</h2>
        </div>
        <div class="login-form-side">
            <div class="login-card">
                <h3 class="fw-bold mb-4" style="color: var(--novity-noir);">Connexion</h3>

                <?php if (!empty($erreur)): ?>
                    <div class="alert alert-danger py-2"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>

                <form method="POST" action="/login">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required autofocus>
                    </div>
                    <div class="mb-4">
                        <label class="form-label">Mot de passe</label>
                        <input type="password" name="mot_de_passe" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-novity w-100 text-white">Se connecter</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
```

**Important** : je ne peux pas visuellement vérifier le contenu de la photo Unsplash référencée
(pas d'outil de rendu d'image disponible dans cet environnement). Après le rebuild, ouvrir
`http://localhost:8080/login` dans un vrai navigateur et vérifier que l'image représente bien des
personnes dans un contexte professionnel, sinon remplacer l'URL par une autre photo Unsplash de
son choix (même format d'URL `images.unsplash.com/photo-<id>?auto=format&fit=crop&w=1200&q=80`).

- [ ] **Step 2: Durcir la configuration de session dans `web/Dockerfile`**

```dockerfile
# Avant
RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY public/ /var/www/html/

# Après
RUN { \
    echo 'upload_max_filesize = 12M'; \
    echo 'post_max_size = 12M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

RUN { \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.cookie_samesite = Lax'; \
    } > /usr/local/etc/php/conf.d/session.ini

COPY public/ /var/www/html/
```

- [ ] **Step 3: Rebuild et vérifier**

Run:
```bash
docker compose up -d --build web
curl -s -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/login
docker compose exec -T web php -i | grep -iE "session.cookie_httponly|session.cookie_samesite"
```
Expected: `HTTP 200`, et `session.cookie_httponly => On => On`,
`session.cookie_samesite => Lax => Lax`.

Puis relancer toute la suite de tests pour confirmer l'absence de régression :
```bash
WEB_URL=http://localhost:8080 IMPORT_URL=http://localhost:8001 \
DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/ -v
```

- [ ] **Step 4: Commit**

```bash
git add web/public/Views/login.php web/Dockerfile
git commit -m "feat(web): design de la page de login (deux colonnes, identité Novity) + durcissement des cookies de session"
```

---

### Task 6: Vérification manuelle complète

**Files:** aucun changement de fichier — vérification uniquement.

- [ ] **Step 1: Rebuild complet**

```bash
docker compose down
docker compose up -d --build
docker compose ps -a
```
Expected: les 4 services démarrent (`migrate` en `exited (0)`, les autres `running`).

- [ ] **Step 2: Seed du premier admin et connexion**

```bash
./scripts/seed-admin.sh admin@novity.io changeme123
curl -s -c /tmp/cookies.txt -D - -o /dev/null \
  -d "email=admin@novity.io" -d "mot_de_passe=changeme123" \
  http://localhost:8080/login
```
Expected: `HTTP/1.1 302 Found`, `Location: /`.

- [ ] **Step 3: Vérifier l'accès à la page CV Analysis après connexion**

```bash
curl -s -b /tmp/cookies.txt -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/
```
Expected: `HTTP 200`.

- [ ] **Step 4: Vérifier l'accès admin et la création d'un compte**

```bash
curl -s -b /tmp/cookies.txt -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/admin/utilisateurs
curl -s -b /tmp/cookies.txt -D - -o /dev/null \
  -d "email=nouveau@novity.io" -d "mot_de_passe=motdepasse123" -d "role=utilisateur" \
  http://localhost:8080/admin/utilisateurs/create
docker compose exec -T db psql -U cv -d cv_analysis -c "SELECT email, role, actif FROM utilisateurs WHERE email = 'nouveau@novity.io';"
```
Expected: `HTTP 200` sur la page admin, `302` sur la création, une ligne
`nouveau@novity.io | utilisateur | t` en base.

- [ ] **Step 5: Vérifier qu'un utilisateur non-admin ne peut pas accéder à `/admin/utilisateurs`**

```bash
curl -s -c /tmp/cookies-user.txt -D - -o /dev/null \
  -d "email=nouveau@novity.io" -d "mot_de_passe=motdepasse123" \
  http://localhost:8080/login
curl -s -b /tmp/cookies-user.txt -o /dev/null -w "HTTP %{http_code}\n" http://localhost:8080/admin/utilisateurs
```
Expected: `403`.

- [ ] **Step 6: Vérifier la déconnexion et la garde**

```bash
curl -s -b /tmp/cookies-user.txt -D - -o /dev/null -X POST http://localhost:8080/logout
curl -s -b /tmp/cookies-user.txt -D - -o /dev/null http://localhost:8080/
rm -f /tmp/cookies.txt /tmp/cookies-user.txt
```
Expected: la seconde requête reçoit `302` vers `/login`.

- [ ] **Step 7: Vérification visuelle de la page de login**

Ouvrir `http://localhost:8080/login` dans un navigateur réel. Confirmer que la photo à gauche
représente bien des personnes dans un contexte professionnel (sinon, remplacer l'URL Unsplash
comme indiqué en Task 5 Step 1) et que le formulaire à droite est lisible avec le bouton vert.

- [ ] **Step 8: Suite de tests complète**

```bash
WEB_URL=http://localhost:8080 IMPORT_URL=http://localhost:8001 \
DATABASE_URL=postgresql://cv:cv@localhost:5433/cv_analysis \
  tests/.venv/bin/pytest tests/ -v
```
Expected: tous les tests passent sauf `test_web_container_starts` (échec préexistant sans
rapport, déjà documenté dans les tâches précédentes).
