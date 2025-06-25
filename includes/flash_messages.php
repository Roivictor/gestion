<?php
// includes/flash_messages.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' .
         htmlspecialchars($_SESSION['success_message']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' .
         htmlspecialchars($_SESSION['error_message']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['warning_message'])) {
    echo '<div class="alert alert-warning alert-dismissible fade show" role="alert">' .
         htmlspecialchars($_SESSION['warning_message']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['warning_message']);
}

if (isset($_SESSION['info_message'])) {
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">' .
         htmlspecialchars($_SESSION['info_message']) .
         '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    unset($_SESSION['info_message']);
}
?>