# Modification de compte / Suppression de profil / Cartes plus jolies Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter un formulaire "Modifier" (email/rôle/actif) sur les comptes dans l'admin, une suppression de profil avec confirmation dans la liste Revue, et restyler les cartes de profils (avatar + hiérarchie visuelle).

**Architecture:** Extensions Model/Controller/route classiques du MVC PHP existant (aucun changement d'architecture). Une migration SQL ajuste une contrainte de clé étrangère pour permettre la suppression de profil sans casser l'historique d'import. Le formulaire "Modifier" utilise un `<tbody>` Alpine par ligne de tableau (pattern nécessaire car `x-data` ne s'étend qu'aux descendants, pas aux lignes sœurs). La carte de profil passe d'un `<a>` unique à un lien "étiré" (*stretched link*) pour pouvoir accueillir un bouton de suppression sans navigation accidentelle.

**Tech Stack:** PHP 8.3 (Controllers/Models/Views), PostgreSQL 16 (migration), Alpine.js (déjà chargé), pytest + httpx + psycopg (tests d'intégration existants dans `tests/web/`).

## Global Constraints

- Spec de référence : `docs/superpowers/specs/2026-07-16-admin-modifier-revue-supprimer-design.md`.
- **Pas de suppression définitive de compte utilisateur** — uniquement modification (email, rôle,
  actif) et le mécanisme de désactivation déjà existant. Ne pas ajouter de vrai `DELETE` sur
  `utilisateurs`.
- Le formulaire "Modifier" ne touche jamais au mot de passe.
- La suppression de profil est un vrai `DELETE` — `experiences`/`formations`/`competences`
  suivent via les cascades déjà en place (`ON DELETE CASCADE`) ; `import_jobs.profile_id` doit
  passer à `NULL` via la migration de ce plan (Task 1), pas être ignoré ni bloquer la suppression.
- Toute nouvelle route de mutation (`/profil/delete/{id}`, `/admin/utilisateurs/{id}/modifier`)
  doit exiger `$_SERVER['REQUEST_METHOD'] === 'POST'` explicitement — précédent de sécurité déjà
  établi dans ce projet (cf. `test_admin_create_user_via_get_is_rejected`, corrige un bypass GET
  historique). Ne pas omettre cette garde.
- Le nom de contrainte `import_jobs_profile_id_fkey` est confirmé (auto-généré par Postgres,
  vérifié via `\d import_jobs` sur la base actuelle) — l'utiliser tel quel dans la migration.
- Pas de nouveaux tokens CSS/couleurs — réutiliser `badge-secondary`, `badge-success`,
  `badge-warning`, `btn-outline`, `btn-default`, `input`, `label` existants.

---

### Task 1: Migration — `import_jobs.profile_id` en `ON DELETE SET NULL`

**Files:**
- Create: `migrations/005_import_jobs_profile_id_set_null.sql`

**Interfaces:**
- Produces : la contrainte FK modifiée, consommée par Task 2 (la suppression de profil doit
  réussir même si un `import_jobs` y fait référence).

- [ ] **Step 1: Créer le fichier de migration**

Create `migrations/005_import_jobs_profile_id_set_null.sql` :

```sql
ALTER TABLE import_jobs DROP CONSTRAINT import_jobs_profile_id_fkey;
ALTER TABLE import_jobs ADD CONSTRAINT import_jobs_profile_id_fkey
    FOREIGN KEY (profile_id) REFERENCES profils (id) ON DELETE SET NULL;
```

- [ ] **Step 2: Rebuild complet et vérifier que la migration s'applique**

Run:
```bash
docker compose up -d --build
docker compose logs migrate --tail=20
```
Expected : le service `migrate` termine sans erreur (`exited (0)` dans `docker compose ps -a`),
les logs ne montrent aucune erreur SQL sur la migration 005.

- [ ] **Step 3: Vérifier la nouvelle contrainte directement en base**

Run:
```bash
docker compose exec -T db psql -U cv -d cv_analysis -c "\d import_jobs" | grep profile_id
```
Expected : la ligne affiche `ON DELETE SET NULL` pour `import_jobs_profile_id_fkey`.

- [ ] **Step 4: Commit**

```bash
git add migrations/005_import_jobs_profile_id_set_null.sql
git commit -m "feat(db): import_jobs.profile_id passe à NULL si le profil est supprimé"
```

---

### Task 2: Backend — suppression de profil (TDD)

**Files:**
- Modify: `web/public/Models/ProfilModel.php` (+ `deleteProfil`, après `updateProfil`, avant le
  commentaire `// ---------- LISTE / RECHERCHE ----------`)
- Modify: `web/public/Controllers/RevueController.php` (+ `deleteProfil`, après `updateProfil`,
  avant le commentaire `// ---------- EXPERIENCES ----------`)
- Modify: `web/public/index.php` (+ 1 route)
- Create: `tests/web/test_profil_suppression.py`

**Interfaces:**
- Produces : `ProfilModel::deleteProfil($id)`, `RevueController::deleteProfil($id)`, route
  `POST /profil/delete/{id}` → redirige vers `/revue`. Consommé par Task 3 (bouton dans la vue).

- [ ] **Step 1: Écrire le test (encore en échec — la route n'existe pas)**

Create `tests/web/test_profil_suppression.py` :

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


def _creer_profil_complet(email: str, user_id: int) -> tuple[int, int]:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES ('Profil A Supprimer', %s, 'à valider', %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET statut = EXCLUDED.statut
                RETURNING id
                """,
                (email, user_id),
            )
            profil_id = cur.fetchone()[0]

            cur.execute(
                "INSERT INTO experiences (profil_id, entreprise, poste) VALUES (%s, %s, %s) RETURNING id",
                (profil_id, "ACME", "Développeur"),
            )
            experience_id = cur.fetchone()[0]

            cur.execute(
                "INSERT INTO competences (experience_id, libelle) VALUES (%s, %s)",
                (experience_id, "PHP"),
            )

            cur.execute(
                "INSERT INTO formations (profil_id, intitule) VALUES (%s, %s)",
                (profil_id, "Master Informatique"),
            )

            cur.execute(
                """
                INSERT INTO import_jobs (statut, importe_par_user_id, profile_id, nom_fichier)
                VALUES ('réussi', %s, %s, %s)
                RETURNING id
                """,
                (user_id, profil_id, "cv-a-supprimer.pdf"),
            )
            import_job_id = cur.fetchone()[0]
        conn.commit()
    return profil_id, import_job_id


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-suppression-profil@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-suppression-profil@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_delete_profil_removes_it_and_nullifies_import_job_link(
    web_client: httpx.Client, user_id: int
) -> None:
    profil_id, import_job_id = _creer_profil_complet(
        "test-delete-profil-1@example.com", user_id
    )

    response = web_client.post(f"/profil/delete/{profil_id}", data={})
    assert response.status_code == 302
    assert response.headers["location"] == "/revue"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM profils WHERE id = %s", (profil_id,))
            assert cur.fetchone() is None

            cur.execute(
                "SELECT profile_id FROM import_jobs WHERE id = %s", (import_job_id,)
            )
            row = cur.fetchone()
            assert row is not None
            assert row[0] is None


def test_delete_profil_via_get_is_rejected(
    web_client: httpx.Client, user_id: int
) -> None:
    profil_id, _ = _creer_profil_complet("test-delete-profil-2@example.com", user_id)

    response = web_client.get(f"/profil/delete/{profil_id}")
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT id FROM profils WHERE id = %s", (profil_id,))
            assert cur.fetchone() is not None
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run:
```bash
tests/.venv/bin/pytest tests/web/test_profil_suppression.py -v
```
Expected : `test_delete_profil_removes_it_and_nullifies_import_job_link` échoue (404 ou fallback
sur la page Import, la route n'existe pas encore) ; `test_delete_profil_via_get_is_rejected` peut
déjà passer par accident (la route n'existant pas, GET tombe déjà sur le catch-all) — vérifier
dans la sortie que c'est bien le premier test qui échoue pour la bonne raison (pas de route
`/profil/delete/*`).

- [ ] **Step 3: Ajouter `ProfilModel::deleteProfil`**

Dans `web/public/Models/ProfilModel.php`, juste après la méthode `updateProfil` (avant le
commentaire `// ---------- LISTE / RECHERCHE ----------`) :

```php
    public function deleteProfil($id) {
        $stmt = $this->db->prepare("DELETE FROM profils WHERE id = ?");
        return $stmt->execute([$id]);
    }
```

- [ ] **Step 4: Ajouter `RevueController::deleteProfil`**

Dans `web/public/Controllers/RevueController.php`, juste après la méthode `updateProfil` (avant
le commentaire `// ---------- EXPERIENCES ----------`) :

```php
    public function deleteProfil($id) {
        $model = new ProfilModel($this->db);
        $model->deleteProfil($id);
        header('Location: /revue');
        exit;
    }
```

- [ ] **Step 5: Ajouter la route**

Dans `web/public/index.php`, remplacer :
```php
    elseif (preg_match('/\/formation\/delete\/(\d+)$/', $uri, $matches)) {
        (new RevueController($pdo))->deleteFormation($matches[1]);
    }
    elseif (preg_match('/\/revue\/?$/', $uri)) {
```
par :
```php
    elseif (preg_match('/\/formation\/delete\/(\d+)$/', $uri, $matches)) {
        (new RevueController($pdo))->deleteFormation($matches[1]);
    }
    elseif (preg_match('/\/profil\/delete\/(\d+)$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        (new RevueController($pdo))->deleteProfil($matches[1]);
    }
    elseif (preg_match('/\/revue\/?$/', $uri)) {
```

- [ ] **Step 6: Rebuild et vérifier que les tests passent**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_profil_suppression.py -v
```
Expected : les 2 tests passent.

- [ ] **Step 7: Commit**

```bash
git add web/public/Models/ProfilModel.php web/public/Controllers/RevueController.php web/public/index.php tests/web/test_profil_suppression.py
git commit -m "feat(web): suppression de profil (backend + route)"
```

---

### Task 3: Frontend — bouton supprimer + restyle des cartes (revue_liste.php)

**Files:**
- Modify: `web/public/Views/revue_liste.php` (bloc de la carte de profil, lignes ~74-104 de la
  version actuelle du fichier)

**Interfaces:**
- Consumes : route `POST /profil/delete/{id}` (Task 2).

- [ ] **Step 1: Remplacer le bloc de la boucle des cartes de profils**

Dans `web/public/Views/revue_liste.php`, remplacer exactement ce bloc (l'unique
`<a class="card ...">` par profil) :

```php
                <?php foreach ($profils as $profil): ?>
                    <a href="/revue/<?= $profil['id'] ?>" class="card block p-4 transition-shadow hover:shadow-md">
                        <div class="flex items-start justify-between">
                            <div>
                                <div class="text-base font-semibold"><?= htmlspecialchars($profil['nom'] ?? 'Sans nom') ?></div>
                                <div class="text-sm text-muted-foreground"><?= htmlspecialchars($profil['titre'] ?? '') ?></div>
                            </div>
                            <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
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
```

par :

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

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run:
```bash
docker compose up -d --build web
docker compose exec -T web php -l /var/www/html/Views/revue_liste.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Vérification manuelle du lien étiré (le bouton ne doit pas déclencher la navigation)**

Avec un compte de test connecté (ex. via `scripts/seed-admin.sql`) et au moins un profil visible
sur `/revue` : cliquer sur l'icône poubelle d'une carte doit afficher la confirmation JS
(`Supprimer ce profil ?`) sans naviguer vers `/revue/{id}` au préalable. Annuler la confirmation
doit laisser la page telle quelle (aucune navigation, aucune suppression).

- [ ] **Step 4: Suite de tests complète**

Run:
```bash
tests/.venv/bin/pytest tests/web/ -v
```
Expected : tous les tests passent (aucune route/comportement testé par ailleurs n'est affecté par
ce changement purement visuel + Task 2).

- [ ] **Step 5: Commit**

```bash
git add web/public/Views/revue_liste.php
git commit -m "style(web): restyle les cartes de profils (avatar, hiérarchie) + bouton supprimer"
```

---

### Task 4: Backend — modification de compte (TDD)

**Files:**
- Modify: `web/public/Models/UtilisateurModel.php` (+ `modifierUtilisateur`, après
  `desactiverUtilisateur`)
- Modify: `web/public/Controllers/AdminController.php` (+ `modifierUtilisateur`, après
  `desactiverUtilisateur`)
- Modify: `web/public/index.php` (+ 1 route)
- Create: `tests/web/test_admin_modifier.py`

**Interfaces:**
- Produces : `UtilisateurModel::modifierUtilisateur($id, $email, $role, $actif)`,
  `AdminController::modifierUtilisateur($id)`, route
  `POST /admin/utilisateurs/{id}/modifier` → redirige vers `/admin/utilisateurs`. Consommé par
  Task 5 (formulaire dans la vue).

- [ ] **Step 1: Écrire le test (encore en échec — la route n'existe pas)**

Create `tests/web/test_admin_modifier.py` :

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


@pytest.fixture(scope="module")
def admin_client() -> httpx.Client:
    _creer_utilisateur("test-modifier-admin@example.com", "bonmotdepasse", "admin")
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-modifier-admin@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_admin_can_modify_email_role_and_reactivate(
    admin_client: httpx.Client,
) -> None:
    target_id = _creer_utilisateur(
        "test-modifier-cible@example.com", "bonmotdepasse", "utilisateur"
    )
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "UPDATE utilisateurs SET actif = false WHERE id = %s", (target_id,)
            )
        conn.commit()

    response = admin_client.post(
        f"/admin/utilisateurs/{target_id}/modifier",
        data={
            "email": "test-modifier-cible-v2@example.com",
            "role": "admin",
            "actif": "1",
        },
    )
    assert response.status_code == 302
    assert response.headers["location"] == "/admin/utilisateurs"

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT email, role, actif FROM utilisateurs WHERE id = %s",
                (target_id,),
            )
            email, role, actif = cur.fetchone()
            assert email == "test-modifier-cible-v2@example.com"
            assert role == "admin"
            assert actif is True


def test_admin_modifier_with_duplicate_email_shows_error_and_does_not_change(
    admin_client: httpx.Client,
) -> None:
    existing_email = "test-modifier-existant@example.com"
    _creer_utilisateur(existing_email, "bonmotdepasse", "utilisateur")
    target_id = _creer_utilisateur(
        "test-modifier-cible2@example.com", "bonmotdepasse", "utilisateur"
    )

    response = admin_client.post(
        f"/admin/utilisateurs/{target_id}/modifier",
        data={"email": existing_email, "role": "utilisateur", "actif": "1"},
    )
    assert response.status_code == 302

    page = admin_client.get("/admin/utilisateurs")
    assert "Impossible de modifier ce compte" in page.text

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute("SELECT email FROM utilisateurs WHERE id = %s", (target_id,))
            assert cur.fetchone()[0] == "test-modifier-cible2@example.com"


def test_admin_modifier_via_get_is_rejected(admin_client: httpx.Client) -> None:
    target_id = _creer_utilisateur(
        "test-modifier-get@example.com", "bonmotdepasse", "utilisateur"
    )

    response = admin_client.get(
        f"/admin/utilisateurs/{target_id}/modifier",
        params={"email": "hacked@example.com", "role": "admin", "actif": "1"},
    )
    assert response.status_code == 200

    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT email, role FROM utilisateurs WHERE id = %s", (target_id,)
            )
            email, role = cur.fetchone()
            assert email == "test-modifier-get@example.com"
            assert role == "utilisateur"
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run:
```bash
tests/.venv/bin/pytest tests/web/test_admin_modifier.py -v
```
Expected : `test_admin_can_modify_email_role_and_reactivate` échoue (la route n'existe pas encore,
la mise à jour n'a pas lieu).

- [ ] **Step 3: Ajouter `UtilisateurModel::modifierUtilisateur`**

Dans `web/public/Models/UtilisateurModel.php`, juste après `desactiverUtilisateur` (avant le `}`
final de la classe) :

```php
    public function modifierUtilisateur($id, $email, $role, $actif) {
        $stmt = $this->db->prepare(
            "UPDATE utilisateurs SET email = ?, role = ?, actif = ? WHERE id = ?"
        );
        return $stmt->execute([$email, $role, $actif, $id]);
    }
```

- [ ] **Step 4: Ajouter `AdminController::modifierUtilisateur`**

Dans `web/public/Controllers/AdminController.php`, juste après `desactiverUtilisateur` (avant le
`}` final de la classe) :

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

- [ ] **Step 5: Ajouter la route**

Dans `web/public/index.php`, remplacer :
```php
    elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/desactiver$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        (new AdminController($pdo))->desactiverUtilisateur($matches[1]);
    }
    elseif (preg_match('/^\/admin\/utilisateurs\/?$/', $uri)) {
```
par :
```php
    elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/desactiver$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        (new AdminController($pdo))->desactiverUtilisateur($matches[1]);
    }
    elseif (preg_match('/^\/admin\/utilisateurs\/(\d+)\/modifier$/', $uri, $matches) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        (new AdminController($pdo))->modifierUtilisateur($matches[1]);
    }
    elseif (preg_match('/^\/admin\/utilisateurs\/?$/', $uri)) {
```

- [ ] **Step 6: Rebuild et vérifier que les tests passent**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_admin_modifier.py -v
```
Expected : les 3 tests passent.

- [ ] **Step 7: Commit**

```bash
git add web/public/Models/UtilisateurModel.php web/public/Controllers/AdminController.php web/public/index.php tests/web/test_admin_modifier.py
git commit -m "feat(web): modification de compte utilisateur (backend + route)"
```

---

### Task 5: Frontend — formulaire "Modifier" inline (admin_utilisateurs.php)

**Files:**
- Modify: `web/public/Views/admin_utilisateurs.php` (bloc `<tbody>` du tableau des comptes)

**Interfaces:**
- Consumes : route `POST /admin/utilisateurs/{id}/modifier` (Task 4).

- [ ] **Step 1: Remplacer le bloc `<tbody>` du tableau des comptes**

Dans `web/public/Views/admin_utilisateurs.php`, remplacer :
```php
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
```
par :
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

(Un `<table>` HTML peut contenir plusieurs `<tbody>` — chacun devient son propre scope Alpine
indépendant, ce qui isole `editOpen` par ligne sans état partagé entre utilisateurs.)

- [ ] **Step 2: Vérifier la syntaxe PHP**

Run:
```bash
docker compose up -d --build web
docker compose exec -T web php -l /var/www/html/Views/admin_utilisateurs.php
```
Expected: `No syntax errors detected`.

- [ ] **Step 3: Vérification manuelle**

Avec `scripts/seed-admin.sql` (compte admin) : sur `/admin/utilisateurs`, cliquer "Modifier" sur
une ligne déplie un formulaire avec les valeurs actuelles pré-remplies ; cliquer "Modifier" sur une
AUTRE ligne ne doit pas affecter l'état de la première (scopes Alpine indépendants) ; soumettre le
formulaire persiste bien les changements et referme implicitement le formulaire (rechargement de
page).

- [ ] **Step 4: Suite de tests complète**

Run:
```bash
tests/.venv/bin/pytest tests/web/ -v
```
Expected : tous les tests passent.

- [ ] **Step 5: Commit**

```bash
git add web/public/Views/admin_utilisateurs.php
git commit -m "feat(web): formulaire Modifier inline sur les comptes admin"
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
Expected : tous les tests passent (le nombre total inclut maintenant les 2 nouveaux tests de
suppression de profil + les 3 nouveaux tests de modification de compte, en plus des tests
existants).

- [ ] **Step 2: Vérification manuelle / capture d'écran**

- `/revue` : les cartes de profils affichent bien l'avatar avec initiales, la hiérarchie
  nom/titre, le badge de statut et l'icône poubelle ; supprimer un profil de test fonctionne avec
  confirmation.
- `/admin/utilisateurs` : "Modifier" déplie un formulaire pré-rempli, la sauvegarde fonctionne
  pour l'email/le rôle/le statut actif, réactiver un compte désactivé fonctionne.
- Aucune régression sur les pages non touchées (`import.php`, `revue.php`).

Pas de commit pour cette tâche (vérification uniquement).
