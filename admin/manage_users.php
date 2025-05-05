<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is an admin
checkPermission(['admin']);

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter values
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? (int)sanitize($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Base query
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Add filters to query
if (!empty($role_filter)) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if ($status_filter !== '') {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Count total records for pagination
$count_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE 1=1" . 
    (empty($role_filter) ? "" : " AND role = ?") . 
    ($status_filter === '' ? "" : " AND status = ?") . 
    (empty($search) ? "" : " AND (username LIKE ? OR email LIKE ? OR full_name LIKE ?)"));

$count_params = [];
if (!empty($role_filter)) $count_params[] = $role_filter;
if ($status_filter !== '') $count_params[] = $status_filter;
if (!empty($search)) {
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
    $count_params[] = "%$search%";
}

$count_stmt->execute($count_params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to query
$query .= " ORDER BY creation_date DESC LIMIT $offset, $records_per_page";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        setMessage("Erreur de sécurité. Veuillez réessayer.", 'danger');
        redirect('/admin/manage_users.php');
    }
    
    // Get action and user ID
    $action = sanitize($_POST['action']);
    $user_id = (int)$_POST['user_id'];
    
    // Check if user exists and is not current user
    if ($user_id === $_SESSION['user_id']) {
        setMessage("Vous ne pouvez pas modifier votre propre compte depuis cette interface.", 'warning');
        redirect('/admin/manage_users.php');
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        setMessage("Utilisateur non trouvé.", 'danger');
        redirect('/admin/manage_users.php');
    }
    
    // Process action
    try {
        switch ($action) {
            case 'activate':
                $stmt = $pdo->prepare("UPDATE users SET status = 1 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                setMessage("Compte activé avec succès.", 'success');
                break;
                
            case 'deactivate':
                $stmt = $pdo->prepare("UPDATE users SET status = 0 WHERE user_id = ?");
                $stmt->execute([$user_id]);
                setMessage("Compte désactivé avec succès.", 'success');
                break;
                
            case 'delete':
                // Check if user has projects
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM projects WHERE student_id = ?");
                $stmt->execute([$user_id]);
                $project_count = $stmt->fetch()['count'];
                
                if ($project_count > 0) {
                    setMessage("Impossible de supprimer cet utilisateur car il a des projets associés.", 'warning');
                } else {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    setMessage("Utilisateur supprimé avec succès.", 'success');
                }
                break;
                
            default:
                setMessage("Action non reconnue.", 'danger');
        }
    } catch (PDOException $e) {
        setMessage("Erreur: " . $e->getMessage(), 'danger');
    }
    
    redirect('/admin/manage_users.php');
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Gestion des utilisateurs</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Filtrer les utilisateurs</h3>
        </div>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="filter-form">
            <div class="form-row">
                <div class="form-col">
                    <label for="role">Rôle</label>
                    <select id="role" name="role">
                        <option value="">Tous les rôles</option>
                        <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Étudiants</option>
                        <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Enseignants</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Administrateurs</option>
                    </select>
                </div>
                
                <div class="form-col">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Actif</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactif</option>
                    </select>
                </div>
                
                <div class="form-col">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Nom, email ou username...">
                </div>
                
                <div class="form-col" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php if (empty($users)): ?>
        <div class="alert alert-info">
            Aucun utilisateur ne correspond à vos critères de recherche.
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom complet</th>
                        <th>Nom d'utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Détails</th>
                        <th>Date de création</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['email']); ?></td>
                            <td>
                                <?php 
                                    switch($user['role']) {
                                        case 'student':
                                            echo '<span class="role-badge student">Étudiant</span>';
                                            break;
                                        case 'teacher':
                                            echo '<span class="role-badge teacher">Enseignant</span>';
                                            break;
                                        case 'admin':
                                            echo '<span class="role-badge admin">Admin</span>';
                                            break;
                                    }
                                ?>
                            </td>
                            <td>
                                <?php if ($user['role'] === 'student'): ?>
                                    Année: <?php echo $user['year_of_study'] ?: 'Non spécifiée'; ?><br>
                                    Dép.: <?php echo htmlspecialchars($user['department'] ?: 'Non spécifié'); ?>
                                <?php elseif ($user['role'] === 'teacher'): ?>
                                    Dép.: <?php echo htmlspecialchars($user['department'] ?: 'Non spécifié'); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td><?php echo formatDate($user['creation_date']); ?></td>
                            <td>
                                <?php if ($user['status']): ?>
                                    <span class="status-badge status-active">Actif</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactive">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                        <?php if ($user['status']): ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="deactivate">
                                                <button type="submit" class="btn btn-warning btn-sm" onclick="return confirm('Êtes-vous sûr de vouloir désactiver cet utilisateur?')">
                                                    <i class="fas fa-user-slash"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                                <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                <input type="hidden" name="action" value="activate">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-user-check"></i>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button type="submit" class="btn btn-danger btn-sm btn-delete" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur? Cette action est irréversible.')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">Vous-même</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page - 1) . '&role=' . $role_filter . '&status=' . $status_filter . '&search=' . $search); ?>">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . $i . '&role=' . $role_filter . '&status=' . $status_filter . '&search=' . $search); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page + 1) . '&role=' . $role_filter . '&status=' . $status_filter . '&search=' . $search); ?>">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.status-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.status-active {
    background-color: #d4edda;
    color: #155724;
}

.status-inactive {
    background-color: #f8d7da;
    color: #721c24;
}

.action-buttons {
    display: flex;
    gap: 5px;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
