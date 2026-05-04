<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$formation_edit = null;

// ============================================
// CREATE - Ajouter une formation
// ============================================
if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom_formation']);
    $duree = trim($_POST['duree']);
    $cout = floatval($_POST['cout']);
    $niveau = trim($_POST['niveau']);
    $description = trim($_POST['description']);
    
    if (empty($nom) || empty($duree) || $cout <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $query = "INSERT INTO formation (nom_formation, duree, cout, niveau, description) 
                      VALUES (:nom, :duree, :cout, :niveau, :description)";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':nom' => $nom,
                ':duree' => $duree,
                ':cout' => $cout,
                ':niveau' => $niveau,
                ':description' => $description
            ]);
            $message = "✅ Formation ajoutée avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de l'ajout : " . $e->getMessage();
        }
    }
}

// ============================================
// UPDATE - Modifier une formation
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_formation']);
    $nom = trim($_POST['nom_formation']);
    $duree = trim($_POST['duree']);
    $cout = floatval($_POST['cout']);
    $niveau = trim($_POST['niveau']);
    $description = trim($_POST['description']);
    
    if (empty($nom) || empty($duree) || $cout <= 0) {
        $error = "Veuillez remplir tous les champs obligatoires.";
    } else {
        try {
            $query = "UPDATE formation SET nom_formation=:nom, duree=:duree, cout=:cout, 
                      niveau=:niveau, description=:description WHERE id_formation=:id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':id' => $id,
                ':nom' => $nom,
                ':duree' => $duree,
                ':cout' => $cout,
                ':niveau' => $niveau,
                ':description' => $description
            ]);
            $message = "✅ Formation modifiée avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de la modification : " . $e->getMessage();
        }
    }
}

// ============================================
// DELETE - Supprimer une formation
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    
    // Vérifier si la formation a des inscriptions
    $check = $db->prepare("SELECT COUNT(*) FROM inscription WHERE id_formation = :id");
    $check->execute([':id' => $id]);
    $nb_inscriptions = $check->fetchColumn();
    
    if ($nb_inscriptions > 0) {
        $error = "❌ Impossible de supprimer : cette formation a {$nb_inscriptions} inscription(s).";
    } else {
        try {
            // Vérifier les sessions liées
            $checkSession = $db->prepare("SELECT COUNT(*) FROM session WHERE id_formation = :id");
            $checkSession->execute([':id' => $id]);
            if ($checkSession->fetchColumn() > 0) {
                $error = "❌ Impossible de supprimer : des sessions sont liées à cette formation.";
            } else {
                $stmt = $db->prepare("DELETE FROM formation WHERE id_formation = :id");
                $stmt->execute([':id' => $id]);
                $message = "✅ Formation supprimée avec succès !";
            }
        } catch (PDOException $e) {
            $error = "❌ Erreur lors de la suppression : " . $e->getMessage();
        }
    }
}

// ============================================
// EDIT - Charger une formation pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM formation WHERE id_formation = :id");
    $stmt->execute([':id' => $id]);
    $formation_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer toutes les formations avec stats
// ============================================
$search = $_GET['search'] ?? '';
$filter_niveau = $_GET['niveau'] ?? '';

$query = "
    SELECT f.*, 
           COUNT(i.id_inscription) as nb_inscriptions,
           COUNT(s.id_session) as nb_sessions
    FROM formation f
    LEFT JOIN inscription i ON f.id_formation = i.id_formation
    LEFT JOIN session s ON f.id_formation = s.id_formation
    WHERE 1=1
";

$params = [];

if (!empty($search)) {
    $query .= " AND (f.nom_formation LIKE :search OR f.description LIKE :search2)";
    $params[':search'] = "%{$search}%";
    $params[':search2'] = "%{$search}%";
}

if (!empty($filter_niveau)) {
    $query .= " AND f.niveau = :niveau";
    $params[':niveau'] = $filter_niveau;
}

$query .= " GROUP BY f.id_formation ORDER BY f.nom_formation ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$formations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_formations = count($formations);
$cout_moyen = $db->query("SELECT AVG(cout) FROM formation")->fetchColumn();
$niveaux = $db->query("SELECT DISTINCT niveau FROM formation ORDER BY niveau")->fetchAll(PDO::FETCH_COLUMN);
?>

<!-- ============================================ -->
<!-- STYLES SPÉCIFIQUES -->
<!-- ============================================ -->
<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .page-header h1 {
        font-size: 26px;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .stats-mini {
        display: flex;
        gap: 20px;
        flex-wrap: wrap;
    }

    .stat-mini-item {
        background: white;
        padding: 12px 20px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        font-size: 14px;
    }

    .stat-mini-item .stat-mini-icon {
        font-size: 20px;
    }

    .stat-mini-item strong {
        color: #667eea;
        font-size: 18px;
    }

    .grid-form-table {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }

    @media (max-width: 900px) {
        .grid-form-table {
            grid-template-columns: 1fr;
        }
    }

    .card-form {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
        position: sticky;
        top: 20px;
        height: fit-content;
    }

    .card-form h3 {
        font-size: 18px;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .card-table {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
    }

    .card-table h3 {
        font-size: 18px;
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }

    .form-grid {
        display: grid;
        gap: 15px;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .form-group label {
        font-size: 13px;
        font-weight: 600;
        color: #2c3e50;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .form-group label .required {
        color: #e74c3c;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .btn-group {
        display: flex;
        gap: 10px;
        margin-top: 10px;
    }

    .btn {
        padding: 10px 20px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .btn-save {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        flex: 1;
    }

    .btn-save:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-cancel {
        background: #f0f0f0;
        color: #666;
    }

    .btn-cancel:hover {
        background: #e0e0e0;
    }

    .btn-edit {
        background: #f39c12;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
    }

    .btn-delete {
        background: #e74c3c;
        color: white;
        padding: 6px 12px;
        border-radius: 6px;
        text-decoration: none;
        font-size: 12px;
    }

    .btn-edit:hover, .btn-delete:hover {
        opacity: 0.8;
    }

    /* Barre de recherche et filtres */
    .toolbar {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-box {
        position: relative;
        flex: 1;
        min-width: 200px;
    }

    .search-box input {
        width: 100%;
        padding: 10px 12px 10px 38px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
    }

    .search-box .search-icon {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 16px;
    }

    .filter-select {
        padding: 10px 12px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 14px;
        background: white;
        cursor: pointer;
    }

    /* Table moderne */
    .table-container {
        overflow-x: auto;
    }

    .table-modern {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }

    .table-modern thead th {
        background: #f8f9fa;
        padding: 14px 15px;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
        color: #7f8c8d;
        font-weight: 600;
        letter-spacing: 0.5px;
        white-space: nowrap;
        border-bottom: 2px solid #e0e0e0;
    }

    .table-modern tbody td {
        padding: 14px 15px;
        border-bottom: 1px solid #f0f0f0;
        vertical-align: middle;
    }

    .table-modern tbody tr {
        transition: background 0.3s ease;
    }

    .table-modern tbody tr:hover {
        background: #f8f9ff;
    }

    .badge-niveau {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
        display: inline-block;
    }

    .niveau-debutant { background: #d4edda; color: #155724; }
    .niveau-intermediaire { background: #fff3cd; color: #856404; }
    .niveau-avance { background: #f8d7da; color: #721c24; }

    .cout-format {
        font-weight: bold;
        color: #27ae60;
    }

    .text-muted {
        color: #95a5a6;
        font-style: italic;
    }

    .actions-cell {
        display: flex;
        gap: 6px;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #95a5a6;
    }

    .empty-state .empty-icon {
        font-size: 48px;
        margin-bottom: 10px;
    }
</style>

<!-- ============================================ -->
<!-- CONTENU PRINCIPAL -->
<!-- ============================================ -->

<div class="page-header">
    <h1>🎓 Gestion des Formations</h1>
    <div class="stats-mini">
        <div class="stat-mini-item">
            <span class="stat-mini-icon">📚</span>
            <span>Total : <strong><?php echo $total_formations; ?></strong></span>
        </div>
        <div class="stat-mini-item">
            <span class="stat-mini-icon">💵</span>
            <span>Coût moyen : <strong><?php echo number_format($cout_moyen, 0, ',', ' '); ?> FC</strong></span>
        </div>
    </div>
</div>

<?php if ($message): ?>
    <div class="message" style="background: #d4edda; color: #155724; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #28a745; animation: slideIn 0.3s ease;">
        <?php echo $message; ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="error" style="background: #f8d7da; color: #721c24; padding: 12px 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #dc3545; animation: slideIn 0.3s ease;">
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<div class="grid-form-table">
    
    <!-- ============================================ -->
    <!-- FORMULAIRE AJOUT / MODIFICATION -->
    <!-- ============================================ -->
    <div class="card-form">
        <h3><?php echo $formation_edit ? '✏️ Modifier la formation' : '➕ Nouvelle formation'; ?></h3>
        <form method="POST" class="form-grid">
            <?php if ($formation_edit): ?>
                <input type="hidden" name="id_formation" value="<?php echo $formation_edit['id_formation']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nom de la formation <span class="required">*</span></label>
                <input type="text" name="nom_formation" placeholder="Ex: Développement Web Full Stack" 
                       value="<?php echo $formation_edit ? htmlspecialchars($formation_edit['nom_formation']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Durée <span class="required">*</span></label>
                <input type="text" name="duree" placeholder="Ex: 6 mois" 
                       value="<?php echo $formation_edit ? htmlspecialchars($formation_edit['duree']) : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Coût (FC) <span class="required">*</span></label>
                <input type="number" name="cout" placeholder="Ex: 500000" step="0.01" min="0"
                       value="<?php echo $formation_edit ? $formation_edit['cout'] : ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Niveau</label>
                <select name="niveau">
                    <option value="Débutant" <?php echo ($formation_edit && $formation_edit['niveau'] == 'Débutant') ? 'selected' : ''; ?>>Débutant</option>
                    <option value="Intermédiaire" <?php echo ($formation_edit && $formation_edit['niveau'] == 'Intermédiaire') ? 'selected' : ''; ?>>Intermédiaire</option>
                    <option value="Avancé" <?php echo ($formation_edit && $formation_edit['niveau'] == 'Avancé') ? 'selected' : ''; ?>>Avancé</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" placeholder="Description détaillée de la formation..."><?php echo $formation_edit ? htmlspecialchars($formation_edit['description']) : ''; ?></textarea>
            </div>
            
            <div class="btn-group">
                <?php if ($formation_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer les modifications</button>
                    <a href="formations.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Ajouter la formation</button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- ============================================ -->
    <!-- TABLEAU DES FORMATIONS -->
    <!-- ============================================ -->
    <div class="card-table">
        <h3>
            <span>📋 Liste des formations</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher une formation..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display: flex; gap: 10px;">
                    <select name="niveau" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous les niveaux</option>
                        <?php foreach ($niveaux as $niv): ?>
                            <option value="<?php echo $niv; ?>" <?php echo $filter_niveau == $niv ? 'selected' : ''; ?>>
                                <?php echo $niv; ?>
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
                        <th>Nom de la formation</th>
                        <th>Durée</th>
                        <th>Coût</th>
                        <th>Niveau</th>
                        <th>Inscriptions</th>
                        <th>Sessions</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($formations) > 0): ?>
                        <?php foreach ($formations as $formation): 
                            $niveau_class = '';
                            switch(strtolower($formation['niveau'])) {
                                case 'débutant': $niveau_class = 'niveau-debutant'; break;
                                case 'intermédiaire': $niveau_class = 'niveau-intermediaire'; break;
                                case 'avancé': $niveau_class = 'niveau-avance'; break;
                            }
                        ?>
                        <tr>
                            <td><strong>#<?php echo $formation['id_formation']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($formation['nom_formation']); ?></strong>
                                <?php if (!empty($formation['description'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars(substr($formation['description'], 0, 50)) . '...'; ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($formation['duree']); ?></td>
                            <td class="cout-format"><?php echo number_format($formation['cout'], 0, ',', ' '); ?> FC</td>
                            <td><span class="badge-niveau <?php echo $niveau_class; ?>"><?php echo htmlspecialchars($formation['niveau']); ?></span></td>
                            <td>
                                <?php if ($formation['nb_inscriptions'] > 0): ?>
                                    <strong style="color: #27ae60;"><?php echo $formation['nb_inscriptions']; ?></strong> apprenant(s)
                                <?php else: ?>
                                    <span class="text-muted">Aucun</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($formation['nb_sessions'] > 0): ?>
                                    <strong style="color: #2980b9;"><?php echo $formation['nb_sessions']; ?></strong> session(s)
                                <?php else: ?>
                                    <span class="text-muted">Aucune</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="actions-cell">
                                    <a href="?modifier=<?php echo $formation['id_formation']; ?>" class="btn-edit" title="Modifier">✏️</a>
                                    <a href="?supprimer=<?php echo $formation['id_formation']; ?>" 
                                       class="btn-delete" 
                                       title="Supprimer"
                                       onclick="return confirm('⚠️ Êtes-vous sûr de vouloir supprimer cette formation ?\n\nCette action est irréversible.');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <div class="empty-icon">📭</div>
                                    <p>Aucune formation trouvée</p>
                                    <?php if (!empty($search) || !empty($filter_niveau)): ?>
                                        <a href="formations.php" class="btn btn-cancel" style="display: inline-flex; margin-top: 10px;">Réinitialiser les filtres</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (count($formations) > 0): ?>
        <div style="margin-top: 15px; font-size: 13px; color: #7f8c8d; text-align: right;">
            <?php echo count($formations); ?> formation(s) affichée(s)
        </div>
        <?php endif; ?>
    </div>
    
</div>

<script>
// Recherche en temps réel avec debounce
let searchTimeout;
document.getElementById('searchInput').addEventListener('keyup', function() {
    clearTimeout(searchTimeout);
    const searchValue = this.value;
    
    searchTimeout = setTimeout(() => {
        const url = new URL(window.location.href);
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        window.location.href = url.toString();
    }, 500);
});

// Animation des messages
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message, .error');
    messages.forEach(msg => {
        setTimeout(() => {
            msg.style.transition = 'all 0.5s ease';
            msg.style.opacity = '0';
            msg.style.transform = 'translateX(-20px)';
            setTimeout(() => msg.remove(), 500);
        }, 5000);
    });
});
</script>

</div>
</body>
</html>