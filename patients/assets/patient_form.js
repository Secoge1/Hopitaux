/**
 * Améliorations du formulaire d'ajout de patient
 * Fonctionnalités de recherche et validation
 */

document.addEventListener('DOMContentLoaded', function() {
    initPatientBirthdateFields();

    // Amélioration du champ pays avec recherche intégrée
    const paysSelect = document.getElementById('pays');
    if (paysSelect) {
        // Ajouter un attribut data-searchable pour indiquer que le select est recherchable
        paysSelect.setAttribute('data-searchable', 'true');
        
        // Ajouter un placeholder au select pour indiquer la fonctionnalité de recherche
        paysSelect.setAttribute('data-placeholder', 'Tapez pour rechercher un pays...');
        
        // Fonction de recherche intégrée au select
        paysSelect.addEventListener('keyup', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const options = this.querySelectorAll('option');
            
            // Filtrer les options en temps réel
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                const value = option.value.toLowerCase();
                const matches = text.includes(searchTerm) || value.includes(searchTerm);
                
                if (matches || option.value === '') {
                    option.style.display = '';
                } else {
                    option.style.display = 'none';
                }
            });
        });
        
        // Réinitialiser l'affichage quand le select perd le focus
        paysSelect.addEventListener('blur', function() {
            setTimeout(() => {
                const options = this.querySelectorAll('option');
                options.forEach(option => {
                    option.style.display = '';
                });
            }, 200);
        });
        
        // Améliorer l'apparence du select pour indiquer qu'il est recherchable
        paysSelect.style.backgroundImage = 'url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'m1 1 6 6 6-6\'/%3e%3c/svg%3e"), url("data:image/svg+xml,%3csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 16 16\'%3e%3cpath fill=\'none\' stroke=\'%23343a40\' stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z\'/%3e%3c/svg%3e")';
        paysSelect.style.backgroundPosition = 'right 0.75rem center, right 2.5rem center';
        paysSelect.style.backgroundRepeat = 'no-repeat, no-repeat';
        paysSelect.style.backgroundSize = '16px 12px, 16px 16px';
        paysSelect.style.paddingRight = '4rem';
    }
    
    // Validation en temps réel des champs
    const requiredFields = document.querySelectorAll('[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            validateField(this);
        });
        
        field.addEventListener('input', function() {
            if (this.classList.contains('is-invalid')) {
                validateField(this);
            }
        });
    });
    
    // Validation d'un champ
    function validateField(field) {
        const value = field.value.trim();
        const isValid = value.length > 0;
        
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }
        
        updateSubmitButton();
    }
    
    // Validation de l'email
    const emailField = document.getElementById('email');
    if (emailField) {
        emailField.addEventListener('blur', function() {
            const email = this.value.trim();
            if (email && !isValidEmail(email)) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                showFieldError(this, 'Format d\'email invalide');
            } else if (email) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                removeFieldError(this);
            }
        });
    }
    
    // Validation du téléphone
    const phoneField = document.getElementById('telephone');
    if (phoneField) {
        phoneField.addEventListener('blur', function() {
            const phone = this.value.trim();
            if (phone && !isValidPhone(phone)) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                showFieldError(this, 'Format de téléphone invalide');
            } else if (phone) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                removeFieldError(this);
            }
        });
    }
    
    // Validation de l'âge / date de naissance
    const ageField = document.getElementById('age_ans');
    if (ageField) {
        ageField.addEventListener('blur', function() {
            const age = parseInt(this.value, 10);
            if (this.value !== '' && (Number.isNaN(age) || age < 0 || age > 120)) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                showFieldError(this, 'Âge invalide (0 à 120 ans)');
            } else if (this.value !== '') {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                removeFieldError(this);
            }
        });
    }

    const birthField = document.getElementById('date_naissance');
    if (birthField) {
        birthField.addEventListener('blur', function() {
            if (!this.value || this.hasAttribute('hidden') || this.closest('[hidden]')) {
                return;
            }
            const birthDate = new Date(this.value + 'T12:00:00');
            const today = new Date();
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }

            if (age > 120 || age < 0) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                showFieldError(this, 'Date de naissance invalide');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                removeFieldError(this);
            }
        });
    }
    
    // Validation du code postal
    const postalField = document.getElementById('code_postal');
    if (postalField) {
        postalField.addEventListener('blur', function() {
            const postal = this.value.trim();
            if (postal && !isValidPostal(postal)) {
                this.classList.remove('is-valid');
                this.classList.add('is-invalid');
                showFieldError(this, 'Code postal invalide');
            } else if (postal) {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
                removeFieldError(this);
            }
        });
    }
    
    // Fonctions de validation
    function isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }
    
    function isValidPhone(phone) {
        const phoneRegex = /^[\d\s\+\-\(\)]{10,}$/;
        return phoneRegex.test(phone);
    }
    
    function isValidPostal(postal) {
        const postalRegex = /^\d{5}$/;
        return postalRegex.test(postal);
    }
    
    // Affichage des erreurs
    function showFieldError(field, message) {
        removeFieldError(field);
        
        const errorDiv = document.createElement('div');
        errorDiv.className = 'invalid-feedback';
        errorDiv.textContent = message;
        errorDiv.id = field.id + '-error';
        
        field.parentElement.appendChild(errorDiv);
    }
    
    function removeFieldError(field) {
        const existingError = document.getElementById(field.id + '-error');
        if (existingError) {
            existingError.remove();
        }
    }
    
    // Mise à jour du bouton de soumission
    function updateSubmitButton() {
        const submitBtn = document.querySelector('button[type="submit"]');
        if (submitBtn) {
            const invalidFields = document.querySelectorAll('.is-invalid');
            submitBtn.disabled = invalidFields.length > 0;
            
            if (invalidFields.length > 0) {
                submitBtn.title = 'Veuillez corriger les erreurs avant de soumettre';
            } else {
                submitBtn.title = 'Créer le patient';
            }
        }
    }
    
    // Auto-complétion de la ville basée sur le code postal
    if (postalField && document.getElementById('ville')) {
        const villeField = document.getElementById('ville');
        
        postalField.addEventListener('blur', function() {
            const postal = this.value.trim();
            if (postal && isValidPostal(postal)) {
                // Simulation d'auto-complétion (dans un vrai projet, on utiliserait une API)
                const villes = {
                    '75001': 'Paris 1er',
                    '75002': 'Paris 2ème',
                    '75003': 'Paris 3ème',
                    '75004': 'Paris 4ème',
                    '75005': 'Paris 5ème',
                    '75006': 'Paris 6ème',
                    '75007': 'Paris 7ème',
                    '75008': 'Paris 8ème',
                    '75009': 'Paris 9ème',
                    '75010': 'Paris 10ème',
                    '75011': 'Paris 11ème',
                    '75012': 'Paris 12ème',
                    '75013': 'Paris 13ème',
                    '75014': 'Paris 14ème',
                    '75015': 'Paris 15ème',
                    '75016': 'Paris 16ème',
                    '75017': 'Paris 17ème',
                    '75018': 'Paris 18ème',
                    '75019': 'Paris 19ème',
                    '75020': 'Paris 20ème'
                };
                
                if (villes[postal]) {
                    villeField.value = villes[postal];
                    villeField.classList.add('is-valid');
                }
            }
        });
    }
    
    // Sauvegarde automatique des données en cours de saisie
    const formFields = document.querySelectorAll('input, select, textarea');
    formFields.forEach(field => {
        field.addEventListener('input', function() {
            saveFormData();
        });
    });
    
    function saveFormData() {
        const formData = {};
        formFields.forEach(field => {
            if (field.name) {
                formData[field.name] = field.value;
            }
        });
        
        localStorage.setItem('patientFormData', JSON.stringify(formData));
    }
    
    function loadFormData() {
        const savedData = localStorage.getItem('patientFormData');
        if (savedData) {
            const formData = JSON.parse(savedData);
            Object.keys(formData).forEach(key => {
                const field = document.querySelector(`[name="${key}"]`);
                if (field && formData[key]) {
                    field.value = formData[key];
                    if (field.hasAttribute('required')) {
                        validateField(field);
                    }
                }
            });
        }
    }
    
    // Charger les données sauvegardées au chargement de la page
    loadFormData();
    
    // Nettoyer le stockage après soumission réussie
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            localStorage.removeItem('patientFormData');
        });
    }
    
    // Amélioration de l'expérience utilisateur
    const formGroups = document.querySelectorAll('.col-md-6, .col-md-4, .col-md-12');
    formGroups.forEach(group => {
        group.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.2s ease';
        });
        
        group.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
});

function initPatientBirthdateFields() {
    const toggleBtn = document.getElementById('toggle_date_naissance');
    const wrap = document.getElementById('date_naissance_wrap');
    const ageField = document.getElementById('age_ans');
    const dateField = document.getElementById('date_naissance');
    if (!toggleBtn || !wrap || !ageField || !dateField) {
        return;
    }

    toggleBtn.addEventListener('click', function() {
        const showDate = wrap.hasAttribute('hidden');
        wrap.toggleAttribute('hidden', !showDate);
        toggleBtn.setAttribute('aria-expanded', showDate ? 'true' : 'false');
        toggleBtn.innerHTML = showDate
            ? '<i class="fas fa-times me-1"></i>Masquer la date'
            : '<i class="fas fa-calendar-alt me-1"></i>Date exacte connue';

        if (showDate) {
            ageField.removeAttribute('required');
            dateField.disabled = false;
            dateField.setAttribute('required', 'required');
            dateField.focus();
        } else {
            dateField.removeAttribute('required');
            dateField.disabled = true;
            ageField.setAttribute('required', 'required');
        }
    });

    dateField.addEventListener('change', function() {
        if (!dateField.value) {
            return;
        }
        const birth = new Date(dateField.value + 'T12:00:00');
        const today = new Date();
        let age = today.getFullYear() - birth.getFullYear();
        const monthDiff = today.getMonth() - birth.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) {
            age--;
        }
        if (age >= 0 && age <= 120) {
            ageField.value = String(age);
        }
    });
}
