<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is a teacher
checkPermission(['teacher']);

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$teacher_id]);
$teacher = $stmt->fetch();

// Get pending projects count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count
    FROM projects 
    WHERE status = 'submitted'
");
$stmt->execute();
$pending_count = $stmt->fetch()['pending_count'];

// Get projects by status
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(CASE WHEN status = 'pending_revision' THEN 1 END) as pending_revision_count
    FROM projects
");
$stmt->execute();
$projects_status = $stmt->fetch();

// Get recent submitted projects
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as student_name, pt.name as project_type, pc.name as category_name
    FROM projects p
    JOIN users u ON p.student_id = u.user_id
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    WHERE p.status = 'submitted'
    ORDER BY p.submission_date DESC
    LIMIT 5
");
$stmt->execute();
$recent_projects = $stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Tableau de bord enseignant</h1>
    </div>
    
    <div class="dashboard-cards">
        <div class="card">
            <div class="card-header">
                <h3>Projets en attente</h3>
                <i class="fas fa-clock"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['submitted_count']; ?></div>
            <a href="/teacher/view_projects.php?status=submitted" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Projets approuvés</h3>
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['approved_count']; ?></div>
            <a href="/teacher/view_projects.php?status=approved" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Projets rejetés</h3>
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['rejected_count']; ?></div>
            <a href="/teacher/view_projects.php?status=rejected" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>En attente de révision</h3>
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['pending_revision_count']; ?></div>
            <a href="/teacher/view_projects.php?status=pending_revision" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Projets récemment soumis</h3>
        </div>
        
        <?php if (empty($recent_projects)): ?>
            <p>Aucun projet en attente d'évaluation.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Étudiant</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Date de soumission</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($project['project_type']); ?></td>
                                <td><?php echo htmlspecialchars($project['category_name'] ?: 'Non spécifiée'); ?></td>
                                <td><?php echo formatDate($project['submission_date']); ?></td>
                                <td class="actions">
                                    <a href="/teacher/evaluate_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fas fa-check"></i> Évaluer
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div style="text-align: right; margin-top: 15px;">
            <a href="/teacher/view_projects.php" class="btn btn-secondary">Voir tous les projets</a>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
