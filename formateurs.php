<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$formateur_edit = null;

// ============================================
// CREATE - Ajouter un formateur
// ============================================
if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom']);
    $specialite = trim($_POST['specialite']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    
    if (empty($nom) || empty($specialite)) {
        $error = "⚠️ Le nom et la spécialité sont obligatoires.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } else {
        // Vérifier si l'email existe déjà
        if (!empty($email)) {
            $check = $db->prepare("SELECT COUNT(*) FROM formateur WHERE email = :email");
            $check->execute([':email' => $email]);
            if ($check->fetchColumn() > 0) {
                $error = "⚠️ Un formateur avec cet email existe déjà.";
            }
        }
        
        if (empty($error)) {
            try {
                $query = "INSERT INTO formateur (nom, specialite, telephone, email) 
                          VALUES (:nom, :specialite, :telephone, :email)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nom' => $nom,
                    ':specialite' => $specialite,
                    ':telephone' => $telephone,
                    ':email' => $email
                ]);
                $message = "✅ Formateur ajouté avec succès !";
            } catch (PDOException $e) {
                $error = "❌ Erreur : " . $e->getMessage();
            }
        }
    }
}

// ============================================
// UPDATE - Modifier un formateur
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_formateur']);
    $nom = trim($_POST['nom']);
    $specialite = trim($_POST['specialite']);
    $telephone = trim($_POST['telephone']);
    $email = trim($_POST['email']);
    
    if (empty($nom) || empty($specialite)) {
        $error = "⚠️ Le nom et la spécialité sont obligatoires.";
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } else {
        try {
            $query = "UPDATE formateur SET nom=:nom, specialite=:specialite, 
                      telephone=:telephone, email=:email WHERE id_formateur=:id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':specialite' => $specialite,
                ':telephone' => $telephone,
                ':email' => $email
            ]);
            $message = "✅ Formateur modifié avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

// ============================================
// DELETE - Supprimer un formateur
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    
    // Vérifier les sessions liées
    $check = $db->prepare("SELECT COUNT(*) FROM session WHERE id_formateur = :id");
    $check->execute([':id' => $id]);
    $nb_sessions = $check->fetchColumn();
    
    if ($nb_sessions > 0) {
        $error = "⚠️ Impossible de supprimer : ce formateur a {$nb_sessions} session(s) planifiée(s).";
    } else {
        try {
            $stmt = $db->prepare("DELETE FROM formateur WHERE id_formateur = :id");
            $stmt->execute([':id' => $id]);
            $message = "✅ Formateur supprimé avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

// ============================================
// EDIT - Charger pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM formateur WHERE id_formateur = :id");
    $stmt->execute([':id' => $id]);
    $formateur_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer tous les formateurs
// ============================================
$search = $_GET['search'] ?? '';
$filter_specialite = $_GET['specialite'] ?? '';

$query = "
    SELECT f.*, 
           COUNT(DISTINCT s.id_session) as nb_sessions,
           GROUP_CONCAT(DISTINCT fo.nom_formation SEPARATOR ', ') as formations_assignees
    FROM formateur f
    LEFT JOIN session s ON f.id_formateur = s.id_formateur
    LEFT JOIN formation fo ON s.id_formation = fo.id_formation
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (f.nom LIKE :search1 OR f.specialite LIKE :search2 OR f.email LIKE :search3)";
    $params[':search1'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
    $params[':search3'] = "%{$search}%";
}

if (!empty($filter_specialite)) {
    $query .= " AND f.specialite = :specialite";
    $params[':specialite'] = $filter_specialite;
}

$query .= " GROUP BY f.id_formateur ORDER BY f.nom ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$formateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques et filtres
$total_formateurs = count($formateurs);
$specialites = $db->query("SELECT DISTINCT specialite FROM formateur ORDER BY specialite")->fetchAll(PDO::FETCH_COLUMN);
$total_sessions_all = $db->query("SELECT COUNT(*) FROM session")->fetchColumn();

// Prochaines sessions par formateur (pour la vue détaillée)
if (isset($_GET['details'])) {
    $id_details = intval($_GET['details']);
    $sessions_formateur = $db->prepare("
        SELECT s.*, f.nom_formation 
        FROM session s 
        JOIN formation f ON s.id_formation = f.id_formation 
        WHERE s.id_formateur = :id 
        ORDER BY s.date_debut DESC
    ");
    $sessions_formateur->execute([':id' => $id_details]);
    $sessions_details = $sessions_formateur->fetchAll(PDO::FETCH_ASSOC);
}
?>

<style>
    .page-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 25px; flex-wrap: wrap; gap: 15px;
    }
    .page-header h1 { font-size: 26px; color: #2c3e50; display: flex; align-items: center; gap: 10px; }
    
    .stats-mini {
        display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 25px;
    }
    .stat-mini {
        background: white; padding: 15px 20px; border-radius: 10px;
        display: flex; align-items: center; gap: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;
    }
    .stat-mini .icon { font-size: 22px; }
    .stat-mini .val { font-size: 20px; font-weight: bold; color: #2c3e50; }
    .stat-mini .lbl { font-size: 12px; color: #7f8c8d; }

    .grid-layout {
        display: grid; grid-template-columns: 380px 1fr; gap: 20px;
    }
    @media (max-width: 900px) { .grid-layout { grid-template-columns: 1fr; } }

    .card {
        background: white; padding: 25px; border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05); border: 1px solid #f0f0f0;
    }
    .card.sticky { position: sticky; top: 20px; height: fit-content; }
    .card h3 {
        font-size: 18px; color: #2c3e50; margin-bottom: 20px;
        padding-bottom: 15px; border-bottom: 2px solid #f0f2f5;
        display: flex; align-items: center; gap: 8px;
    }
    .form-grid { display: grid; gap: 15px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-size: 13px; font-weight: 600; color: #2c3e50; text-transform: uppercase; letter-spacing: 0.5px; }
    .form-group label .required { color: #e74c3c; }
    .form-group input, .form-group select, .form-group textarea {
        padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px;
        font-size: 14px; transition: all 0.3s ease; font-family: inherit;
    }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
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
    .btn-edit { background: #f39c12; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; }
    .btn-delete { background: #e74c3c; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; }
    .btn-info { background: #3498db; color: white; padding: 6px 12px; border-radius: 6px; text-decoration: none; font-size: 12px; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    .btn-group { display: flex; gap: 10px; }
    
    .toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    .search-box { position: relative; flex: 1; min-width: 200px; }
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
    
    .specialite-badge {
        display: inline-block; padding: 4px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 500; background: #e8eaf6; color: #3f51b5;
    }
    .text-muted { color: #95a5a6; font-style: italic; }
    .text-success { color: #27ae60; font-weight: bold; }
    .empty-state { text-align: center; padding: 40px; color: #95a5a6; }
    .empty-state .empty-icon { font-size: 48px; margin-bottom: 10px; }
    .actions-cell { display: flex; gap: 4px; flex-wrap: wrap; }

    /* Carte formateur style CV */
    .formateur-card {
        background: white; border-radius: 12px; padding: 20px;
        border: 1px solid #f0f0f0; transition: all 0.3s ease;
        display: flex; gap: 15px; align-items: flex-start;
    }
    .formateur-card:hover { box-shadow: 0 5px 20px rgba(0,0,0,0.1); transform: translateY(-3px); }
    .formateur-avatar {
        width: 60px; height: 60px; border-radius: 50%;
        background: linear-gradient(135deg, #667eea, #764ba2);
        display: flex; align-items: center; justify-content: center;
        color: white; font-size: 24px; font-weight: bold; flex-shrink: 0;
    }
    .formateur-info h4 { color: #2c3e50; margin-bottom: 5px; }
    .formateur-info p { font-size: 13px; color: #7f8c8d; margin: 2px 0; }
    
    /* Vue cards */
    .cards-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px;
    }

    /* Session item */
    .session-item {
        padding: 10px 0; border-bottom: 1px solid #f0f0f0;
        display: flex; justify-content: space-between; align-items: center;
    }
    .session-item:last-child { border-bottom: none; }

    /* Vue détaillée */
    .detail-overlay {
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center;
        z-index: 1000; animation: fadeIn 0.3s ease;
    }
    .detail-modal {
        background: white; border-radius: 15px; padding: 30px;
        max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;
    }
    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
</style>

<div class="page-header">
    <h1>👨‍🏫 Gestion des Formateurs</h1>
    <div class="stats-mini">
        <div class="stat-mini">
            <span class="icon">👥</span>
            <div><div class="val"><?php echo $total_formateurs; ?></div><div class="lbl">Formateurs</div></div>
        </div>
        <div class="stat-mini">
            <span class="icon">📅</span>
            <div><div class="val"><?php echo $total_sessions_all; ?></div><div class="lbl">Sessions totales</div></div>
        </div>
        <div class="stat-mini">
            <span class="icon">🔧</span>
            <div><div class="val"><?php echo count($specialites); ?></div><div class="lbl">Spécialités</div></div>
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
    
    <!-- ============================================ -->
    <!-- FORMULAIRE -->
    <!-- ============================================ -->
    <div class="card sticky">
        <h3><?php echo $formateur_edit ? '✏️ Modifier le formateur' : '➕ Nouveau formateur'; ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($formateur_edit): ?>
                <input type="hidden" name="id_formateur" value="<?php echo $formateur_edit['id_formateur']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nom complet <span class="required">*</span></label>
                <input type="text" name="nom" placeholder="Ex: Henriette Cosmaas" required
                       value="<?php echo $formateur_edit ? htmlspecialchars($formateur_edit['nom']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Spécialité <span class="required">*</span></label>
                <input type="text" name="specialite" placeholder="Ex: Développement Web, Réseaux..." required
                       value="<?php echo $formateur_edit ? htmlspecialchars($formateur_edit['specialite']) : ''; ?>"
                       list="specialites-list">
                <datalist id="specialites-list">
                    <?php foreach ($specialites as $spec): ?>
                        <option value="<?php echo htmlspecialchars($spec); ?>">
                    <?php endforeach; ?>
                </datalist>
            </div>
            
            <div class="form-group">
                <label>Téléphone</label>
                <input type="text" name="telephone" placeholder="Ex: +243 123 456 789"
                       value="<?php echo $formateur_edit ? htmlspecialchars($formateur_edit['telephone']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="Ex: jean@example.com"
                       value="<?php echo $formateur_edit ? htmlspecialchars($formateur_edit['email']) : ''; ?>">
            </div>
            
            <div class="btn-group">
                <?php if ($formateur_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="formateurs.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Ajouter le formateur</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- ============================================ -->
    <!-- LISTE DES FORMATEURS -->
    <!-- ============================================ -->
    <div class="card">
        <h3>
            <span>📋 Liste des formateurs</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display: flex; gap: 10px;">
                    <select name="specialite" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Toutes les spécialités</option>
                        <?php foreach ($specialites as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $filter_specialite == $spec ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec); ?>
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
                        <th>Nom</th>
                        <th>Spécialité</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Sessions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($formateurs) > 0): ?>
                        <?php foreach ($formateurs as $form): ?>
                        <tr>
                            <td><strong>#<?php echo $form['id_formateur']; ?></strong></td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div style="width: 35px; height: 35px; border-radius: 50%; background: linear-gradient(135deg, #667eea, #764ba2); display: flex; align-items: center; justify-content: center; color: white; font-size: 14px; font-weight: bold; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($form['nom'], 0, 1)); ?>
                                    </div>
                                    <strong><?php echo htmlspecialchars($form['nom']); ?></strong>
                                </div>
                            </td>
                            <td><span class="specialite-badge"><?php echo htmlspecialchars($form['specialite']); ?></span></td>
                            <td><?php echo $form['telephone'] ? htmlspecialchars($form['telephone']) : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            <td><?php echo $form['email'] ? '<a href="mailto:'.htmlspecialchars($form['email']).'" style="color:#667eea;">'.htmlspecialchars($form['email']).'</a>' : '<span class="text-muted">Non renseigné</span>'; ?></td>
                            <td>
                                <?php if ($form['nb_sessions'] > 0): ?>
                                    <span class="text-success"><?php echo $form['nb_sessions']; ?> session(s)</span>
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="?modifier=<?php echo $form['id_formateur']; ?>" class="btn-edit btn-sm" title="Modifier">✏️</a>
                                    <?php if ($form['nb_sessions'] > 0): ?>
                                        <a href="?details=<?php echo $form['id_formateur']; ?>" class="btn-info btn-sm" title="Voir sessions">📅</a>
                                    <?php endif; ?>
                                    <a href="?supprimer=<?php echo $form['id_formateur']; ?>" class="btn-delete btn-sm" 
                                       onclick="return confirm('⚠️ Supprimer ce formateur ?');" title="Supprimer">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty-state">
                                    <div class="empty-icon">👨‍🏫</div>
                                    <p>Aucun formateur trouvé</p>
                                    <a href="formateurs.php" style="color:#667eea; text-decoration:none;">Réinitialiser les filtres</a>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
</div>

<!-- ============================================ -->
<!-- VUE DÉTAILLÉE DES SESSIONS D'UN FORMATEUR -->
<!-- ============================================ -->
<?php if (isset($_GET['details']) && isset($sessions_details)): 
    $formateur_info = $db->prepare("SELECT nom FROM formateur WHERE id_formateur = :id");
    $formateur_info->execute([':id' => $id_details]);
    $form_nom = $formateur_info->fetchColumn();
?>
<div class="detail-overlay" id="detailModal" onclick="if(event.target===this) closeModal()">
    <div class="detail-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0;">📅 Sessions de <?php echo htmlspecialchars($form_nom); ?></h3>
            <a href="formateurs.php" style="text-decoration: none; font-size: 20px; color: #666;">✕</a>
        </div>
        
        <?php if (count($sessions_details) > 0): ?>
            <?php foreach ($sessions_details as $sess): ?>
            <div class="session-item">
                <div>
                    <strong><?php echo htmlspecialchars($sess['nom_formation']); ?></strong><br>
                    <small style="color: #7f8c8d;">
                        📅 <?php echo date('d/m/Y', strtotime($sess['date_debut'])); ?> → <?php echo date('d/m/Y', strtotime($sess['date_fin'])); ?>
                        | 🕐 <?php echo htmlspecialchars($sess['horaire']); ?>
                        | 🏫 <?php echo htmlspecialchars($sess['salle']); ?>
                    </small>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="text-align: center; color: #95a5a6;">Aucune session trouvée</p>
        <?php endif; ?>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="sessions.php" class="btn btn-save" style="display: inline-flex;">📅 Gérer les sessions</a>
        </div>
    </div>
</div>
<?php endif; ?>

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

// Fermer modal
function closeModal() {
    window.location.href = 'formateurs.php';
}

// Masquer les messages
document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.transition='all 0.5s'; m.style.opacity='0'; setTimeout(() => m.remove(), 500); }, 5000));
});
</script>

</div>
</body>
</html>