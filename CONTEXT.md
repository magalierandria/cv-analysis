# Base de profils (CV)

Constitution et exploration d'une base de profils structurés à partir de CV PDF, avec revue humaine avant validation.

## Language

**Profil** :
Représentation structurée d'un CV : une personne avec une identité, des expériences (et leurs compétences), et des formations.
_Avoid_: Candidat, CV (comme entité persistée)

**Compétence** :
Mot-clé rattaché à une expérience (ex. `FastAPI`, `CI/CD`), non normalisé en v1.
_Avoid_: Skill (en interface), technologie (comme synonyme générique)

**Statut (profil)** :
État du cycle de vie d'un profil : `à valider` ou `validé`.
_Avoid_: Status, état

**Import** :
Parcours unitaire de dépôt d'un PDF, délégué au service Python, aboutissant à la création d'un profil `à valider` ou à un échec sans profil créé.
_Avoid_: Upload (comme nom de domaine)

**Job d'import** :
Traitement asynchrone déclenché par un import : extraction du texte, structuration LLM, écriture du profil en BDD. Possède son propre cycle de vie (`en cours`, `réussi`, `échoué`).
_Avoid_: Tâche, processus, job (sans précision)

**Revue** :
Écran où l'utilisateur compare le PDF source aux champs extraits et les corrige avant validation.
_Avoid_: Validation (qui désigne l'acte de confirmer), édition (trop générique)

**Validation** :
Acte humain de confirmer un profil `à valider`, le faisant passer à `validé` (nom et email requis, contrôle doublon email).
_Avoid_: Approbation, publication

**Utilisateur** :
Personne authentifiée par email/mot de passe qui accède à la base de profils partagée. Comptes créés manuellement en v1 (pas d'inscription publique).
_Avoid_: User (en interface), compte

**BDD** :
PostgreSQL, source de vérité partagée entre l'application web PHP et le service Python d'import.
_Avoid_: Database, base
