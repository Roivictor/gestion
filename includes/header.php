<?php

// Empêcher l'accès direct
if (!defined('SITE_NAME')) {
    die('Accès direct interdit');
}

// Assurez-vous que la session est démarrée au tout début du script ou dans un fichier inclus avant header.php
// Par exemple, dans config.php ou en haut de la page qui inclut ce header.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Fonction isLoggedIn() doit être définie dans config.php ou un fichier inclus avant ce header.
// Exemple simple (si elle n'existe pas encore ou doit être adaptée) :

if (!function_exists('isLoggedIn')) {
    function isLoggedIn() {
        return isset($_SESSION['user']['id']);
    }
}

?>

<header class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand" href="index.php"><?= SITE_NAME ?></a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Accueil</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="products.php">Produits</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="about.php">À propos</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="contact.php">Contact</a>
                </li>
            </ul>

            <meta name="csrf-token" content="<?= generateCsrfToken() ?>">
            <div id="toast-container" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100"></div>
            
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link position-relative" href="cart.php">
                        <i class="bi bi-cart"></i>
                        <?php if (!empty($_SESSION['cart'])): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                <?= count($_SESSION['cart']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </li>
                
                <?php if (isLoggedIn()): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> 
                            <?php
                            // C'est la ligne qui doit être corrigée
                            if (isset($_SESSION['user']['username'])) {
                                echo htmlspecialchars($_SESSION['user']['username']);
                            } else {
                                // Fallback si pour une raison quelconque le username n'est pas là (ne devrait pas arriver si isLoggedIn est true)
                                echo "Utilisateur"; 
                            }
                            ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="account.php">Mon compte</a></li>
                            <li><a class="dropdown-item" href="account.php">Mes commandes</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php">Déconnexion</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">Connexion</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">Inscription</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</header>