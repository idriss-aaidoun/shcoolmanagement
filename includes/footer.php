    </main>
    
    <footer>
        <div class="footer-content">
            <div class="footer-section">
                <h3>ENSA Projects</h3>
                <p>Application de gestion des projets des étudiants de l'ENSA</p>
            </div>
            <div class="footer-section">
                <h3>Liens rapides</h3>
                <ul>
                    <li><a href="/index.php">Accueil</a></li>
                    <?php if (!isLoggedIn()): ?>
                        <li><a href="/login.php">Connexion</a></li>
                        <li><a href="/register.php">Inscription</a></li>
                    <?php endif; ?>
                </ul>
            </div>
            <div class="footer-section">
                <h3>Contact</h3>
                <p>Ecole Nationale des Sciences Appliquées</p>
                <p>Kenitra, Maroc</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> ENSA Projects. Tous droits réservés.</p>
        </div>
    </footer>
    
    <script src="/js/script.js"></script>
</body>
</html>
