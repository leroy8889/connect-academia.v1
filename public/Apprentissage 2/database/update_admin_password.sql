-- Script SQL pour mettre à jour le mot de passe admin
-- À exécuter si la base de données existe déjà
-- Mot de passe: Admin@2024

-- Mettre à jour le mot de passe admin existant
UPDATE admins 
SET password = '$2y$12$DPxR8qwlD2R2NHqfzl.UK.s1FoKn1CQ9ywo94mSCId9BIJpkshWxC'
WHERE email = 'admin@connectacademia.ga';

-- Si l'admin n'existe pas, le créer
INSERT INTO admins (nom, prenom, email, password, role)
SELECT 'Admin', 'Connect', 'admin@connectacademia.ga',
       '$2y$12$DPxR8qwlD2R2NHqfzl.UK.s1FoKn1CQ9ywo94mSCId9BIJpkshWxC',
       'super_admin'
WHERE NOT EXISTS (
    SELECT 1 FROM admins WHERE email = 'admin@connectacademia.ga'
);

