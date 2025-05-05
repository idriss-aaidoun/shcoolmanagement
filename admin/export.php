<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is an admin
checkPermission(['admin']);

// Initialize variables
$export_type = isset($_POST['export_type']) ? sanitize($_POST['export_type']) : '';
$format = isset($_POST['format']) ? sanitize($_POST['format']) : '';
$academic_year = isset($_POST['academic_year']) ? sanitize($_POST['academic_year']) : '';
$project_type = isset($_POST['project_type']) ? (int)$_POST['project_type'] : 0;
$category = isset($_POST['category']) ? (int)$_POST['category'] : 0;

// Get all academic years for filter
$stmt = $pdo->prepare("SELECT DISTINCT academic_year FROM projects ORDER BY academic_year DESC");
$stmt->execute();
$academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get project types
$stmt = $pdo->prepare("SELECT * FROM project_types ORDER BY name");
$stmt->execute();
$project_types = $stmt->fetchAll();

// Get project categories
$stmt = $pdo->prepare("SELECT * FROM project_categories ORDER BY name");
$stmt->execute();
$project_categories = $stmt->fetchAll();

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($export_type) && !empty($format)) {
    // Base query for projects
    $projects_query = "
        SELECT p.project_id, p.title, p.description, p.submission_date, p.status, p.academic_year,
               u.full_name as student_name, u.email as student_email, u.year_of_study,
               u2.full_name as supervisor_name,
               pt.name as project_type, pc.name as category_name, m.name as module_name
        FROM projects p
        JOIN users u ON p.student_id = u.user_id
        LEFT JOIN users u2 ON p.supervisor_id = u2.user_id
        LEFT JOIN project_types pt ON p.type_id = pt.type_id
        LEFT JOIN project_categories pc ON p.category_id = pc.category_id
        LEFT JOIN modules m ON p.module_id = m.module_id
        WHERE 1=1
    ";
    
    $users_query = "
        SELECT user_id, username, email, full_name, role, department, year_of_study, 
               creation_date, last_login, status
        FROM users
        WHERE 1=1
    ";
    
    $params = [];
    
    // Add filters for projects
    if (!empty($academic_year)) {
        $projects_query .= " AND p.academic_year = ?";
        $params[] = $academic_year;
    }
    
    if (!empty($project_type)) {
        $projects_query .= " AND p.type_id = ?";
        $params[] = $project_type;
    }
    
    if (!empty($category)) {
        $projects_query .= " AND p.category_id = ?";
        $params[] = $category;
    }
    
    $projects_query .= " ORDER BY p.submission_date DESC";
    $users_query .= " ORDER BY creation_date DESC";
    
    try {
        // Execute query based on export type
        if ($export_type === 'projects') {
            $stmt = $pdo->prepare($projects_query);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
            $filename = 'projets_export_' . date('Y-m-d');
            $headers = [
                'ID', 'Titre', 'Description', 'Date Soumission', 'Statut', 'Année Académique',
                'Étudiant', 'Email Étudiant', 'Année d\'étude', 'Superviseur',
                'Type de Projet', 'Catégorie', 'Module'
            ];
        } else if ($export_type === 'users') {
            $stmt = $pdo->prepare($users_query);
            $stmt->execute();
            $data = $stmt->fetchAll();
            $filename = 'utilisateurs_export_' . date('Y-m-d');
            $headers = [
                'ID', 'Nom d\'utilisateur', 'Email', 'Nom Complet', 'Rôle', 'Département', 
                'Année d\'étude', 'Date Création', 'Dernière Connexion', 'Statut'
            ];
            
            // Transform status to readable format
            foreach ($data as &$row) {
                $row['status'] = $row['status'] ? 'Actif' : 'Inactif';
                $row['role'] = ucfirst($row['role']);
            }
        }
        
        // Generate export based on format
        if ($format === 'excel') {
            // CSV Export (Excel compatible)
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=' . $filename . '.csv');
            
            $output = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Add headers
            fputcsv($output, $headers);
            
            // Add data
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
            
            fclose($output);
            exit;
        } else if ($format === 'pdf') {
            // Set message that PDF export is not implemented in this version
            setMessage('L\'export PDF n\'est pas implémenté dans cette version.', 'warning');
            redirect('/admin/export.php');
        }
    } catch (PDOException $e) {
        setMessage('Erreur lors de l\'export: ' . $e->getMessage(), 'danger');
        redirect('/admin/export.php');
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Exporter les données</h1>
        <a href="/admin/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h3>Options d'exportation</h3>
        </div>
        
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
            <div class="form-group">
                <label for="export_type">Type de données à exporter*</label>
                <select id="export_type" name="export_type" required onchange="toggleFilters()">
                    <option value="">Sélectionner un type</option>
                    <option value="projects" <?php echo $export_type === 'projects' ? 'selected' : ''; ?>>Projets</option>
                    <option value="users" <?php echo $export_type === 'users' ? 'selected' : ''; ?>>Utilisateurs</option>
                </select>
            </div>
            
            <div id="project_filters" style="display: <?php echo $export_type === 'projects' ? 'block' : 'none'; ?>">
                <div class="form-row">
                    <div class="form-col">
                        <label for="academic_year">Année académique</label>
                        <select id="academic_year" name="academic_year">
                            <option value="">Toutes les années</option>
                            <?php foreach ($academic_years as $year): ?>
                                <option value="<?php echo htmlspecialchars($year); ?>" <?php echo $academic_year === $year ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($year); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-col">
                        <label for="project_type">Type de projet</label>
                        <select id="project_type" name="project_type">
                            <option value="">Tous les types</option>
                            <?php foreach ($project_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>" <?php echo $project_type == $type['type_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-col">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category">
                            <option value="">Toutes les catégories</option>
                            <?php foreach ($project_categories as $cat): ?>
                                <option value="<?php echo $cat['category_id']; ?>" <?php echo $category == $cat['category_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="format">Format d'exportation*</label>
                <select id="format" name="format" required>
                    <option value="">Sélectionner un format</option>
                    <option value="excel" <?php echo $format === 'excel' ? 'selected' : ''; ?>>Excel (CSV)</option>
                    <option value="pdf" <?php echo $format === 'pdf' ? 'selected' : ''; ?>>PDF</option>
                </select>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-download"></i> Télécharger
                </button>
            </div>
        </form>
    </div>
    
    <div class="export-info">
        <h3>Informations sur l'exportation</h3>
        
        <div class="info-section">
            <h4>Export des projets</h4>
            <p>Cet export inclut toutes les informations sur les projets, y compris:</p>
            <ul>
                <li>Titre et description du projet</li>
                <li>Date de soumission et statut</li>
                <li>Année académique</li>
                <li>Informations sur l'étudiant et le superviseur</li>
                <li>Type, catégorie et module du projet</li>
            </ul>
        </div>
        
        <div class="info-section">
            <h4>Export des utilisateurs</h4>
            <p>Cet export inclut toutes les informations sur les utilisateurs, y compris:</p>
            <ul>
                <li>Nom d'utilisateur et email</li>
                <li>Nom complet et rôle</li>
                <li>Département et année d'étude (pour les étudiants)</li>
                <li>Date de création et dernière connexion</li>
                <li>Statut du compte</li>
            </ul>
        </div>
        
        <div class="info-section">
            <h4>Note sur la confidentialité</h4>
            <p>Les informations exportées peuvent contenir des données personnelles. Veillez à respecter les règles de confidentialité et de protection des données lors de l'utilisation de ces exports.</p>
        </div>
    </div>
</div>

<style>
.export-info {
    margin-top: 30px;
}

.info-section {
    margin-bottom: 20px;
}

.info-section h4 {
    margin-bottom: 10px;
    color: var(--dark-color);
}

.info-section ul {
    margin-left: 20px;
    list-style: disc;
}

.info-section ul li {
    margin-bottom: 5px;
}
</style>

<script>
function toggleFilters() {
    const exportType = document.getElementById('export_type').value;
    const projectFilters = document.getElementById('project_filters');
    
    if (exportType === 'projects') {
        projectFilters.style.display = 'block';
    } else {
        projectFilters.style.display = 'none';
    }
}
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
