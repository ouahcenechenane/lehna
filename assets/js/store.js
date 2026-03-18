/**
 * assets/js/store.js
 * JavaScript de la boutique publique LEHNA
 * Filtrage dynamique, recherche, pagination
 */

'use strict';

document.addEventListener('DOMContentLoaded', () => {

    /* ── Filtres catégories (sans rechargement) ── */
    const catBtns = document.querySelectorAll('.cat-filter-btn');
    const cards   = document.querySelectorAll('.product-card');
    const noResultEl = document.getElementById('noProducts');
    const resultCountEl = document.getElementById('resultCount');

    // Recherche dans l'URL pour activer le bon filtre
    const urlParams = new URLSearchParams(window.location.search);
    let activeCategory = urlParams.get('cat') || 'all';
    let searchQuery    = urlParams.get('search') || '';

    function filterProducts() {
        let visibleCount = 0;

        cards.forEach(card => {
            const cat  = card.dataset.categorie || '';
            const nom  = (card.dataset.nom || '').toLowerCase();
            const code = (card.dataset.code || '').toLowerCase();

            const catMatch    = (activeCategory === 'all') || (cat === activeCategory);
            const searchMatch = searchQuery === '' || nom.includes(searchQuery.toLowerCase()) || code.includes(searchQuery.toLowerCase());

            if (catMatch && searchMatch) {
                card.style.display = '';
                card.style.animation = 'none';
                // Légère animation pour les cartes qui réapparaissent
                requestAnimationFrame(() => {
                    card.style.animation = '';
                });
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        if (noResultEl) {
            noResultEl.style.display = visibleCount === 0 ? '' : 'none';
        }

        if (resultCountEl) {
            resultCountEl.textContent = `${visibleCount} produit${visibleCount !== 1 ? 's' : ''} trouvé${visibleCount !== 1 ? 's' : ''}`;
        }
    }

    // Boutons de filtre catégorie
    catBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            catBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeCategory = btn.dataset.cat || 'all';
            filterProducts();
        });
    });

    // Active le bon bouton selon l'URL
    catBtns.forEach(btn => {
        if (btn.dataset.cat === activeCategory) btn.classList.add('active');
    });

    /* ── Barre de recherche live ── */
    const searchInput = document.getElementById('searchInput');

    if (searchInput) {
        // Pré-remplir depuis l'URL
        if (searchQuery) searchInput.value = searchQuery;

        let debounceTimer;
        searchInput.addEventListener('input', () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                searchQuery = searchInput.value.trim();
                filterProducts();
            }, 200);
        });

        // Touche Escape vide la recherche
        searchInput.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                searchInput.value = '';
                searchQuery = '';
                filterProducts();
            }
        });
    }

    // Filtre initial
    filterProducts();

});
