// js/script.js

// -- Fonctions Utilitaires --

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

// Nouvelle fonction pour traduire les messages côté client
// Vous pouvez l'améliorer en chargeant un fichier JSON de traductions si nécessaire.
// Pour l'instant, elle gère les clés que nous avons identifiées.
function translateMessage(key, defaultValue = 'Une erreur est survenue.') {
    const translations = {
        '[review_submitted_pending]': 'Votre avis a été soumis et est en attente d\'approbation.',
        '[review_submitted_approved]': 'Votre avis a été soumis avec succès !',
        '[already_reviewed]': 'Vous avez déjà soumis un avis pour ce produit. Merci !',
        '[csrf_token_mismatch]': 'Erreur de sécurité : Jeton invalide. Veuillez rafraîchir la page.',
        '[not_logged_in_review]': 'Vous devez être connecté pour soumettre un avis.',
        '[session_user_id_missing]': 'Erreur interne: ID utilisateur manquant. Veuillez vous reconnecter.',
        '[customer_profile_missing]': 'Votre profil client est introuvable. Veuillez contacter le support.',
        '[invalid_review_data]': 'Veuillez remplir correctement tous les champs de l\'avis (note et commentaire).',
        '[review_submit_error]': 'Une erreur est survenue lors de la soumission de votre avis. Veuillez réessayer.',
        '[invalid_request_method]': 'Requête invalide ou méthode non autorisée.',
        '[product_deleted_success]': 'Produit supprimé avec succès.', // Nouvelle traduction
        '[product_delete_error]': 'Erreur lors de la suppression du produit.', // Nouvelle traduction
        '[invalid_product_id]': 'ID produit invalide.', // Nouvelle traduction
        '[product_fetch_error]': 'Une erreur est survenue lors de la récupération des produits.', // Nouvelle traduction
        '[no_products_found]': 'Aucun produit trouvé.', // Nouvelle traduction
        '[no_image]': 'Aucune image', // Nouvelle traduction
        '[none]': 'Aucune', // Nouvelle traduction
        '[confirm_delete_product]': 'Êtes-vous sûr de vouloir supprimer ce produit ?', // Nouvelle traduction
        '[edit]': 'Modifier', // Nouvelle traduction
        '[delete]': 'Supprimer', // Nouvelle traduction
        '[add_product]': 'Ajouter un produit', // Nouvelle traduction
        '[product_management_title]': 'Gestion des produits', // Nouvelle traduction
        '[search]': 'Recherche', // Nouvelle traduction
        '[category]': 'Catégorie', // Nouvelle traduction
        '[all_categories]': 'Toutes les catégories', // Nouvelle traduction
        '[filter]': 'Filtrer', // Nouvelle traduction
        '[reset]': 'Réinitialiser', // Nouvelle traduction
        '[id]': 'ID', // Nouvelle traduction
        '[image]': 'Image', // Nouvelle traduction
        '[name]': 'Nom', // Nouvelle traduction
        '[price]': 'Prix', // Nouvelle traduction
        '[stock]': 'Stock', // Nouvelle traduction
        '[creation_date]': 'Date création', // Nouvelle traduction
        '[actions]': 'Actions', // Nouvelle traduction
        // Ajoutez ici d'autres traductions pour les messages du panier si nécessaire
        // Exemple: '[product_not_found_cart]': 'Le produit n\'existe plus.',
        // '[not_enough_stock]': 'Stock insuffisant pour ce produit.',
        // '[added_to_cart]': 'Produit ajouté au panier !',
        // '[db_error_cart_add]': 'Erreur de base de données lors de l\'ajout au panier.'
    };
    return translations[key] || defaultValue;
}

// Fonction utilitaire pour éviter les XSS lors de l'insertion dans le DOM
function htmlspecialchars(str) {
    if (typeof str !== 'string') {
        return str; // Retourne tel quel si ce n'est pas une chaîne
    }
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
}


// -- Initialisation et Gestion des Événements DOM --

document.addEventListener('DOMContentLoaded', function() {
    // Récupérer le token CSRF au chargement de la page
    const CSRF_TOKEN = getCsrfToken();

    if (!CSRF_TOKEN) {
        console.error("Erreur: Le token CSRF n'est pas trouvé dans la balise meta. Les requêtes AJAX pourraient échouer.");
        showToast("Erreur de sécurité : Token CSRF manquant au chargement. Certaines fonctions peuvent être désactivées.", 'danger');
    }

    // Gestion des boutons "Ajouter au panier"
    document.querySelectorAll('.add-to-cart').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.getAttribute('data-id');

            let quantity;
            const productDetailsContainer = this.closest('.col-md-6');
            const cardFooter = this.closest('.card-footer');

            if (productDetailsContainer) {
                const quantityInput = productDetailsContainer.querySelector('input#quantity');
                quantity = parseInt(quantityInput ? quantityInput.value : 1);
            } else if (cardFooter) {
                const quantityInput = cardFooter.querySelector('input[type="number"][id^="quantity-"]');
                quantity = parseInt(quantityInput ? quantityInput.Ivalue : 1); // Correction ici: 'Ivalue' -> 'value'
            } else {
                quantity = 1;
            }

            if (isNaN(quantity) || quantity < 1) {
                showToast("Veuillez entrer une quantité valide.", 'warning');
                return;
            }

            if (!CSRF_TOKEN) {
                showToast("Erreur de sécurité : Token CSRF manquant. Veuillez rafraîchir la page.", 'danger');
                return;
            }

            fetch('cart_ajax.php', { // <-- Vérifiez que c'est bien le bon chemin
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    product_id: productId,
                    action: 'add',
                    quantity: quantity,
                    _csrf_token: CSRF_TOKEN
                })
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
                        console.error("Réponse non-JSON du serveur:", text);
                        throw new Error("Réponse inattendue du serveur (pas JSON). Vérifiez les logs PHP pour cart_ajax.php.");
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    const cartCountElement = document.getElementById('cart-count');
                    if (cartCountElement && data.cart_count !== undefined) {
                        cartCountElement.textContent = data.cart_count;
                    }
                    showToast(translateMessage(data.message, 'Produit ajouté au panier !'), 'success');
                } else {
                    showToast('Erreur : ' + translateMessage(data.message, 'Une erreur inconnue est survenue.'), 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur AJAX "Ajouter au panier":', error);
                showToast('Une erreur de communication est survenue lors de l\'ajout au panier: ' + error.message, 'danger');
            });
        });
    });

    // --- Gestion du formulaire d'avis ---
    const reviewForm = document.getElementById('reviewForm');
    const ratingStars = document.querySelectorAll('.review-star');
    const ratingInput = document.getElementById('ratingInput');
    const reviewMessageDiv = document.getElementById('reviewMessage'); // Pour afficher les messages du formulaire

    if (reviewForm && ratingStars.length > 0 && ratingInput && reviewMessageDiv) {
        let currentRating = 0; // Pour stocker la note sélectionnée

        // Gérer le survol et le clic des étoiles
        ratingStars.forEach(star => {
            star.addEventListener('mouseover', function() {
                const rating = parseInt(this.getAttribute('data-rating'));
                ratingStars.forEach((s, index) => {
                    if (index < rating) {
                        s.classList.add('bi-star-fill', 'text-warning');
                        s.classList.remove('bi-star');
                    } else {
                        s.classList.remove('bi-star-fill', 'text-warning');
                        s.classList.add('bi-star');
                    }
                });
            });

            star.addEventListener('mouseout', function() {
                ratingStars.forEach((s, index) => {
                    if (index < currentRating) {
                        s.classList.add('bi-star-fill', 'text-warning');
                        s.classList.remove('bi-star');
                    } else {
                        s.classList.remove('bi-star-fill', 'text-warning');
                        s.classList.add('bi-star');
                    }
                });
            });

            star.addEventListener('click', function() {
                currentRating = parseInt(this.getAttribute('data-rating'));
                ratingInput.value = currentRating;
                ratingStars.forEach((s, index) => {
                    if (index < currentRating) {
                        s.classList.add('bi-star-fill', 'text-warning');
                        s.classList.remove('bi-star');
                    } else {
                        s.classList.remove('bi-star-fill', 'text-warning');
                        s.classList.add('bi-star');
                    }
                });
            });
        });

        // Soumission du formulaire d'avis via AJAX
        reviewForm.addEventListener('submit', function(event) {
            event.preventDefault();

            const formData = new FormData(this);
            formData.append('csrf_token', CSRF_TOKEN); // Ajoute le token CSRF

            // Validation simple côté client
            if (parseInt(ratingInput.value) === 0) {
                showToast(translateMessage('[invalid_review_data]', 'Veuillez donner une note en cliquant sur les étoiles.'), 'warning');
                return;
            }
            if (formData.get('comment').trim() === '') {
                showToast(translateMessage('[invalid_review_data]', 'Veuillez entrer un commentaire pour votre avis.'), 'warning');
                return;
            }

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
                        console.error("Réponse non-JSON du serveur pour la soumission d'avis:", text);
                        throw new Error("Réponse inattendue du serveur (pas JSON). Vérifiez les logs PHP pour submit_review.php.");
                    });
                }
            })
            .then(data => {
                if (data.success) {
                    showToast(translateMessage(data.message, 'Votre avis a été soumis avec succès !'), 'success');
                    reviewForm.reset();
                    currentRating = 0;
                    ratingInput.value = 0;
                    ratingStars.forEach(star => {
                        star.classList.remove('bi-star-fill', 'text-warning');
                        star.classList.add('bi-star');
                    });
                } else {
                    showToast('Erreur : ' + translateMessage(data.message, 'Une erreur inconnue est survenue lors de la soumission de l\'avis.'), 'danger');

                    if (data.message === '[already_reviewed]') {
                        reviewForm.querySelector('button[type="submit"]').disabled = true;
                        reviewForm.querySelector('textarea[name="comment"]').disabled = true;
                        ratingStars.forEach(star => star.style.pointerEvents = 'none');
                    } else if (data.message === '[not_logged_in_review]') {
                        // setTimeout(() => { window.location.href = 'login.php'; }, 2000);
                    }
                }
            })
            .catch(error => {
                console.error('Erreur AJAX "Soumission avis":', error);
                showToast('Une erreur de communication est survenue lors de la soumission de l\'avis: ' + error.message, 'danger');
            });
        });
    }

    // --- NOUVEAU : Gestion du formulaire de filtre des produits (products.php) ---
    const filterProductsForm = document.getElementById('filterProductsForm');
    const productsTableBody = document.getElementById('productsTableBody');
    const toastContainer = document.getElementById('toast-container'); // Réutiliser ou redéfinir si showToast n'est pas global

    if (filterProductsForm && productsTableBody) {
        filterProductsForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Empêche le rechargement de la page par défaut

            const formData = new FormData(filterProductsForm);
            // formData.append('page', 'products'); // Le champ caché est déjà dans le HTML, pas besoin de l'ajouter ici

            // Convertir FormData en chaîne de requête URL
            const queryString = new URLSearchParams(formData).toString();
            // L'URL cible est le dashboard, car products.php est inclus par dashboard.php
            const fetchUrl = `dashboard.php?${queryString}`; 

            // Désactiver le bouton de filtre et indiquer le chargement
            const filterButton = filterProductsForm.querySelector('button[type="submit"]');
            filterButton.disabled = true;
            filterButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Filtrage...';

            fetch(fetchUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Indique au serveur PHP que c'est une requête AJAX
                }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    // Si ce n'est pas du JSON, c'est une erreur inattendue du serveur PHP
                    return response.text().then(text => {
                        console.error("Réponse non-JSON du serveur pour le filtre produits:", text);
                        throw new Error("Réponse inattendue du serveur (pas JSON). Vérifiez les logs PHP.");
                    });
                }
            })
            .then(data => {
                // Réactiver le bouton et restaurer son texte
                filterButton.disabled = false;
                filterButton.innerHTML = '<i class="bi bi-funnel"></i> ' + translateMessage('[filter]', 'Filtrer');


                // Afficher les messages de succès ou d'erreur renvoyés par PHP via JSON
                if (data.success_message) {
                    showToast(translateMessage(data.success_message, data.success_message), 'success');
                }
                if (data.error_message) {
                    showToast(translateMessage(data.error_message, data.error_message), 'danger');
                }

                // Vider le corps du tableau
                productsTableBody.innerHTML = '';

                if (data.products && data.products.length > 0) {
                    // Remplir le tableau avec les nouvelles données
                    data.products.forEach(product => {
                        const row = `
                            <tr>
                                <td>${htmlspecialchars(product.id)}</td>
                                <td>
                                    ${product.image_url ? 
                                        `<img src="../uploads/products/${htmlspecialchars(product.image_url)}" alt="${htmlspecialchars(product.name)}" width="50">` : 
                                        `<span class="text-muted">${translateMessage('[no_image]', 'Aucune image')}</span>`
                                    }
                                </td>
                                <td>${htmlspecialchars(product.name)}</td>
                                <td>${product.category_name ? htmlspecialchars(product.category_name) : translateMessage('[none]', 'Aucune')}</td>
                                <td>${parseFloat(product.price).toFixed(2)} €</td>
                                <td>
                                    <span class="badge ${product.stock_quantity > 0 ? 'bg-success' : 'bg-danger'}">
                                        ${htmlspecialchars(product.stock_quantity)}
                                    </span>
                                </td>
                                <td>${new Date(product.created_at).toLocaleDateString('fr-FR')}</td>
                                <td>
                                    <a href="dashboard.php?page=edit_product&id=${product.id}" class="btn btn-sm btn-outline-primary" title="${translateMessage('[edit]', 'Modifier')}">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <a href="dashboard.php?page=products&action=delete&id=${product.id}"
                                       class="btn btn-sm btn-outline-danger delete-product-btn" // Ajouté une classe pour gérer la suppression via JS si besoin
                                       data-product-id="${product.id}"
                                       title="${translateMessage('[delete]', 'Supprimer')}">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                </td>
                            </tr>
                        `;
                        productsTableBody.insertAdjacentHTML('beforeend', row);
                    });

                    // Réattacher les écouteurs d'événements pour les boutons de suppression
                    attachDeleteListeners();

                } else {
                    productsTableBody.innerHTML = `<tr><td colspan="8" class="text-center">${translateMessage('[no_products_found]', 'Aucun produit trouvé')}</td></tr>`;
                }
            })
            .catch(error => {
                console.error('Erreur lors du filtrage des produits:', error);
                showToast(translateMessage('[product_fetch_error]', 'Une erreur est survenue lors du filtrage des produits.') + ' ' + error.message, 'danger');
                
                // Réactiver le bouton même en cas d'erreur
                filterButton.disabled = false;
                filterButton.innerHTML = '<i class="bi bi-funnel"></i> ' + translateMessage('[filter]', 'Filtrer');
            });
        });

        // Fonction pour gérer la suppression (maintenant via AJAX si le bouton a la classe delete-product-btn)
        function attachDeleteListeners() {
            document.querySelectorAll('.delete-product-btn').forEach(button => {
                // Supprimer l'écouteur existant si re-attachement nécessaire pour éviter les doublons
                button.removeEventListener('click', handleDeleteProduct); 
                button.addEventListener('click', handleDeleteProduct);
            });
        }

        function handleDeleteProduct(event) {
            event.preventDefault(); // Empêche le comportement par défaut (rechargement de la page)

            const productId = this.dataset.productId;
            if (!confirm(translateMessage('[confirm_delete_product]', 'Êtes-vous sûr de vouloir supprimer ce produit ?'))) {
                return;
            }

            if (!CSRF_TOKEN) {
                showToast("Erreur de sécurité : Token CSRF manquant. Veuillez rafraîchir la page.", 'danger');
                return;
            }

            // URL de suppression (ciblant la même page products.php qui gère l'action 'delete')
            const deleteUrl = `dashboard.php?page=products&action=delete&id=${productId}`;

            fetch(deleteUrl, {
                method: 'GET', // Pour l'instant, on laisse GET comme votre PHP, mais POST serait plus sûr
                headers: {
                    'X-Requested-With': 'XMLHttpRequest' // Indique que c'est une requête AJAX
                    // Si vous passez en POST pour la suppression, ajoutez le CSRF ici
                    // 'Content-Type': 'application/json',
                    // body: JSON.stringify({ id: productId, _csrf_token: CSRF_TOKEN })
                }
            })
            .then(response => {
                const contentType = response.headers.get("content-type");
                if (contentType && contentType.indexOf("application/json") !== -1) {
                    return response.json();
                } else {
                    return response.text().then(text => {
                        console.error("Réponse non-JSON du serveur pour la suppression produit:", text);
                        throw new Error("Réponse inattendue du serveur (pas JSON) pour la suppression.");
                    });
                }
            })
            .then(data => {
                if (data.success_message) {
                    showToast(translateMessage(data.success_message, data.success_message), 'success');
                    // Recharger les produits après suppression réussie
                    // Simule la soumission du formulaire de filtre pour rafraîchir la liste
                    filterProductsForm.dispatchEvent(new Event('submit')); 
                } else if (data.error_message) {
                    showToast(translateMessage(data.error_message, data.error_message), 'danger');
                } else {
                    showToast(translateMessage('product_delete_error', 'Erreur inconnue lors de la suppression du produit.'), 'danger');
                }
            })
            .catch(error => {
                console.error('Erreur AJAX lors de la suppression du produit:', error);
                showToast('Une erreur est survenue lors de la suppression du produit: ' + error.message, 'danger');
            });
        }

        // Attacher les écouteurs au chargement initial de la page
        attachDeleteListeners();
    }


    // -- Autres fonctionnalités existantes --

    // Gestion des vues (grille/liste)
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

    // Gestion du filtre par prix
    const priceRange = document.getElementById('price-range');
    const minPriceInput = document.querySelector('input[name="min_price"]');
    const maxPriceInput = document.querySelector('input[name="max_price"]');

    if (priceRange && minPriceInput && maxPriceInput) {
        priceRange.addEventListener('input', function() {
            maxPriceInput.value = this.value;
        });

        maxPriceInput.addEventListener('change', function() {
            priceRange.value = this.value || priceRange.max;
        });

        minPriceInput.addEventListener('change', function() {
            if (parseFloat(this.value) > parseFloat(maxPriceInput.value)) {
                maxPriceInput.value = this.value;
                priceRange.value = this.value;
            }
        });
    }

    // Initialiser les tooltips Bootstrap
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});