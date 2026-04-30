# Configuration de l'Assistant IA Gemini

## Prérequis

1. **Clé API Gemini** : Obtenez une clé API depuis [Google AI Studio](https://makersuite.google.com/app/apikey)
2. **Extension PHP cURL** : Doit être activée sur votre serveur
3. **Base de données** : La table `ia_conversations` doit être créée

## Installation

### 1. Créer la table dans la base de données

Exécutez le script SQL suivant :

```sql
-- Exécuter le fichier database/add_ia_conversations.sql
-- Ou exécuter directement :
```

```bash
mysql -u root -p connect_academia < database/add_ia_conversations.sql
```

### 2. Configurer la clé API Gemini

Éditez le fichier `includes/config.php` et ajoutez votre clé API :

```php
define('GEMINI_API_KEY', 'VOTRE_CLE_API_ICI');
```

⚠️ **Important** : Ne partagez jamais votre clé API publiquement. Elle doit rester secrète.

### 3. (Optionnel) Améliorer l'extraction PDF

Pour une meilleure extraction du texte des PDFs, vous pouvez installer une bibliothèque PHP :

#### Option A : Utiliser pdftotext (recommandé)

Installez `poppler-utils` sur votre serveur :

```bash
# Ubuntu/Debian
sudo apt-get install poppler-utils

# macOS
brew install poppler

# Windows
# Téléchargez depuis https://poppler.freedesktop.org/
```

#### Option B : Utiliser la bibliothèque PHP smalot/pdfparser

```bash
composer require smalot/pdfparser
```

Puis modifiez `api/gemini.php` pour utiliser cette bibliothèque (déjà prévu dans le code).

## Test

1. Connectez-vous en tant qu'élève
2. Ouvrez un document (cours, TD ou annale)
3. Cliquez sur le bouton "Assistant" dans la barre d'outils
4. Posez une question sur le document

## Dépannage

### Erreur "Configuration IA non disponible"
- Vérifiez que `GEMINI_API_KEY` est bien défini dans `includes/config.php`

### Erreur "Erreur de connexion à l'IA"
- Vérifiez votre connexion internet
- Vérifiez que la clé API est valide
- Consultez les logs PHP pour plus de détails

### Le contenu du PDF n'est pas extrait
- Installez `pdftotext` (voir Option A ci-dessus)
- Ou installez la bibliothèque PHP `smalot/pdfparser` (voir Option B ci-dessus)

## Sécurité

- ✅ La clé API est stockée uniquement côté serveur
- ✅ Rate limiting : 10 requêtes/minute par utilisateur
- ✅ Protection CSRF sur toutes les requêtes
- ✅ Vérification des droits d'accès au document
- ✅ Historique des conversations sauvegardé en base de données

## Coûts

L'API Gemini 2.5 Flash est gratuite avec des limites généreuses. Consultez [la documentation officielle](https://ai.google.dev/pricing) pour les détails.

