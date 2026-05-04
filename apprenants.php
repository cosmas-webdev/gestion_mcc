<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$apprenant_edit = null;

// ============================================
// CREATE - Ajouter un apprenant
// ============================================
if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom']);
    $postnom = trim($_POST['postnom']);
    $prenom = trim($_POST['prenom']);
    $sexe = $_POST['sexe'];
    $date_naissance = $_POST['date_naissance'];
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $adresse = trim($_POST['adresse']);
    
    if (empty($nom) || empty($prenom)) {
        $error = "⚠️ Le nom et le prénom sont obligatoires.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } else {
        try {
            $query = "INSERT INTO apprenant (nom, postnom, prenom, sexe, date_naissance, telephone, email, adresse) 
                      VALUES (:nom, :postnom, :prenom, :sexe, :date_naissance, :telephone, :email, :adresse)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':postnom' => $postnom,
                ':prenom' => $prenom,
                ':sexe' => $sexe,
                ':date_naissance' => $date_naissance,
                ':telephone' => $telephone,
                ':email' => $email,
                ':adresse' => $adresse
            ]);
            $message = "✅ Apprenant ajouté avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}

// ============================================
// UPDATE - Modifier un apprenant
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_apprenant']);
    $nom = trim($_POST['nom']);
    $postnom = trim($_POST['postnom']);
    $prenom = trim($_POST['prenom']);
    $sexe = $_POST['sexe'];
    $date_naissance = $_POST['date_naissance'];
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    $adresse = trim($_POST['adresse']);
    
    if (empty($nom) || empty($prenom)) {
        $error = "⚠️ Le nom et le prénom sont obligatoires.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } else {
        try {
            $query = "UPDATE apprenant SET nom=:nom, postnom=:postnom, prenom=:prenom, sexe=:sexe, 
                      date_naissance=:date_naissance, telephone=:telephone, email=:email, adresse=:adresse 
                      WHERE id_apprenant=:id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':postnom' => $postnom,
                ':prenom' => $prenom,
                ':sexe' => $sexe,
                ':date_naissance' => $date_naissance,
                ':telephone' => $telephone,
                ':email' => $email,
                ':adresse' => $adresse
            ]);
            $message = "✅ Apprenant modifié avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// ============================================
// DELETE - Supprimer un apprenant
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    
    // Vérifier si l'apprenant a des inscriptions
    $check = $db->prepare("SELECT COUNT(*) FROM inscription WHERE id_apprenant = :id");
    $check->execute([':id' => $id]);
    $nb_inscriptions = $check->fetchColumn();
    
    if ($nb_inscriptions > 0) {
        $error = "⚠️ Impossible de supprimer : cet apprenant a {$nb_inscriptions} inscription(s). Supprimez d'abord ses inscriptions.";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM apprenant WHERE id_apprenant = :id");
            $stmt->execute([':id' => $id]);
            $message = "✅ Apprenant supprimé avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// ============================================
// EDIT - Charger pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM apprenant WHERE id_apprenant = :id");
    $stmt->execute([':id' => $id]);
    $apprenant_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer tous les apprenants
// ============================================
$search = $_GET['search'] ?? '';
$filter_sexe = $_GET['sexe'] ?? '';

$query = "
    SELECT a.*, 
           COUNT(i.id_inscription) as nb_inscriptions,
           GROUP_CONCAT(DISTINCT f.nom_formation SEPARATOR ', ') as formations_suivies
    FROM apprenant a
    LEFT JOIN inscription i ON a.id_apprenant = i.id_apprenant
    LEFT JOIN formation f ON i.id_formation = f.id_formation
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (a.nom LIKE :s1 OR a.postnom LIKE :s2 OR a.prenom LIKE :s3 OR a.telephone LIKE :s4 OR a.email LIKE :s5)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
    $params[':s3'] = "%{$search}%";
    $params[':s4'] = "%{$search}%";
    $params[':s5'] = "%{$search}%";
}

if (!empty($filter_sexe)) {
    $query .= " AND a.sexe = :sexe";
    $params[':sexe'] = $filter_sexe;
}

$query .= " GROUP BY a.id_apprenant ORDER BY a.nom, a.postnom, a.prenom";

$stmt = $db->prepare($query);
$stmt->execute($params);
$apprenants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_apprenants = count($apprenants);
$total_hommes = $db->query("SELECT COUNT(*) FROM apprenant WHERE sexe = 'M'")->fetchColumn();
$total_femmes = $db->query("SELECT COUNT(*) FROM apprenant WHERE sexe = 'F'")->fetchColumn();
$total_inscriptions_all = $db->query("SELECT COUNT(*) FROM inscription")->fetchColumn();
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

    .stats-mini {
        display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px;
    }
    .stat-mini {
        background: var(--white); padding: 15px 20px; border-radius: 10px;
        display: flex; align-items: center; gap: 10px;
        box-shadow: var(--shadow); border: 1px solid #f0f0f0;
        cursor: pointer; transition: transform 0.3s;
    }
    .stat-mini:hover { transform: translateY(-3px); }
    .stat-mini .icon { font-size: 22px; }
    .stat-mini .val { font-size: 20px; font-weight: bold; color: var(--text); }
    .stat-mini .lbl { font-size: 12px; color: var(--text-light); }

    .grid-layout {
        display: grid; grid-template-columns: 400px 1fr; gap: 20px;
    }
    .card {
        background: var(--white); padding: 25px; border-radius: var(--radius);
        box-shadow: var(--shadow); border: 1px solid #f0f0f0;
    }
    .card.sticky { position: sticky; top: 20px; height: fit-content; }
    .card h3 {
        font-size: 18px; color: var(--text); margin-bottom: 20px;
        padding-bottom: 15px; border-bottom: 2px solid #f0f2f5;
        display: flex; align-items: center; gap: 8px;
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
        outline: none; border-color: var(--primary); background: white;
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

    .sexe-badge {
        display: inline-block; padding: 4px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 500;
    }
    .sexe-m { background: #e3f2fd; color: #1565c0; }
    .sexe-f { background: #fce4ec; color: #c62828; }
    .text-muted { color: var(--text-light); font-style: italic; }
    .empty-state { text-align: center; padding: 40px; color: var(--text-light); }
    .empty-icon { font-size: 48px; margin-bottom: 10px; }
    .actions-cell { display: flex; gap: 4px; }

    .apprenant-avatar {
        width: 35px; height: 35px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: bold; font-size: 14px; flex-shrink: 0;
    }
    .avatar-m { background: linear-gradient(135deg, #42a5f5, #1e88e5); }
    .avatar-f { background: linear-gradient(135deg, #ec407a, #d81b60); }

    .quick-info {
        background: #f0f4ff; padding: 12px 15px; border-radius: 8px;
        font-size: 13px; color: var(--text); margin-top: 15px;
    }

    @media (max-width: 1100px) { .grid-layout { grid-template-columns: 1fr; } .card.sticky { position: static; } }
    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } .toolbar { flex-direction: column; } }
</style>

<div class="page-header">
    <h1>📚 Gestion des Apprenants</h1>
    <div class="stats-mini">
        <div class="stat-mini" onclick="window.location='?sexe='">
            <span class="icon">👥</span>
            <div><div class="val"><?php echo $total_apprenants; ?></div><div class="lbl">Total</div></div>
        </div>
        <div class="stat-mini" onclick="window.location='?sexe=M'">
            <span class="icon">👨</span>
            <div><div class="val"><?php echo $total_hommes; ?></div><div class="lbl">Hommes</div></div>
        </div>
        <div class="stat-mini" onclick="window.location='?sexe=F'">
            <span class="icon">👩</span>
            <div><div class="val"><?php echo $total_femmes; ?></div><div class="lbl">Femmes</div></div>
        </div>
        <div class="stat-mini">
            <span class="icon">📝</span>
            <div><div class="val"><?php echo $total_inscriptions_all; ?></div><div class="lbl">Inscriptions</div></div>
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
        <h3><?php echo $apprenant_edit ? '✏️ Modifier l\'apprenant' : '➕ Nouvel apprenant'; ?></h3>
        <form method="POST" class="form-grid" id="apprenantForm">
            <?php if ($apprenant_edit): ?>
                <input type="hidden" name="id_apprenant" value="<?php echo $apprenant_edit['id_apprenant']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nom <span class="required">*</span></label>
                <input type="text" name="nom" placeholder="Nom de famille" required
                       value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['nom']) : ''; ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Postnom</label>
                    <input type="text" name="postnom" placeholder="Postnom"
                           value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['postnom']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label>Prénom <span class="required">*</span></label>
                    <input type="text" name="prenom" placeholder="Prénom" required
                           value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['prenom']) : ''; ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Sexe</label>
                    <select name="sexe">
                        <option value="M" <?php echo ($apprenant_edit && $apprenant_edit['sexe'] == 'M') ? 'selected' : ''; ?>>👨 Masculin</option>
                        <option value="F" <?php echo ($apprenant_edit && $apprenant_edit['sexe'] == 'F') ? 'selected' : ''; ?>>👩 Féminin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date de naissance</label>
                    <input type="date" name="date_naissance"
                           value="<?php echo $apprenant_edit ? $apprenant_edit['date_naissance'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" placeholder="Ex: +243 123 456 789"
                       value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['telephone']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Ex: exemple@email.com"
                       value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['email']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Adresse</label>
                <input type="text" name="adresse" placeholder="Adresse complète"
                       value="<?php echo $apprenant_edit ? htmlspecialchars($apprenant_edit['adresse']) : ''; ?>">
            </div>
            
            <div class="btn-group">
                <?php if ($apprenant_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="apprenants.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Ajouter l'apprenant</button>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($total_apprenants > 0 && !$apprenant_edit): ?>
        <div class="quick-info">
            💡 <strong>Astuce :</strong> Après avoir créé un apprenant, vous pouvez l'inscrire à une formation dans le module <a href="inscriptions.php" style="color: var(--primary);">Inscriptions</a>.
        </div>
        <?php endif; ?>
    </div>
    
    <!-- LISTE DES APPRENANTS -->
    <div class="card">
        <h3>
            <span>📋 Liste des apprenants</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display:flex; gap:8px;">
                    <select name="sexe" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous</option>
                        <option value="M" <?php echo $filter_sexe=='M'?'selected':''; ?>>👨 Hommes</option>
                        <option value="F" <?php echo $filter_sexe=='F'?'selected':''; ?>>👩 Femmes</option>
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
                        <th>Sexe</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Inscriptions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($apprenants) > 0): ?>
                        <?php foreach ($apprenants as $app): 
                            $avatar_class = $app['sexe'] == 'M' ? 'avatar-m' : 'avatar-f';
                            $initials = strtoupper(substr($app['nom'], 0, 1) . substr($app['prenom'], 0, 1));
                        ?>
                        <tr>
                            <td><strong>#<?php echo $app['id_apprenant']; ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="apprenant-avatar <?php echo $avatar_class; ?>"><?php echo $initials; ?></div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['nom'] . ' ' . $app['prenom']); ?></strong>
                                        <?php if ($app['postnom']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($app['postnom']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="sexe-badge <?php echo $app['sexe'] == 'M' ? 'sexe-m' : 'sexe-f'; ?>"><?php echo $app['sexe'] == 'M' ? '👨 M' : '👩 F'; ?></span></td>
                            <td><?php echo $app['telephone'] ? htmlspecialchars($app['telephone']) : '<span class="text-muted">-</span>'; ?></td>
                            <td><?php echo $app['email'] ? '<a href="mailto:'.htmlspecialchars($app['email']).'" style="color:var(--primary);">'.htmlspecialchars($app['email']).'</a>' : '<span class="text-muted">-</span>'; ?></td>
                            <td>
                                <?php if ($app['nb_inscriptions'] > 0): ?>
                                    <strong style="color: var(--success);"><?php echo $app['nb_inscriptions']; ?></strong>
                                    <?php if ($app['formations_suivies']): ?>
                                        <br><small style="font-size:11px; color: var(--text-light);"><?php echo htmlspecialchars($app['formations_suivies']); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="?modifier=<?php echo $app['id_apprenant']; ?>" class="btn btn-sm" style="background:#f39c12; color:white; text-decoration:none;">✏️</a>
                                    <a href="?supprimer=<?php echo $app['id_apprenant']; ?>" class="btn btn-sm" style="background:#e74c3c; color:white; text-decoration:none;" onclick="return confirm('⚠️ Supprimer cet apprenant ?')">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📚</div><p>Aucun apprenant trouvé</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top:15px; font-size:13px; color:var(--text-light); text-align:right;">
            <?php echo count($apprenants); ?> apprenant(s)
        </div>
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

document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.opacity='0'; m.style.transition='all 0.5s'; setTimeout(()=>m.remove(),500); }, 5000));
});
</script>

</div>
</body>
</html>