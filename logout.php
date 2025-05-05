<?php
// Start session
session_start();

// Include functions
require_once 'includes/functions.php';

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to homepage with message
setMessage('Vous avez été déconnecté avec succès.', 'success');
redirect('/index.php');
?>
