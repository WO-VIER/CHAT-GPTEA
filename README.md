# CHATGPTEA 🍵

Un clone de ChatGPT utilisant l'API OpenRouter avec Laravel, Vue.js et Inertia.js.

##  Installation

### 1. Installer les dépendances PHP
```bash
composer install
```

### 2. Installer les dépendances JS
```bash
npm install
```

### 3. Ajouter la clé API dans .env

Ajoutez votre clé API OpenRouter dans le fichier `.env` :
```properties
OPENROUTER_API_KEY=votre-clé-api-ici
```

> **Important :** Obtenez votre clé API sur [OpenRouter.ai](https://openrouter.ai/)

### 4. Configuration de l'environnement
```bash
php artisan key:generate
```

### 5. Configurer la base de données
```bash
# Créer les tables
php artisan migrate

# Peupler la base de données (pour provider_icons et ai_model)
php artisan migrate:refresh --seed
```

### 6. Démarrer l'application
```bash
# Lancer le serveur
php artisan serve

# Dans un autre terminal (pour le développement)
npm run dev
```

## 🔧 Fonctionnalités

- Chat en temps réel avec les modèles IA
- Interface moderne avec Vue.js et Tailwind CSS
- Gestion des conversations
- Support de multiples modèles d'IA
- Authentification utilisateur

## 🛠️ Technologies utilisées

- **Laravel** - Framework PHP
- **Vue.js 3** - Framework JavaScript
- **Inertia.js** - Bridge Laravel/Vue
- **Tailwind CSS** - Framework CSS
- **OpenRouter API** - Accès aux modèles IA
