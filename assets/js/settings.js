document.addEventListener('DOMContentLoaded', function() {
    // Fonction pour afficher un message
    function showMessage(text, type = 'info', options = {}) {
        const container = document.getElementById('messageContainer');
        if (!container) {
            console.error('Le conteneur de messages (#messageContainer) n\'a pas été trouvé dans le DOM');
            return null;
        }

        const message = document.createElement('div');
        message.className = `message message-${type}`;
        
        const icons = {
            success: 'fa-check-circle',
            error: 'fa-times-circle',
            warning: 'fa-exclamation-triangle',
            info: 'fa-info-circle',
            confirm: 'fa-question-circle'
        };
        
        let html = `
            <div class="message-content">
                <div class="message-icon"><i class="fas ${icons[type] || 'fa-info-circle'}"></i></div>
                <div class="message-text">${text}</div>
            </div>
        `;
        
        if (options.actions) {
            html += `<div class="message-actions">`;
            options.actions.forEach(action => {
                html += `<button class="${action.class || ''}">${action.text}</button>`;
            });
            html += `</div>`;
        } else {
            html += `<button class="btn-close"><i class="fas fa-times"></i></button>`;
        }
        
        message.innerHTML = html;
        container.appendChild(message);

        // Gestion des événements
        if (options.actions) {
            const buttons = message.querySelectorAll('.message-actions button');
            options.actions.forEach((action, index) => {
                buttons[index].addEventListener('click', () => {
                    if (action.clickHandler) action.clickHandler();
                    closeMessage(message);
                });
            });
        } else {
            message.querySelector('.btn-close').addEventListener('click', () => closeMessage(message));
            if (options.timeout !== false) {
                setTimeout(() => closeMessage(message), options.duration || 4000);
            }
        }
        
        return message;
    }

    function closeMessage(message) {
        if (!message) return;
        message.classList.add('hide');
        setTimeout(() => {
            if (message.parentNode) {
                message.parentNode.removeChild(message);
            }
        }, 300);
    }

    // Gestion du menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.querySelector('.dashboard-sidebar');
    const body = document.body;
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            body.classList.toggle('sidebar-active');
        });
        
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 992 && 
                !sidebar.contains(event.target) && 
                event.target !== menuToggle && 
                !menuToggle.contains(event.target)) {
                sidebar.classList.remove('active');
                body.classList.remove('sidebar-active');
            }
        });
    }

    // Gestion de l'affichage du formulaire de compte
    const editAccountBtn = document.getElementById('editAccountBtn');
    const accountFormContainer = document.getElementById('accountFormContainer');
    const cancelAccountBtn = document.getElementById('cancelAccountBtn');

    if (editAccountBtn) {
        editAccountBtn.addEventListener('click', function() {
            accountFormContainer.style.display = 'block';
        });
    }

    if (cancelAccountBtn) {
        cancelAccountBtn.addEventListener('click', function() {
            showMessage('Voulez-vous vraiment annuler les modifications ? Les changements non enregistrés seront perdus.', 'confirm', {
                actions: [
                    {
                        text: 'Oui',
                        class: 'btn-confirm',
                        clickHandler: function() {
                            accountFormContainer.style.display = 'none';
                        }
                    },
                    {
                        text: 'Non',
                        class: 'btn-cancel'
                    }
                ],
                timeout: false
            });
        });
    }

    // Soumission du formulaire de compte
    const editAccountForm = document.getElementById('editAccountForm');
    if (editAccountForm) {
        editAccountForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(editAccountForm);
            const submitBtn = editAccountForm.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            submitBtn.disabled = true;
            
            fetch('api/update_account.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('userNameDisplay').textContent = data.name;
                    document.getElementById('userEmailDisplay').textContent = data.email;
                    accountFormContainer.style.display = 'none';
                    showMessage('Informations mises à jour avec succès !', 'success');
                } else {
                    let errorMsg = 'Erreur lors de la mise à jour';
                    if (data.message) errorMsg += ': ' + data.message;
                    if (data.errors) {
                        errorMsg += '\n' + Object.values(data.errors).join('\n');
                    }
                    showMessage(errorMsg, 'error', { duration: 5000 });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Une erreur est survenue lors de la mise à jour', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    }

    // Gestion de l'entreprise
    const editEntrepriseBtn = document.getElementById('editEntrepriseBtn');
    const editEntrepriseOverlay = document.getElementById('editEntrepriseOverlay');
    
    if (editEntrepriseBtn) {
        editEntrepriseBtn.addEventListener('click', function() {
            const originalBtnText = editEntrepriseBtn.innerHTML;
            editEntrepriseBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Chargement...';
            editEntrepriseBtn.disabled = true;
            
            fetch('api/get_entreprise.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const form = document.getElementById('editEntrepriseForm');
                        form.innerHTML = 
                        `   <input type="hidden" name="entreprise_id" value="${data.entreprise.id}">
                            <div class="form-group">
                                <label>Nom de l'entreprise *</label>
                                <input type="text" name="nom" value="${data.entreprise.nom}" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Adresse *</label>
                                <textarea name="adresse" rows="3" required>${data.entreprise.adresse}</textarea>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Téléphone</label>
                                    <input type="text" name="telephone" value="${data.entreprise.telephone}">
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" name="email" value="${data.entreprise.email}">
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>TVA (%)</label>
                                    <input type="number" step="0.01" name="tva" value="${data.entreprise.tva}" required>
                                </div>
                                <div class="form-group">
                                    <label>Devise</label>
                                    <select name="devise" required>
                                        <option value="XOF" ${data.entreprise.devise === 'XOF' ? 'selected' : ''}>Franc CFA (XOF)</option>
                                        <option value="EUR" ${data.entreprise.devise === 'EUR' ? 'selected' : ''}>Euro (EUR)</option>
                                        <option value="USD" ${data.entreprise.devise === 'USD' ? 'selected' : ''}>Dollar (USD)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Logo</label>
                                <input type="file" name="logo" accept="image/*">
                                ${data.entreprise.logo ? `
                                <div class="current-logo">
                                    <img src="${data.entreprise.logo}" width="100">
                                    <label><input type="checkbox" name="remove_logo"> Supprimer le logo</label>
                                </div>
                                ` : ''}
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Enregistrer
                                </button>
                                <button type="button" id="cancelEditEntreprise" class="btn btn-cancel">
                                    <i class="fas fa-times"></i> Annuler
                                </button>
                            </div>
                        `;
                        
                        editEntrepriseOverlay.style.display = 'flex';
                        
                        document.getElementById('cancelEditEntreprise').addEventListener('click', function() {
                            showMessage('Voulez-vous vraiment annuler les modifications ? Les changements non enregistrés seront perdus.', 'confirm', {
                                actions: [
                                    {
                                        text: 'Oui',
                                        class: 'btn-confirm',
                                        clickHandler: function() {
                                            editEntrepriseOverlay.style.display = 'none';
                                        }
                                    },
                                    {
                                        text: 'Non',
                                        class: 'btn-cancel'
                                    }
                                ],
                                timeout: false
                            });
                        });
                    } else {
                        showMessage('Erreur lors du chargement des données de l\'entreprise', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Une erreur est survenue lors du chargement', 'error');
                })
                .finally(() => {
                    editEntrepriseBtn.innerHTML = originalBtnText;
                    editEntrepriseBtn.disabled = false;
                });
        });
    }

    // Gestion des formulaires entreprise
    const createEntrepriseForm = document.getElementById('createEntrepriseForm');
    const editEntrepriseForm = document.getElementById('editEntrepriseForm');
    
    function handleEntrepriseFormSubmit(form, isEdit = false) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const endpoint = isEdit ? 'api/update_entreprise.php' : 'api/create_entreprise.php';
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalBtnText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
            submitBtn.disabled = true;
            
            fetch(endpoint, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showMessage(
                        isEdit ? 'Entreprise mise à jour avec succès' : 'Entreprise créée avec succès', 
                        'success',
                        { duration: 3000 }
                    );
                    setTimeout(() => location.reload(), 1500);
                } else {
                    let errorMsg = 'Erreur: ' + (data.message || 'Action échouée');
                    if (data.errors) {
                        errorMsg += '\n' + Object.values(data.errors).join('\n');
                    }
                    showMessage(errorMsg, 'error', { duration: 5000 });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showMessage('Une erreur est survenue', 'error');
            })
            .finally(() => {
                submitBtn.innerHTML = originalBtnText;
                submitBtn.disabled = false;
            });
        });
    }
    
    if (createEntrepriseForm) handleEntrepriseFormSubmit(createEntrepriseForm);
    if (editEntrepriseForm) handleEntrepriseFormSubmit(editEntrepriseForm, true);
});