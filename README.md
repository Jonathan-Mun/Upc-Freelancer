# UPC FREELANCE — Guide d'installation

## Prérequis
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.6+
- Apache avec mod_rewrite activé
- Composer (optionnel)

---

## Installation en 5 étapes

### 1. Cloner / déposer les fichiers
```bash
cp -r upc_freelance/ /var/www/html/upc_freelance/
```

### 2. Créer la base de données
```bash
mysql -u root -p < /var/www/html/upc_freelance/database.sql
```
Ou via phpMyAdmin : importer `database.sql`

### 3. Configurer la connexion BDD
Éditer `/var/www/html/upc_freelance/includes/db.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'upc_freelance');
define('DB_USER', 'votre_user');
define('DB_PASS', 'votre_mot_de_passe');
```

### 4. Permissions dossier storage
```bash
chmod -R 755 /var/www/html/upc_freelance/storage/
chown -R www-data:www-data /var/www/html/upc_freelance/storage/
```

### 5. Activer mod_rewrite Apache
```bash
a2enmod rewrite
sudo service apache2 restart
```

---

## Accès

| Page             | URL                                          |
|------------------|----------------------------------------------|
| Accueil          | http://localhost/upc_freelance/public/        |
| Connexion        | http://localhost/upc_freelance/public/login.php |
| Inscription      | http://localhost/upc_freelance/public/register.php |
| Admin            | http://localhost/upc_freelance/admin/login.php |

### Compte Admin par défaut
- **Email** : admin@upcfreelance.com
- **Mot de passe** : Admin@2025
- ⚠️ **Changer immédiatement ce mot de passe en production !**

---

## Structure des fichiers

```
upc_freelance/
├── public/          # Pages publiques
├── auth/            # Backend authentification
├── app/             # Application (utilisateur connecté)
│   ├── dashboard.php
│   ├── projects/
│   ├── postulations/
│   ├── contracts/
│   ├── messages/
│   ├── wallet/
│   ├── notifications/
│   └── profile/
├── admin/           # Administration
├── api/             # API (futur)
├── includes/        # Noyau système
├── storage/         # Fichiers uploadés
├── database.sql     # Schéma BDD
└── .htaccess
```

---

## Sécurité en production

1. **HTTPS obligatoire** — Décommenter la règle HTTPS dans `.htaccess`
2. **Changer les identifiants BDD** dans `includes/db.php`
3. **Activer `secure` sur les cookies** dans `includes/auth.php`
4. **Configurer l'envoi d'emails** dans `auth/forgot-password.php`
5. **Changer le mot de passe admin** via phpMyAdmin :
   ```sql
   UPDATE admin_users SET password_hash = '$2y$12$...' WHERE email = 'admin@upcfreelance.com';
   ```

---

## Technologies utilisées

- **Backend** : PHP 8.1+ (PDO, sessions sécurisées)
- **BDD** : MySQL / MariaDB (InnoDB, UTF8mb4)
- **CSS** : Tailwind CSS (CDN)
- **Icônes** : Google Material Symbols
- **Fonts** : Inter, Work Sans (Google Fonts)
- **Sécurité** : CSRF tokens, password_hash bcrypt, rate limiting, headers sécurité

---

## Crédits
UPC Freelance — Plateforme Étudiante © 2025
