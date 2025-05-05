<?php
// Include database connection
require_once 'config/db_connect.php';
require_once 'includes/functions.php';

// Start the session
session_start();

// Check if user is logged in
if (!isLoggedIn()) {
    setMessage("Vous devez vous connecter pour télécharger un fichier.", 'danger');
    redirect('/login.php');
}

// Check if deliverable ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    setMessage("ID de livrable non spécifié", 'danger');
    redirect('/index.php');
}

$deliverable_id = intval($_GET['id']);

// Get deliverable information
$stmt = $pdo->prepare("
    SELECT d.*, p.student_id, p.status
    FROM deliverables d
    JOIN projects p ON d.project_id = p.project_id
    WHERE d.deliverable_id = ?
");
$stmt->execute([$deliverable_id]);
$deliverable = $stmt->fetch();

if (!$deliverable) {
    setMessage("Livrable non trouvé", 'danger');
    redirect('/index.php');
}

// Check permissions
// - Students can only download their own deliverables
// - Teachers and admins can download any deliverable
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

if ($user_role === 'student' && $deliverable['student_id'] !== $user_id) {
    setMessage("Vous n'avez pas l'autorisation de télécharger ce fichier.", 'danger');
    redirect('/student/dashboard.php');
}

// Check if file exists
if (!file_exists($deliverable['file_path'])) {
    setMessage("Le fichier n'existe pas ou a été supprimé.", 'danger');
    
    if ($user_role === 'student') {
        redirect('/student/dashboard.php');
    } elseif ($user_role === 'teacher') {
        redirect('/teacher/dashboard.php');
    } else {
        redirect('/admin/dashboard.php');
    }
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($deliverable['filename']) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($deliverable['file_path']));

// Clean output buffer
ob_clean();
flush();

// Read file and output it
readfile($deliverable['file_path']);
exit;
?>
