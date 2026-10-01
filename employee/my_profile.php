<?php
// employee/my_profile.php - Corrected and with additional debug logs

// Ensure $pdo is available (via config.php) and the user is logged in.
// Role verification is already done in dashboard.php.
if (!isset($pdo) || !isset($_SESSION['user_id']) || !isset($_SESSION['user']['role'])) {
    error_log("DEBUG: my_profile.php - Access denied or PDO not available. User ID: " . ($_SESSION['user_id'] ?? 'N/A') . ", Role: " . ($_SESSION['user']['role'] ?? 'N/A'));
    echo '<div class="alert alert-danger" role="alert">Access or configuration error.</div>';
    return;
}

$currentUser = $_SESSION['user']; // User info is already loaded into session in dashboard.php
$employee_id = $currentUser['id']; // The ID of the logged-in employee

// Process profile update form (if submitted)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    error_log("DEBUG: my_profile.php - Profile update form submitted (POST).");
    error_log("DEBUG: my_profile.php - Content of \$_POST: " . print_r($_POST, true));

    // CSRF token verification (VERY IMPORTANT FOR SECURITY)
    // Check if the token is present AND if the verifyCsrfToken function exists AND if the token is valid
    if (!isset($_POST['csrf_token']) || !function_exists('verifyCsrfToken') || !verifyCsrfToken($_POST['csrf_token'])) {
        error_log("DEBUG: my_profile.php - CSRF Error: Token missing or invalid. Submitted: " . ($_POST['csrf_token'] ?? 'N/A'));
        setFlashMessage('danger', 'Invalid CSRF token.');
        redirect(BASE_URL . 'employee/dashboard.php?page=my_profile');
        exit(); // Stop execution after redirection
    } else {
        error_log("DEBUG: my_profile.php - CSRF Token VERIFIED successfully.");

        // Sanitize submitted data
        $first_name = function_exists('sanitize') ? sanitize($_POST['first_name']) : trim($_POST['first_name']);
        $last_name = function_exists('sanitize') ? sanitize($_POST['last_name']) : trim($_POST['last_name']);
        $email = function_exists('sanitize') ? sanitize($_POST['email']) : trim($_POST['email']);
        $phone = function_exists('sanitize') ? sanitize($_POST['phone']) : trim($_POST['phone']);
        $address = function_exists('sanitize') ? sanitize($_POST['address']) : trim($_POST['address']);

        // Simple validation (add more robust validations if necessary)
        if (empty($first_name) || empty($last_name) || empty($email)) {
            setFlashMessage('danger', 'All fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            setFlashMessage('danger', 'Invalid email format.');
        } else {
            try {
                // Update employee information in the database
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET
                        first_name = :first_name,
                        last_name = :last_name,
                        email = :email,
                        phone = :phone,
                        address = :address
                    WHERE id = :id AND role = 'employee'
                ");
                $stmt->execute([
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':id' => $employee_id
                ]);

                if ($stmt->rowCount() > 0) {
                    setFlashMessage('success', 'Profile updated successfully.');
                    // Update session information to be current immediately
                    $_SESSION['user']['first_name'] = $first_name;
                    $_SESSION['user']['last_name'] = $last_name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['address'] = $address;
                    $currentUser = $_SESSION['user']; // Update local variable
                    error_log("DEBUG: my_profile.php - Profile updated successfully. Session updated.");
                } else {
                    setFlashMessage('info', 'No changes were made or an error occurred.');
                    error_log("DEBUG: my_profile.php - No changes detected or silent error.");
                }

            } catch (PDOException $e) {
                setFlashMessage('danger', 'Error updating profile: ' . $e->getMessage());
                error_log("ERROR: PDO Error updating employee profile: " . $e->getMessage());
            }
        }
        // Redirect after POST processing to prevent form resubmission
        redirect(BASE_URL . 'employee/dashboard.php?page=my_profile');
        exit(); // Stop execution after redirection
    }
}

// Generate a new CSRF token for the form (for GET requests)
// This token will be displayed in the hidden field of the form
$csrf_token = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
error_log("DEBUG: my_profile.php - CSRF Token generated for the form (GET): " . $csrf_token);

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">My Profile</h1>
</div>

<?php
// Flash messages will be displayed by dashboard.php, so no need here
// if ($success_message):
//     echo '<div class="alert alert-success alert-dismissible fade show" role="alert">' . htmlspecialchars($success_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
// endif;
// if ($error_message):
//     echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">' . htmlspecialchars($error_message) . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
// endif;
?>

<div class="card shadow-sm">
    <div class="card-header bg-primary text-white">
        Profile Information
    </div>
    <div class="card-body">
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

            <div class="mb-3">
                <label for="username" class="form-label">Username :</label>
                <input type="text" class="form-control" id="username" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" disabled>
                <div class="form-text">Your username cannot be changed.</div>
            </div>

            <div class="mb-3">
                <label for="role" class="form-label">Role :</label>
                <input type="text" class="form-control" id="role" value="<?= htmlspecialchars(ucfirst($currentUser['role'] ?? '')) ?>" disabled>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="first_name" class="form-label">First Name :</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" value="<?= htmlspecialchars($currentUser['first_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label for="last_name" class="form-label">Last Name :</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" value="<?= htmlspecialchars($currentUser['last_name'] ?? '') ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="email" class="form-label">Email :</label>
                <input type="email" class="form-control" id="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
            </div>

            <div class="mb-3">
                <label for="phone" class="form-label">Phone :</label>
                <input type="text" class="form-control" id="phone" name="phone" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label for="address" class="form-label">Address :</label>
                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($currentUser['address'] ?? '') ?></textarea>
            </div>

            <button type="submit" name="update_profile" class="btn btn-primary">
                <i class="bi bi-save"></i> Save Changes
            </button>
            <a href="<?= BASE_URL ?>employee/dashboard.php?page=change_password" class="btn btn-outline-secondary ms-2">
                <i class="bi bi-key"></i> Change Password
            </a>
        </form>
    </div>
</div>