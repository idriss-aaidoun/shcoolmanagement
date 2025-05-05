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

// Get project types
$stmt = $pdo->prepare("SELECT * FROM project_types ORDER BY name");
$stmt->execute();
$project_types = $stmt->fetchAll();

// Get project categories
$stmt = $pdo->prepare("SELECT * FROM project_categories ORDER BY name");
$stmt->execute();
$project_categories = $stmt->fetchAll();

// Get modules
$stmt = $pdo->prepare("SELECT * FROM modules ORDER BY name");
$stmt->execute();
$modules = $stmt->fetchAll();

// Initialize variables
$title = $description = $type_id = $category_id = $module_id = $academic_year = '';
$errors = [];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $type_id = sanitize($_POST['type_id']);
    $category_id = sanitize($_POST['category_id']);
    $module_id = sanitize($_POST['module_id'] ?? null);
    $academic_year = sanitize($_POST['academic_year']);
    
    // Validate inputs
    if (empty($title)) {
        $errors[] = "Le titre du projet est requis";
    }
    
    if (empty($description)) {
        $errors[] = "La description du projet est requise";
    }
    
    if (empty($type_id)) {
        $errors[] = "Le type de projet est requis";
    }
    
    if (empty($academic_year)) {
        $errors[] = "L'année académique est requise";
    }
    
    // If no errors, insert project
    if (empty($errors)) {
        try {
            // Begin transaction
            $pdo->beginTransaction();
            
            // Insert project
            $stmt = $pdo->prepare("
                INSERT INTO projects (title, description, student_id, category_id, type_id, module_id, academic_year)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title,
                $description,
                $student_id,
                $category_id ?: null,
                $type_id,
                $module_id ?: null,
                $academic_year
            ]);
            
            $project_id = $pdo->lastInsertId();
            
            // Upload deliverables if any
            if (!empty($_FILES['deliverables']['name'][0])) {
                $upload_dir = '../uploads/';
                
                // Loop through all uploaded files
                for ($i = 0; $i < count($_FILES['deliverables']['name']); $i++) {
                    $file = [
                        'name' => $_FILES['deliverables']['name'][$i],
                        'type' => $_FILES['deliverables']['type'][$i],
                        'tmp_name' => $_FILES['deliverables']['tmp_name'][$i],
                        'error' => $_FILES['deliverables']['error'][$i],
                        'size' => $_FILES['deliverables']['size'][$i],
                    ];
                    
                    // Upload file
                    $upload_result = uploadFile($file, $upload_dir);
                    
                    if ($upload_result['success']) {
                        // Insert deliverable info into database
                        $stmt = $pdo->prepare("
                            INSERT INTO deliverables (project_id, filename, file_path, file_type, description)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $project_id,
                            $file['name'],
                            $upload_result['path'],
                            $upload_result['type'],
                            "Livrable du projet"
                        ]);
                    } else {
                        // Roll back transaction and show error
                        $pdo->rollBack();
                        $errors[] = $upload_result['error'];
                        break;
                    }
                }
            }
            
            // Commit transaction if no upload errors
            if (empty($errors)) {
                $pdo->commit();
                setMessage("Projet soumis avec succès!", 'success');
                redirect('/student/view_projects.php');
            }
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = "Erreur de soumission: " . $e->getMessage();
        }
    }
}

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Soumettre un nouveau projet</h1>
        <a href="/student/dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
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
    
    <div class="form-section">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label for="title">Titre du projet*</label>
                <input type="text" id="title" name="title" value="<?php echo $title; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="type_id">Type de projet*</label>
                <select id="type_id" name="type_id" required>
                    <option value="">Sélectionner un type</option>
                    <?php foreach ($project_types as $type): ?>
                        <option value="<?php echo $type['type_id']; ?>" <?php echo $type_id == $type['type_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($type['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-col">
                    <label for="category_id">Catégorie</label>
                    <select id="category_id" name="category_id">
                        <option value="">Sélectionner une catégorie</option>
                        <?php foreach ($project_categories as $category): ?>
                            <option value="<?php echo $category['category_id']; ?>" <?php echo $category_id == $category['category_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-col">
                    <label for="module_id">Module (si applicable)</label>
                    <select id="module_id" name="module_id">
                        <option value="">Sélectionner un module</option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module['module_id']; ?>" <?php echo $module_id == $module['module_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($module['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="academic_year">Année académique*</label>
                <select id="academic_year" name="academic_year" required>
                    <option value="">Sélectionner une année</option>
                    <?php 
                        $current_year = date('Y');
                        for ($i = 0; $i < 5; $i++) {
                            $year = ($current_year - $i) . '-' . ($current_year - $i + 1);
                            echo '<option value="' . $year . '" ' . ($academic_year == $year ? 'selected' : '') . '>' . $year . '</option>';
                        }
                    ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description">Description du projet*</label>
                <textarea id="description" name="description" rows="6" required><?php echo $description; ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="deliverables">Livrables (PDF, DOC, DOCX, PPT, PPTX, ZIP, RAR, TXT)</label>
                <input type="file" id="deliverables" name="deliverables[]" multiple>
                <div id="deliverables-preview" class="file-preview"></div>
                <small>Vous pouvez sélectionner plusieurs fichiers.</small>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Soumettre le projet</button>
                <a href="/student/dashboard.php" class="btn btn-secondary">Annuler</a>
            </div>
        </form>
    </div>
</div>

<?php
// Include footer
include_once '../includes/footer.php';
?>
