<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Statistiques
$stats = [
    'apprenants' => $db->query("SELECT COUNT(*) FROM apprenant")->fetchColumn(),
    'formations' => $db->query("SELECT COUNT(*) FROM formation")->fetchColumn(),
    'inscriptions' => $db->query("SELECT COUNT(*) FROM inscription")->fetchColumn(),
    'formateurs' => $db->query("SELECT COUNT(*) FROM formateur")->fetchColumn(),
    'sessions' => $db->query("SELECT COUNT(*) FROM session")->fetchColumn(),
    'paiements' => $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiement")->fetchColumn(),
];

// Dernieres inscriptions
$dernieres_inscriptions = $db->query("
    SELECT i.*, a.nom, a.prenom, f.nom_formation 
    FROM inscription i 
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant 
    JOIN formation f ON i.id_formation = f.id_formation 
    ORDER BY i.date_inscription DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Tableau de Bord</h1>

<div class="welcome">
    <h2>Bienvenue, <?php echo htmlspecialchars($_SESSION['user_nom']); ?>!</h2>
    <p>Systeme de Gestion du Centre de Formation MCC</p>
</div>

<div class="stats">
    <div class="stat-card">
        <h3>Apprenants</h3>
        <div class="stat-number"><?php echo $stats['apprenants']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Formations</h3>
        <div class="stat-number"><?php echo $stats['formations']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Inscriptions</h3>
        <div class="stat-number"><?php echo $stats['inscriptions']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Formateurs</h3>
        <div class="stat-number"><?php echo $stats['formateurs']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Sessions</h3>
        <div class="stat-number"><?php echo $stats['sessions']; ?></div>
    </div>
    <div class="stat-card">
        <h3>Revenus</h3>
        <div class="stat-number"><?php echo number_format($stats['paiements'], 2); ?> $</div>
    </div>
</div>

<div class="card">
    <h3>Dernieres Inscriptions</h3>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Apprenant</th>
                <th>Formation</th>
                <th>Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dernieres_inscriptions as $inscription): ?>
            <tr>
                <td><?php echo date('d/m/Y', strtotime($inscription['date_inscription'])); ?></td>
                <td><?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?></td>
                <td><?php echo htmlspecialchars($inscription['nom_formation']); ?></td>
                <td><?php echo htmlspecialchars($inscription['statut']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

</div>
</body>
</html>
