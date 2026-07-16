# Couleurs de statut (warning/info/success) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ajouter des tokens de couleur sémantiques de statut (success/warning/info) à la palette Tailwind/shadcn du dashboard et les appliquer aux badges de statut existants dans `revue_liste.php` et `import.php`, en particulier pour corriger le statut `échoué` actuellement affiché en gris neutre.

**Architecture:** Extension pure CSS (variables `:root` + classes `@layer components` dans `input.css`, mapping dans `tailwind.config.js`), plus mise à jour de la classe CSS choisie dans 2 vues PHP existantes selon la valeur réelle du champ `statut` déjà en base. Aucun changement de Controller, Model, route ou schéma BDD.

**Tech Stack:** Tailwind CSS (CLI standalone, compilé au build Docker), PHP 8.3 (vues), pytest + httpx + psycopg (tests d'intégration black-box existants dans `tests/web/`).

## Global Constraints

- Spec de référence : `docs/superpowers/specs/2026-07-16-couleurs-statut-design.md`.
- Palette : `--warning: 217 119 6` (`#d97706`), `--info: 37 99 235` (`#2563eb`), `--success: 22 163 74` (`#16a34a`, alias de `--primary`) — valeurs approuvées, ne pas les changer.
- Ne pas toucher `login.php` ni `import_status.php` (hors scope, Bootstrap CDN séparé).
- Ne pas renommer `badge-default`/`badge-secondary`/`badge-destructive`/`alert-destructive` existants.
- Aucun nouveau statut métier (pas de `rejeté` en base) — uniquement le rendu des statuts déjà existants (`profils.statut IN ('à valider', 'validé')`, `import_jobs.statut IN ('en cours', 'réussi', 'échoué')`).
- Le CSS n'est compilé qu'au build de l'image Docker (`docker compose up -d --build web`) — pas de watch/hot-reload. Chaque étape de vérification impliquant du CSS ou une vue PHP nécessite un rebuild.
- Tests d'intégration : `tests/.venv/bin/pytest tests/web/ -v` avec les conteneurs démarrés (`WEB_URL` défaut `http://localhost:8080`, `DATABASE_URL` défaut `postgresql://cv:cv@localhost:5433/cv_analysis`).

---

### Task 1: Tokens CSS + wiring Tailwind (fondation)

**Files:**
- Modify: `web/assets/css/input.css:5-19` (bloc `:root`) et `:58-66` (badges) et `:94-96` (alert-destructive)
- Modify: `web/assets/tailwind.config.js` (safelist + `theme.extend.colors`)

**Interfaces:**
- Produces : classes CSS `badge-success`, `badge-warning`, `badge-info`, `alert-warning`, `alert-info`, et couleurs Tailwind `success`/`warning`/`info` (avec `-foreground`), consommées par les Tasks 2 et 3.

- [ ] **Step 1: Ajouter les tokens de couleur dans `:root`**

Dans `web/assets/css/input.css`, remplacer le bloc `:root` :

```css
:root {
  /* Triplets RGB (espace-séparés) plutôt que hex : requis par Tailwind pour
     que les modificateurs d'opacité (bg-primary/90, bg-destructive/10, ...)
     fonctionnent via rgb(var(--x) / <alpha-value>) dans tailwind.config.js. */
  --background: 255 255 255; /* #ffffff */
  --foreground: 10 10 10; /* #0a0a0a */
  --card: 255 255 255; /* #ffffff */
  --border: 229 231 235; /* #e5e7eb */
  --muted: 244 244 245; /* #f4f4f5 */
  --muted-foreground: 113 113 122; /* #71717a */
  --primary: 22 163 74; /* #16a34a */
  --primary-foreground: 255 255 255; /* #ffffff */
  --destructive: 220 38 38; /* #dc2626 */
  --success: 22 163 74; /* #16a34a — alias sémantique de --primary, dédié aux statuts */
  --success-foreground: 255 255 255;
  --warning: 217 119 6; /* #d97706 */
  --warning-foreground: 255 255 255;
  --info: 37 99 235; /* #2563eb */
  --info-foreground: 255 255 255;
  --radius: 0.5rem;
}
```

- [ ] **Step 2: Ajouter les classes composants `badge-*` et `alert-*`**

Dans `web/assets/css/input.css`, juste après le bloc `.badge-destructive` existant (avant `.input`) :

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
```

Et juste après `.alert-destructive` (fin du fichier, avant la fermeture de `@layer components`) :

```css
  .alert-warning {
    @apply rounded-md border border-warning/50 bg-warning/10 px-4 py-3 text-sm text-warning;
  }
  .alert-info {
    @apply rounded-md border border-info/50 bg-info/10 px-4 py-3 text-sm text-info;
  }
```

- [ ] **Step 3: Wiring dans `tailwind.config.js`**

Dans `web/assets/tailwind.config.js`, ajouter au tableau `safelist` (après `"alert-destructive",`) :

```js
    "badge-success",
    "badge-warning",
    "badge-info",
    "alert-warning",
    "alert-info",
```

Et dans `theme.extend.colors`, après le bloc `destructive` :

```js
        destructive: "rgb(var(--destructive) / <alpha-value>)",
        success: {
          DEFAULT: "rgb(var(--success) / <alpha-value>)",
          foreground: "rgb(var(--success-foreground) / <alpha-value>)",
        },
        warning: {
          DEFAULT: "rgb(var(--warning) / <alpha-value>)",
          foreground: "rgb(var(--warning-foreground) / <alpha-value>)",
        },
        info: {
          DEFAULT: "rgb(var(--info) / <alpha-value>)",
          foreground: "rgb(var(--info-foreground) / <alpha-value>)",
        },
```

- [ ] **Step 4: Rebuild l'image web et vérifier que le CSS compilé contient les nouvelles classes**

Run:
```bash
docker compose up -d --build web
docker compose exec web grep -c "badge-warning\|badge-info\|badge-success\|alert-warning\|alert-info" /var/www/html/css/app.css
```
Expected: un nombre `> 0` (les sélecteurs `.badge-warning`, `.badge-info`, etc. sont présents dans le CSS minifié).

- [ ] **Step 5: Commit**

```bash
git add web/assets/css/input.css web/assets/tailwind.config.js
git commit -m "feat(web): ajoute les tokens de couleur de statut success/warning/info"
```

---

### Task 2: Badge de statut profil dans `revue_liste.php` (TDD)

**Files:**
- Modify: `web/public/Views/revue_liste.php:81` (classe du badge de statut)
- Create: `tests/web/test_badges_statut.py`

**Interfaces:**
- Consumes : classes `badge-success`/`badge-warning` produites en Task 1.
- Produces : fonctions de test `test_profil_valide_affiche_badge_success`, `test_profil_a_valider_affiche_badge_warning`, réutilisées telles quelles par Task 3 (même fichier de test, étendu).

- [ ] **Step 1: Créer le fichier de test avec les cas profils (encore en échec)**

Create `tests/web/test_badges_statut.py` :

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


def _creer_profil(nom: str, email: str, statut: str, user_id: int) -> None:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO profils (nom, email, statut, importe_par_user_id, pdf_source)
                VALUES (%s, %s, %s, %s, 'test.pdf')
                ON CONFLICT (email) DO UPDATE SET statut = EXCLUDED.statut
                """,
                (nom, email, statut, user_id),
            )
        conn.commit()


def _creer_import_job(nom_fichier: str, statut: str, user_id: int) -> None:
    with psycopg.connect(DATABASE_URL) as conn:
        with conn.cursor() as cur:
            cur.execute(
                """
                INSERT INTO import_jobs (statut, importe_par_user_id, nom_fichier)
                VALUES (%s, %s, %s)
                """,
                (statut, user_id, nom_fichier),
            )
        conn.commit()


@pytest.fixture(scope="module")
def user_id() -> int:
    return _creer_utilisateur(
        "test-badges-statut@example.com", "bonmotdepasse", "utilisateur"
    )


@pytest.fixture(scope="module")
def web_client(user_id: int) -> httpx.Client:
    with httpx.Client(base_url=WEB_URL, timeout=10.0) as client:
        client.post(
            "/login",
            data={
                "email": "test-badges-statut@example.com",
                "mot_de_passe": "bonmotdepasse",
            },
        )
        yield client


def test_profil_valide_affiche_badge_success(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_profil(
        "Test Badge Valide", "test-badge-valide@example.com", "validé", user_id
    )

    response = web_client.get("/revue")
    assert response.status_code == 200
    assert "badge-success" in response.text


def test_profil_a_valider_affiche_badge_warning(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_profil(
        "Test Badge Attente", "test-badge-attente@example.com", "à valider", user_id
    )

    response = web_client.get("/revue")
    assert response.status_code == 200
    assert "badge-warning" in response.text
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run (avec les conteneurs démarrés — `docker compose up -d`) :
```bash
tests/.venv/bin/pytest tests/web/test_badges_statut.py -v
```
Expected: `FAILED` sur les deux tests, `assert "badge-success" in response.text` / `assert "badge-warning" in response.text` — le HTML actuel contient encore `badge-default`/`badge-secondary`.

- [ ] **Step 3: Mettre à jour la classe du badge dans `revue_liste.php`**

Dans `web/public/Views/revue_liste.php`, ligne 81, remplacer :

```php
                            <span class="<?= $profil['statut'] === 'validé' ? 'badge-default' : 'badge-secondary' ?>">
```

par :

```php
                            <span class="<?= $profil['statut'] === 'validé' ? 'badge-success' : 'badge-warning' ?>">
```

- [ ] **Step 4: Rebuild et relancer les tests**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_badges_statut.py -v
```
Expected: `test_profil_valide_affiche_badge_success` et `test_profil_a_valider_affiche_badge_warning` passent (`PASSED`).

- [ ] **Step 5: Commit**

```bash
git add web/public/Views/revue_liste.php tests/web/test_badges_statut.py
git commit -m "feat(web): badge success/warning pour le statut des profils dans revue_liste.php"
```

---

### Task 3: Badge de statut import dans `import.php` (TDD)

**Files:**
- Modify: `web/public/Views/import.php:97` (classe du badge de statut)
- Modify: `tests/web/test_badges_statut.py` (ajout de 3 tests)

**Interfaces:**
- Consumes : classes `badge-success`/`badge-warning`/`badge-destructive` produites en Task 1, helpers `_creer_import_job`/`web_client`/`user_id` déjà présents dans `tests/web/test_badges_statut.py` depuis Task 2.

- [ ] **Step 1: Ajouter les 3 tests import (encore en échec) à la fin de `tests/web/test_badges_statut.py`**

```python
def test_import_reussi_affiche_badge_success(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_import_job("cv-reussi.pdf", "réussi", user_id)

    response = web_client.get("/")
    assert response.status_code == 200
    assert "badge-success" in response.text


def test_import_echoue_affiche_badge_destructive(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_import_job("cv-echoue.pdf", "échoué", user_id)

    response = web_client.get("/")
    assert response.status_code == 200
    assert "badge-destructive" in response.text


def test_import_en_cours_affiche_badge_warning(
    web_client: httpx.Client, user_id: int
) -> None:
    _creer_import_job("cv-en-cours.pdf", "en cours", user_id)

    response = web_client.get("/")
    assert response.status_code == 200
    assert "badge-warning" in response.text
```

- [ ] **Step 2: Lancer les tests et vérifier qu'ils échouent**

Run:
```bash
tests/.venv/bin/pytest tests/web/test_badges_statut.py -v -k import
```
Expected: les 3 tests `échouent`. `test_import_reussi_affiche_badge_success` échoue car `import.php` produit actuellement `badge-default` (pas `badge-success`) ; `test_import_echoue_affiche_badge_destructive` et `test_import_en_cours_affiche_badge_warning` échouent car tout statut non-`réussi` retombe aujourd'hui sur `badge-secondary`.

- [ ] **Step 3: Mettre à jour la classe du badge dans `import.php`**

Dans `web/public/Views/import.php`, ligne 97, remplacer :

```php
                                        <span class="<?= $item['statut'] == 'réussi' ? 'badge-default' : 'badge-secondary' ?>">
```

par :

```php
                                        <span class="<?= match ($item['statut']) {
                                            'réussi' => 'badge-success',
                                            'échoué' => 'badge-destructive',
                                            default => 'badge-warning',
                                        } ?>">
```

- [ ] **Step 4: Rebuild et relancer toute la suite `test_badges_statut.py`**

Run:
```bash
docker compose up -d --build web
tests/.venv/bin/pytest tests/web/test_badges_statut.py -v
```
Expected: les 5 tests du fichier passent (`PASSED`).

- [ ] **Step 5: Commit**

```bash
git add web/public/Views/import.php tests/web/test_badges_statut.py
git commit -m "feat(web): badges success/warning/destructive selon le statut réel d'import"
```

---

### Task 4: Vérification finale et non-régression

**Files:** aucun fichier modifié dans cette tâche — vérification uniquement.

**Interfaces:** N/A.

- [ ] **Step 1: Rebuild complet et suite de tests web au complet**

Run:
```bash
docker compose up -d --build
tests/.venv/bin/pytest tests/web/ -v
```
Expected: tous les tests passent, y compris `test_auth.py` et `test_import_flow.py` (aucune régression — aucun Controller/Model/route n'a été modifié).

- [ ] **Step 2: Vérification manuelle dans le navigateur**

- Se connecter sur `http://localhost:8080`.
- `revue_liste.php` (`/revue`) : confirmer qu'un profil `à valider` affiche un badge ambre et qu'un profil `validé` affiche un badge vert.
- `import.php` (`/`) : confirmer qu'un import `réussi` est vert, qu'un import `échoué` est rouge, et qu'un import `en cours` est ambre.
- Vérifier à l'œil le contraste texte/fond des badges ambre et rouge (pas d'outil de contraste automatisé dans le projet).

- [ ] **Step 3: Nettoyage des données de test (optionnel, si la base de test doit rester propre)**

Run:
```bash
docker compose exec db psql -U cv -d cv_analysis -c "DELETE FROM profils WHERE email LIKE 'test-badge-%@example.com';"
docker compose exec db psql -U cv -d cv_analysis -c "DELETE FROM import_jobs WHERE nom_fichier LIKE 'cv-%.pdf' AND nom_fichier IN ('cv-reussi.pdf','cv-echoue.pdf','cv-en-cours.pdf');"
```

Pas de commit nécessaire pour cette tâche (vérification uniquement).
