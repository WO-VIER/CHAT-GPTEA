#!/bin/bash
echo "Migration en cours..."
php artisan migrate

echo "Régénération des IDE helpers..."
php artisan ide-helper:models -M

echo "Terminé !"
