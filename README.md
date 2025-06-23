CHATGPTEA

1.Installer les dépendances PHP
composer install

2.Installer les dépendances JS

3.Ajouter la clé api .env

4.Configuration de l'environnement
php artisan key:generate

5.Configurer la base de données
php artisan migrate 
php artisan migrate:refresh --seed  (Pour populer provider_icons et ai_model)
