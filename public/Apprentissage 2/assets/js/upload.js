/**
 * Connect'Academia - Gestion Upload PDF
 */

const dropZone = document.getElementById('dropZone');
const fileInput = document.getElementById('fichier');
const uploadForm = document.getElementById('uploadForm');
const uploadProgress = document.getElementById('uploadProgress');
const progressFill = document.getElementById('progressFill');
const progressText = document.getElementById('progressText');

if (dropZone && fileInput) {
    // Drag & Drop
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        const file = e.dataTransfer.files[0];
        if (file && validateFile(file)) {
            fileInput.files = e.dataTransfer.files;
            updateFileDisplay(file);
        }
    });
    
    fileInput.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file && validateFile(file)) {
            updateFileDisplay(file);
        }
    });
}

function validateFile(file) {
    if (file.type !== 'application/pdf') {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Erreur', 'Seuls les fichiers PDF sont acceptés.', 'error');
        } else {
            alert('Seuls les fichiers PDF sont acceptés.');
        }
        return false;
    }
    
    if (file.size > 20 * 1024 * 1024) {
        if (typeof Swal !== 'undefined') {
            Swal.fire('Erreur', 'Fichier trop volumineux (max 20 Mo).', 'error');
        } else {
            alert('Fichier trop volumineux (max 20 Mo).');
        }
        return false;
    }
    
    return true;
}

function updateFileDisplay(file) {
    const fileName = dropZone.querySelector('p');
    if (fileName) {
        fileName.textContent = `${file.name} (${formatSize(file.size)})`;
    }
}

function formatSize(bytes) {
    if (bytes >= 1024 * 1024) {
        return (bytes / 1024 / 1024).toFixed(1) + ' Mo';
    }
    return Math.round(bytes / 1024) + ' Ko';
}

if (uploadForm) {
    uploadForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(uploadForm);
        
        uploadProgress.classList.add('active');
        progressFill.style.width = '0%';
        progressText.textContent = '0%';
        
        try {
            const xhr = new XMLHttpRequest();
            
            xhr.upload.addEventListener('progress', (e) => {
                if (e.lengthComputable) {
                    const percent = Math.round((e.loaded / e.total) * 100);
                    progressFill.style.width = percent + '%';
                    progressText.textContent = percent + '% — Upload...';
                }
            });
            
            xhr.addEventListener('load', () => {
                if (xhr.status === 200) {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Succès', 'Ressource publiée !', 'success').then(() => {
                                location.reload();
                            });
                        } else {
                            alert('Ressource publiée !');
                            location.reload();
                        }
                    } else {
                        if (typeof Swal !== 'undefined') {
                            Swal.fire('Erreur', data.error || 'Erreur lors de l\'upload', 'error');
                        } else {
                            alert(data.error || 'Erreur lors de l\'upload');
                        }
                    }
                } else {
                    if (typeof Swal !== 'undefined') {
                        Swal.fire('Erreur', 'Erreur serveur', 'error');
                    } else {
                        alert('Erreur serveur');
                    }
                }
                uploadProgress.classList.remove('active');
            });
            
            xhr.addEventListener('error', () => {
                if (typeof Swal !== 'undefined') {
                    Swal.fire('Erreur', 'Erreur réseau', 'error');
                } else {
                    alert('Erreur réseau');
                }
                uploadProgress.classList.remove('active');
            });
            
            xhr.open('POST', '../api/upload.php');
            xhr.send(formData);
        } catch (error) {
            console.error('Erreur upload:', error);
            uploadProgress.classList.remove('active');
        }
    });
}

