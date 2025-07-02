<?php
// admin/send_notification.php

// Ce fichier est inclus dans le dashboard.php de l'admin.
// Par conséquent, session_start(), require_once '../config.php'; et checkAdminRole()
// sont déjà exécutés par dashboard.php.

// On s'assure que $pdo est disponible depuis config.php
if (!isset($pdo)) {
    die("Erreur: La connexion à la base de données n'est pas disponible.");
}

// Récupérer la liste de tous les employés pour le sélecteur
$employees = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role = 'employee' ORDER BY last_name, first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Erreur lors de la récupération des employés pour l'envoi de notification: " . $e->getMessage());
    setFlashMessage('danger', "Erreur lors du chargement de la liste des employés.");
}

// Gérer l'envoi du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_notification'])) {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || !verifyCsrfToken($_POST['csrf_token'])) {
        setFlashMessage('danger', "Erreur de sécurité CSRF.");
        // Rediriger pour effacer les données du formulaire et éviter la soumission multiple
        redirect(BASE_URL . 'admin/dashboard.php?page=send_notification');
        exit();
    }

    $selectedEmployeeId = sanitize($_POST['employee_id'] ?? '');
    $notificationMessage = sanitize($_POST['notification_message'] ?? '');

    // Validation
    if (empty($selectedEmployeeId) || !is_numeric($selectedEmployeeId) || $selectedEmployeeId <= 0) {
        setFlashMessage('danger', "Veuillez sélectionner un employé valide.");
    } elseif (empty($notificationMessage)) {
        setFlashMessage('danger', "Le message de notification ne peut pas être vide.");
    } else {
        // Vérifier si l'employé existe et est bien un employé
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'employee'");
            $stmt->execute([':id' => $selectedEmployeeId]);
            $employeeExists = $stmt->fetchColumn();

            if (!$employeeExists) {
                setFlashMessage('danger', "L'employé sélectionné n'existe pas ou n'est pas un employé.");
            } else {
                // Insérer la notification dans la base de données
                $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, created_at, is_read) VALUES (:user_id, :message, NOW(), FALSE)");
                if ($stmt->execute([':user_id' => $selectedEmployeeId, ':message' => $notificationMessage])) {
                    setFlashMessage('success', "Notification envoyée avec succès à l'employé.");
                    // Réinitialiser le message du formulaire après l'envoi réussi
                    $_POST['notification_message'] = '';
                } else {
                    setFlashMessage('danger', "Erreur lors de l'envoi de la notification.");
                }
            }
        } catch (PDOException $e) {
            error_log("Erreur PDO lors de l'envoi de notification: " . $e->getMessage());
            setFlashMessage('danger', "Une erreur est survenue lors de l'envoi de la notification.");
        }
    }
    // Après le traitement du formulaire, rediriger pour éviter le problème de re-soumission
    // et afficher le message flash.
    redirect(BASE_URL . 'admin/dashboard.php?page=send_notification');
    exit();
}
?>

<div class="container-fluid">
    <h1 class="h3 mb-4 text-gray-800">Envoyer une notification aux employés</h1>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Formulaire d'envoi de notification</h6>
        </div>
        <div class="card-body">
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="mb-3">
                    <label for="employee_id" class="form-label">Sélectionner un employé :</label>
                    <select class="form-select" id="employee_id" name="employee_id" required>
                        <option value="">-- Choisir un employé --</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= htmlspecialchars($employee['id']) ?>">
                                <?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . ' (' . $employee['email'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="notification_message" class="form-label">Message de notification :</label>
                    <textarea class="form-control" id="notification_message" name="notification_message" rows="5" required placeholder="Saisissez le message pour l'employé..."><?= htmlspecialchars($_POST['notification_message'] ?? '') ?></textarea>
                </div>

                <button type="submit" name="send_notification" class="btn btn-primary">Envoyer la notification</button>
            </form>
        </div>
    </div>
</div>