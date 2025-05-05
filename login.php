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
$username = $password = '';
$errors = [];

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    // Validate inputs
    if (empty($username)) {
        $errors[] = "Nom d'utilisateur requis";
    }
    
    if (empty($password)) {
        $errors[] = "Mot de passe requis";
    }
    
    // If no errors, attempt to login
    if (empty($errors)) {
        try {
            // Prepare statement
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Check if user exists and password is correct
            if ($user && password_verify($password, $user['password'])) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                
                // Update last login time
                $updateStmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->execute([$user['user_id']]);
                
                // Redirect to appropriate dashboard
                setMessage('Connexion réussie. Bienvenue ' . $user['full_name'] . '!', 'success');
                redirect('/' . $user['role'] . '/dashboard.php');
            } else {
                $errors[] = "Nom d'utilisateur ou mot de passe incorrect";
            }
        } catch (PDOException $e) {
            $errors[] = "Erreur de connexion: " . $e->getMessage();
        }
    }
}

// Include header
include_once 'includes/header.php';
?>

<section class="auth-section">
    <div class="container">
        <div class="auth-form">
            <h2>Connexion</h2>
            
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
                    <label for="username">Nom d'utilisateur</label>
                    <input type="text" id="username" name="username" value="<?php echo $username; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Se connecter</button>
                </div>
                
                <p class="auth-link">Vous n'avez pas de compte? <a href="/register.php">S'inscrire</a></p>
            </form>
        </div>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';
?>
