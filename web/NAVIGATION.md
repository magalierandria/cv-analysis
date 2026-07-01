# Spécification de la Navigation - Projet Auto-CV

## Liste des pages principales
- **Login**: Page d'authentification pour accéder à la base de profils partagée.
- **Dashboard**: Vue d'ensemble de la base de profils.
- **Import**: Parcours unitaire de dépôt d'un PDF.
- **Revue**: Écran de comparaison entre le PDF source et les champs extraits pour correction avant validation.
- **Profil**: Visualisation de la représentation structurée (identité, expériences, formations).

## Flux de navigation (User Journey)
1. L'utilisateur s'authentifie via la page **Login**.
2. Accès au **Dashboard** pour consulter la base de profils partagée.
3. Accès au parcours d'**Import** pour déposer un PDF.
4. Le "Job d'import" est déclenché par le service Python pour extraire les données.
5. Redirection vers la page de **Revue** pour comparer le PDF et les champs extraits.
6. Réalisation de la **Validation** (action humaine de confirmer le profil).
7. Accès au **Profil** validé.