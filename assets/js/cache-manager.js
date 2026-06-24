/**
 * FONCTION JAVASCRIPT - RAFRAÎCHISSEMENT AUTO DU CACHE
 * À ajouter dans vos fichiers JavaScript principaux
 */

// Fonction pour rafraîchir le cache après une action
function refreshCache() {
    fetch('/config/refresh_cache.php', {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('✅ Cache rafraîchi:', data.version);
        }
    })
    .catch(error => {
        console.error('❌ Erreur rafraîchissement cache:', error);
    });
}

// Fonction pour recharger la page en forçant le rechargement
function forceReload() {
    // Ajouter un timestamp pour éviter le cache
    const url = new URL(window.location.href);
    url.searchParams.set('_t', Date.now());
    window.location.href = url.toString();
}

// Fonction à appeler après chaque ajout/modification/suppression
function afterDataChange(callback) {
    // 1. Rafraîchir le cache serveur
    refreshCache();
    
    // 2. Attendre 500ms puis exécuter le callback
    setTimeout(() => {
        if (callback && typeof callback === 'function') {
            callback();
        } else {
            // Par défaut, recharger la page
            forceReload();
        }
    }, 500);
}

// Exemple d'utilisation après un formulaire
/*
document.querySelector('#monFormulaire').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Envoyer les données
    fetch('votre_url.php', {
        method: 'POST',
        body: new FormData(this)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Appeler le rafraîchissement du cache et recharger
            afterDataChange();
        }
    });
});
*/

// Désactiver le cache du navigateur pour les requêtes AJAX
fetch = new Proxy(fetch, {
    apply(target, thisArg, args) {
        const [url, options = {}] = args;
        
        // Ajouter des headers anti-cache
        options.headers = options.headers || {};
        options.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate';
        options.headers['Pragma'] = 'no-cache';
        options.headers['Expires'] = '0';
        
        // Ajouter un timestamp dans l'URL pour éviter le cache
        const urlObj = typeof url === 'string' ? new URL(url, window.location.origin) : url;
        urlObj.searchParams.set('_t', Date.now());
        
        return target.call(thisArg, urlObj, options);
    }
});

console.log('✅ Système anti-cache JavaScript chargé');
