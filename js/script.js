// js/script.js - Version Optimisée et Unifiée

// --- Fonctions Utilitaires Globales ---

/**
 * Récupère le token CSRF depuis la balise meta.
 * @returns {string} Le token CSRF ou une chaîne vide si non trouvé.
 */
function getCsrfToken() {
    const metaTag = document.querySelector('meta[name="csrf-token"]');
    return metaTag ? metaTag.content : '';
}

/**
 * Traduit les messages clés côté client.
 * NOTE : Puisque la gestion de langue n'est pas implémentée (selon config.php),
 * cette fonction retourne simplement la valeur par défaut ou la clé elle-même.
 * Si une gestion de langue est ajoutée à l'avenir, c'est ici qu'il faudra l'implémenter.
 * @param {string} key La clé de traduction (ex: '[product_added_to_cart]').
 * @param {string} [defaultValue] La valeur par défaut si la clé n'est pas trouvée dans un dictionnaire.
 * @returns {string} Le message traduit ou la valeur par défaut/clé.
 */
function translateMessage(key, defaultValue) {
    // Si la valeur par défaut est fournie, l'utiliser. Sinon, retourner la clé.
    return defaultValue !== undefined ? defaultValue : key;
}

/**
 * Fonction utilitaire pour éviter les XSS lors de l'insertion dans le DOM.
 * Déclarée globalement pour être réutilisable par toutes les fonctions nécessitant un échappement HTML.
 * @param {string} str La chaîne à échapper.
 * @returns {string} La chaîne échappée.
 */
function htmlspecialchars(str) {
    if (typeof str !== 'string') {
        return String(str); // Convertir en chaîne en toute sécurité, puis échapper
    }
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}

/**
 * Affiche une notification toast Bootstrap.
 * Nécessite un élément #toast-container dans le DOM (de préférence en haut à droite pour une visibilité constante).
 * @param {string} message Le message à afficher.
 * @param {string} type Le type de toast (ex: 'info', 'success', 'warning', 'danger').
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toast-container');
    if (!toastContainer) {
        console.warn('Toast container (#toast-container) not found. Displaying alert instead.');
        alert(message); // Fallback si le conteneur n'existe pas
        return;
    }

    // Le message est déjà échappé par htmlspecialchars avant l'insertion dans le DOM
    const sanitizedMessage = htmlspecialchars(message);

    const toastHtml = `
        <div class="toast align-items-center text-white bg-${type} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4000">
            <div class="d-flex">
                <div class="toast-body">
                    ${sanitizedMessage}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;

    const div = document.createElement('div');
    div.innerHTML = toastHtml;
    const toastElement = div.firstElementChild; // Récupère l'élément toast créé
    toastContainer.appendChild(toastElement);

    const toast = new bootstrap.Toast(toastElement); // Crée une nouvelle instance Toast
    toast.show();

    // Supprime le toast du DOM une fois qu'il est complètement masqué
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

/**
 * Met à jour l'affichage du nombre d'articles dans le panier.
 * @param {number} count Le nouveau nombre d'articles.
 */
function updateCartCount(count) {
    const cartCountElement = document.getElementById('cart-count');
    if (cartCountElement && count !== undefined && !isNaN(count)) {
        cartCountElement.textContent = count;
    }
}

// --- Initialisation et Gestion des Événements DOM ---

document.addEventListener('DOMContentLoaded', function() {
    const CSRF_TOKEN = getCsrfToken();

    if (!CSRF_TOKEN) {
        console.error("Erreur: Le token CSRF n'est pas trouvé dans la balise meta. Les requêtes AJAX pourraient échouer.");
        showToast(translateMessage('[csrf_token_mismatch]', 'Erreur de sécurité : Token CSRF manquant au chargement. Certaines fonctions peuvent être désactivées.'), 'danger');
    }

    // --- Gestion de l'ajout au panier ---
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();

            // S'assurer que le bouton utilise data-product-id
            const productId = this.dataset.productId; 
            // Récupère la variant_id, en utilisant 'selectedVariantId' si présent (pour les pages produit détaillées)
            // ou en l'omettant pour les pages listant les produits principaux (comme index.php)
            const rawVariantId = this.dataset.selectedVariantId; 

            let variantId = null; // Par défaut à null pour indiquer le produit principal

            // Si rawVariantId est défini et n'est pas une chaîne vide
            if (rawVariantId !== undefined && rawVariantId !== "") {
                const parsed = parseInt(rawVariantId);
                if (!isNaN(parsed) && parsed > 0) { // Si c'est un nombre valide et positif
                    variantId = parsed;
                }
            }
            // LIGNES DE DÉBOGAGE
            // console.log("DEBUG - rawVariantId:", rawVariantId, "Type:", typeof rawVariantId);
            // console.log("DEBUG - variantId (après conversion):", variantId, "Type:", typeof variantId);
            // FIN DES LIGNES DE DÉBOGAGE

            const quantityInput = document.getElementById('quantity'); // Peut être undefined sur index.php
            const quantity = parseInt(quantityInput ? quantityInput.value : 1); // Quantité par défaut à 1

            if (isNaN(quantity) || quantity < 1) {
                showToast(translateMessage('[invalid_quantity]', "Veuillez entrer une quantité valide."), 'warning');
                return;
            }

            if (!CSRF_TOKEN) {
                showToast(translateMessage('[csrf_token_mismatch]', 'Erreur de sécurité : Token CSRF manquant.'), 'danger');
                return;
            }

            const parsedProductId = parseInt(productId);
            
            if (isNaN(parsedProductId) || parsedProductId <= 0) {
                showToast(translateMessage('[invalid_product_id]', 'ID de produit invalide. Impossible d\'ajouter au panier.'), 'danger');
                console.error("Missing or invalid product ID for add to cart:", productId);
                return;
            }

            // Validation finale de variantId: si ce n'est PAS null, il doit être un nombre valide.
            if (variantId !== null && (isNaN(variantId) || variantId <= 0)) {
                showToast(translateMessage('[invalid_variant_id]', 'ID de variante invalide. Impossible d\'ajouter au panier.'), 'danger');
                console.error("Invalid variant ID for add to cart (after final check):", rawVariantId);
                return;
            }

            const originalButtonText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Ajout...';

            const requestBody = {
                product_id: parsedProductId,
                variant_id: variantId, // Sera null si aucune variante spécifique (produit principal)
                action: 'add', 
                quantity: quantity,
                _csrf_token: CSRF_TOKEN // Cohérence avec cart_ajax.php attendu
            };

            fetch('cart_ajax.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json().then(data => {
                        if (!response.ok) {
                            throw new Error(translateMessage(data.message, data.message || response.statusText));
                        }
                        return data;
                    });
                } else {
                    return response.text().then(text => {
                        console.error("Non-JSON server response for add to cart:", text);
                        throw new Error(translateMessage('[unexpected_server_response]', 'Réponse inattendue du serveur.'));
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cart_count);
                    showToast(translateMessage(data.message, 'Article ajouté au panier !'), 'success'); 
                } else {
                    showToast(translateMessage(data.message, 'Erreur lors de l\'ajout au panier.'), 'danger'); 
                }
            })
            .catch(error => {
                console.error('AJAX Error "Add to Cart":', error);
                showToast('Une erreur de communication est survenue lors de l\'ajout au panier : ' + error.message, 'danger');
            })
            .finally(() => {
                this.disabled = false;
                this.innerHTML = originalButtonText;
            });
        });
    });

    // --- Gestion du formulaire d'avis ---
    const reviewForm = document.getElementById('reviewForm');
    const ratingStars = document.querySelectorAll('.review-star');
    const ratingInput = document.getElementById('rating');

    if (reviewForm && ratingInput) {
        let currentRating = parseInt(ratingInput.value) || 0;

        const updateStarDisplay = (ratingToDisplay) => {
            if (ratingStars.length > 0) {
                ratingStars.forEach((star, index) => {
                    if (index < ratingToDisplay) {
                        star.classList.add('bi-star-fill', 'text-warning');
                        star.classList.remove('bi-star');
                    } else {
                        star.classList.remove('bi-star-fill', 'text-warning');
                        star.classList.add('bi-star');
                    }
                });
            }
        };

        if (ratingStars.length > 0) {
            ratingStars.forEach(star => {
                star.addEventListener('mouseover', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    updateStarDisplay(rating);
                });

                star.addEventListener('mouseout', function() {
                    updateStarDisplay(currentRating);
                });

                star.addEventListener('click', function() {
                    currentRating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = currentRating;
                    updateStarDisplay(currentRating);
                });
            });
        }

        updateStarDisplay(currentRating);

        reviewForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            // Assurez-vous que le backend PHP attend 'csrf_token' ou ' _csrf_token' pour les formulaires FormData
            formData.append('csrf_token', CSRF_TOKEN); // Correction ici pour utiliser 'csrf_token' si le backend s'attend à 'csrf_token'

            if (parseInt(ratingInput.value) === 0) {
                showToast(translateMessage('[invalid_review_data]', 'Veuillez donner une note.'), 'warning');
                return;
            }
            if (formData.get('comment').trim() === '') {
                showToast(translateMessage('[invalid_review_data]', 'Veuillez entrer un commentaire pour votre avis.'), 'warning');
                return;
            }

            if (!CSRF_TOKEN) {
                showToast(translateMessage('[csrf_token_mismatch]', 'Erreur de sécurité : Token CSRF manquant.'), 'danger');
                return;
            }

            const submitButton = reviewForm.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.innerHTML;
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Envoi...';


            fetch('submit_review.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json().then(data => {
                        if (!response.ok) {
                            throw new Error(translateMessage(data.message, data.message || response.statusText));
                        }
                        return data;
                    });
                } else {
                    return response.text().then(text => {
                        console.error("Non-JSON server response for review submission:", text);
                        throw new Error(translateMessage('[unexpected_server_response]', 'Réponse inattendue du serveur pour la soumission d\'avis.'));
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    showToast(translateMessage(data.message, 'Votre avis a été soumis avec succès !'), 'success');
                    reviewForm.reset();
                    currentRating = 0;
                    ratingInput.value = 0;
                    updateStarDisplay(0);
                    const reviewModal = bootstrap.Modal.getInstance(document.getElementById('reviewModal'));
                    if (reviewModal) {
                        reviewModal.hide();
                    }
                } else {
                    showToast(translateMessage(data.message, 'Une erreur inconnue est survenue lors de la soumission de l\'avis.'), 'danger');

                    if (data.message === '[already_reviewed]') {
                        submitButton.disabled = true;
                        const commentTextarea = reviewForm.querySelector('textarea[name="comment"]');
                        if (commentTextarea) commentTextarea.disabled = true;
                        ratingStars.forEach(star => star.style.pointerEvents = 'none');
                    }
                }
            })
            .catch(error => {
                console.error('AJAX Error "Review Submission":', error);
                showToast(translateMessage('[communication_error]', 'Une erreur de communication est survenue lors de la soumission de l\'avis : ') + error.message, 'danger');
            })
            .finally(() => {
                submitButton.disabled = false;
                submitButton.innerHTML = originalButtonText;
            });
        });
    }

    // --- Gestion du formulaire de filtre des produits (Admin Dashboard) ---
    const filterProductsForm = document.getElementById('filterProductsForm');
    const productsTableBody = document.getElementById('productsTableBody');

    if (filterProductsForm && productsTableBody) {
        productsTableBody.addEventListener('click', function(event) {
            const targetButton = event.target.closest('.delete-product-btn');
            if (targetButton) {
                handleDeleteProduct.call(targetButton, event);
            }
        });

        filterProductsForm.addEventListener('submit', handleProductFilterSubmit);
        const filterButton = filterProductsForm.querySelector('button[type="submit"]');

        function handleProductFilterSubmit(event) {
            event.preventDefault();

            const formData = new FormData(filterProductsForm);
            const queryString = new URLSearchParams(formData).toString();
            // Assurez-vous que admin_ajax.php gère _csrf_token via GET pour cette action
            const fetchUrl = `admin_ajax.php?action=filter_products&_csrf_token=${CSRF_TOKEN}&${queryString}`; 

            filterButton.disabled = true;
            filterButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + translateMessage('[filter_button_filtering]', 'Filtrage...');

            fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error("Non-JSON server response for product filter:", text);
                        throw new Error(translateMessage('[unexpected_server_response]', 'Réponse inattendue du serveur pour le filtre des produits.'));
                    });
                }
            })
            .then(data => {
                filterButton.disabled = false;
                filterButton.innerHTML = '<i class="bi bi-funnel"></i> ' + translateMessage('[filter_button_filter]', 'Filtrer');

                if (data.error_message) {
                    showToast(translateMessage(data.error_message, data.error_message), 'danger');
                } else if (data.success_message) {
                    showToast(translateMessage(data.success_message, data.success_message), 'success');
                }

                productsTableBody.innerHTML = '';

                if (data.products && data.products.length > 0) {
                    data.products.forEach(product => {
                        const row = `
                            <tr>
                                <td>${htmlspecialchars(String(product.id))}</td>
                                <td>
                                    ${product.image_url ?
                                        `<img src="../uploads/products/${htmlspecialchars(product.image_url)}" alt="${htmlspecialchars(product.name)}" width="50">` :
                                        `<span class="text-muted">${translateMessage('[no_image]', 'Aucune image')}</span>`
                                    }
                                </td>
                                <td>${htmlspecialchars(product.name)}</td>
                                <td>${product.category_name ? htmlspecialchars(product.category_name) : translateMessage('[none]', 'Aucune')}</td>
                                <td>${parseFloat(product.price).toFixed(2).replace('.', ',')} €</td> <td>
                                    <span class="badge ${product.stock_quantity > 0 ? 'bg-success' : 'bg-danger'}">
                                        ${htmlspecialchars(String(product.stock_quantity))}
                                    </span>
                                </td>
                                <td>${new Date(product.created_at).toLocaleDateString('fr-FR')}</td>
                                <td>
                                    <a href="dashboard.php?page=edit_product&id=${product.id}" class="btn btn-sm btn-outline-primary" title="${translateMessage('[edit]', 'Modifier')}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <button type="button"
                                            class="btn btn-sm btn-outline-danger delete-product-btn"
                                            data-product-id="${product.id}"
                                            title="${translateMessage('[delete]', 'Supprimer')}">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        `;
                        productsTableBody.insertAdjacentHTML('beforeend', row);
                    });

                } else {
                    productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center">${translateMessage('[no_products_found]', 'Aucun produit trouvé')}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Erreur lors du filtrage des produits:', error);
                showToast(translateMessage('[product_fetch_error]', 'Une erreur est survenue lors du filtrage des produits.') + ' ' + error.message, 'danger');
            })
            .finally(() => {
                filterButton.disabled = false;
                filterButton.innerHTML = '<i class="bi bi-funnel"></i> ' + translateMessage('[filter_button_filter]', 'Filtrer');
            });
        }

        function handleDeleteProduct(event) {
            event.preventDefault();

            const button = this;
            const productId = button.dataset.productId;

            if (!confirm(translateMessage('[delete_product_confirm]', 'Êtes-vous sûr de vouloir supprimer ce produit ?'))) {
                return;
            }

            if (!CSRF_TOKEN) {
                showToast(translateMessage('[csrf_token_mismatch]', 'Erreur de sécurité : Token CSRF manquant.'), 'danger');
                return;
            }

            const originalButtonContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> ' + translateMessage('[delete_button_deleting]', 'Suppression...');

            const deleteUrl = `admin_ajax.php?action=delete_product&id=${productId}&_csrf_token=${CSRF_TOKEN}`;

            fetch(deleteUrl, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error("Non-JSON server response for product deletion:", text);
                        throw new Error(translateMessage('[unexpected_server_response]', 'Réponse inattendue du serveur pour la suppression.'));
                    });
                }
            })
            .then(data => {
                if (data.success_message) {
                    showToast(translateMessage(data.success_message, 'Produit supprimé avec succès !'), 'success'); 
                    filterProductsForm.dispatchEvent(new Event('submit')); // Re-filtrer pour rafraîchir la liste
                } else if (data.error_message) {
                    showToast(translateMessage(data.error_message, data.error_message), 'danger'); 
                } else {
                    showToast(translateMessage('[product_delete_error]', 'Erreur inconnue lors de la suppression du produit.'), 'danger');
                }
            })
            .catch(error => {
                console.error('AJAX Error during product deletion:', error);
                showToast(translateMessage('[communication_error]', 'Une erreur est survenue lors de la suppression du produit : ') + error.message, 'danger');
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalButtonContent;
            });
        }
    }


    // --- Autres fonctionnalités existantes (non AJAX) ---

    // Grid/List View Toggle
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

    // Price Range Slider and Inputs (on product list page)
    const priceRange = document.getElementById('price-range');
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');

    if (priceRange && minPriceInput && maxPriceInput) {
        maxPriceInput.value = priceRange.value;

        priceRange.addEventListener('input', function() {
            maxPriceInput.value = this.value;
        });

        maxPriceInput.addEventListener('change', function() {
            let maxValue = parseFloat(this.value);
            let minValue = parseFloat(minPriceInput.value || 0);

            if (isNaN(maxValue)) {
                maxValue = parseFloat(priceRange.max);
                this.value = priceRange.max;
            }

            if (maxValue < minValue) {
                maxValue = minValue;
                this.value = minValue;
            }
            priceRange.value = maxValue;
        });

        minPriceInput.addEventListener('change', function() {
            let minValue = parseFloat(this.value);
            let maxValue = parseFloat(maxPriceInput.value || priceRange.max);

            if (isNaN(minValue)) {
                minValue = 0;
                this.value = 0;
            }

            if (minValue > maxValue) {
                minValue = maxValue;
                this.value = maxValue;
            }
            if (minValue > parseFloat(priceRange.value)) {
                priceRange.value = minValue;
                maxPriceInput.value = minValue;
            }
        });
    }

}); // Fin de DOMContentLoaded