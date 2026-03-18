/**
 * assets/js/admin.js
 * JavaScript global de l'interface d'administration LEHNA
 */

'use strict';

/* ════════════════════════════════════════════════════════════
   TOAST NOTIFICATIONS
   ════════════════════════════════════════════════════════════ */

/**
 * Affiche une notification "toast" temporaire.
 * @param {string} message  Texte à afficher
 * @param {string} type     'success' | 'error' | 'info'
 * @param {number} duration Durée en ms (défaut 3500)
 */
function showNotif(message, type = 'info', duration = 3500) {
    // Crée le conteneur si inexistant
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    // Suppression après la durée
    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(20px)';
        toast.style.transition = 'all .3s ease';
        setTimeout(() => toast.remove(), 300);
    }, duration);
}

/* ════════════════════════════════════════════════════════════
   GESTION DES ALERTES FLASH
   ════════════════════════════════════════════════════════════ */
document.addEventListener('DOMContentLoaded', () => {
    // Auto-disparition des alertes flash après 5s
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity .4s ease';
            setTimeout(() => alert.remove(), 400);
        }, 5000);
    });

    // Fermeture du modal si clic sur backdrop
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.addEventListener('click', () => {
            backdrop.closest('.modal')?.classList.add('hidden');
        });
    });
});

/* ════════════════════════════════════════════════════════════
   CONFIRMATION DE SUPPRESSION GÉNÉRIQUE
   ════════════════════════════════════════════════════════════ */
// Déjà géré page par page pour plus de flexibilité

/* ════════════════════════════════════════════════════════════
   UTILITAIRES
   ════════════════════════════════════════════════════════════ */

/**
 * Formate un nombre en prix algérien.
 * @param {number} val
 * @returns {string}
 */
function formatPrix(val) {
    return new Intl.NumberFormat('fr-DZ', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(val) + ' DA';
}

/**
 * Debounce – évite de déclencher une fonction trop souvent.
 * @param {Function} fn
 * @param {number}   delay
 */
function debounce(fn, delay = 300) {
    let t;
    return (...args) => {
        clearTimeout(t);
        t = setTimeout(() => fn(...args), delay);
    };
}
