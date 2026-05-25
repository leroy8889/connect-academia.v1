# Guide de mise en production — Connect'Academia

## 1. APP_URL — Point critique n°1

Actuellement configuré pour MAMP local + tunnel :
```
APP_URL=https://connect-academia.net/connect%27academia.v1
```

En production (hébergeur dédié, VPS, cPanel…), l'app sera à la **racine du domaine**, pas dans un sous-dossier. Changer :

```env
# Si l'app est à la racine du domaine (recommandé)
APP_URL=https://connect-academia.net

# Si l'app est dans un sous-dossier sur l'hébergeur (rare)
APP_URL=https://connect-academia.net/sous-dossier
```

> **Pourquoi c'est critique :** `APP_URL` sert de fallback si l'auto-détection échoue. Le webhook MoneyFusion est construit dynamiquement depuis `HTTP_HOST` + `BASE_URL`, donc si le serveur est bien configuré (HTTPS natif, pas de tunnel), l'auto-détection prend le dessus. Mais il faut quand même que `APP_URL` soit correct.

---

## 2. MoneyFusion — Paiement

### URLs actuelles (compte ERP_CRM — test/prod identique pour MoneyFusion)

```env
MONEY_FUSION_PAYMENT_URL=https://pay.moneyfusion.net/ERP_CRM/8a42a83a0b006fd3/pay/
```

Cette URL contient l'identifiant marchand `8a42a83a0b006fd3`. MoneyFusion n'a **pas d'environnement sandbox séparé** — les paiements test et prod passent par la même API. En production, **cette URL ne change pas** sauf si MoneyFusion vous attribue un nouvel identifiant marchand.

### Vérifier avec MoneyFusion si :
- Le compte `8a42a83a0b006fd3` est bien activé pour la production
- Les frais de 8% (`FEE_RATE = 1.08` dans `app/Core/MoneyFusion.php`) sont toujours corrects
- Le montant minimum accepté est bien compatible avec 2000 XAF

### Webhook URL automatique

Le webhook MoneyFusion est construit dynamiquement à chaque initiation de paiement :
```
{domaine_production}/api/paiement/callback
```

Aucun changement de code nécessaire — l'URL s'adapte automatiquement au domaine de production.

### API de vérification statut (v3)

```
https://pay.moneyfusion.net/api/v3/payments/status/{token}
```

Hardcodée dans `app/Core/MoneyFusion.php:78` — ne pas changer, c'est l'endpoint officiel v3.

---

## 3. Variables .env à changer obligatoirement

| Variable | Valeur actuelle (dev) | Valeur production |
|----------|----------------------|-------------------|
| `APP_URL` | `https://connect-academia.net/connect%27academia.v1` | `https://votre-domaine.com` |
| `APP_DEBUG` | `false` | `false` ✓ déjà bon |
| `APP_ENV` | `production` | `production` ✓ déjà bon |
| `APP_SECRET` | `ConnectAcademia_Secret_Gabon_2026_XAF` | **Changer** — générer une clé aléatoire longue |
| `DB_HOST` | `127.0.0.1` | IP ou host du serveur DB de prod |
| `DB_USER` | `root` | Utilisateur DB dédié (pas root) |
| `DB_PASS` | `root` | Mot de passe fort |
| `DB_SOCKET` | `/Applications/MAMP/tmp/mysql/mysql.sock` | **Supprimer** cette ligne (spécifique MAMP) |
| `REDIS_HOST` | `127.0.0.1` | `127.0.0.1` si Redis local, sinon IP Redis |
| `REDIS_PASSWORD` | _(vide)_ | Mot de passe Redis si configuré |
| `GEMINI_API_KEY` | clé actuelle | Garder ou renouveler si exposée |

---

## 4. Base de données

1. Exporter `connect_academia1` depuis MAMP :
   ```bash
   mysqldump -u root -p connect_academia1 > connect_academia1_export.sql
   ```
2. Importer sur le serveur de production
3. S'assurer que toutes les migrations sont appliquées (`database/001_schema_unifie.sql` → `008_add_moderateur1_role.sql`)
4. Supprimer `DB_SOCKET` du `.env` production (spécifique MAMP local)

---

## 5. Cloudflare / DNS

Actuellement : tunnel Cloudflare (`cloudflared tunnel run connect-academia`) depuis la machine locale.

En production sur VPS/hébergeur : configurer le DNS Cloudflare en mode **Proxy** (nuage orange) pointant vers l'IP du serveur. Le tunnel `cloudflared` ne sera plus nécessaire.

```
Type  Nom                  Valeur           Proxy
A     connect-academia.net  IP_SERVEUR_PROD  ✓ (proxy Cloudflare)
A     www                  IP_SERVEUR_PROD  ✓ (proxy Cloudflare)
```

SSL : activer "Full (strict)" dans Cloudflare SSL/TLS si le serveur a un certificat. Sinon "Full" suffit avec Let's Encrypt.

---

## 6. Fichiers à vérifier côté serveur

- **`storage/logs/`** — doit être accessible en écriture par Apache/PHP
- **`public/uploads/`** — doit être accessible en écriture
- **`.htaccess`** — doit être chargé (`AllowOverride All` dans la config Apache)
- **`.env`** — ne doit PAS être accessible publiquement (déjà bloqué dans `.htaccess`)

---

## 7. Checklist avant mise en ligne

- [ ] `APP_URL` mis à jour avec le vrai domaine (sans sous-dossier)
- [ ] `APP_SECRET` régénéré (clé unique, 64+ caractères)
- [ ] `DB_SOCKET` supprimé du `.env`
- [ ] Identifiants DB changés (pas root/root)
- [ ] Migrations SQL toutes appliquées
- [ ] Redis configuré ou désactivé (l'app fonctionne sans)
- [ ] `storage/logs/` et `public/uploads/` writables
- [ ] Test paiement 2000 XAF réussi en prod
- [ ] Webhook MoneyFusion reçu et transaction confirmée automatiquement
- [ ] Bouton "Sync" admin testé (`/admin/paiement` → Sync)
- [ ] Suppression du tunnel ngrok (processus `ngrok` local)
- [ ] Suppression du tunnel cloudflared local si serveur dédié
