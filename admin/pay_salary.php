// admin/process_salary_payment.php (exemple)

// ... votre logique de traitement de paiement de salaire ...

if ($paymentSuccessful) {
    $employeeId = $paidEmployeeId; // L'ID de l'employé dont le salaire est payé
    $salaryAmount = $amountPaid; // Le montant du salaire payé

    // Insérer une notification pour l'employé
    $message = "Votre salaire de " . number_format($salaryAmount, 2, ',', ' ') . " CFA a été payé.";
    $link = BASE_URL . 'employee/salaries.php'; // Lien vers la page des salaires de l'employé

    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, link) VALUES (:user_id, :message, :link)");
        $stmt->execute([
            ':user_id' => $employeeId,
            ':message' => $message,
            ':link' => $link
        ]);
        // Notification insérée avec succès
    } catch (PDOException $e) {
        error_log("Erreur lors de l'insertion de la notification de salaire: " . $e->getMessage());
    }

    // ... Redirection ou message de succès pour l'admin ...
} else {
    // ... Gestion de l'échec du paiement ...
}