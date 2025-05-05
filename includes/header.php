<?php
// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include function file
require_once $_SERVER['DOCUMENT_ROOT'] . '/includes/functions.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Projets ENSA</title>
    <link rel="stylesheet" href="/css/style.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <header>
        <div class="logo">
            <h1>ENSA Projects</h1>
        </div>
        <nav>
            <ul>
                <li><a href="/index.php">Accueil</a></li>
                
                <?php if (!isLoggedIn()): ?>
                    <li><a href="/login.php">Connexion</a></li>
                    <li><a href="/register.php">Inscription</a></li>
                <?php else: ?>
                    <?php if (hasRole('student')): ?>
                        <li><a href="/student/dashboard.php">Tableau de bord</a></li>
                        <li><a href="/student/submit_project.php">Soumettre un projet</a></li>
                        <li><a href="/student/view_projects.php">Mes projets</a></li>
                    <?php elseif (hasRole('teacher')): ?>
                        <li><a href="/teacher/dashboard.php">Tableau de bord</a></li>
                        <li><a href="/teacher/view_projects.php">Projets à évaluer</a></li>
                    <?php elseif (hasRole('admin')): ?>
                        <li><a href="/admin/dashboard.php">Tableau de bord</a></li>
                        <li><a href="/admin/manage_users.php">Gestion des utilisateurs</a></li>
                        <li><a href="/admin/statistics.php">Statistiques</a></li>
                    <?php endif; ?>
                    
                    <li class="dropdown">
                        <a href="#" class="dropbtn"><?php echo $_SESSION['username']; ?> <i class="fa fa-caret-down"></i></a>
                        <div class="dropdown-content">
                            <a href="/logout.php">Déconnexion</a>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>
    
    <main>
        <?php displayMessage(); ?>
