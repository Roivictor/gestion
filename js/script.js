// js/script.js

// Fonction pour récupérer le token CSRF depuis la balise meta
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.content : '';
}

// Fonction pour afficher une notification toast Bootstrap
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.warn('Toast container not found. Displaying alert instead.');
        alert(message); // Fallback si le conteneur n'existe pas
        return;
    }

    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = toastHtml;
    const toastElement = div.firstElementChild; // Récupère l'élément toast créé
    toastContainer.appendChild(toastElement);

    const toast = new bootstrap.Toast(toastElement); // Crée une nouvelle instance de Toast
    toast.show();

    // Supprime le toast du DOM une fois qu'il est complètement masqué
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}


document.addEventListener('DOMContentLoaded', function() {
    // Récupérer le token CSRF au chargement de la page
    const CSRF_TOKEN = getCsrfToken();

    if (!CSRF_TOKEN) {
        console.error("Erreur: Le token CSRF n'est pas trouvé dans la balise meta. Les requêtes AJAX pourraient échouer.");
        // Pas d'alerte bloquante ici, car cela n'empêche pas le chargement de la page.
        // L'alerte sera montrée si une action requérant le token est tentée.
    }

    // Gestion des boutons "Ajouter au panier"
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');

            // Récupérer la quantité depuis l'input du même conteneur (si disponible)
            // Assurez-vous que cette sélecteur est correct par rapport à votre HTML !
            const quantityInput = this.closest('div.card-footer, div.product-details').querySelector('input[type="number"][id^="quantity-"]'); // Sélecteur plus robuste si l'ID est dynamique
            const quantity = parseInt(quantityInput ? quantityInput.value : 1);

            if (isNaN(quantity) || quantity < 1) {
                showToast("Veuillez entrer une quantité valide.", 'warning');
                return;
            }

            if (!CSRF_TOKEN) {
                showToast("Erreur de sécurité : Token CSRF manquant. Veuillez rafraîchir la page.", 'danger');
                return;
            }

            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    product_id: productId,
                    action: 'add', // Si votre add_to_cart.php utilise 'action'
                    quantity: quantity,
                    csrf_token: CSRF_TOKEN // Inclure le token CSRF
                }).toString()
            })
            .then(response => {
                if (!response.ok) {
                    // Si le statut n'est pas 2xx, lance une erreur pour être capturée par .catch()
                    // Essaye de lire la réponse JSON même en cas d'erreur pour obtenir plus de détails
                    return response.json().then(err => { throw new Error(err.message || response.statusText); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const cartCountElement = document.getElementById('cart-count');
                    if (cartCountElement && data.cart_count !== undefined) {
                        cartCountElement.textContent = data.cart_count;
                    }
                    showToast(data.message || 'Produit ajouté au panier !', 'success');
                } else {
                    showToast('Erreur : ' + (data.message || 'Une erreur inconnue est survenue.'), 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur AJAX:', error);
                showToast('Une erreur de communication est survenue: ' + error.message, 'danger');
            });
        });
    });

    // 11. Gestion des vues (grille/liste) avec JavaScript (existante)
    // Assurez-vous que ces IDs existent dans votre HTML products.php
    const gridViewBtn = document.getElementById('grid-view');
    const listViewBtn = document.getElementById('list-view');
    const productsContainer = document.getElementById('products-container');

    if (gridViewBtn && listViewBtn && productsContainer) {
        gridViewBtn.addEventListener('click', function() {
            productsContainer.classList.remove('list-view');
            this.classList.add('active');
            listViewBtn.classList.remove('active');
        });

        listViewBtn.addEventListener('click', function() {
            productsContainer.classList.add('list-view');
            this.classList.add('active');
            gridViewBtn.classList.remove('active');
        });
    }


    // 12. Gestion du filtre par prix (interaction entre le slider et l'input numérique) (existante)
    // Assurez-vous que ces IDs/noms existent dans votre HTML products.php
    const priceRange = document.getElementById('price-range');
    const minPriceInput = document.querySelector('input[name="min_price"]'); // J'ai ajouté min_price pour la clarté
    const maxPriceInput = document.querySelector('input[name="max_price"]');

    if (priceRange && minPriceInput && maxPriceInput) {
        priceRange.addEventListener('input', function() {
            // Ici, le slider contrôle généralement la valeur MAX du prix
            maxPriceInput.value = this.value;
        });

        maxPriceInput.addEventListener('change', function() {
            // Quand l'input max_price est changé, met à jour la valeur du slider
            priceRange.value = this.value || priceRange.max;
        });

        // Vous pourriez vouloir une interaction bidirectionnelle pour min_price aussi
        minPriceInput.addEventListener('change', function() {
            // Assurez-vous que la valeur min ne dépasse pas la max si les deux sont contrôlées par JS
            if (parseFloat(this.value) > parseFloat(maxPriceInput.value)) {
                maxPriceInput.value = this.value;
                priceRange.value = this.value; // Ajuste aussi le slider
            }
        });
    }

    // Initialiser les tooltips Bootstrap (si vous en avez sur la page)
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});