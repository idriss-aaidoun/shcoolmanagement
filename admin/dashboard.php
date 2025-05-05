<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is an admin
checkPermission(['admin']);

// Get admin information
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Get project counts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_projects,
           COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
           COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
           COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
           COUNT(CASE WHEN status = 'pending_revision' THEN 1 END) as pending_count
    FROM projects
");
$stmt->execute();
$projects_stats = $stmt->fetch();

// Get user counts
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_users,
           COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
           COUNT(CASE WHEN role = 'teacher' THEN 1 END) as teacher_count,
           COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count
    FROM users
");
$stmt->execute();
$users_stats = $stmt->fetch();

// Get recent projects
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as student_name, 
           pt.name as project_type, pc.name as category_name
    FROM projects p
    JOIN users u ON p.student_id = u.user_id
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    ORDER BY p.submission_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects = $stmt->fetchAll();

// Get recent users
$stmt = $pdo->prepare("
    SELECT * FROM users
    ORDER BY creation_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Tableau de bord administrateur</h1>
        <div>
            <a href="/admin/statistics.php" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i> Statistiques détaillées
            </a>
            <a href="/admin/export.php" class="btn btn-secondary">
                <i class="fas fa-file-export"></i> Exporter les données
            </a>
        </div>
    </div>
    
    <div class="dashboard-section">
        <h2>Aperçu des projets</h2>
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <h3>Total des projets</h3>
                    <i class="fas fa-project-diagram"></i>
                </div>
                <div class="card-number"><?php echo $projects_stats['total_projects']; ?></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Projets soumis</h3>
                    <i class="fas fa-clock"></i>
                </div>
                <div class="card-number"><?php echo $projects_stats['submitted_count']; ?></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Projets approuvés</h3>
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="card-number"><?php echo $projects_stats['approved_count']; ?></div>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Projets à réviser</h3>
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <div class="card-number"><?php echo $projects_stats['pending_count']; ?></div>
            </div>
        </div>
    </div>
    
    <div class="dashboard-section">
        <h2>Aperçu des utilisateurs</h2>
        <div class="dashboard-cards">
            <div class="card">
                <div class="card-header">
                    <h3>Total des utilisateurs</h3>
                    <i class="fas fa-users"></i>
                </div>
                <div class="card-number"><?php echo $users_stats['total_users']; ?></div>
                <a href="/admin/manage_users.php" class="btn btn-primary btn-sm">Gérer</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Étudiants</h3>
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="card-number"><?php echo $users_stats['student_count']; ?></div>
                <a href="/admin/manage_users.php?role=student" class="btn btn-primary btn-sm">Voir</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Enseignants</h3>
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <div class="card-number"><?php echo $users_stats['teacher_count']; ?></div>
                <a href="/admin/manage_users.php?role=teacher" class="btn btn-primary btn-sm">Voir</a>
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h3>Administrateurs</h3>
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="card-number"><?php echo $users_stats['admin_count']; ?></div>
                <a href="/admin/manage_users.php?role=admin" class="btn btn-primary btn-sm">Voir</a>
            </div>
        </div>
    </div>
    
    <div class="dashboard-row">
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Projets récents</h3>
                </div>
                
                <?php if (empty($recent_projects)): ?>
                    <p>Aucun projet n'a été soumis.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Titre</th>
                                    <th>Étudiant</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_projects as $project): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($project['title']); ?></td>
                                        <td><?php echo htmlspecialchars($project['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($project['project_type']); ?></td>
                                        <td><?php echo formatDate($project['submission_date']); ?></td>
                                        <td>
                                            <?php 
                                                switch($project['status']) {
                                                    case 'submitted':
                                                        echo '<span class="project-badge badge-submitted">Soumis</span>';
                                                        break;
                                                    case 'approved':
                                                        echo '<span class="project-badge badge-approved">Approuvé</span>';
                                                        break;
                                                    case 'rejected':
                                                        echo '<span class="project-badge badge-rejected">Rejeté</span>';
                                                        break;
                                                    case 'pending_revision':
                                                        echo '<span class="project-badge badge-pending">Révision</span>';
                                                        break;
                                                }
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="dashboard-col">
            <div class="card">
                <div class="card-header">
                    <h3>Utilisateurs récents</h3>
                </div>
                
                <?php if (empty($recent_users)): ?>
                    <p>Aucun utilisateur n'a été créé récemment.</p>
                <?php else: ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Nom</th>
                                    <th>Rôle</th>
                                    <th>Inscription</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($user['full_name']); ?><br>
                                            <small><?php echo htmlspecialchars($user['email']); ?></small>
                                        </td>
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
                                        <td><?php echo formatDate($user['creation_date']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-section {
    margin-bottom: 30px;
}

.dashboard-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 20px;
}

.role-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 20px;
    font-size: 0.8rem;
}

.role-badge.student {
    background-color: #d1ecf1;
    color: #0c5460;
}

.role-badge.teacher {
    background-color: #d4edda;
    color: #155724;
}

.role-badge.admin {
    background-color: #cce5ff;
    color: #004085;
}
</style>

<?php
// Include footer
include_once '../includes/footer.php';
?>
