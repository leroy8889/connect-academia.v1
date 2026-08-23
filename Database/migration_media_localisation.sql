-- ═══════════════════════════════════════════════════════════════════════════
-- Migration — Médias & Localisation (module Orientation)
-- Date : 2026-07-03
--
-- 1. video_cle       : clé B2 de la vidéo de présentation uploadée
--                      (remplace le lien YouTube video_url, conservé en legacy)
-- 2. map_embed_url   : URL src de l'iframe Google Maps "Intégrer une carte"
--                      (remplace la saisie latitude/longitude, colonnes conservées)
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE etablissements
  ADD COLUMN video_cle     VARCHAR(255) NULL AFTER video_url,
  ADD COLUMN map_embed_url TEXT         NULL AFTER longitude;
