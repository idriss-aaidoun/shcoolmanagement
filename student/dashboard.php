<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is a student
checkPermission(['student']);

// Get student information
$student_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get student's projects count
$stmt = $pdo->prepare("SELECT COUNT(*) as total_projects FROM projects WHERE student_id = ?");
$stmt->execute([$student_id]);
$projects_count = $stmt->fetch()['total_projects'];

// Get projects by status
$stmt = $pdo->prepare("
    SELECT 
        COUNT(CASE WHEN status = 'submitted' THEN 1 END) as submitted_count,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_count,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_count,
        COUNT(CASE WHEN status = 'pending_revision' THEN 1 END) as pending_count
    FROM projects 
    WHERE student_id = ?
");
$stmt->execute([$student_id]);
$projects_status = $stmt->fetch();

// Get recent projects
$stmt = $pdo->prepare("
    SELECT p.*, pt.name as project_type, pc.name as category_name
    FROM projects p
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    WHERE p.student_id = ?
    ORDER BY p.submission_date DESC
    LIMIT 5
");
$stmt->execute([$student_id]);
$recent_projects = $stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Tableau de bord étudiant</h1>
        <a href="/student/submit_project.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Soumettre un nouveau projet
        </a>
    </div>
    
    <div class="dashboard-cards">
        <div class="card">
            <div class="card-header">
                <h3>Mes projets</h3>
                <i class="fas fa-project-diagram"></i>
            </div>
            <div class="card-number"><?php echo $projects_count; ?></div>
            <a href="/student/view_projects.php" class="btn btn-primary btn-sm">Voir tous</a>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Projets approuvés</h3>
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['approved_count']; ?></div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Projets en attente</h3>
                <i class="fas fa-clock"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['submitted_count']; ?></div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3>Projets à réviser</h3>
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="card-number"><?php echo $projects_status['pending_count']; ?></div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Projets récents</h3>
        </div>
        
        <?php if (empty($recent_projects)): ?>
            <p>Vous n'avez pas encore soumis de projets.</p>
        <?php else: ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Titre</th>
                            <th>Type</th>
                            <th>Catégorie</th>
                            <th>Date de soumission</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_projects as $project): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($project['title']); ?></td>
                                <td><?php echo htmlspecialchars($project['project_type']); ?></td>
                                <td><?php echo htmlspecialchars($project['category_name']); ?></td>
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
                                                echo '<span class="project-badge badge-pending">Révision demandée</span>';
                                                break;
                                        }
                                    ?>
                                </td>
                                <td class="actions">
                                    <a href="/student/edit_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <div style="text-align: right; margin-top: 15px;">
            <a href="/student/view_projects.php" class="btn btn-secondary">Voir tous les projets</a>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
