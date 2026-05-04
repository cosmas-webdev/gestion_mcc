<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$inscription_edit = null;

// ============================================
// CREATE - Ajouter une inscription
// ============================================
if (isset($_POST['ajouter'])) {
    $id_apprenant = intval($_POST['id_apprenant']);
    $id_formation = intval($_POST['id_formation']);
    $date_inscription = $_POST['date_inscription'];
    $statut = $_POST['statut'];
    
    if (empty($id_apprenant) || empty($id_formation) || empty($date_inscription)) {
        $error = "⚠️ Veuillez remplir tous les champs obligatoires.";
    } else {
        $check = $db->prepare("SELECT COUNT(*) FROM inscription WHERE id_apprenant = :app AND id_formation = :form");
        $check->execute([':app' => $id_apprenant, ':form' => $id_formation]);
        
        if ($check->fetchColumn() > 0) {
            $error = "⚠️ Cet apprenant est déjà inscrit à cette formation.";
        } else {
            try {
                $query = "INSERT INTO inscription (date_inscription, statut, id_apprenant, id_formation) 
                          VALUES (:date, :statut, :apprenant, :formation)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':date' => $date_inscription,
                    ':statut' => $statut,
                    ':apprenant' => $id_apprenant,
                    ':formation' => $id_formation
                ]);
                $message = "✅ Inscription ajoutée avec succès !";
            } catch (PDOException $e) {
                $error = "❌ Erreur lors de l'ajout : " . $e->getMessage();
            }
        }
    }
}

// ============================================
// UPDATE - Modifier une inscription
// ============================================
if (isset($_POST['modifier'])) {
    $id_inscription = intval($_POST['id_inscription']);
    $id_apprenant = intval($_POST['id_apprenant']);
    $id_formation = intval($_POST['id_formation']);
    $date_inscription = $_POST['date_inscription'];
    $statut = $_POST['statut'];
    
    try {
        $query = "UPDATE inscription SET date_inscription=:date, statut=:statut, 
                  id_apprenant=:apprenant, id_formation=:formation 
                  WHERE id_inscription=:id";
        $stmt = $db->prepare($query);
        $stmt->execute([
            ':id' => $id_inscription,
            ':date' => $date_inscription,
            ':statut' => $statut,
            ':apprenant' => $id_apprenant,
            ':formation' => $id_formation
        ]);
        $message = "✅ Inscription modifiée avec succès !";
    } catch (PDOException $e) {
        $error = "❌ Erreur lors de la modification : " . $e->getMessage();
    }
}

// ============================================
// DELETE - Supprimer une inscription
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    
    $checkPaiement = $db->prepare("SELECT COUNT(*) FROM paiement WHERE id_inscription = :id");
    $checkPaiement->execute([':id' => $id]);
    
    $checkCertificat = $db->prepare("SELECT COUNT(*) FROM certificat WHERE id_inscription = :id");
    $checkCertificat->execute([':id' => $id]);
    
    if ($checkPaiement->fetchColumn() > 0) {
        $error = "⚠️ Impossible de supprimer : des paiements sont liés à cette inscription.";
    } elseif ($checkCertificat->fetchColumn() > 0) {
        $error = "⚠️ Impossible de supprimer : un certificat est lié à cette inscription.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM inscription WHERE id_inscription = :id");
            $stmt->execute([':id' => $id]);
            $message = "✅ Inscription supprimée avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// ============================================
// EDIT - Charger une inscription pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM inscription WHERE id_inscription = :id");
    $stmt->execute([':id' => $id]);
    $inscription_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// LIRE - Récupérer toutes les inscriptions
// ============================================
$search = $_GET['search'] ?? '';
$filter_statut = $_GET['statut'] ?? '';
$filter_formation = $_GET['formation'] ?? '';

$query = "
    SELECT i.*, 
           a.nom, a.postnom, a.prenom, a.telephone as tel_apprenant,
           f.nom_formation, f.cout,
           COALESCE(SUM(p.montant), 0) as total_paye,
           (f.cout - COALESCE(SUM(p.montant), 0)) as solde_restant
    FROM inscription i
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant
    JOIN formation f ON i.id_formation = f.id_formation
    LEFT JOIN paiement p ON i.id_inscription = p.id_inscription
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (a.nom LIKE :search1 OR a.prenom LIKE :search2 OR a.postnom LIKE :search3 OR f.nom_formation LIKE :search4)";
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
    $params[':search4'] = "%{$search}%";
}

if (!empty($filter_statut)) {
    $query .= " AND i.statut = :statut";
    $params[':statut'] = $filter_statut;
}

if (!empty($filter_formation)) {
    $query .= " AND i.id_formation = :formation";
    $params[':formation'] = $filter_formation;
}

$query .= " GROUP BY i.id_inscription ORDER BY i.date_inscription DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$inscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Données pour les listes déroulantes
$apprenants = $db->query("SELECT id_apprenant, nom, postnom, prenom FROM apprenant ORDER BY nom, postnom")->fetchAll(PDO::FETCH_ASSOC);
$formations = $db->query("SELECT id_formation, nom_formation, cout FROM formation ORDER BY nom_formation")->fetchAll(PDO::FETCH_ASSOC);
$formations_filtre = $db->query("SELECT DISTINCT f.id_formation, f.nom_formation FROM formation f JOIN inscription i ON f.id_formation = i.id_formation ORDER BY f.nom_formation")->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_inscriptions = count($inscriptions);
$total_actives = $db->query("SELECT COUNT(*) FROM inscription WHERE statut IN ('Actif', 'En cours')")->fetchColumn();
$total_terminees = $db->query("SELECT COUNT(*) FROM inscription WHERE statut IN ('Terminé', 'Termine')")->fetchColumn();
$total_revenus = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiement")->fetchColumn();

// Fonction pour limiter le prix max à 200$
function limiterPrix($prix) {
    return min(floatval($prix), 200.00);
}
?>

<style>
    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
    }
    .page-header h1 { font-size: 26px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
    .stats-bar {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px; margin-bottom: 25px;
    }
    .stat-item {
        background: white; padding: 18px 20px; border-radius: 10px;
        display: flex; align-items: center; gap: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;
    }
    .stat-icon {
        width: 45px; height: 45px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        font-size: 20px; flex-shrink: 0;
    }
    .stat-icon.blue { background: #e3f2fd; color: #2980b9; }
    .stat-icon.green { background: #e8f5e9; color: #27ae60; }
    .stat-icon.orange { background: #fff3e0; color: #f39c12; }
    .stat-icon.purple { background: #f3e5f5; color: #8e44ad; }
    .stat-content .stat-value { font-size: 22px; font-weight: bold; color: #2c3e50; }
    .stat-content .stat-label { font-size: 13px; color: #7f8c8d; }

    .grid-layout { display: grid; grid-template-columns: 380px 1fr; gap: 20px; }
    @media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }

    .card {
        background: white; padding: 25px; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;
    }
    .card.sticky { position: sticky; top: 20px; height: fit-content; }
    .card h3 {
        font-size: 18px; color: #2c3e50; margin-bottom: 20px;
        padding-bottom: 15px; border-bottom: 2px solid #f0f2f5;
        display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    }
    .form-grid { display: grid; gap: 15px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 13px; font-weight: 600; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group label .required { color: #e74c3c; }
    .form-group input, .form-group select {
        padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px;
        font-size: 14px; transition: all 0.3s ease;
    }
    .form-group input:focus, .form-group select:focus {
        outline: none; border-color: #667eea; box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
    }
    .btn {
        padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer;
        font-size: 14px; font-weight: 500; transition: all 0.3s ease;
        display: flex; align-items: center; gap: 6px; justify-content: center;
    }
    .btn-save { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; flex: 1; }
    .btn-save:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(102,126,234,0.3); }
    .btn-cancel { background: #f0f0f0; color: #666; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    .btn-group { display: flex; gap: 8px; }

    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .search-box { position: relative; flex: 1; min-width: 180px; }
    .search-box input { width: 100%; padding: 10px 12px 10px 38px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
    .search-box .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); }
    .filter-select { padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; background: white; cursor: pointer; }

    .table-container { overflow-x: auto; }
    .table-modern { width: 100%; border-collapse: collapse; font-size: 14px; }
    .table-modern thead th {
        background: #f8f9fa; padding: 14px 15px; text-align: left;
        font-size: 12px; text-transform: uppercase; color: #7f8c8d;
        font-weight: 600; letter-spacing: 0.5px; white-space: nowrap; border-bottom: 2px solid #e0e0e0;
    }
    .table-modern tbody td { padding: 14px 15px; border-bottom: 1px solid #f0f0f0; vertical-align: middle; }
    .table-modern tbody tr:hover { background: #f8f9ff; }

    .status-badge {
        padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 500; display: inline-block;
    }
    .status-actif { background: #d4edda; color: #155724; }
    .status-termine { background: #d1ecf1; color: #0c5460; }
    .status-en-attente { background: #fff3cd; color: #856404; }
    .status-abandonne { background: #f8d7da; color: #721c24; }
    .solde-ok { color: #27ae60; font-weight: bold; }
    .solde-ko { color: #e74c3c; font-weight: bold; }
    .text-muted { color: #95a5a6; }
    .empty-state { text-align: center; padding: 40px; color: #95a5a6; }
    .empty-icon { font-size: 48px; margin-bottom: 10px; }
    .info-formation { font-size: 12px; color: #7f8c8d; margin-top: 3px; }
    .info-prix-max { display: inline-block; padding: 2px 8px; background: #fff3e0; color: #e65100; border-radius: 4px; font-size: 11px; margin-left: 5px; }
</style>

<div class="page-header">
    <h1>📝 Gestion des Inscriptions</h1>
</div>

<div class="stats-bar">
    <div class="stat-item">
        <div class="stat-icon blue">📝</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $total_inscriptions; ?></div>
            <div class="stat-label">Total inscriptions</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon green">✅</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $total_actives; ?></div>
            <div class="stat-label">Inscriptions actives</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon orange">🎓</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo $total_terminees; ?></div>
            <div class="stat-label">Formations terminées</div>
        </div>
    </div>
    <div class="stat-item">
        <div class="stat-icon purple">💵</div>
        <div class="stat-content">
            <div class="stat-value"><?php echo number_format($total_revenus, 2, '.', ','); ?> $</div>
            <div class="stat-label">Revenus totaux</div>
        </div>
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
    
    <!-- FORMULAIRE -->
    <div class="card sticky">
        <h3><?php echo $inscription_edit ? '✏️ Modifier l\'inscription' : '➕ Nouvelle inscription'; ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($inscription_edit): ?>
                <input type="hidden" name="id_inscription" value="<?php echo $inscription_edit['id_inscription']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Apprenant <span class="required">*</span></label>
                <select name="id_apprenant" required id="selectApprenant">
                    <option value="">-- Sélectionner un apprenant --</option>
                    <?php foreach ($apprenants as $app): ?>
                        <option value="<?php echo $app['id_apprenant']; ?>" 
                            <?php echo ($inscription_edit && $inscription_edit['id_apprenant'] == $app['id_apprenant']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($app['nom'] . ' ' . ($app['postnom'] ? $app['postnom'] . ' ' : '') . $app['prenom']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Formation <span class="required">*</span> <span class="info-prix-max">max 200$</span></label>
                <select name="id_formation" required id="selectFormation" onchange="updateCout()">
                    <option value="">-- Sélectionner une formation --</option>
                    <?php foreach ($formations as $form): 
                        $prix_affiche = limiterPrix($form['cout']);
                    ?>
                        <option value="<?php echo $form['id_formation']; ?>" 
                            data-cout="<?php echo $prix_affiche; ?>"
                            <?php echo ($inscription_edit && $inscription_edit['id_formation'] == $form['id_formation']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($form['nom_formation']); ?> (<?php echo number_format($prix_affiche, 2, '.', ','); ?> $)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="coutInfo" class="info-formation"></div>
            </div>
            
            <div class="form-group">
                <label>Date d'inscription <span class="required">*</span></label>
                <input type="date" name="date_inscription" required 
                       value="<?php echo $inscription_edit ? $inscription_edit['date_inscription'] : date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label>Statut</label>
                <select name="statut">
                    <option value="En cours" <?php echo ($inscription_edit && $inscription_edit['statut'] == 'En cours') ? 'selected' : ''; ?>>En cours</option>
                    <option value="Actif" <?php echo ($inscription_edit && $inscription_edit['statut'] == 'Actif') ? 'selected' : ''; ?>>Actif</option>
                    <option value="En attente" <?php echo ($inscription_edit && $inscription_edit['statut'] == 'En attente') ? 'selected' : ''; ?>>En attente</option>
                    <option value="Terminé" <?php echo ($inscription_edit && $inscription_edit['statut'] == 'Terminé') ? 'selected' : ''; ?>>Terminé</option>
                    <option value="Abandonné" <?php echo ($inscription_edit && $inscription_edit['statut'] == 'Abandonné') ? 'selected' : ''; ?>>Abandonné</option>
                </select>
            </div>
            
            <div class="btn-group">
                <?php if ($inscription_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="inscriptions.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Enregistrer l'inscription</button>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (!$inscription_edit): ?>
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #f0f0f0;">
            <p style="font-size: 13px; color: #7f8c8d;">
                💡 <strong>Astuce :</strong> Après avoir créé une inscription, vous pourrez gérer les paiements dans le module <a href="paiements.php" style="color: #667eea;">Paiements</a>.
            </p>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- TABLEAU -->
    <div class="card">
        <h3>
            <span>📋 Liste des inscriptions</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display: flex; gap: 10px;">
                    <select name="statut" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous les statuts</option>
                        <option value="En cours" <?php echo $filter_statut == 'En cours' ? 'selected' : ''; ?>>En cours</option>
                        <option value="Actif" <?php echo $filter_statut == 'Actif' ? 'selected' : ''; ?>>Actif</option>
                        <option value="En attente" <?php echo $filter_statut == 'En attente' ? 'selected' : ''; ?>>En attente</option>
                        <option value="Terminé" <?php echo $filter_statut == 'Terminé' ? 'selected' : ''; ?>>Terminé</option>
                        <option value="Abandonné" <?php echo $filter_statut == 'Abandonné' ? 'selected' : ''; ?>>Abandonné</option>
                    </select>
                    <select name="formation" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Toutes les formations</option>
                        <?php foreach ($formations_filtre as $form): ?>
                            <option value="<?php echo $form['id_formation']; ?>" <?php echo $filter_formation == $form['id_formation'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($form['nom_formation']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                        <th>Coût</th>
                        <th>Payé</th>
                        <th>Solde</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($inscriptions) > 0): ?>
                        <?php foreach ($inscriptions as $ins):
                            $status_class = '';
                            switch(strtolower($ins['statut'])) {
                                case 'actif': case 'en cours': $status_class = 'status-actif'; break;
                                case 'terminé': case 'termine': $status_class = 'status-termine'; break;
                                case 'en attente': $status_class = 'status-en-attente'; break;
                                case 'abandonné': case 'abandonne': $status_class = 'status-abandonne'; break;
                            }
                            $cout_affiche = limiterPrix($ins['cout']);
                            $total_paye_affiche = limiterPrix($ins['total_paye']);
                            $solde = limiterPrix($ins['solde_restant']);
                        ?>
                        <tr>
                            <td><strong>#<?php echo $ins['id_inscription']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($ins['nom'] . ' ' . $ins['prenom']); ?></strong>
                                <?php if ($ins['postnom']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($ins['postnom']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($ins['nom_formation']); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($ins['date_inscription'])); ?></td>
                            <td><strong><?php echo number_format($cout_affiche, 2, '.', ','); ?> $</strong></td>
                            <td style="color: #27ae60;"><?php echo number_format($total_paye_affiche, 2, '.', ','); ?> $</td>
                            <td class="<?php echo $solde <= 0 ? 'solde-ok' : 'solde-ko'; ?>">
                                <?php echo number_format($solde, 2, '.', ','); ?> $
                            </td>
                            <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($ins['statut']); ?></span></td>
                            <td>
                                <div class="btn-group">
                                    <a href="?modifier=<?php echo $ins['id_inscription']; ?>" class="btn btn-sm" style="background:#f39c12; color:white; text-decoration:none;">✏️</a>
                                    <a href="paiements.php?inscription=<?php echo $ins['id_inscription']; ?>" class="btn btn-sm" style="background:#3498db; color:white; text-decoration:none;" title="Paiements">💵</a>
                                    <a href="?supprimer=<?php echo $ins['id_inscription']; ?>" class="btn btn-sm" style="background:#e74c3c; color:white; text-decoration:none;" onclick="return confirm('⚠️ Supprimer cette inscription ?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📭</div><p>Aucune inscription trouvée</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($inscriptions) > 0): ?>
        <div style="margin-top:15px; font-size:13px; color:#7f8c8d; text-align:right;">
            <?php echo count($inscriptions); ?> inscription(s)
        </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
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

function updateCout() {
    const select = document.getElementById('selectFormation');
    const option = select.options[select.selectedIndex];
    const cout = option.getAttribute('data-cout');
    const coutInfo = document.getElementById('coutInfo');
    if (cout) {
        coutInfo.innerHTML = '💵 Coût : <strong>' + new Intl.NumberFormat('en-US', {style:'currency', currency:'USD'}).format(cout) + '</strong>';
    } else {
        coutInfo.innerHTML = '';
    }
}
updateCout();

document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.opacity='0'; m.style.transition='all 0.5s'; setTimeout(()=>m.remove(),500); }, 5000));
});
</script>

</div>
</body>
</html>