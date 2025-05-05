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

// Check if project ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setMessage("ID de projet non spécifié", 'danger');
    redirect('/teacher/view_projects.php');
}

$project_id = intval($_GET['id']);

// Check if project exists
$stmt = $pdo->prepare("
    SELECT p.*, u.full_name as student_name, u.email as student_email,
           pt.name as project_type, pc.name as category_name, 
           m.name as module_name
    FROM projects p
    JOIN users u ON p.student_id = u.user_id
    LEFT JOIN project_types pt ON p.type_id = pt.type_id
    LEFT JOIN project_categories pc ON p.category_id = pc.category_id
    LEFT JOIN modules m ON p.module_id = m.module_id
    WHERE p.project_id = ?
");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    setMessage("Projet non trouvé", 'danger');
    redirect('/teacher/view_projects.php');
}

// Get project deliverables
$stmt = $pdo->prepare("SELECT * FROM deliverables WHERE project_id = ? ORDER BY upload_date DESC");
$stmt->execute([$project_id]);
$deliverables = $stmt->fetchAll();

// Get previous evaluations
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as evaluator_name
    FROM evaluations e
    JOIN users u ON e.evaluator_id = u.user_id
    WHERE e.project_id = ?
    ORDER BY e.evaluation_date DESC
");
$stmt->execute([$project_id]);
$evaluations = $stmt->fetchAll();

// Initialize variables
$comments = '';
$grade = '';
$status = $project['status'];
$errors = [];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $comments = sanitize($_POST['comments']);
    $grade = sanitize($_POST['grade']);
    $status = sanitize($_POST['status']);
    
    // Validate inputs
    if ($status === 'approved' && empty($grade)) {
        $errors[] = "Note requise pour approuver le projet";
    }
    
    if (!empty($grade) && (!is_numeric($grade) || $grade < 0 || $grade > 20)) {
        $errors[] = "La note doit être un nombre entre 0 et 20";
    }
    
    // If no errors, save evaluation
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Update project status
            $stmt = $pdo->prepare("UPDATE projects SET status = ?, supervisor_id = ? WHERE project_id = ?");
            $stmt->execute([$status, $teacher_id, $project_id]);
            
            // Add evaluation
            $stmt = $pdo->prepare("
                INSERT INTO evaluations (project_id, evaluator_id, comments, grade, evaluation_date)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $project_id,
                $teacher_id,
                $comments,
                !empty($grade) ? $grade : null
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            setMessage("Projet évalué avec succès!", 'success');
            redirect('/teacher/view_projects.php');
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Erreur d'évaluation: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Évaluer le projet</h1>
        <a href="/teacher/view_projects.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour aux projets
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="project-detail">
        <div class="project-header">
            <div>
                <h2><?php echo htmlspecialchars($project['title']); ?></h2>
                <p>
                    <strong>Soumis par:</strong> <?php echo htmlspecialchars($project['student_name']); ?> 
                    (<?php echo htmlspecialchars($project['student_email']); ?>)
                </p>
                <p><strong>Date de soumission:</strong> <?php echo formatDate($project['submission_date']); ?></p>
                <p><strong>Type:</strong> <?php echo htmlspecialchars($project['project_type']); ?></p>
                <p><strong>Catégorie:</strong> <?php echo htmlspecialchars($project['category_name'] ?: 'Non spécifiée'); ?></p>
                <p><strong>Module:</strong> <?php echo htmlspecialchars($project['module_name'] ?: 'Non spécifié'); ?></p>
                <p><strong>Année académique:</strong> <?php echo htmlspecialchars($project['academic_year']); ?></p>
            </div>
            <div>
                <span class="project-badge 
                    <?php 
                        switch($project['status']) {
                            case 'submitted': echo 'badge-submitted'; break;
                            case 'approved': echo 'badge-approved'; break;
                            case 'rejected': echo 'badge-rejected'; break;
                            case 'pending_revision': echo 'badge-pending'; break;
                        }
                    ?>">
                    <?php 
                        switch($project['status']) {
                            case 'submitted': echo 'Soumis'; break;
                            case 'approved': echo 'Approuvé'; break;
                            case 'rejected': echo 'Rejeté'; break;
                            case 'pending_revision': echo 'Révision demandée'; break;
                        }
                    ?>
                </span>
            </div>
        </div>
        
        <div class="project-description">
            <h3>Description du projet</h3>
            <p><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
        </div>
        
        <?php if (!empty($deliverables)): ?>
            <div class="project-deliverables">
                <h3>Livrables (<?php echo count($deliverables); ?>)</h3>
                
                <?php foreach ($deliverables as $deliverable): ?>
                    <div class="deliverable-item">
                        <div class="deliverable-icon">
                            <?php
                            $ext = strtolower(pathinfo($deliverable['filename'], PATHINFO_EXTENSION));
                            $icon = 'fa-file';
                            
                            if ($ext === 'pdf') {
                                $icon = 'fa-file-pdf';
                            } elseif (in_array($ext, ['doc', 'docx'])) {
                                $icon = 'fa-file-word';
                            } elseif (in_array($ext, ['ppt', 'pptx'])) {
                                $icon = 'fa-file-powerpoint';
                            } elseif (in_array($ext, ['zip', 'rar'])) {
                                $icon = 'fa-file-archive';
                            } elseif ($ext === 'txt') {
                                $icon = 'fa-file-alt';
                            }
                            ?>
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="deliverable-info">
                            <strong><?php echo htmlspecialchars($deliverable['filename']); ?></strong>
                            <small>Téléversé le <?php echo formatDate($deliverable['upload_date']); ?></small>
                        </div>
                        <div class="deliverable-actions">
                            <a href="/download.php?id=<?php echo $deliverable['deliverable_id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                Aucun livrable n'a été soumis pour ce projet.
            </div>
        <?php endif; ?>
        
        <?php if (!empty($evaluations)): ?>
            <div class="previous-evaluations">
                <h3>Évaluations précédentes</h3>
                
                <?php foreach ($evaluations as $evaluation): ?>
                    <div class="evaluation-item">
                        <p><strong>Évaluateur:</strong> <?php echo htmlspecialchars($evaluation['evaluator_name']); ?></p>
                        <p><strong>Date:</strong> <?php echo formatDate($evaluation['evaluation_date']); ?></p>
                        <?php if (!empty($evaluation['grade'])): ?>
                            <p><strong>Note:</strong> <?php echo $evaluation['grade']; ?>/20</p>
                        <?php endif; ?>
                        <?php if (!empty($evaluation['comments'])): ?>
                            <p><strong>Commentaires:</strong></p>
                            <div class="evaluation-comments">
                                <?php echo nl2br(htmlspecialchars($evaluation['comments'])); ?>
                            </div>
                        <?php else: ?>
                            <p><em>Aucun commentaire</em></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div class="evaluation-form">
            <h3>Nouvelle évaluation</h3>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $project_id); ?>" method="post">
                <div class="form-group">
                    <label for="status">Statut du projet*</label>
                    <select id="status" name="status" required>
                        <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Soumis (en attente)</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approuver</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejeter</option>
                        <option value="pending_revision" <?php echo $status === 'pending_revision' ? 'selected' : ''; ?>>Demander une révision</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="grade">Note (/20)</label>
                    <input type="number" id="grade" name="grade" min="0" max="20" step="0.5" value="<?php echo $grade; ?>">
                    <small>Laissez vide si vous ne souhaitez pas attribuer de note pour le moment.</small>
                </div>
                
                <div class="form-group">
                    <label for="comments">Commentaires</label>
                    <textarea id="comments" name="comments" rows="6"><?php echo $comments; ?></textarea>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Soumettre l'évaluation</button>
                    <a href="/teacher/view_projects.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
