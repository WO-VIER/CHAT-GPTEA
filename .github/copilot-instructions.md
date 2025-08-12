# Copilot Instructions for chatgpt-like

## Vue d'ensemble

Ce projet est une application web de type chat, inspirée de ChatGPT, développée avec Laravel côté backend et probablement un framework JS moderne côté frontend. L'architecture suit la structure classique Laravel : `app/Http/Controllers`, `app/Models`, `app/Services`, `routes/`, etc.

## Points d'attention spécifiques

-   **Diagramme de classes** : Les relations entre modèles doivent être correctes. Utiliser Modelio pour générer le diagramme, à intégrer dans le PDF final.
-   **Base de données** : Ajouter des contraintes sur la table `provider icon` (voir `app/Models/ProviderIcon.php`).
-   **Sécurité** : Protéger la route de stream par une authentification (vérifier les middlewares dans `routes/web.php` et `app/Http/Middleware/`).
-   **Post-login** : Après connexion, rediriger l'utilisateur vers l'interface de chat.
-   **Responsive** : L'interface doit être responsive (voir `resources/views/` et `resources/css/`).
-   **Sélecteur de modèle** : Corriger la fermeture du sélecteur (bouton/Esc).
-   **Instructions personnalisées** : Prendre en compte les instructions personnalisées utilisateur.
-   **UI réception de messages** : Corriger le bug d'affichage lors de la réception de messages.
-   **Rendu markdown/code** : Activer le rendu markdown et le highlighting du code dans l'UI.

## Conventions et patterns

-   **Services** : La logique métier complexe est placée dans `app/Services/` (ex : `ChatService.php`, `ConversationService.php`).
-   **Contrôleurs** : Utiliser `app/Http/Controllers/` pour la logique HTTP, garder les contrôleurs fins.
-   **Modèles** : Les modèles Eloquent sont dans `app/Models/`.
-   **Migrations/Seeders** : Gérer la structure et les données de la base dans `database/migrations/` et `database/seeders/`.
-   **Tests** : Les tests sont dans `tests/Feature/` et `tests/Unit/` (utilise Pest).

## Workflows développeur

-   **Lancer le serveur** : `php artisan serve`
-   **Migrations** : `php artisan migrate`
-   **Tests** : `vendor\bin\pest`
-   **Build frontend** : `npm run dev` ou `npm run build`

## Intégrations et dépendances

-   **Debug** : Utilisation de DebugBar (`config/debugbar.php`, `LogToDebugBar.php`).
-   **API AI** : Intégration avec des modèles AI via `app/Models/AiModel.php` et `app/Services/ChatService.php`.
-   **Fichiers de config** : Les intégrations externes sont configurées dans `config/services.php` et `config/openai.php`.

## Exemples de fichiers clés

-   `app/Services/ChatService.php` : logique de chat et appels API AI
-   `app/Http/Controllers/` : endpoints HTTP
-   `resources/views/` : templates Blade pour l'UI
-   `public/` : assets accessibles publiquement

---

Adaptez-vous aux conventions existantes. Pour toute question sur une règle ou un workflow, vérifiez d'abord les fichiers cités ci-dessus.
