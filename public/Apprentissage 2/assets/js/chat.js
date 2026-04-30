/**
 * Connect'Academia - Chat IA Assistant
 * Gestion de l'interface de chat avec Gemini
 */

let chatPanel = null;
let chatMessages = null;
let chatForm = null;
let chatInput = null;
let chatSendBtn = null;
let documentId = null;
let documentType = null;
let csrfToken = null;
let isLoading = false;

/**
 * Initialiser le chat
 */
function initChat(docId, docType, token) {
    documentId = docId;
    documentType = docType;
    csrfToken = token;
    
    chatPanel = document.getElementById('chatPanel');
    chatMessages = document.getElementById('chatMessages');
    chatForm = document.getElementById('chatForm');
    chatInput = document.getElementById('chatInput');
    chatSendBtn = document.getElementById('chatSendBtn');
    
    if (!chatPanel || !chatMessages || !chatForm || !chatInput) {
        console.error('Éléments du chat non trouvés');
        return;
    }
    
    // Gérer la soumission du formulaire
    chatForm.addEventListener('submit', handleChatSubmit);
    
    // Auto-resize du textarea
    chatInput.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });
    
    // Permettre Enter pour envoyer (Shift+Enter pour nouvelle ligne)
    chatInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (!isLoading && this.value.trim()) {
                handleChatSubmit(e);
            }
        }
    });
    
    // Charger l'historique au chargement
    loadChatHistory();
}

/**
 * Toggle l'affichage du chat
 */
function toggleChat() {
    if (!chatPanel) return;
    
    const isOpen = chatPanel.classList.contains('open');
    
    if (isOpen) {
        chatPanel.classList.remove('open');
        // Mettre à jour l'icône du bouton
        const icon = document.getElementById('chatToggleIcon');
        if (icon) {
            icon.setAttribute('data-lucide', 'message-circle');
            lucide.createIcons();
        }
    } else {
        chatPanel.classList.add('open');
        // Mettre à jour l'icône du bouton
        const icon = document.getElementById('chatToggleIcon');
        if (icon) {
            icon.setAttribute('data-lucide', 'message-circle-off');
            lucide.createIcons();
        }
        // Focus sur l'input
        setTimeout(() => chatInput?.focus(), 100);
    }
}

/**
 * Charger l'historique des conversations
 */
async function loadChatHistory() {
    if (!documentId) return;
    
    try {
        // Construire l'URL de manière absolue pour éviter les problèmes avec les espaces dans le chemin
        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        const historyUrl = new URL(`api/chat_history.php?document_id=${documentId}`, window.location.origin + basePath);
        
        const response = await fetch(historyUrl.toString(), {
            method: 'GET',
            headers: {
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin'
        });
        
        if (!response.ok) {
            throw new Error('Erreur lors du chargement de l\'historique');
        }
        
        const data = await response.json();
        
        if (data.success && data.history && data.history.length > 0) {
            // Afficher l'historique
            data.history.forEach(entry => {
                addMessage('user', entry.user_message);
                addMessage('assistant', entry.ia_response);
            });
            
            // Scroll en bas
            scrollToBottom();
        } else {
            // Afficher le message d'accueil
            showWelcomeMessage();
        }
    } catch (error) {
        console.error('Erreur chargement historique:', error);
        showWelcomeMessage();
    }
}

/**
 * Afficher le message d'accueil
 */
function showWelcomeMessage() {
    const welcomeText = `👋 Bonjour ! Je suis **Assistant Connect'Acadrmia**, ton assistant pédagogique. 🇬🇦\n\nJe suis là pour t'aider à comprendre ce document, répondre à toutes tes questions et t'accompagner vers la réussite au **Baccalauréat Gabonais**.\n\nN'hésite pas, pose-moi ta première question ! 📚`;
    
    addMessage('assistant', welcomeText, true);
}

/**
 * Gérer la soumission du formulaire
 */
async function handleChatSubmit(e) {
    e.preventDefault();
    
    if (isLoading || !chatInput.value.trim()) {
        return;
    }
    
    const message = chatInput.value.trim();
    chatInput.value = '';
    chatInput.style.height = 'auto';
    
    // Afficher le message de l'utilisateur
    addMessage('user', message);
    
    // Afficher l'indicateur de chargement
    const loadingId = showLoading();
    
    // Désactiver le formulaire
    setLoading(true);
    
    try {
        // Construire l'URL de manière absolue pour éviter les problèmes avec les espaces dans le chemin
        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
        const apiUrl = new URL('api/gemini.php', window.location.origin + basePath);
        
        // Préparer les données de la requête
        const requestData = {
            document_id: documentId,
            message: message
        };
        
        console.log('Envoi requête POST vers:', apiUrl.toString());
        console.log('Méthode:', 'POST');
        console.log('Données:', requestData);
        console.log('Token CSRF:', csrfToken ? 'présent' : 'absent');
        
        const response = await fetch(apiUrl.toString(), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            credentials: 'same-origin',
            body: JSON.stringify(requestData)
        });
        
        console.log('Réponse reçue - Status:', response.status, 'StatusText:', response.statusText);
        
        // Retirer l'indicateur de chargement
        removeLoading(loadingId);
        
        // Vérifier si la réponse est OK
        if (!response.ok) {
            let errorMsg = 'Une erreur est survenue. Veuillez réessayer.';
            try {
                const errorData = await response.json();
                if (errorData && errorData.error) {
                    errorMsg = errorData.error;
                }
            } catch (e) {
                // Si la réponse n'est pas du JSON, utiliser le message par défaut
                errorMsg = `Erreur ${response.status}: ${response.statusText}`;
            }
            addMessage('assistant', `⚠️ ${errorMsg}`, false, true);
            setLoading(false);
            return;
        }
        
        // Parser la réponse JSON
        let data;
        try {
            const responseText = await response.text();
            if (!responseText) {
                throw new Error('Réponse vide');
            }
            data = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Erreur parsing JSON:', parseError);
            addMessage('assistant', '⚠️ Erreur de l\'IA. Veuillez réessayer.', false, true);
            setLoading(false);
            return;
        }
        
        if (data.success && data.response) {
            // Afficher la réponse de l'IA
            addMessage('assistant', data.response);
        } else {
            // Afficher l'erreur
            const errorMsg = data.error || 'Une erreur est survenue. Veuillez réessayer.';
            addMessage('assistant', `⚠️ ${errorMsg}`, false, true);
        }
    } catch (error) {
        console.error('Erreur envoi message:', error);
        removeLoading(loadingId);
        addMessage('assistant', '⚠️ Erreur de connexion. Veuillez vérifier votre connexion internet et réessayer.', false, true);
    } finally {
        setLoading(false);
    }
}

/**
 * Ajouter un message dans le chat
 */
function addMessage(role, content, isWelcome = false, isError = false) {
    if (!chatMessages) return;
    
    const messageDiv = document.createElement('div');
    messageDiv.className = `chat-message ${role}`;
    
    // Avatar
    const avatar = document.createElement('div');
    avatar.className = 'chat-message__avatar';
    
    if (role === 'user') {
        avatar.innerHTML = '<i data-lucide="user"></i>';
    } else {
        avatar.innerHTML = '<i data-lucide="sparkles"></i>';
    }
    
    // Contenu
    const contentDiv = document.createElement('div');
    contentDiv.className = 'chat-message__content';
    
    if (isError) {
        contentDiv.style.borderColor = '#DC2626';
        contentDiv.style.color = '#DC2626';
    }
    
    // Convertir le markdown basique en HTML
    content = formatMessage(content);
    contentDiv.innerHTML = content;
    
    messageDiv.appendChild(avatar);
    messageDiv.appendChild(contentDiv);
    
    chatMessages.appendChild(messageDiv);
    
    // Réinitialiser les icônes Lucide
    lucide.createIcons();
    
    // Scroll en bas
    scrollToBottom();
}

/**
 * Formater le message (markdown basique)
 */
function formatMessage(text) {
    if (!text) return '';
    
    // Échapper le HTML d'abord
    let escaped = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    
    // Code inline (avant les autres transformations)
    escaped = escaped.replace(/`([^`]+)`/g, '<code>$1</code>');
    
    // Gras (éviter les conflits avec les listes)
    escaped = escaped.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
    escaped = escaped.replace(/__([^_]+)__/g, '<strong>$1</strong>');
    
    // Italique (après le gras pour éviter les conflits)
    escaped = escaped.replace(/(?<!\*)\*([^*]+)\*(?!\*)/g, '<em>$1</em>');
    escaped = escaped.replace(/(?<!_)_([^_]+)_(?!_)/g, '<em>$1</em>');
    
    // Diviser en paragraphes
    const paragraphs = escaped.split(/\n\n+/);
    const formatted = paragraphs.map(para => {
        para = para.trim();
        if (!para) return '';
        
        // Titres
        if (para.match(/^### /)) {
            return '<h3>' + para.replace(/^### /, '') + '</h3>';
        }
        if (para.match(/^## /)) {
            return '<h2>' + para.replace(/^## /, '') + '</h2>';
        }
        if (para.match(/^# /)) {
            return '<h1>' + para.replace(/^# /, '') + '</h1>';
        }
        
        // Listes
        const lines = para.split('\n');
        const listItems = [];
        let inList = false;
        let listContent = [];
        
        lines.forEach(line => {
            const bulletMatch = line.match(/^[\-\*] (.+)$/);
            const numberMatch = line.match(/^\d+\. (.+)$/);
            
            if (bulletMatch || numberMatch) {
                if (!inList) {
                    inList = true;
                }
                const content = bulletMatch ? bulletMatch[1] : numberMatch[1];
                listContent.push('<li>' + content + '</li>');
            } else {
                if (inList && listContent.length > 0) {
                    listItems.push('<ul>' + listContent.join('') + '</ul>');
                    listContent = [];
                    inList = false;
                }
                if (line.trim()) {
                    listItems.push('<p>' + line + '</p>');
                }
            }
        });
        
        if (inList && listContent.length > 0) {
            listItems.push('<ul>' + listContent.join('') + '</ul>');
        }
        
        if (listItems.length > 0) {
            return listItems.join('');
        }
        
        // Paragraphe normal
        return '<p>' + para.replace(/\n/g, '<br>') + '</p>';
    }).filter(p => p).join('');
    
    return formatted;
}

/**
 * Afficher l'indicateur de chargement
 */
function showLoading() {
    if (!chatMessages) return null;
    
    const loadingDiv = document.createElement('div');
    loadingDiv.className = 'chat-message assistant';
    loadingDiv.id = 'chat-loading-' + Date.now();
    
    const avatar = document.createElement('div');
    avatar.className = 'chat-message__avatar';
    avatar.innerHTML = '<i data-lucide="sparkles"></i>';
    
    const loadingContent = document.createElement('div');
    loadingContent.className = 'chat-message__loading';
    loadingContent.innerHTML = `
        <div class="chat-message__loading-dot"></div>
        <div class="chat-message__loading-dot"></div>
        <div class="chat-message__loading-dot"></div>
    `;
    
    loadingDiv.appendChild(avatar);
    loadingDiv.appendChild(loadingContent);
    
    chatMessages.appendChild(loadingDiv);
    
    lucide.createIcons();
    scrollToBottom();
    
    return loadingDiv.id;
}

/**
 * Retirer l'indicateur de chargement
 */
function removeLoading(loadingId) {
    if (!loadingId) return;
    
    const loading = document.getElementById(loadingId);
    if (loading) {
        loading.remove();
    }
}

/**
 * Activer/désactiver le mode chargement
 */
function setLoading(loading) {
    isLoading = loading;
    
    if (chatInput) {
        chatInput.disabled = loading;
    }
    
    if (chatSendBtn) {
        chatSendBtn.disabled = loading;
    }
}

/**
 * Scroll en bas de la zone de messages
 */
function scrollToBottom() {
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
}

