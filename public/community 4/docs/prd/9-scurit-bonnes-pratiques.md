# 9. Sécurité & Bonnes Pratiques

## 9.1 Protection des Données

- **Mots de passe** : Hashage bcrypt (cost factor 12 minimum)
- **Sessions** : Tokens aléatoires sécurisés (`session_regenerate_id()` à chaque connexion)
- **HTTPS** : Certificat SSL/TLS obligatoire en production
- **Données sensibles** : Jamais stockées en clair (passwords, tokens)

## 9.2 Protection Contre les Attaques

| Menace | Contre-mesure |
|---|---|
| SQL Injection | Utilisation exclusive des Prepared Statements PDO |
| XSS | Échappement systématique `htmlspecialchars()` côté serveur + Content Security Policy |
| CSRF | Token CSRF dans chaque formulaire POST |
| Brute Force | Rate limiting : max 5 tentatives / 15 min par IP |
| Upload malveillant | Validation type MIME, extension, taille, scan des fichiers |
| Énumération | Messages d'erreur non-spécifiques (*'Email ou mot de passe incorrect'*) |

## 9.3 Confidentialité (RGPD)

- Collecte des données minimale et justifiée
- Page de politique de confidentialité obligatoire
- Droit à la suppression du compte (RGPD Article 17)
- Consentement explicite aux conditions d'utilisation

---
