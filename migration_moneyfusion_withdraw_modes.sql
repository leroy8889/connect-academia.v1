-- Migration : alignement des modes de retrait sur les clés officielles
-- MoneyFusion (GET https://pay.moneyfusion.net/api/v1/withdraw/methods).
-- Gabon (countryCode "ga") : airtel-ga (Airtel Money), moov-ga (Moov Money).
-- À rejouer en production après déploiement.

UPDATE etablissement_paiement SET withdraw_mode = 'airtel-ga' WHERE withdraw_mode = 'airtel_money';
UPDATE etablissement_paiement SET withdraw_mode = 'moov-ga'   WHERE withdraw_mode = 'moov_money';

UPDATE reversements SET withdraw_mode = 'airtel-ga' WHERE withdraw_mode = 'airtel_money';
UPDATE reversements SET withdraw_mode = 'moov-ga'   WHERE withdraw_mode = 'moov_money';

-- Orange Money n'existe pas au Gabon côté MoneyFusion : les comptes restants
-- avec un mode inconnu doivent être recorrigés manuellement par le super admin
-- (/admin/orientation/{id}/paiement). Cette requête les liste :
SELECT id, etablissement_id, withdraw_mode, numero_mobile_money
FROM etablissement_paiement
WHERE withdraw_mode NOT IN ('airtel-ga', 'moov-ga', 'crypto-ga');
