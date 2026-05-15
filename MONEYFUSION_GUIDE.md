# Guide d'Intégration MoneyFusion (v3) — Connect'Academia

Ce document explique l'implémentation de la passerelle de paiement MoneyFusion dans le projet Connect'Academia v1.

## 1. Configuration (.env)

Les variables suivantes doivent être configurées pour le bon fonctionnement de l'API :

```dotenv
# URLs MoneyFusion
MONEY_FUSION_PAYMENT_URL=https://www.pay.moneyfusion.net/ERP_CRM/8a42a83a0b006fd3/pay/
MONEY_FUSION_CHECK_URL=https://www.pay.moneyfusion.net/paiementNotif

# Prix des abonnements (en XAF)
PRIX_MENSUEL_XAF=2000
PRIX_ANNUEL_XAF=15000
```

## 2. Architecture Technique

### A. Le Helper Core (`app/Core/MoneyFusion.php`)
Cette classe centralise la logique métier de MoneyFusion :
- **Frais (FEE_RATE)** : Une constante de `1.08` est appliquée.
- **`calculateAmountToSend(float $price)`** : Calcule le montant HT à envoyer à l'API (`round($price / 1.08)`).
- **`initiate(array $data)`** : Envoie la requête POST JSON à MoneyFusion.
- **`checkStatus(string $token)`** : Vérifie l'état d'une transaction via l'URL de notification.

### B. Le Contrôleur (`app/Controllers/PaiementController.php`)
Gère le cycle de vie d'un paiement :
1.  **`initier()`** : 
    - Récupère le prix dans le `.env`.
    - Applique le calcul des frais.
    - Crée une transaction en base de données avec le statut `en_attente`.
    - Redirige l'utilisateur vers l'URL de paiement fournie par MoneyFusion.
2.  **`callback()` (Webhook)** :
    - Reçoit les notifications POST asynchrones de MoneyFusion.
    - Met à jour le statut de la transaction (`succes` ou `echec`).
    - Active l'abonnement de l'utilisateur si le paiement est confirmé (`payin.session.completed`).
3.  **`retour()`** :
    - Gère la redirection de l'utilisateur après le paiement.
    - Effectue une vérification de sécurité proactive du statut.

## 3. Flux de Paiement

1.  **Utilisateur** : Clique sur "Payer" dans la vue `abonnement/choisir`.
2.  **Application** : Appelle `/api/paiement/initier`.
3.  **MoneyFusion** : Affiche l'interface de paiement (Mobile Money, Carte, etc.).
4.  **Retour** : L'utilisateur revient sur `/paiement/retour`.
5.  **Validation** : Le système vérifie le paiement et redirige vers `/abonnement/confirmation`.

## 4. Tests avec ngrok

Pour tester les notifications (callbacks) en local :
1.  Lancer ngrok : `ngrok http 80` (si XAMPP).
2.  Mettre à jour `APP_URL` dans le `.env` avec le lien ngrok.
3.  Dans le dashboard MoneyFusion, configurer l'URL de notification sur :
    `https://VOTRE_LIEN_NGROK/connect-academia.v1/api/paiement/callback`

## 5. Maintenance et Évolutions

- **Changer les prix** : Modifiez simplement `PRIX_MENSUEL_XAF` dans le `.env`.
- **Changer les frais** : Modifiez la constante `FEE_RATE` dans `app/Core/MoneyFusion.php`.
- **Vérifier les logs** : Toutes les interactions avec MoneyFusion sont logguées dans `storage/logs/app.log` avec le préfixe `[Paiement]` ou `[MF Webhook]`.

---
