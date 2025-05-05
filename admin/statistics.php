<?php
// Include database connection
require_once '../config/db_connect.php';
require_once '../includes/functions.php';

// Start the session
session_start();

// Check if user is logged in and is an admin
checkPermission(['admin']);

// Get academic year filter
$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : '';

// Get all academic years for filter
$stmt = $pdo->prepare("SELECT DISTINCT academic_year FROM projects ORDER BY academic_year DESC");
$stmt->execute();
$academic_years = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Projects by type
$stmt = $pdo->prepare("
    SELECT pt.name, COUNT(p.project_id) as count
    FROM project_types pt
    LEFT JOIN projects p ON pt.type_id = p.type_id
    " . (!empty($academic_year) ? "WHERE p.academic_year = ?" : "") . "
    GROUP BY pt.name
    ORDER BY count DESC
");

if (!empty($academic_year)) {
    $stmt->execute([$academic_year]);
} else {
    $stmt->execute();
}

$projects_by_type = $stmt->fetchAll();

// Projects by category
$stmt = $pdo->prepare("
    SELECT pc.name, COUNT(p.project_id) as count
    FROM project_categories pc
    LEFT JOIN projects p ON pc.category_id = p.category_id
    " . (!empty($academic_year) ? "WHERE p.academic_year = ?" : "") . "
    GROUP BY pc.name
    ORDER BY count DESC
");

if (!empty($academic_year)) {
    $stmt->execute([$academic_year]);
} else {
    $stmt->execute();
}

$projects_by_category = $stmt->fetchAll();

// Projects by status
$stmt = $pdo->prepare("
    SELECT status, COUNT(*) as count
    FROM projects
    " . (!empty($academic_year) ? "WHERE academic_year = ?" : "") . "
    GROUP BY status
");

if (!empty($academic_year)) {
    $stmt->execute([$academic_year]);
} else {
    $stmt->execute();
}

$projects_by_status = $stmt->fetchAll();

// Student years distribution
$stmt = $pdo->prepare("
    SELECT year_of_study, COUNT(*) as count
    FROM users
    WHERE role = 'student'
    GROUP BY year_of_study
");
$stmt->execute();
$students_by_year = $stmt->fetchAll();

// Projects by month
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(submission_date, '%Y-%m') as month, COUNT(*) as count
    FROM projects
    " . (!empty($academic_year) ? "WHERE academic_year = ?" : "") . "
    GROUP BY DATE_FORMAT(submission_date, '%Y-%m')
    ORDER BY month DESC
    LIMIT 12
");

if (!empty($academic_year)) {
    $stmt->execute([$academic_year]);
} else {
    $stmt->execute();
}

$projects_by_month = $stmt->fetchAll();

// Average projects per student
$stmt = $pdo->prepare("
    SELECT AVG(project_count) as avg_projects
    FROM (
        SELECT student_id, COUNT(*) as project_count
        FROM projects
        " . (!empty($academic_year) ? "WHERE academic_year = ?" : "") . "
        GROUP BY student_id
    ) as student_projects
");

if (!empty($academic_year)) {
    $stmt->execute([$academic_year]);
} else {
    $stmt->execute();
}

$avg_projects = $stmt->fetch()['avg_projects'];

// Include header
include_once '../includes/header.php';
?>

<div class="container">
    <div class="dashboard-header">
        <h1>Statistiques</h1>
        <div>
            <a href="/admin/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Retour au tableau de bord
            </a>
            <a href="/admin/export.php" class="btn btn-primary">
                <i class="fas fa-file-export"></i> Exporter les données
            </a>
        </div>
    </div>
    
    <div class="filter-card">
        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="get" class="filter-form">
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
                
                <div class="form-col" style="display: flex; align-items: flex-end;">
                    <button type="submit" class="btn btn-primary">Filtrer</button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn btn-secondary">Réinitialiser</a>
                </div>
            </div>
        </form>
    </div>
    
    <div class="stats-container">
        <div class="stats-card">
            <div class="stats-header">
                <h3>Projets par type</h3>
            </div>
            <div class="stats-body" id="projectsByTypeChart"></div>
        </div>
        
        <div class="stats-card">
            <div class="stats-header">
                <h3>Projets par catégorie</h3>
            </div>
            <div class="stats-body" id="projectsByCategoryChart"></div>
        </div>
        
        <div class="stats-card">
            <div class="stats-header">
                <h3>Statut des projets</h3>
            </div>
            <div class="stats-body" id="projectsByStatusChart"></div>
        </div>
        
        <div class="stats-card">
            <div class="stats-header">
                <h3>Répartition des étudiants par année</h3>
            </div>
            <div class="stats-body" id="studentsByYearChart"></div>
        </div>
        
        <div class="stats-card full-width">
            <div class="stats-header">
                <h3>Évolution du nombre de projets par mois</h3>
            </div>
            <div class="stats-body" id="projectsByMonthChart"></div>
        </div>
    </div>
    
    <div class="stats-summary">
        <div class="summary-card">
            <h3>Moyenne de projets par étudiant</h3>
            <div class="summary-number">
                <?php echo number_format($avg_projects, 2); ?>
            </div>
        </div>
    </div>
</div>

<style>
.filter-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: var(--shadow);
    padding: 20px;
    margin-bottom: 30px;
}

.stats-container {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stats-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: var(--shadow);
    padding: 20px;
}

.full-width {
    grid-column: 1 / -1;
}

.stats-summary {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background-color: white;
    border-radius: 10px;
    box-shadow: var(--shadow);
    padding: 20px;
    flex: 1;
    min-width: 200px;
    text-align: center;
}

.summary-number {
    font-size: 2.5rem;
    font-weight: bold;
    color: var(--primary-color);
    margin-top: 10px;
}
</style>

<!-- Include Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Function to create chart
function createChart(elementId, type, labels, data, backgroundColor, borderColor) {
    const ctx = document.getElementById(elementId).getContext('2d');
    
    return new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: 'Nombre de projets',
                data: data,
                backgroundColor: backgroundColor,
                borderColor: borderColor,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

// Projects by type chart
const projectsByTypeLabels = <?php echo json_encode(array_column($projects_by_type, 'name')); ?>;
const projectsByTypeData = <?php echo json_encode(array_column($projects_by_type, 'count')); ?>;
const projectsByTypeColors = [
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 99, 132, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)'
];

createChart(
    'projectsByTypeChart', 
    'bar', 
    projectsByTypeLabels, 
    projectsByTypeData, 
    projectsByTypeColors,
    'rgba(54, 162, 235, 1)'
);

// Projects by category chart
const projectsByCategoryLabels = <?php echo json_encode(array_column($projects_by_category, 'name')); ?>;
const projectsByCategoryData = <?php echo json_encode(array_column($projects_by_category, 'count')); ?>;
const projectsByCategoryColors = [
    'rgba(255, 99, 132, 0.6)',
    'rgba(54, 162, 235, 0.6)',
    'rgba(255, 206, 86, 0.6)',
    'rgba(75, 192, 192, 0.6)',
    'rgba(153, 102, 255, 0.6)',
    'rgba(255, 159, 64, 0.6)',
    'rgba(199, 199, 199, 0.6)',
    'rgba(83, 102, 255, 0.6)'
];

createChart(
    'projectsByCategoryChart', 
    'pie', 
    projectsByCategoryLabels, 
    projectsByCategoryData, 
    projectsByCategoryColors,
    'white'
);

// Projects by status chart
const statusLabels = {
    'submitted': 'Soumis',
    'approved': 'Approuvé',
    'rejected': 'Rejeté',
    'pending_revision': 'Révision demandée'
};

const statusColors = {
    'submitted': 'rgba(255, 206, 86, 0.6)',
    'approved': 'rgba(75, 192, 192, 0.6)',
    'rejected': 'rgba(255, 99, 132, 0.6)',
    'pending_revision': 'rgba(54, 162, 235, 0.6)'
};

const projectsByStatusLabels = <?php 
    echo json_encode(array_map(function($item) use ($statusLabels) {
        return $statusLabels[$item['status']] ?? $item['status'];
    }, $projects_by_status)); 
?>;

const projectsByStatusData = <?php echo json_encode(array_column($projects_by_status, 'count')); ?>;

const projectsByStatusColors = <?php 
    echo json_encode(array_map(function($item) use ($statusColors) {
        return $statusColors[$item['status']] ?? 'rgba(153, 102, 255, 0.6)';
    }, $projects_by_status)); 
?>;

createChart(
    'projectsByStatusChart', 
    'doughnut', 
    projectsByStatusLabels, 
    projectsByStatusData, 
    projectsByStatusColors,
    'white'
);

// Students by year chart
const yearLabels = {
    '3': '3ème année',
    '4': '4ème année',
    '5': '5ème année',
    null: 'Non spécifiée'
};

const studentsByYearLabels = <?php 
    echo json_encode(array_map(function($item) use ($yearLabels) {
        return $yearLabels[$item['year_of_study']] ?? 'Non spécifiée';
    }, $students_by_year)); 
?>;

const studentsByYearData = <?php echo json_encode(array_column($students_by_year, 'count')); ?>;
const studentsByYearColors = ['rgba(54, 162, 235, 0.6)', 'rgba(255, 99, 132, 0.6)', 'rgba(255, 206, 86, 0.6)', 'rgba(75, 192, 192, 0.6)'];

createChart(
    'studentsByYearChart', 
    'bar', 
    studentsByYearLabels, 
    studentsByYearData, 
    studentsByYearColors,
    'rgba(54, 162, 235, 1)'
);

// Projects by month chart
const projectsByMonthLabels = <?php 
    echo json_encode(array_map(function($item) {
        $date = new DateTime($item['month'] . '-01');
        return $date->format('M Y');
    }, $projects_by_month)); 
?>;

const projectsByMonthData = <?php echo json_encode(array_column($projects_by_month, 'count')); ?>;

createChart(
    'projectsByMonthChart', 
    'line', 
    projectsByMonthLabels.reverse(), 
    projectsByMonthData.reverse(), 
    'rgba(75, 192, 192, 0.6)',
    'rgba(75, 192, 192, 1)'
);
</script>

<?php
// Include footer
include_once '../includes/footer.php';
?>
