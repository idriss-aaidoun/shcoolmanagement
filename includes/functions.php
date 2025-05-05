<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['role'] === $role;
}

/**
 * Redirect to a specific page
 * @param string $location
 */
function redirect($location) {
    header("Location: $location");
    exit;
}

/**
 * Check if user has permission to access page
 * @param array $allowed_roles
 */
function checkPermission($allowed_roles = []) {
    if (!isLoggedIn()) {
        $_SESSION['message'] = "Vous devez vous connecter pour accéder à cette page.";
        redirect('/login.php');
    }

    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        $_SESSION['message'] = "Vous n'avez pas l'autorisation d'accéder à cette page.";
        redirect('/' . $_SESSION['role'] . '/dashboard.php');
    }
}

/**
 * Display flash messages
 */
function displayMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        $message_type = isset($_SESSION['message_type']) ? $_SESSION['message_type'] : 'info';
        echo '<div class="alert alert-' . $message_type . '">' . $message . '</div>';
        unset($_SESSION['message']);
        unset($_SESSION['message_type']);
    }
}

/**
 * Set flash message
 * @param string $message
 * @param string $type
 */
function setMessage($message, $type = 'info') {
    $_SESSION['message'] = $message;
    $_SESSION['message_type'] = $type;
}

/**
 * Sanitize input
 * @param string $data
 * @return string
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a random token
 * @return string
 */
function generateToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Check CSRF token
 * @param string $token
 * @return bool
 */
function checkToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

/**
 * Get user information by ID
 * @param PDO $pdo
 * @param int $user_id
 * @return array|false
 */
function getUserById($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Get project by ID
 * @param PDO $pdo
 * @param int $project_id
 * @return array|false
 */
function getProjectById($pdo, $project_id) {
    $stmt = $pdo->prepare("
        SELECT p.*, u.full_name as student_name, u2.full_name as supervisor_name, 
               pt.name as project_type, pc.name as category_name, m.name as module_name
        FROM projects p
        LEFT JOIN users u ON p.student_id = u.user_id
        LEFT JOIN users u2 ON p.supervisor_id = u2.user_id
        LEFT JOIN project_types pt ON p.type_id = pt.type_id
        LEFT JOIN project_categories pc ON p.category_id = pc.category_id
        LEFT JOIN modules m ON p.module_id = m.module_id
        WHERE project_id = ?
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetch();
}

/**
 * Check if project belongs to user
 * @param PDO $pdo
 * @param int $project_id
 * @param int $user_id
 * @return bool
 */
function isProjectOwner($pdo, $project_id, $user_id) {
    $stmt = $pdo->prepare("SELECT student_id FROM projects WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if ($project && $project['student_id'] == $user_id) {
        return true;
    }
    
    return false;
}

/**
 * Get all deliverables for a project
 * @param PDO $pdo
 * @param int $project_id
 * @return array
 */
function getProjectDeliverables($pdo, $project_id) {
    $stmt = $pdo->prepare("SELECT * FROM deliverables WHERE project_id = ? ORDER BY upload_date DESC");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll();
}

/**
 * Get evaluation for a project
 * @param PDO $pdo
 * @param int $project_id
 * @return array|false
 */
function getProjectEvaluation($pdo, $project_id) {
    $stmt = $pdo->prepare("
        SELECT e.*, u.full_name as evaluator_name
        FROM evaluations e
        JOIN users u ON e.evaluator_id = u.user_id
        WHERE e.project_id = ?
        ORDER BY evaluation_date DESC
        LIMIT 1
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetch();
}

/**
 * Format date to French format
 * @param string $date
 * @return string
 */
function formatDate($date) {
    return date('d/m/Y à H:i', strtotime($date));
}

/**
 * Get options for select from table
 * @param PDO $pdo
 * @param string $table
 * @param string $id_field
 * @param string $name_field
 * @param int $selected_id
 * @return string
 */
function getSelectOptions($pdo, $table, $id_field, $name_field, $selected_id = null) {
    $stmt = $pdo->prepare("SELECT $id_field, $name_field FROM $table ORDER BY $name_field");
    $stmt->execute();
    $options = '';
    
    while ($row = $stmt->fetch()) {
        $selected = ($selected_id == $row[$id_field]) ? 'selected' : '';
        $options .= "<option value='{$row[$id_field]}' $selected>{$row[$name_field]}</option>";
    }
    
    return $options;
}

/**
 * Upload file securely
 * @param array $file
 * @param string $destination
 * @return array
 */
function uploadFile($file, $destination = 'uploads/') {
    // Check if file was uploaded without errors
    if ($file['error'] == 0) {
        $filename = basename($file['name']);
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        // Allowed file extensions
        $allowed_extensions = ['pdf', 'doc', 'docx', 'zip', 'rar', 'ppt', 'pptx', 'txt'];
        
        if (in_array($extension, $allowed_extensions)) {
            // Generate a unique filename
            $new_filename = uniqid() . '_' . $filename;
            $upload_path = $destination . $new_filename;
            
            // Try to move the uploaded file
            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                return [
                    'success' => true,
                    'filename' => $new_filename,
                    'path' => $upload_path,
                    'type' => $extension
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Erreur lors du téléchargement du fichier.'
                ];
            }
        } else {
            return [
                'success' => false,
                'error' => 'Type de fichier non autorisé. Les types autorisés sont: ' . implode(', ', $allowed_extensions)
            ];
        }
    } else {
        return [
            'success' => false,
            'error' => 'Erreur lors du téléchargement du fichier: ' . $file['error']
        ];
    }
}
?>
