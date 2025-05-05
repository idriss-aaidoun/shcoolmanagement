<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is a teacher
checkPermission(['teacher']);

// Get teacher ID
$teacher_id = $_SESSION['user_id'];

// Set up pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

// Get filter values
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$type_filter = isset($_GET['type']) ? (int)$_GET['type'] : 0;
$student_filter = isset($_GET['student']) ? sanitize($_GET['student']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Base query
$query = "
    SELECT p.*, u.full_name as student_name, u.email as student_email,
           pt.name as project_type, pc.name as category_name, 
           m.name as module_name, COUNT(d.deliverable_id) as deliverables_count
    FROM projects p
    JOIN users u ON p.student_id = u.user_id
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    LEFT JOIN modules m ON p.module_id = m.module_id
    LEFT JOIN deliverables d ON p.project_id = d.project_id
    WHERE 1=1
";

// Add filters to query
$params = [];

if (!empty($status_filter)) {
    $query .= " AND p.status = ?";
    $params[] = $status_filter;
}

if (!empty($type_filter)) {
    $query .= " AND p.type_id = ?";
    $params[] = $type_filter;
}

if (!empty($student_filter)) {
    $query .= " AND (u.full_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$student_filter%";
    $params[] = "%$student_filter%";
}

if (!empty($search)) {
    $query .= " AND (p.title LIKE ? OR p.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Group by to avoid duplicate records due to multiple deliverables
$query .= " GROUP BY p.project_id";

// Count total records for pagination
$count_stmt = $pdo->prepare(str_replace("p.*, u.full_name as student_name, u.email as student_email,
           pt.name as project_type, pc.name as category_name, 
           m.name as module_name, COUNT(d.deliverable_id) as deliverables_count", "COUNT(DISTINCT p.project_id) as total", $query));
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
        <h1>Projets des étudiants</h1>
        <a href="/teacher/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
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
                    <label for="student">Étudiant</label>
                    <input type="text" id="student" name="student" value="<?php echo htmlspecialchars($student_filter); ?>" placeholder="Nom ou email...">
                </div>
                
                <div class="form-col">
                    <label for="search">Recherche</label>
                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Titre ou description...">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-col">
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
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Titre</th>
                        <th>Étudiant</th>
                        <th>Type</th>
                        <th>Soumis le</th>
                        <th>Statut</th>
                        <th>Livrables</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($project['title']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($project['student_name']); ?><br>
                                <small><?php echo htmlspecialchars($project['student_email']); ?></small>
                            </td>
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
                                            echo '<span class="project-badge badge-pending">Révision demandée</span>';
                                            break;
                                    }
                                ?>
                            </td>
                            <td><?php echo $project['deliverables_count']; ?></td>
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
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page - 1) . '&status=' . $status_filter . '&type=' . $type_filter . '&student=' . $student_filter . '&search=' . $search); ?>">
                        <i class="fas fa-chevron-left"></i> Précédent
                    </a>
                <?php endif; ?>
                
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . $i . '&status=' . $status_filter . '&type=' . $type_filter . '&student=' . $student_filter . '&search=' . $search); ?>" 
                       class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?page=' . ($page + 1) . '&status=' . $status_filter . '&type=' . $type_filter . '&student=' . $student_filter . '&search=' . $search); ?>">
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
