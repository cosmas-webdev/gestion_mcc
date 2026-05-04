<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$session_edit = null;

// ============================================
// CREATE - Ajouter une session
// ============================================
if (isset($_POST['ajouter'])) {
    $id_formation = intval($_POST['id_formation']);
    $id_formateur = intval($_POST['id_formateur']);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $horaire = trim($_POST['horaire']);
    $salle = trim($_POST['salle']);
    
    if (empty($id_formation) || empty($id_formateur) || empty($date_debut) || empty($date_fin)) {
        $error = "⚠️ Veuillez remplir tous les champs obligatoires.";
    } elseif (strtotime($date_fin) < strtotime($date_debut)) {
        $error = "⚠️ La date de fin doit être postérieure à la date de début.";
    } else {
        // Vérifier les conflits de salle et formateur
        $conflit = $db->prepare("
            SELECT COUNT(*) FROM session 
            WHERE salle = :salle 
            AND ((date_debut BETWEEN :debut AND :fin) OR (date_fin BETWEEN :debut AND :fin))
        ");
        $conflit->execute([':salle' => $salle, ':debut' => $date_debut, ':fin' => $date_fin]);
        
        if ($conflit->fetchColumn() > 0 && !empty($salle)) {
            $error = "⚠️ Conflit : la salle {$salle} est déjà occupée sur cette période.";
        } else {
            try {
                $query = "INSERT INTO session (date_debut, date_fin, horaire, salle, id_formation, id_formateur) 
                          VALUES (:debut, :fin, :horaire, :salle, :formation, :formateur)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':debut' => $date_debut,
                    ':fin' => $date_fin,
                    ':horaire' => $horaire,
                    ':salle' => $salle,
                    ':formation' => $id_formation,
                    ':formateur' => $id_formateur
                ]);
                $message = "✅ Session ajoutée avec succès !";
            } catch (PDOException $e) {
                $error = "❌ Erreur : " . $e->getMessage();
            }
        }
    }
}

// ============================================
// UPDATE - Modifier une session
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_session']);
    $id_formation = intval($_POST['id_formation']);
    $id_formateur = intval($_POST['id_formateur']);
    $date_debut = $_POST['date_debut'];
    $date_fin = $_POST['date_fin'];
    $horaire = trim($_POST['horaire']);
    $salle = trim($_POST['salle']);
    
    if (strtotime($date_fin) < strtotime($date_debut)) {
        $error = "⚠️ La date de fin doit être postérieure à la date de début.";
    } else {
        try {
            $query = "UPDATE session SET date_debut=:debut, date_fin=:fin, horaire=:horaire, 
                      salle=:salle, id_formation=:formation, id_formateur=:formateur 
                      WHERE id_session=:id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':debut' => $date_debut,
                ':fin' => $date_fin,
                ':horaire' => $horaire,
                ':salle' => $salle,
                ':formation' => $id_formation,
                ':formateur' => $id_formateur
            ]);
            $message = "✅ Session modifiée avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

// ============================================
// DELETE - Supprimer une session
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    try {
        $stmt = $db->prepare("DELETE FROM session WHERE id_session = :id");
        $stmt->execute([':id' => $id]);
        $message = "✅ Session supprimée avec succès !";
    } catch (PDOException $e) {
        $error = "❌ Erreur : " . $e->getMessage();
    }
}

// ============================================
// EDIT - Charger pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM session WHERE id_session = :id");
    $stmt->execute([':id' => $id]);
    $session_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer toutes les sessions
// ============================================
$search = $_GET['search'] ?? '';
$filter_formation = $_GET['formation'] ?? '';
$filter_statut = $_GET['statut'] ?? '';

$aujourdhui = date('Y-m-d');

$query = "
    SELECT s.*, 
           f.nom_formation, f.niveau as niveau_formation,
           fo.nom as nom_formateur,
           CASE 
               WHEN s.date_fin < :today THEN 'terminée'
               WHEN s.date_debut <= :today2 AND s.date_fin >= :today3 THEN 'en_cours'
               ELSE 'a_venir'
           END as statut_session,
           DATEDIFF(s.date_fin, s.date_debut) as duree_jours
    FROM session s
    JOIN formation f ON s.id_formation = f.id_formation
    JOIN formateur fo ON s.id_formateur = fo.id_formateur
    WHERE 1=1
";

$params = [
    ':today' => $aujourdhui,
    ':today2' => $aujourdhui,
    ':today3' => $aujourdhui
];

if (!empty($search)) {
    $query .= " AND (f.nom_formation LIKE :s1 OR fo.nom LIKE :s2 OR s.salle LIKE :s3)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
}

if (!empty($filter_formation)) {
    $query .= " AND s.id_formation = :form";
    $params[':form'] = $filter_formation;
}

if (!empty($filter_statut)) {
    if ($filter_statut == 'en_cours') {
        $query .= " AND s.date_debut <= :now1 AND s.date_fin >= :now2";
        $params[':now1'] = $aujourdhui;
        $params[':now2'] = $aujourdhui;
    } elseif ($filter_statut == 'a_venir') {
        $query .= " AND s.date_debut > :now";
        $params[':now'] = $aujourdhui;
    } elseif ($filter_statut == 'terminee') {
        $query .= " AND s.date_fin < :now";
        $params[':now'] = $aujourdhui;
    }
}

$query .= " ORDER BY s.date_debut ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour les selects
$formations = $db->query("SELECT id_formation, nom_formation FROM formation ORDER BY nom_formation")->fetchAll(PDO::FETCH_ASSOC);
$formateurs = $db->query("SELECT id_formateur, nom FROM formateur ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$formations_filtre = $db->query("SELECT DISTINCT f.id_formation, f.nom_formation FROM formation f JOIN session s ON f.id_formation = s.id_formation ORDER BY f.nom_formation")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_sessions = count($sessions);
$sessions_en_cours = $db->prepare("SELECT COUNT(*) FROM session WHERE date_debut <= ? AND date_fin >= ?");
$sessions_en_cours->execute([$aujourdhui, $aujourdhui]);
$nb_en_cours = $sessions_en_cours->fetchColumn();
$nb_a_venir = $db->prepare("SELECT COUNT(*) FROM session WHERE date_debut > ?")->execute([$aujourdhui]) ? $db->query("SELECT COUNT(*) FROM session WHERE date_debut > '$aujourdhui'")->fetchColumn() : 0;
$nb_terminees = $db->prepare("SELECT COUNT(*) FROM session WHERE date_fin < ?")->execute([$aujourdhui]) ? $db->query("SELECT COUNT(*) FROM session WHERE date_fin < '$aujourdhui'")->fetchColumn() : 0;
?>

<style>
    :root {
        --primary: #667eea;
        --primary-dark: #764ba2;
        --success: #27ae60;
        --warning: #f39c12;
        --danger: #e74c3c;
        --info: #3498db;
        --bg: #f0f2f5;
        --white: #ffffff;
        --text: #2c3e50;
        --text-light: #7f8c8d;
        --border: #e0e0e0;
        --radius: 12px;
        --shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
    }
    .page-header h1 { font-size: 26px; color: var(--text); display: flex; align-items: center; gap: 10px; }

    /* Grille de statistiques */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }
    .stat-card {
        background: var(--white); padding: 20px; border-radius: var(--radius);
        display: flex; align-items: center; gap: 15px;
        box-shadow: var(--shadow); border: 1px solid #f0f0f0;
        transition: transform 0.3s; cursor: pointer;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-card .stat-icon {
        width: 50px; height: 50px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 22px; flex-shrink: 0;
    }
    .stat-card .stat-icon.blue { background: #e3f2fd; color: var(--info); }
    .stat-card .stat-icon.green { background: #e8f5e9; color: var(--success); }
    .stat-card .stat-icon.orange { background: #fff3e0; color: var(--warning); }
    .stat-card .stat-icon.purple { background: #f3e5f5; color: #8e44ad; }
    .stat-card .stat-val { font-size: 24px; font-weight: bold; color: var(--text); }
    .stat-card .stat-lbl { font-size: 13px; color: var(--text-light); }

    /* Layout principal */
    .grid-layout {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 20px;
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
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 12px; font-weight: 600; color: var(--text); text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group label .required { color: var(--danger); }
    .form-group input, .form-group select {
        padding: 10px 12px; border: 2px solid var(--border); border-radius: 8px;
        font-size: 14px; transition: all 0.3s; background: #fafafa;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; border-color: var(--primary); background: var(--white);
        box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }

    .btn {
        padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
        font-size: 14px; font-weight: 500; transition: all 0.3s;
        display: flex; align-items: center; gap: 6px; justify-content: center;
    }
    .btn-save { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; flex: 1; }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
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

    .badge {
        display: inline-block; padding: 4px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 500;
    }
    .badge-en-cours { background: #d4edda; color: #155724; }
    .badge-a-venir { background: #e3f2fd; color: #0d47a1; }
    .badge-terminee { background: #f5f5f5; color: #616161; }

    .text-muted { color: var(--text-light); }
    .text-success { color: var(--success); }
    .empty-state { text-align: center; padding: 40px; color: var(--text-light); }
    .empty-icon { font-size: 48px; margin-bottom: 10px; }

    /* Mode calendrier */
    .calendar-view {
        display: none;
        grid-template-columns: repeat(7, 1fr);
        gap: 4px;
    }
    .calendar-view.active { display: grid; }
    .calendar-day {
        background: white; border-radius: 4px; padding: 8px; min-height: 70px;
        border: 1px solid #f0f0f0; font-size: 12px; cursor: pointer; transition: all 0.2s;
    }
    .calendar-day:hover { background: #f0f0ff; }
    .calendar-day.has-session { border-left: 3px solid var(--primary); background: #f8f9ff; }
    .calendar-day.today { background: #e8eaf6; font-weight: bold; }
    .calendar-day .session-dot {
        display: block; padding: 2px 5px; margin-top: 3px; border-radius: 3px;
        font-size: 10px; background: var(--primary); color: white; overflow: hidden; white-space: nowrap;
    }

    .view-toggle { display: flex; gap: 5px; }
    .view-toggle button {
        padding: 8px 15px; border: 2px solid var(--border); background: white;
        border-radius: 6px; cursor: pointer; font-size: 13px; transition: all 0.3s;
    }
    .view-toggle button.active { background: var(--primary); color: white; border-color: var(--primary); }

    @media (max-width: 1100px) {
        .grid-layout { grid-template-columns: 1fr; }
        .card.sticky { position: static; }
    }
    @media (max-width: 768px) {
        .form-row { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .calendar-view { grid-template-columns: repeat(7, 1fr); font-size: 10px; }
    }
    @media (max-width: 480px) {
        .stats-grid { grid-template-columns: 1fr; }
        .toolbar { flex-direction: column; }
        .toolbar .search-box, .toolbar .filter-select { width: 100%; }
    }
</style>

<div class="page-header">
    <h1>📅 Gestion des Sessions</h1>
    <div class="view-toggle">
        <button class="active" onclick="switchView('table')" id="btnTable">📋 Tableau</button>
        <button onclick="switchView('calendar')" id="btnCalendar">📆 Calendrier</button>
    </div>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card" onclick="window.location='?statut=en_cours'">
        <div class="stat-icon green">🟢</div>
        <div><div class="stat-val"><?php echo $nb_en_cours; ?></div><div class="stat-lbl">En cours</div></div>
    </div>
    <div class="stat-card" onclick="window.location='?statut=a_venir'">
        <div class="stat-icon blue">🔵</div>
        <div><div class="stat-val"><?php echo $nb_a_venir; ?></div><div class="stat-lbl">À venir</div></div>
    </div>
    <div class="stat-card" onclick="window.location='?statut=terminee'">
        <div class="stat-icon purple">⚪</div>
        <div><div class="stat-val"><?php echo $nb_terminees; ?></div><div class="stat-lbl">Terminées</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📊</div>
        <div><div class="stat-val"><?php echo $total_sessions; ?></div><div class="stat-lbl">Total sessions</div></div>
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
        <h3><?php echo $session_edit ? '✏️ Modifier la session' : '➕ Nouvelle session'; ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($session_edit): ?>
                <input type="hidden" name="id_session" value="<?php echo $session_edit['id_session']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Formation <span class="required">*</span></label>
                <select name="id_formation" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($formations as $f): ?>
                        <option value="<?php echo $f['id_formation']; ?>" <?php echo ($session_edit && $session_edit['id_formation'] == $f['id_formation']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($f['nom_formation']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Formateur <span class="required">*</span></label>
                <select name="id_formateur" required>
                    <option value="">-- Choisir --</option>
                    <?php foreach ($formateurs as $form): ?>
                        <option value="<?php echo $form['id_formateur']; ?>" <?php echo ($session_edit && $session_edit['id_formateur'] == $form['id_formateur']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($form['nom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date début <span class="required">*</span></label>
                    <input type="date" name="date_debut" required value="<?php echo $session_edit ? $session_edit['date_debut'] : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Date fin <span class="required">*</span></label>
                    <input type="date" name="date_fin" required value="<?php echo $session_edit ? $session_edit['date_fin'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Horaire</label>
                    <input type="text" name="horaire" placeholder="Ex: 08h00 - 12h00" value="<?php echo $session_edit ? htmlspecialchars($session_edit['horaire']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Salle</label>
                    <input type="text" name="salle" placeholder="Ex: Salle A101" value="<?php echo $session_edit ? htmlspecialchars($session_edit['salle']) : ''; ?>">
                </div>
            </div>
            
            <div class="btn-group">
                <?php if ($session_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="sessions.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Planifier la session</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- ============================================ -->
    <!-- LISTE DES SESSIONS -->
    <!-- ============================================ -->
    <div class="card">
        <h3>
            <span>📋 Sessions planifiées</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display:flex; gap:8px;">
                    <select name="statut" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous</option>
                        <option value="en_cours" <?php echo $filter_statut=='en_cours'?'selected':''; ?>>En cours</option>
                        <option value="a_venir" <?php echo $filter_statut=='a_venir'?'selected':''; ?>>À venir</option>
                        <option value="terminee" <?php echo $filter_statut=='terminee'?'selected':''; ?>>Terminées</option>
                    </select>
                    <select name="formation" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Toutes formations</option>
                        <?php foreach ($formations_filtre as $ff): ?>
                            <option value="<?php echo $ff['id_formation']; ?>" <?php echo $filter_formation==$ff['id_formation']?'selected':''; ?>>
                                <?php echo htmlspecialchars($ff['nom_formation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </h3>
        
        <!-- Vue Tableau -->
        <div id="tableView" class="table-container">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Formation</th>
                        <th>Formateur</th>
                        <th>Dates</th>
                        <th>Horaire</th>
                        <th>Salle</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($sessions) > 0): ?>
                        <?php foreach ($sessions as $s): 
                            $badge = '';
                            switch($s['statut_session']) {
                                case 'en_cours': $badge = 'badge-en-cours'; $txt = 'En cours'; break;
                                case 'a_venir': $badge = 'badge-a-venir'; $txt = 'À venir'; break;
                                default: $badge = 'badge-terminee'; $txt = 'Terminée';
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo $s['id_session']; ?></strong></td>
                            <td><strong><?php echo htmlspecialchars($s['nom_formation']); ?></strong></td>
                            <td><?php echo htmlspecialchars($s['nom_formateur']); ?></td>
                            <td>
                                📅 <?php echo date('d/m/Y', strtotime($s['date_debut'])); ?> → <?php echo date('d/m/Y', strtotime($s['date_fin'])); ?>
                                <br><small class="text-muted"><?php echo $s['duree_jours']; ?> jours</small>
                            </td>
                            <td><?php echo $s['horaire'] ? htmlspecialchars($s['horaire']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo $s['salle'] ? '🏫 '.htmlspecialchars($s['salle']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><span class="badge <?php echo $badge; ?>"><?php echo $txt; ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?modifier=<?php echo $s['id_session']; ?>" class="btn btn-sm" style="background:#f39c12; color:white; text-decoration:none;">✏️</a>
                                    <a href="?supprimer=<?php echo $s['id_session']; ?>" class="btn btn-sm" style="background:#e74c3c; color:white; text-decoration:none;" onclick="return confirm('Supprimer cette session ?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📅</div><p>Aucune session trouvée</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Vue Calendrier (mois courant) -->
        <div id="calendarView" class="calendar-view" style="display:none;">
            <?php
            $mois = date('m'); $annee = date('Y');
            $premier_jour = mktime(0,0,0,$mois,1,$annee);
            $nb_jours = date('t', $premier_jour);
            $jour_semaine = date('N', $premier_jour);
            
            // Jours vides avant le 1er
            for ($i = 1; $i < $jour_semaine; $i++) {
                echo '<div class="calendar-day" style="background:#fafafa;"></div>';
            }
            
            // Jours du mois
            for ($jour = 1; $jour <= $nb_jours; $jour++) {
                $date_jour = "$annee-$mois-" . str_pad($jour, 2, '0', STR_PAD_LEFT);
                $today_class = ($date_jour == $aujourdhui) ? ' today' : '';
                $has_session = false;
                $session_label = '';
                
                foreach ($sessions as $s) {
                    if ($date_jour >= $s['date_debut'] && $date_jour <= $s['date_fin']) {
                        $has_session = true;
                        $session_label = htmlspecialchars(substr($s['nom_formation'], 0, 15));
                    }
                }
                
                $session_class = $has_session ? ' has-session' : '';
                echo "<div class='calendar-day{$today_class}{$session_class}' title='{$date_jour}'>";
                echo "<strong>{$jour}</strong>";
                if ($has_session) echo "<span class='session-dot'>{$session_label}</span>";
                echo "</div>";
            }
            ?>
        </div>
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

// Switch vue Tableau / Calendrier
function switchView(view) {
    document.getElementById('tableView').style.display = view === 'table' ? '' : 'none';
    document.getElementById('calendarView').style.display = view === 'calendar' ? 'grid' : 'none';
    document.getElementById('btnTable').classList.toggle('active', view === 'table');
    document.getElementById('btnCalendar').classList.toggle('active', view === 'calendar');
}

// Messages auto-disparition
document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.opacity='0'; m.style.transition='all 0.5s'; setTimeout(()=>m.remove(),500); }, 5000));
});
</script>

</div>
</body>
</html>