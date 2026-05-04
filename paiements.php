<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$paiement_edit = null;
$inscription_preselectionnee = isset($_GET['inscription']) ? intval($_GET['inscription']) : 0;

// ============================================
// CREATE - Ajouter un paiement
// ============================================
if (isset($_POST['ajouter'])) {
    $id_inscription = intval($_POST['id_inscription']);
    $montant = floatval($_POST['montant']);
    $date_paiement = $_POST['date_paiement'];
    $mode_paiement = trim($_POST['mode_paiement']);
    
    if (empty($id_inscription) || $montant <= 0 || empty($date_paiement)) {
        $error = "⚠️ Veuillez remplir tous les champs obligatoires.";
    } else {
        // Récupérer le coût de la formation et les paiements déjà effectués
        $info = $db->prepare("
            SELECT f.cout, COALESCE(SUM(p.montant), 0) as total_paye
            FROM inscription i
            JOIN formation f ON i.id_formation = f.id_formation
            LEFT JOIN paiement p ON i.id_inscription = p.id_inscription
            WHERE i.id_inscription = :id
            GROUP BY i.id_inscription
        ");
        $info->execute([':id' => $id_inscription]);
        $data = $info->fetch(PDO::FETCH_ASSOC);
        
        if (!$data) {
            $error = "⚠️ Inscription introuvable.";
        } else {
            $cout_total = $data['cout'];
            $deja_paye = $data['total_paye'];
            $nouveau_total = $deja_paye + $montant;
            $solde_restant = $cout_total - $nouveau_total;
            
            if ($solde_restant < -0.01) {
                $error = "⚠️ Le montant dépasse le coût total de la formation ({$cout_total} FC). Maximum restant : " . ($cout_total - $deja_paye) . " FC.";
            } else {
                try {
                    $query = "INSERT INTO paiement (montant, date_paiement, mode_paiement, solde_restant, id_inscription) 
                              VALUES (:montant, :date, :mode, :solde, :inscription)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':montant' => $montant,
                        ':date' => $date_paiement,
                        ':mode' => $mode_paiement,
                        ':solde' => max(0, $solde_restant),
                        ':inscription' => $id_inscription
                    ]);
                    $message = "✅ Paiement de {$montant} FC enregistré avec succès !";
                    if ($solde_restant <= 0) {
                        $message .= " 🎉 La formation est totalement payée !";
                    } else {
                        $message .= " Solde restant : {$solde_restant} FC.";
                    }
                } catch (PDOException $e) {
                    $error = "❌ Erreur : " . $e->getMessage();
                }
            }
        }
    }
}

// ============================================
// UPDATE - Modifier un paiement
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_paiement']);
    $id_inscription = intval($_POST['id_inscription']);
    $montant = floatval($_POST['montant']);
    $date_paiement = $_POST['date_paiement'];
    $mode_paiement = trim($_POST['mode_paiement']);
    
    try {
        // Recalculer le solde
        $info = $db->prepare("
            SELECT f.cout, COALESCE(SUM(p2.montant), 0) as total_paye
            FROM inscription i
            JOIN formation f ON i.id_formation = f.id_formation
            LEFT JOIN paiement p2 ON i.id_inscription = p2.id_inscription AND p2.id_paiement != :pid
            WHERE i.id_inscription = :iid
            GROUP BY i.id_inscription
        ");
        $info->execute([':pid' => $id, ':iid' => $id_inscription]);
        $data = $info->fetch(PDO::FETCH_ASSOC);
        
        $solde_restant = $data['cout'] - ($data['total_paye'] + $montant);
        
        $query = "UPDATE paiement SET montant=:montant, date_paiement=:date, 
                  mode_paiement=:mode, solde_restant=:solde, id_inscription=:inscription 
                  WHERE id_paiement=:id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $id,
            ':montant' => $montant,
            ':date' => $date_paiement,
            ':mode' => $mode_paiement,
            ':solde' => max(0, $solde_restant),
            ':inscription' => $id_inscription
        ]);
        $message = "✅ Paiement modifié avec succès !";
    } catch (PDOException $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// ============================================
// DELETE - Supprimer un paiement
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    try {
        $stmt = $db->prepare("DELETE FROM paiement WHERE id_paiement = :id");
        $stmt->execute([':id' => $id]);
        $message = "✅ Paiement supprimé avec succès !";
    } catch (PDOException $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// ============================================
// EDIT - Charger pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("
        SELECT p.*, a.nom, a.prenom, f.nom_formation 
        FROM paiement p
        JOIN inscription i ON p.id_inscription = i.id_inscription
        JOIN apprenant a ON i.id_apprenant = a.id_apprenant
        JOIN formation f ON i.id_formation = f.id_formation
        WHERE p.id_paiement = :id
    ");
    $stmt->execute([':id' => $id]);
    $paiement_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer tous les paiements
// ============================================
$search = $_GET['search'] ?? '';
$filter_mode = $_GET['mode'] ?? '';
$filter_inscription = $_GET['inscription'] ?? '';
$date_debut = $_GET['date_debut'] ?? '';
$date_fin = $_GET['date_fin'] ?? '';

$query = "
    SELECT p.*, 
           a.nom, a.postnom, a.prenom,
           f.nom_formation, f.cout as cout_formation,
           i.statut as statut_inscription
    FROM paiement p
    JOIN inscription i ON p.id_inscription = i.id_inscription
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant
    JOIN formation f ON i.id_formation = f.id_formation
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (a.nom LIKE :s1 OR a.prenom LIKE :s2 OR f.nom_formation LIKE :s3)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
}

if (!empty($filter_mode)) {
    $query .= " AND p.mode_paiement = :mode";
    $params[':mode'] = $filter_mode;
}

if (!empty($filter_inscription)) {
    $query .= " AND p.id_inscription = :ins";
    $params[':ins'] = $filter_inscription;
}

if (!empty($date_debut)) {
    $query .= " AND p.date_paiement >= :d1";
    $params[':d1'] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND p.date_paiement <= :d2";
    $params[':d2'] = $date_fin;
}

$query .= " ORDER BY p.date_paiement DESC, p.id_paiement DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$paiements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour les selects
$inscriptions_list = $db->query("
    SELECT i.id_inscription, a.nom, a.prenom, f.nom_formation, f.cout,
           COALESCE(SUM(p.montant), 0) as total_paye
    FROM inscription i
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant
    JOIN formation f ON i.id_formation = f.id_formation
    LEFT JOIN paiement p ON i.id_inscription = p.id_inscription
    GROUP BY i.id_inscription
    ORDER BY a.nom
")->fetchAll(PDO::FETCH_ASSOC);

$modes_paiement = $db->query("SELECT DISTINCT mode_paiement FROM paiement WHERE mode_paiement != '' ORDER BY mode_paiement")->fetchAll(PDO::FETCH_COLUMN);

// Statistiques
$total_paiements = count($paiements);
$total_montant = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiement")->fetchColumn();
$total_montant_mois = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiement WHERE MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE())")->fetchColumn();
$nb_inscriptions_non_payees = $db->query("
    SELECT COUNT(*) FROM inscription i
    WHERE (SELECT COALESCE(SUM(montant),0) FROM paiement WHERE id_inscription = i.id_inscription) < 
          (SELECT cout FROM formation WHERE id_formation = i.id_formation)
")->fetchColumn();
$paiements_par_mode = $db->query("
    SELECT mode_paiement, COUNT(*) as nb, SUM(montant) as total
    FROM paiement GROUP BY mode_paiement ORDER BY total DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    :root {
        --primary: #667eea; --primary-dark: #764ba2;
        --success: #27ae60; --warning: #f39c12; --danger: #e74c3c;
        --info: #3498db; --bg: #f0f2f5; --white: #ffffff;
        --text: #2c3e50; --text-light: #7f8c8d; --border: #e0e0e0;
        --radius: 12px; --shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
    }
    .page-header h1 { font-size: 26px; color: var(--text); display: flex; align-items: center; gap: 10px; }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px; margin-bottom: 25px;
    }
    .stat-card {
        background: var(--white); padding: 20px; border-radius: var(--radius);
        display: flex; align-items: center; gap: 15px;
        box-shadow: var(--shadow); border: 1px solid #f0f0f0; transition: transform 0.3s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon {
        width: 50px; height: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0;
    }
    .stat-icon.green { background: #e8f5e9; color: var(--success); }
    .stat-icon.blue { background: #e3f2fd; color: var(--info); }
    .stat-icon.orange { background: #fff3e0; color: var(--warning); }
    .stat-icon.red { background: #fce4ec; color: var(--danger); }
    .stat-val { font-size: 22px; font-weight: bold; color: var(--text); }
    .stat-lbl { font-size: 13px; color: var(--text-light); }

    .grid-layout {
        display: grid; grid-template-columns: 420px 1fr; gap: 20px;
    }
    .card {
        background: var(--white); padding: 25px; border-radius: var(--radius);
        box-shadow: var(--shadow); border: 1px solid #f0f0f0;
    }
    .card.sticky { position: sticky; top: 20px; height: fit-content; }
    .card h3 {
        font-size: 18px; color: var(--text); margin-bottom: 20px;
        padding-bottom: 15px; border-bottom: 2px solid #f0f2f5;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }

    .form-grid { display: grid; gap: 15px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 12px; font-weight: 600; color: var(--text); text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group label .required { color: var(--danger); }
    .form-group input, .form-group select {
        padding: 10px 12px; border: 2px solid var(--border); border-radius: 8px;
        font-size: 14px; transition: all 0.3s; background: #fafafa;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; border-color: var(--primary); background: white;
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

    .btn {
        padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
        font-size: 14px; font-weight: 500; transition: all 0.3s;
        display: flex; align-items: center; gap: 6px; justify-content: center;
    }
    .btn-save { background: linear-gradient(135deg, #27ae60, #2ecc71); color: white; flex: 1; }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(39,174,96,0.3); }
    .btn-cancel { background: #f0f0f0; color: #666; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    .btn-group { display: flex; gap: 8px; }

    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .search-box { position: relative; flex: 1; min-width: 180px; }
    .search-box input { width: 100%; padding: 10px 12px 10px 38px; border: 2px solid var(--border); border-radius: 8px; font-size: 14px; }
    .search-box .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
    .filter-select { padding: 10px 12px; border: 2px solid var(--border); border-radius: 8px; font-size: 14px; background: white; cursor: pointer; }

    .table-container { overflow-x: auto; }
    .table-modern { width: 100%; border-collapse: collapse; font-size: 14px; }
    .table-modern thead th {
        background: #f8f9fa; padding: 14px 15px; text-align: left;
        font-size: 12px; text-transform: uppercase; color: var(--text-light);
        font-weight: 600; letter-spacing: 0.5px; white-space: nowrap; border-bottom: 2px solid var(--border);
    }
    .table-modern tbody td { padding: 14px 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .table-modern tbody tr:hover { background: #f8f9ff; }

    .montant-positif { color: var(--success); font-weight: bold; font-size: 16px; }
    .solde-zero { color: var(--success); font-weight: bold; }
    .solde-restant { color: var(--danger); font-weight: bold; }
    .badge-mode {
        display: inline-block; padding: 4px 10px; border-radius: 20px;
        font-size: 12px; font-weight: 500;
    }
    .mode-especes { background: #e8f5e9; color: #2e7d32; }
    .mode-virement { background: #e3f2fd; color: #1565c0; }
    .mode-mobile { background: #f3e5f5; color: #6a1b9a; }
    .mode-cheque { background: #fff3e0; color: #e65100; }
    .text-muted { color: var(--text-light); font-style: italic; }
    .empty-state { text-align: center; padding: 40px; color: var(--text-light); }
    .empty-icon { font-size: 48px; margin-bottom: 10px; }

    /* Barre de progression */
    .progress-container { margin-top: 8px; }
    .progress-bar {
        width: 100%; height: 6px; background: #f0f0f0; border-radius: 3px; overflow: hidden;
    }
    .progress-fill {
        height: 100%; border-radius: 3px; transition: width 0.8s ease;
    }

    /* Quick info */
    .quick-info {
        background: #f0f4ff; padding: 12px 15px; border-radius: 8px;
        font-size: 13px; color: var(--text); margin-top: 15px;
    }
    .quick-info strong { color: var(--primary); }

    @media (max-width: 1100px) {
        .grid-layout { grid-template-columns: 1fr; }
        .card.sticky { position: static; }
    }
    @media (max-width: 600px) {
        .form-row { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
        .toolbar { flex-direction: column; }
    }
    @media (max-width: 400px) {
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <h1>💰 Gestion des Paiements</h1>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div><div class="stat-val"><?php echo number_format($total_montant, 0, ',', ' '); ?> FC</div><div class="stat-lbl">Total perçu</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📅</div>
        <div><div class="stat-val"><?php echo number_format($total_montant_mois, 0, ',', ' '); ?> FC</div><div class="stat-lbl">Ce mois</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📝</div>
        <div><div class="stat-val"><?php echo $total_paiements; ?></div><div class="stat-lbl">Transactions</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div><div class="stat-val"><?php echo $nb_inscriptions_non_payees; ?></div><div class="stat-lbl">Soldes impayés</div></div>
    </div>
</div>

<?php if ($message): ?>
    <div style="background:#d4edda; color:#155724; padding:12px 20px; border-radius:8px; margin-bottom:20px; border-left:4px solid #28a745;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#f8d7da; color:#721c24; padding:12px 20px; border-radius:8px; margin-bottom:20px; border-left:4px solid #dc3545;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid-layout">
    
    <!-- ============================================ -->
    <!-- FORMULAIRE -->
    <!-- ============================================ -->
    <div class="card sticky">
        <h3><?php echo $paiement_edit ? '✏️ Modifier le paiement' : '➕ Nouveau paiement'; ?></h3>
        <form method="POST" class="form-grid" id="paiementForm">
            <?php if ($paiement_edit): ?>
                <input type="hidden" name="id_paiement" value="<?php echo $paiement_edit['id_paiement']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Inscription <span class="required">*</span></label>
                <select name="id_inscription" required id="selectInscription" onchange="updateInfoInscription()">
                    <option value="">-- Sélectionner une inscription --</option>
                    <?php foreach ($inscriptions_list as $ins): 
                        $reste = $ins['cout'] - $ins['total_paye'];
                        $selected = '';
                        if ($paiement_edit && $paiement_edit['id_inscription'] == $ins['id_inscription']) $selected = 'selected';
                        elseif (!$paiement_edit && $inscription_preselectionnee == $ins['id_inscription']) $selected = 'selected';
                    ?>
                        <option value="<?php echo $ins['id_inscription']; ?>" 
                            data-cout="<?php echo $ins['cout']; ?>"
                            data-paye="<?php echo $ins['total_paye']; ?>"
                            data-reste="<?php echo $reste; ?>"
                            <?php echo $selected; ?>>
                            <?php echo htmlspecialchars($ins['nom'] . ' ' . $ins['prenom'] . ' - ' . $ins['nom_formation']); ?> 
                            (Reste: <?php echo number_format($reste, 0, ',', ' '); ?> FC)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="infoInscription" class="quick-info" style="display:none;"></div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Montant (FC) <span class="required">*</span></label>
                    <input type="number" name="montant" id="montantInput" placeholder="Ex: 100000" step="0.01" min="1" required
                           value="<?php echo $paiement_edit ? $paiement_edit['montant'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Date <span class="required">*</span></label>
                    <input type="date" name="date_paiement" required
                           value="<?php echo $paiement_edit ? $paiement_edit['date_paiement'] : date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Mode de paiement</label>
                <select name="mode_paiement">
                    <option value="Espèces" <?php echo ($paiement_edit && $paiement_edit['mode_paiement'] == 'Espèces') ? 'selected' : ''; ?>>💵 Espèces</option>
                    <option value="Virement bancaire" <?php echo ($paiement_edit && $paiement_edit['mode_paiement'] == 'Virement bancaire') ? 'selected' : ''; ?>>🏦 Virement bancaire</option>
                    <option value="Mobile Money" <?php echo ($paiement_edit && $paiement_edit['mode_paiement'] == 'Mobile Money') ? 'selected' : ''; ?>>📱 Mobile Money</option>
                    <option value="Chèque" <?php echo ($paiement_edit && $paiement_edit['mode_paiement'] == 'Chèque') ? 'selected' : ''; ?>>📝 Chèque</option>
                    <option value="Carte bancaire" <?php echo ($paiement_edit && $paiement_edit['mode_paiement'] == 'Carte bancaire') ? 'selected' : ''; ?>>💳 Carte bancaire</option>
                </select>
            </div>
            
            <div class="btn-group">
                <?php if ($paiement_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="paiements.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">💰 Enregistrer le paiement</button>
                <?php endif; ?>
            </div>
        </form>
        
        <!-- Répartition par mode -->
        <?php if (count($paiements_par_mode) > 0 && !$paiement_edit): ?>
        <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
            <h4 style="font-size: 14px; color: var(--text); margin-bottom: 12px;">📊 Répartition par mode</h4>
            <?php foreach ($paiements_par_mode as $pm): 
                $pct = $total_montant > 0 ? round(($pm['total'] / $total_montant) * 100) : 0;
            ?>
            <div style="margin-bottom: 10px;">
                <div style="display:flex; justify-content:space-between; font-size:12px; margin-bottom:3px;">
                    <span><?php echo htmlspecialchars($pm['mode_paiement']); ?></span>
                    <span><strong><?php echo number_format($pm['total'], 0, ',', ' '); ?> FC</strong> (<?php echo $pct; ?>%)</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width:<?php echo $pct; ?>%; background: linear-gradient(90deg, #667eea, #764ba2);"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ============================================ -->
    <!-- LISTE DES PAIEMENTS -->
    <!-- ============================================ -->
    <div class="card">
        <h3>
            <span>📋 Historique des paiements</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <select name="mode" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous modes</option>
                        <?php foreach ($modes_paiement as $md): ?>
                            <option value="<?php echo htmlspecialchars($md); ?>" <?php echo $filter_mode==$md?'selected':''; ?>><?php echo htmlspecialchars($md); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="date" name="date_debut" class="filter-select" value="<?php echo $date_debut; ?>" onchange="document.getElementById('filterForm').submit()" title="Date début">
                    <input type="date" name="date_fin" class="filter-select" value="<?php echo $date_fin; ?>" onchange="document.getElementById('filterForm').submit()" title="Date fin">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </h3>
        
        <div class="table-container">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Apprenant</th>
                        <th>Formation</th>
                        <th>Date</th>
                        <th>Montant</th>
                        <th>Mode</th>
                        <th>Solde</th>
                        <th>Progression</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($paiements) > 0): ?>
                        <?php foreach ($paiements as $p): 
                            $pct_paye = $p['cout_formation'] > 0 ? round((($p['cout_formation'] - $p['solde_restant']) / $p['cout_formation']) * 100) : 100;
                            $mode_class = '';
                            switch($p['mode_paiement']) {
                                case 'Espèces': $mode_class = 'mode-especes'; break;
                                case 'Virement bancaire': $mode_class = 'mode-virement'; break;
                                case 'Mobile Money': $mode_class = 'mode-mobile'; break;
                                case 'Chèque': $mode_class = 'mode-cheque'; break;
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo $p['id_paiement']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($p['nom'] . ' ' . $p['prenom']); ?></strong>
                                <?php if ($p['postnom']): ?><br><small class="text-muted"><?php echo htmlspecialchars($p['postnom']); ?></small><?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($p['nom_formation']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['date_paiement'])); ?></td>
                            <td class="montant-positif">+<?php echo number_format($p['montant'], 0, ',', ' '); ?> FC</td>
                            <td><span class="badge-mode <?php echo $mode_class; ?>"><?php echo htmlspecialchars($p['mode_paiement']); ?></span></td>
                            <td class="<?php echo $p['solde_restant'] <= 0 ? 'solde-zero' : 'solde-restant'; ?>">
                                <?php echo number_format($p['solde_restant'], 0, ',', ' '); ?> FC
                            </td>
                            <td>
                                <div class="progress-container">
                                    <div class="progress-bar">
                                        <div class="progress-fill" style="width:<?php echo $pct_paye; ?>%; background: <?php echo $pct_paye >= 100 ? '#27ae60' : '#f39c12'; ?>;"></div>
                                    </div>
                                    <small style="font-size:11px; color:var(--text-light);"><?php echo $pct_paye; ?>%</small>
                                </div>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <a href="?modifier=<?php echo $p['id_paiement']; ?>" class="btn btn-sm" style="background:#f39c12; color:white; text-decoration:none;">✏️</a>
                                    <a href="?supprimer=<?php echo $p['id_paiement']; ?>" class="btn btn-sm" style="background:#e74c3c; color:white; text-decoration:none;" onclick="return confirm('Supprimer ce paiement ?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">💰</div><p>Aucun paiement trouvé</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($paiements) > 0): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:15px; font-size:13px; color:var(--text-light); flex-wrap:wrap; gap:10px;">
            <span><?php echo count($paiements); ?> transaction(s)</span>
            <span>Total affiché : <strong style="color:var(--success);"><?php echo number_format(array_sum(array_column($paiements, 'montant')), 0, ',', ' '); ?> FC</strong></span>
        </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
// Recherche
let searchTimeout;
document.getElementById('searchInput').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    const val = this.value;
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location.href);
        val ? url.searchParams.set('search', val) : url.searchParams.delete('search');
        window.location.href = url.toString();
    }, 500);
});

// Afficher info inscription
function updateInfoInscription() {
    const select = document.getElementById('selectInscription');
    const option = select.options[select.selectedIndex];
    const infoDiv = document.getElementById('infoInscription');
    const montantInput = document.getElementById('montantInput');
    
    if (option.value && option.getAttribute('data-cout')) {
        const cout = parseFloat(option.getAttribute('data-cout'));
        const paye = parseFloat(option.getAttribute('data-paye'));
        const reste = parseFloat(option.getAttribute('data-reste'));
        
        infoDiv.style.display = 'block';
        infoDiv.innerHTML = `
            💰 <strong>Coût total :</strong> ${new Intl.NumberFormat('fr-FR').format(cout)} FC<br>
            ✅ <strong>Déjà payé :</strong> ${new Intl.NumberFormat('fr-FR').format(paye)} FC<br>
            ⚠️ <strong>Reste à payer :</strong> ${new Intl.NumberFormat('fr-FR').format(reste)} FC
        `;
        
        if (!montantInput.value || parseFloat(montantInput.value) > reste) {
            montantInput.value = reste > 0 ? reste : '';
        }
        montantInput.max = reste;
    } else {
        infoDiv.style.display = 'none';
        montantInput.max = '';
    }
}

// Au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateInfoInscription();
    
    // Messages auto-disparition
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.opacity='0'; m.style.transition='all 0.5s'; setTimeout(()=>m.remove(),500); }, 5000));
});
</script>

</div>
</body>
</html>