<?php
// Include header
include_once 'includes/header.php';
?>

<section class="hero">
    <div class="hero-content">
        <h1>Bienvenue sur l'application de gestion des projets ENSA</h1>
        <p>Centralisez, partagez et valorisez les projets et stages des étudiants</p>
        
        <?php if (!isLoggedIn()): ?>
            <div class="cta-buttons">
                <a href="/login.php" class="btn btn-primary">Connexion</a>
                <a href="/register.php" class="btn btn-secondary">Inscription</a>
            </div>
        <?php else: ?>
            <div class="cta-buttons">
                <a href="/<?php echo $_SESSION['role']; ?>/dashboard.php" class="btn btn-primary">Accéder à mon espace</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="features">
    <div class="container">
        <h2>Fonctionnalités</h2>
        
        <div class="feature-grid">
            <div class="feature-card">
                <i class="fas fa-upload"></i>
                <h3>Soumission de projets</h3>
                <p>Soumettez vos projets et stages avec tous les livrables associés</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-history"></i>
                <h3>Historique</h3>
                <p>Gardez une trace de tous vos projets académiques</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-check-circle"></i>
                <h3>Validation</h3>
                <p>Les enseignants peuvent évaluer et valider les projets</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-search"></i>
                <h3>Recherche</h3>
                <p>Retrouvez facilement les projets par type, module ou mot-clé</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-file-download"></i>
                <h3>Téléchargement</h3>
                <p>Accédez aux livrables de projets pour consultation</p>
            </div>
            
            <div class="feature-card">
                <i class="fas fa-chart-bar"></i>
                <h3>Statistiques</h3>
                <p>Visualisez des statistiques sur les projets réalisés</p>
            </div>
        </div>
    </div>
</section>

<section class="about">
    <div class="container">
        <h2>À propos</h2>
        <p>Cette application a été développée pour répondre au besoin de centralisation et de traçabilité des projets et stages réalisés par les étudiants de l'ENSA.</p>
        <p>Elle permet de :</p>
        <ul>
            <li>Suivre les projets réalisés dans tous les modules et stages</li>
            <li>Archiver les livrables (rapports, codes, présentations)</li>
            <li>Assurer la traçabilité et la visibilité des projets</li>
            <li>Favoriser l'échange et l'inspiration entre étudiants</li>
        </ul>
    </div>
</section>

<?php
// Include footer
include_once 'includes/footer.php';
?>
