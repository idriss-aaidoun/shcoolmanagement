<?php
// Include database connection
require_once 'config/db_connect.php';
include_once 'includes/functions.php';

// Start the session
session_start();

// Check if user is already logged in
if (isLoggedIn()) {
    redirect('/' . $_SESSION['role'] . '/dashboard.php');
}

// Initialize variables
$username = $email = $full_name = $role = $department = $year_of_study = '';
$errors = [];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $department = sanitize($_POST['department'] ?? '');
    $year_of_study = sanitize($_POST['year_of_study'] ?? '');
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = "Nom d'utilisateur requis";
    } elseif (strlen($username) < 3) {
        $errors[] = "Le nom d'utilisateur doit contenir au moins 3 caractères";
    }
    
    if (empty($email)) {
        $errors[] = "Email requis";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Format d'email invalide";
    }
    
    if (empty($full_name)) {
        $errors[] = "Nom complet requis";
    }
    
    if (empty($password)) {
        $errors[] = "Mot de passe requis";
    } elseif (strlen($password) < 6) {
        $errors[] = "Le mot de passe doit contenir au moins 6 caractères";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Les mots de passe ne correspondent pas";
    }
    
    if (empty($role)) {
        $errors[] = "Rôle requis";
    }
    
    if ($role === 'student' && empty($year_of_study)) {
        $errors[] = "Année d'étude requise pour les étudiants";
    }
    
    // Check if username already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Ce nom d'utilisateur est déjà utilisé";
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) {
        $errors[] = "Cet email est déjà utilisé";
    }
    
    // If no errors, create user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Prepare statement
            $stmt = $pdo->prepare("
                INSERT INTO users (username, password, email, full_name, role, department, year_of_study) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            // Execute
            $stmt->execute([
                $username,
                $hashed_password,
                $email,
                $full_name,
                $role,
                $department,
                $role === 'student' ? $year_of_study : null
            ]);
            
            // Set message and redirect to login
            setMessage('Compte créé avec succès. Vous pouvez maintenant vous connecter.', 'success');
            redirect('/login.php');
        } catch (PDOException $e) {
            $errors[] = "Erreur d'inscription: " . $e->getMessage();
        }
    }
}

// Include header
include_once 'includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-form">
            <h2>Inscription</h2>
            
            <?php if (!empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
                <div class="form-group">
                    <label for="username">Nom d'utilisateur*</label>
                    <input type="text" id="username" name="username" value="<?php echo $username; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email*</label>
                    <input type="email" id="email" name="email" value="<?php echo $email; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="full_name">Nom complet*</label>
                    <input type="text" id="full_name" name="full_name" value="<?php echo $full_name; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe*</label>
                    <input type="password" id="password" name="password" required>
                    <small>Au moins 6 caractères</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirmer le mot de passe*</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="form-group">
                    <label for="role">Rôle*</label>
                    <select id="role" name="role" required onchange="toggleStudentFields()">
                        <option value="">Sélectionner un rôle</option>
                        <option value="student" <?php echo $role === 'student' ? 'selected' : ''; ?>>Étudiant</option>
                        <option value="teacher" <?php echo $role === 'teacher' ? 'selected' : ''; ?>>Enseignant</option>
                    </select>
                </div>
                
                <div id="student-fields" style="display: <?php echo $role === 'student' ? 'block' : 'none'; ?>">
                    <div class="form-group">
                        <label for="department">Département</label>
                        <input type="text" id="department" name="department" value="<?php echo $department; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="year_of_study">Année d'étude*</label>
                        <select id="year_of_study" name="year_of_study">
                            <option value="">Sélectionner une année</option>
                            <option value="3" <?php echo $year_of_study === '3' ? 'selected' : ''; ?>>3ème année</option>
                            <option value="4" <?php echo $year_of_study === '4' ? 'selected' : ''; ?>>4ème année</option>
                            <option value="5" <?php echo $year_of_study === '5' ? 'selected' : ''; ?>>5ème année</option>
                        </select>
                    </div>
                </div>
                
                <div id="teacher-fields" style="display: <?php echo $role === 'teacher' ? 'block' : 'none'; ?>">
                    <div class="form-group">
                        <label for="department">Département</label>
                        <input type="text" id="department" name="department" value="<?php echo $department; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">S'inscrire</button>
                </div>
                
                <p class="auth-link">Vous avez déjà un compte? <a href="/login.php">Se connecter</a></p>
            </form>
        </div>
    </div>
</section>

<script>
function toggleStudentFields() {
    const role = document.getElementById('role').value;
    const studentFields = document.getElementById('student-fields');
    const teacherFields = document.getElementById('teacher-fields');
    
    if (role === 'student') {
        studentFields.style.display = 'block';
        teacherFields.style.display = 'none';
    } else if (role === 'teacher') {
        studentFields.style.display = 'none';
        teacherFields.style.display = 'block';
    } else {
        studentFields.style.display = 'none';
        teacherFields.style.display = 'none';
    }
}
</script>

<?php
// Include footer
include_once 'includes/footer.php';
?>
