# Connect'Academia

Plateforme web d'entraide pédagogique pour les élèves de Terminale au Gabon.

## 🚀 Installation

### Prérequis

- PHP 8.x
- MySQL 8.x
- Apache avec mod_rewrite activé

### Étapes d'installation

1. **Cloner ou télécharger le projet**
   ```bash
   cd /chemin/vers/votre/projet
   ```

2. **Créer la base de données**
   ```sql
   CREATE DATABASE connect_academia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. **Importer le schéma et les données initiales**
   ```bash
   mysql -u root -p connect_academia < database/schema.sql
   mysql -u root -p connect_academia < database/seed.sql
   ```

4. **Configurer la base de données**
   
   Éditez `includes/config.php` et modifiez les constantes :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'connect_academia');
   define('DB_USER', 'votre_utilisateur');
   define('DB_PASS', 'votre_mot_de_passe');
   define('BASE_URL', 'http://localhost/ApprentissageV1');
   ```

5. **Créer les dossiers d'upload**
   ```bash
   mkdir -p uploads/ressources
   chmod 755 uploads/ressources
   ```

6. **Configurer Apache**
   
   Assurez-vous que le module `mod_rewrite` est activé et que le `.htaccess` est pris en compte.

## 🔐 Comptes par défaut

### Administrateur
- **Email** : `admin@connectacademia.ga`
- **Mot de passe** : `Admin@2024`

⚠️ **Important** : Changez ce mot de passe immédiatement après la première connexion !

## 📁 Structure du projet

```
ApprentissageV1/
├── admin/              # Back-office administrateur
├── api/                # Endpoints API
├── assets/             # CSS, JS, images
├── database/           # Schéma SQL et seed
├── includes/           # Fichiers PHP partagés
├── uploads/            # Fichiers PDF uploadés
├── index.php           # Page d'accueil
├── login.php           # Connexion élève
├── register.php        # Inscription élève
├── dashboard.php       # Dashboard élève
└── ...
```

## 🎨 Design

Le design est inspiré de :
- **Coursera** pour l'espace élève
- **Linear / Attio** pour le back-office admin

Les templates de design sont disponibles dans `docs/images/`.

## 🔧 Fonctionnalités principales

### Espace Élève
- Inscription et connexion
- Dashboard avec statistiques
- Consultation des ressources par matière
- Lecteur PDF intégré avec progression
- Système de favoris
- Suivi de progression
- Notifications

### Espace Admin
- Gestion des utilisateurs
- Upload de ressources PDF
- Gestion des séries et matières
- Statistiques et analytics
- Envoi de notifications

## 📝 Notes de développement

- Le projet utilise **PHP vanilla** (pas de framework)
- **JavaScript vanilla** (pas de framework)
- **PDF.js** pour l'affichage des PDFs
- **Lucide Icons** pour les icônes
- **SweetAlert2** pour les notifications UI

## 🐛 Dépannage

### Problème de connexion admin
Si vous ne pouvez pas vous connecter avec les identifiants admin par défaut :

**Option 1 : Script PHP (recommandé)**
```bash
php fix_admin_password.php
```

**Option 2 : Script SQL**
```bash
mysql -u root -p connect_academia < database/update_admin_password.sql
```

**Option 3 : Mise à jour manuelle**
```sql
UPDATE admins 
SET password = '$2y$12$DPxR8qwlD2R2NHqfzl.UK.s1FoKn1CQ9ywo94mSCId9BIJpkshWxC'
WHERE email = 'admin@connectacademia.ga';
```

Les identifiants par défaut sont :
- **Email** : `admin@connectacademia.ga`
- **Mot de passe** : `Admin@2024`

### Erreur de connexion à la base de données
- Vérifiez les identifiants dans `includes/config.php`
- Assurez-vous que MySQL est démarré
- Pour MAMP, vérifiez que le serveur MySQL est actif dans le panneau de contrôle

### Erreur 404 sur les pages
- Vérifiez que `mod_rewrite` est activé dans Apache
- Vérifiez que le `.htaccess` est présent

### Erreur d'upload de fichiers
- Vérifiez les permissions du dossier `uploads/`
- Vérifiez la taille maximale dans `php.ini` (`upload_max_filesize` et `post_max_size`)

## 📄 Licence

Ce projet est développé pour Connect'Academia.

---

**Version** : 1.0  
**Dernière mise à jour** : 2024

