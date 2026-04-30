<?php
/**
 * Connect'Academia - Gestion Ressources Admin
 */
require_once __DIR__ . '/../includes/admin_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/helpers.php';

$pdo = getDB();
$admin_id = $_SESSION['admin_id'];

// Récupérer les ressources
$stmt = $pdo->query("
    SELECT r.id, r.titre, r.type, r.nb_vues, r.created_at,
           s.nom as serie, m.nom as matiere, ch.titre as chapitre
    FROM ressources r
    JOIN series s ON s.id = r.serie_id
    JOIN matieres m ON m.id = r.matiere_id
    LEFT JOIN chapitres ch ON ch.id = r.chapitre_id
    WHERE r.is_deleted = 0
    ORDER BY r.created_at DESC
");
$ressources = $stmt->fetchAll();

// Récupérer les séries et matières pour le formulaire
$series = $pdo->query("SELECT id, nom FROM series WHERE is_active = 1 ORDER BY nom ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des ressources — Connect'Academia</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar__logo">
                <img src="../assets/img/logo.svg" alt="Connect'Academia">
                <span>Gabon Terminale Admin</span>
            </div>
            <nav class="admin-sidebar__nav">
                <a href="dashboard.php" class="admin-sidebar__nav-item">
                    <i data-lucide="layout-dashboard"></i>
                    <span>Dashboard</span>
                </a>
                <a href="users.php" class="admin-sidebar__nav-item">
                    <i data-lucide="users"></i>
                    <span>Élèves</span>
                </a>
                <a href="ressources.php" class="admin-sidebar__nav-item active">
                    <i data-lucide="file-text"></i>
                    <span>Ressources</span>
                </a>
                <a href="series.php" class="admin-sidebar__nav-item">
                    <i data-lucide="compass"></i>
                    <span>Séries & Matières</span>
                </a>
                <a href="stats.php" class="admin-sidebar__nav-item">
                    <i data-lucide="bar-chart-2"></i>
                    <span>Analytiques</span>
                </a>
            </nav>
            <a href="logout.php" class="admin-sidebar__nav-item" style="margin-top: auto;">
                <i data-lucide="log-out"></i>
                <span>Déconnexion</span>
            </a>
        </aside>
        
        <main class="admin-main">
            <div class="admin-topbar">
                <div class="admin-topbar__breadcrumb">Pages / Ressources</div>
            </div>
            
            <div class="admin-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                    <div>
                        <h1 style="margin-bottom: 8px;">Gestion des ressources</h1>
                        <p style="color: var(--color-text-light);">Organisez et publiez les ressources pédagogiques</p>
                    </div>
                    <button onclick="openUploadModal()" class="btn-primary">
                        <i data-lucide="plus"></i>
                        Nouveau
                    </button>
                </div>
                
                <!-- Tableau ressources -->
                <div class="admin-table">
                    <table>
                        <thead>
                            <tr>
                                <th>TITRE</th>
                                <th>VUES</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ressources as $ressource): ?>
                            <tr>
                                <td style="font-weight: 600;"><?= e($ressource['titre']) ?></td>
                                <td><?= number_format($ressource['nb_vues']) ?></td>
                                <td>
                                    <button onclick="voirRessource(<?= $ressource['id'] ?>)" class="btn-secondary" style="font-size: 12px; padding: 6px 12px;">Voir</button>
                                    <button onclick="supprimerRessource(<?= $ressource['id'] ?>, '<?= e(addslashes($ressource['titre'])) ?>')" class="btn-danger" style="font-size: 12px; padding: 6px 12px; margin-left: 8px;">Supprimer</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Modal Upload -->
    <div id="uploadModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
        <div style="background: var(--color-white); border-radius: var(--radius-lg); padding: 32px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h2 style="display: flex; align-items: center; gap: 8px;">
                    <i data-lucide="upload"></i>
                    Ajouter une ressource
                </h2>
                <button onclick="closeUploadModal()" style="background: none; border: none; cursor: pointer; font-size: 24px; color: var(--color-text-light);">×</button>
            </div>
            
            <form id="uploadForm" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="titre">Titre du document *</label>
                    <input type="text" id="titre" name="titre" required placeholder="Ex: Devoir de Mathématiques n°1">
                </div>
                
                <div class="form-group">
                    <label for="description">Description courte</label>
                    <textarea id="description" name="description" placeholder="Présentation rapide du contenu..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="type">Type *</label>
                    <select id="type" name="type" required>
                        <option value="">Sélectionner un type</option>
                        <option value="cours">Cours</option>
                        <option value="td">Travail Dirigé</option>
                        <option value="ancienne_epreuve">Ancienne Épreuve</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="serie_id">Série *</label>
                    <select id="serie_id" name="serie_id" required>
                        <option value="">Toutes</option>
                        <?php foreach ($series as $serie): ?>
                            <option value="<?= $serie['id'] ?>"><?= e($serie['nom']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="matiere_id">Matière *</label>
                    <select id="matiere_id" name="matiere_id" required>
                        <option value="">Choisir la matière</option>
                        <option value="mathématiques">Mathématiques</option>
                        <option value="français">Français</option>
                        <option value="philosophie">Philosophie</option>
                        <option value="SVT">SVT</option>
                        <option value="histoire-géographie">Histoire-Géographie</option>
                        <option value="Aaglais">Anglais</option>
                        <option value="Espagnol">Espagnol</option>
                        <option value="Sciences-Physiques">Sciences-Physiques</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="chapitre">Chapitre</label>
                    <input type="text" id="chapitre" name="chapitre" placeholder="Ex: Analyse">
                </div>
                
                <div class="upload-zone" id="dropZone" onclick="document.getElementById('fichier').click()">
                    <div class="upload-zone__icon">
                        <i data-lucide="upload-cloud"></i>
                    </div>
                    <p style="font-weight: 600; margin-bottom: 8px;">Cliquez ou glissez-déposez votre fichier PDF</p>
                    <p style="font-size: 13px; color: var(--color-text-light);">PDF uniquement, taille max 20 Mo</p>
                    <input type="file" id="fichier" name="fichier" accept=".pdf" required style="display: none;">
                </div>
                
                <div class="upload-progress" id="uploadProgress">
                    <div class="upload-progress-bar">
                        <div class="upload-progress-fill" id="progressFill" style="width: 0%"></div>
                    </div>
                    <div style="text-align: center; margin-top: 8px; font-size: 13px; color: var(--color-text-light);" id="progressText">0%</div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 24px;">
                    <button type="button" onclick="closeUploadModal()" class="btn-secondary" style="flex: 1;">Annuler</button>
                    <button type="submit" class="btn-primary" style="flex: 1;">Mettre en ligne</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script src="../assets/js/upload.js"></script>
    <script>
        lucide.createIcons();
        
        function openUploadModal() {
            document.getElementById('uploadModal').style.display = 'flex';
        }
        
        function closeUploadModal() {
            document.getElementById('uploadModal').style.display = 'none';
            document.getElementById('uploadForm').reset();
        }
        
        // Charger les matières selon la série
        document.getElementById('serie_id').addEventListener('change', async function() {
            const serieId = this.value;
            const matiereSelect = document.getElementById('matiere_id');
            
            if (!serieId) {
                matiereSelect.innerHTML = '<option value="">Choisir la matière</option>';
                return;
            }
            
            try {
                const response = await fetch(`../api/matieres.php?serie_id=${serieId}`);
                const data = await response.json();
                
                if (data.success) {
                    matiereSelect.innerHTML = '<option value="">Choisir la matière</option>';
                    data.data.forEach(m => {
                        matiereSelect.innerHTML += `<option value="${m.id}">${m.nom}</option>`;
                    });
                }
            } catch (error) {
                console.error('Erreur:', error);
            }
        });
        
        // Fonction pour voir une ressource
        function voirRessource(ressourceId) {
            window.open(`../viewer.php?ressource=${ressourceId}`, '_blank');
        }
        
        // Fonction pour supprimer une ressource
        async function supprimerRessource(ressourceId, titre) {
            const result = await Swal.fire({
                title: 'Êtes-vous sûr ?',
                html: `Voulez-vous vraiment supprimer la ressource<br><strong>"${titre}"</strong> ?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Oui, supprimer',
                cancelButtonText: 'Annuler'
            });
            
            if (result.isConfirmed) {
                try {
                    const response = await fetch('../api/delete_ressource.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ ressource_id: ressourceId })
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        await Swal.fire({
                            title: 'Supprimé !',
                            text: 'La ressource a été supprimée avec succès.',
                            icon: 'success',
                            confirmButtonText: 'OK'
                        });
                        // Recharger la page pour mettre à jour la liste
                        location.reload();
                    } else {
                        await Swal.fire({
                            title: 'Erreur',
                            text: data.error || 'Une erreur est survenue lors de la suppression.',
                            icon: 'error',
                            confirmButtonText: 'OK'
                        });
                    }
                } catch (error) {
                    console.error('Erreur:', error);
                    await Swal.fire({
                        title: 'Erreur',
                        text: 'Une erreur est survenue lors de la suppression.',
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                }
            }
        }
    </script>
</body>
</html>

