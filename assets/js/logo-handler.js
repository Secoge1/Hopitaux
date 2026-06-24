/**
 * Gestion du chargement et de l'affichage du logo du système
 */
(function() {
    'use strict';
    
    /**
     * Initialiser la gestion des logos
     */
    function initLogoHandling() {
        // Trouver tous les logos système
        const logos = document.querySelectorAll('.system-logo');
        
        logos.forEach(logo => {
            // Si c'est une image
            if (logo.tagName === 'IMG') {
                // Gérer l'erreur de chargement
                logo.addEventListener('error', function() {
                    handleLogoError(this);
                });
                
                // Si l'image est déjà en erreur (chargée avant le script)
                if (logo.complete && logo.naturalHeight === 0) {
                    handleLogoError(logo);
                }
            }
        });
    }
    
    /**
     * Gérer l'erreur de chargement du logo
     * @param {HTMLImageElement} img - L'image en erreur
     */
    function handleLogoError(img) {
        console.warn('Erreur de chargement du logo, utilisation du fallback');
        
        // Cacher l'image
        img.style.display = 'none';
        
        // Chercher le fallback SVG dans le même conteneur
        const container = img.parentElement;
        if (container) {
            const fallback = container.querySelector('.logo-fallback');
            if (fallback) {
                fallback.style.display = 'inline-block';
            } else {
                // Créer un fallback si aucun n'existe
                createFallbackLogo(container, img);
            }
        }
    }
    
    /**
     * Créer un logo fallback SVG
     * @param {HTMLElement} container - Le conteneur
     * @param {HTMLImageElement} img - L'image originale
     */
    function createFallbackLogo(container, img) {
        const width = img.width || 64;
        const height = img.height || 64;
        const classes = img.className;
        
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('width', width);
        svg.setAttribute('height', height);
        svg.setAttribute('viewBox', '0 0 64 64');
        svg.setAttribute('class', classes + ' logo-fallback');
        svg.style.display = 'inline-block';
        
        svg.innerHTML = `
            <circle cx="32" cy="32" r="30" fill="#17A1B8" stroke="#0F7A8A" stroke-width="2"/>
            <path d="M20 28H24V32H28V28H32V24H28V20H24V24H20V28Z" fill="white"/>
            <path d="M36 28V24H44V28H36ZM36 32V36H44V32H36Z" fill="white"/>
            <circle cx="28" cy="48" r="2" fill="white"/>
            <circle cx="36" cy="48" r="2" fill="white"/>
        `;
        
        container.appendChild(svg);
    }
    
    /**
     * Recharger le logo si nécessaire
     */
    function reloadLogos() {
        const logos = document.querySelectorAll('.system-logo');
        logos.forEach(logo => {
            if (logo.tagName === 'IMG' && logo.naturalHeight === 0) {
                // Essayer de recharger l'image
                const src = logo.src;
                logo.src = '';
                setTimeout(() => {
                    logo.src = src + '?t=' + Date.now();
                }, 100);
            }
        });
    }
    
    // Initialiser au chargement du DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initLogoHandling);
    } else {
        initLogoHandling();
    }
    
    // Observer les changements dans le DOM pour gérer les logos ajoutés dynamiquement
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) {
                        // Si c'est un logo
                        if (node.classList && node.classList.contains('system-logo')) {
                            if (node.tagName === 'IMG') {
                                node.addEventListener('error', function() {
                                    handleLogoError(this);
                                });
                            }
                        }
                        // Ou si le nœud contient des logos
                        else {
                            const logos = node.querySelectorAll('.system-logo');
                            logos.forEach(logo => {
                                if (logo.tagName === 'IMG') {
                                    logo.addEventListener('error', function() {
                                        handleLogoError(this);
                                    });
                                }
                            });
                        }
                    }
                });
            }
        });
    });
    
    // Observer le body
    if (document.body) {
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
    
    // Exposer les fonctions globalement pour debug
    window.logoHandler = {
        reload: reloadLogos,
        init: initLogoHandling
    };
    
})();

