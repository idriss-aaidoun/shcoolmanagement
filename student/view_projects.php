<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is a student
checkPermission(['student']);

// Get student ID
$student_id = $_SESSION['user_id'];

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter values
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Base query
$query = "
    SELECT p.*, pt.name as project_type, pc.name as category_name, 
           m.name as module_name, COUNT(d.deliverable_id) as deliverables_count,
           e.comments, e.grade
    FROM projects p
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    LEFT JOIN modules m ON p.module_id = m.module_id
    LEFT JOIN deliverables d ON p.project_id = d.project_id
    LEFT JOIN (
        SELECT project_id, comments, grade 
        FROM evaluations 
        WHERE evaluation_id IN (
            SELECT MAX(evaluation_id) 
            FROM evaluations 
            GROUP BY project_id
        )
    ) e ON p.project_id = e.project_id
    WHERE p.student_id = ?
";

// Add filters to query
$params = [$student_id];

if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $query .= " AND p.type_id = ?";
    $params[] = $type_filter;
}

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Group by to avoid duplicate records due to multiple deliverables
$query .= " GROUP BY p.project_id";

// Count total records for pagination
$count_stmt = $pdo->prepare(str_replace("p.*, pt.name as project_type, pc.name as category_name, 
           m.name as module_name, COUNT(d.deliverable_id) as deliverables_count,
           e.comments, e.grade", "COUNT(DISTINCT p.project_id) as total", $query));
$count_stmt->execute($params);
$total_records = $count_stmt->fetch()['total'];
$total_pages = ceil($total_records / $records_per_page);

// Add pagination to query
$query .= " ORDER BY p.submission_date DESC LIMIT $offset, $records_per_page";

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Get project types for filter dropdown
$stmt = $pdo->prepare("SELECT * FROM project_types ORDER BY name");
$stmt->execute();
$project_types = $stmt->fetchAll();

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Mes projets</h1>
        <a href="/student/submit_project.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Soumettre un nouveau projet
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Filtrer les projets</h3>
        </div>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="filter-form">
            <div class="form-row">
                <div class="form-col">
                    <label for="status">Statut</label>
                    <select id="status" name="status">
                        <option value="">Tous les statuts</option>
                        <option value="submitted" <?php echo $status_filter === 'submitted' ? 'selected' : ''; ?>>Soumis</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approuvé</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejeté</option>
                        <option value="pending_revision" <?php echo $status_filter === 'pending_revision' ? 'selected' : ''; ?>>Révision demandée</option>
                    </select>
                </div>
                
                <div class="form-col">
                    <label for="type">Type de projet</label>
                    <select id="type" name="type">
                        <option value="">Tous les types</option>
                        <?php foreach ($project_types as $type): ?>
                            <option value="<?php echo $type['type_id']; ?>" <?php echo $type_filter == $type['type_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-col">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Titre ou description...">
                </div>
                
                <div class="form-col" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
    
    <?php if (empty($projects)): ?>
        <div class="alert alert-info">
            Aucun projet ne correspond à vos critères de recherche.
        </div>
    <?php else: ?>
        <div class="project-cards">
            <?php foreach ($projects as $project): ?>
                <div class="project-card">
                    <h3><?php echo htmlspecialchars($project['title']); ?></h3>
                    
                    <div class="project-meta">
                        <span><i class="fas fa-calendar-alt"></i> <?php echo formatDate($project['submission_date']); ?></span>
                        <span><i class="fas fa-file"></i> <?php echo $project['deliverables_count']; ?> livrable(s)</span>
                    </div>
                    
                    <?php 
                        // Status badge
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
                    
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($project['project_type']); ?></p>
                    <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($project['category_name'] ?: 'Non spécifiée'); ?></p>
                    <p><strong>Module:</strong> <?php echo htmlspecialchars($project['module_name'] ?: 'Non spécifié'); ?></p>
                    <p><strong>Année académique:</strong> <?php echo htmlspecialchars($project['academic_year']); ?></p>
                    
                    <?php if (!empty($project['grade'])): ?>
                        <p><strong>Évaluation:</strong> <?php echo $project['grade']; ?>/20</p>
                    <?php endif; ?>
                    
                    <?php if (!empty($project['comments'])): ?>
                        <div class="project-comments">
                            <p><strong>Commentaires:</strong> <?php echo htmlspecialchars($project['comments']); ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <div class="project-actions">
                        <?php if ($project['status'] === 'submitted' || $project['status'] === 'pending_revision'): ?>
                            <a href="/student/edit_project.php?id=<?php echo $project['project_id']; ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-edit"></i> Modifier
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page - 1) . '&status=' . $status_filter . '&type=' . $type_filter . '&search=' . $search); ?>">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . $i . '&status=' . $status_filter . '&type=' . $type_filter . '&search=' . $search); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page + 1) . '&status=' . $status_filter . '&type=' . $type_filter . '&search=' . $search); ?>">
                        Suivant <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
