// Gestion de l'ajout au panier
document.querySelectorAll('.add-to-cart').forEach(button => {
    button.addEventListener('click', function() {
        const productId = this.getAttribute('data-id');
        
        fetch('add_to_cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&action=add&quantity=1`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour le badge du panier
                const cartBadge = document.querySelector('.navbar .bi-cart').nextElementSibling;
                if (cartBadge) {
                    cartBadge.textContent = data.cart_count;
                } else {
                    const cartLink = document.querySelector('.navbar .bi-cart').parentElement;
                    cartLink.innerHTML += `<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">${data.cart_count}</span>`;
                }
                
                // Afficher une notification
                const toast = document.createElement('div');
                toast.className = 'position-fixed bottom-0 end-0 p-3';
                toast.style.zIndex = '11';
                toast.innerHTML = `
                    <div class="toast show" role="alert" aria-live="assertive" aria-atomic="true">
                        <div class="toast-header bg-success text-white">
                            <strong class="me-auto">Panier</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
                        </div>
                        <div class="toast-body">
                            Produit ajouté au panier!
                        </div>
                    </div>
                `;
                document.body.appendChild(toast);
                
                // Supprimer la notification après 3 secondes
                setTimeout(() => {
                    toast.remove();
                }, 3000);
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
        });
    });
});

// Initialiser les tooltips Bootstrap
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});