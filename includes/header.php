<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC Gestion - <?php echo ucfirst(str_replace('.php', '', $current_page)); ?></title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <nav class="navbar">
        <a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="apprenants.php" class="<?php echo $current_page == 'apprenants.php' ? 'active' : ''; ?>">Apprenants</a>
        <a href="formations.php" class="<?php echo $current_page == 'formations.php' ? 'active' : ''; ?>">Formations</a>
        <a href="inscriptions.php" class="<?php echo $current_page == 'inscriptions.php' ? 'active' : ''; ?>">Inscriptions</a>
        <a href="formateurs.php" class="<?php echo $current_page == 'formateurs.php' ? 'active' : ''; ?>">Formateurs</a>
        <a href="sessions.php" class="<?php echo $current_page == 'sessions.php' ? 'active' : ''; ?>">Sessions</a>
        <a href="paiements.php" class="<?php echo $current_page == 'paiements.php' ? 'active' : ''; ?>">Paiements</a>
        <a href="certificats.php" class="<?php echo $current_page == 'certificats.php' ? 'active' : ''; ?>">Certificats</a>
        <a href="utilisateurs.php" class="<?php echo $current_page == 'utilisateurs.php' ? 'active' : ''; ?>">Utilisateurs</a>
        <div class="user-info">
            <span><?php echo htmlspecialchars($_SESSION['user_nom']); ?></span>
            <span>(<?php echo htmlspecialchars($_SESSION['user_role']); ?>)</span>
            <a href="logout.php">Deconnexion</a>
        </div>
    </nav>
    <div class="container">
