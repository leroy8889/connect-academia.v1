-- Connect'Academia - Données initiales

-- Séries
INSERT INTO series (nom, description, couleur) VALUES
('A1', 'Terminale A1 — Lettres et Langues', '#8B52FA'),
('A2', 'Terminale A2 — Lettres et Sciences Humaines', '#6C3FC9'),
('B',  'Terminale B — Sciences Économiques', '#4A90D9'),
('C',  'Terminale C — Mathématiques et Physique', '#E85D04'),
('D',  'Terminale D — Sciences de la Vie et de la Terre', '#2DC653');

-- Matières Série A1
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques', 'calculator', 1, 1),
('Français / Littérature', 'book-open', 1, 2),
('Philosophie', 'lightbulb', 1, 3),
('Anglais (LV1)', 'globe', 1, 4),
('Histoire-Géographie', 'map', 1, 5),
('SVT', 'leaf', 1, 6),
('LV2 Espagnol', 'languages', 1, 7);

-- Matières Série A2
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques', 'calculator', 2, 1),
('Français / Littérature', 'book-open', 2, 2),
('Philosophie', 'lightbulb', 2, 3),
('Anglais (LV1)', 'globe', 2, 4),
('Histoire-Géographie', 'map', 2, 5),
('SVT', 'leaf', 2, 6),
('Économie / Gestion', 'trending-up', 2, 7),
('LV2 Espagnol', 'languages', 2, 8);

-- Matières Série B
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Économie / Gestion', 'trending-up', 3, 1),
('Mathématiques', 'calculator', 3, 2),
('Philosophie', 'lightbulb', 3, 3),
('Français / Littérature', 'book-open', 3, 4),
('Anglais (LV1)', 'globe', 3, 5),
('Histoire-Géographie', 'map', 3, 6),
('Comptabilité', 'receipt', 3, 7),
('LV2 Espagnol', 'languages', 3, 8);

-- Matières Série C
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('Mathématiques', 'calculator', 4, 1),
('Physique-Chimie', 'flask-conical', 4, 2),
('SVT', 'leaf', 4, 3),
('Philosophie', 'lightbulb', 4, 4),
('Français / Littérature', 'book-open', 4, 5),
('Anglais (LV1)', 'globe', 4, 6);

-- Matières Série D
INSERT INTO matieres (nom, icone, serie_id, ordre) VALUES
('SVT', 'leaf', 5, 1),
('Mathématiques', 'calculator', 5, 2),
('Physique-Chimie', 'flask-conical', 5, 3),
('Philosophie', 'lightbulb', 5, 4),
('Français / Littérature', 'book-open', 5, 5),
('Anglais (LV1)', 'globe', 5, 6);

-- Admin par défaut (mot de passe: Admin@2024)
-- Hash bcrypt généré pour "Admin@2024"
INSERT INTO admins (nom, prenom, email, password, role) VALUES
('Admin', 'Connect', 'admin@connectacademia.ga',
 '$2y$12$DPxR8qwlD2R2NHqfzl.UK.s1FoKn1CQ9ywo94mSCId9BIJpkshWxC',
 'super_admin');

