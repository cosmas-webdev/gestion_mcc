<?php
require_once 'includes/header.php';
require_once 'config/database.php';

// Vérifier que l'utilisateur est admin
if ($_SESSION['user_role'] !== 'admin') {
    header('Location: dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';
$user_edit = null;

// ============================================
// CREATE - Ajouter un utilisateur
// ============================================
if (isset($_POST['ajouter'])) {
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $role = $_POST['role'];
    $statut = isset($_POST['statut']) ? 1 : 0;
    
    if (empty($nom) || empty($email) || empty($password)) {
        $error = "⚠️ Veuillez remplir tous les champs obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } elseif (strlen($password) < 6) {
        $error = "⚠️ Le mot de passe doit contenir au moins 6 caractères.";
    } elseif ($password !== $password_confirm) {
        $error = "⚠️ Les mots de passe ne correspondent pas.";
    } else {
        // Vérifier si l'email existe déjà
        $check = $db->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = :email");
        $check->execute([':email' => $email]);
        
        if ($check->fetchColumn() > 0) {
            $error = "⚠️ Un utilisateur avec cet email existe déjà.";
        } else {
            try {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $query = "INSERT INTO utilisateur (nom, email, password, role, statut, created_at) 
                          VALUES (:nom, :email, :password, :role, :statut, NOW())";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':nom' => $nom,
                    ':email' => $email,
                    ':password' => $password_hash,
                    ':role' => $role,
                    ':statut' => $statut
                ]);
                $message = "✅ Utilisateur créé avec succès !";
            } catch (PDOException $e) {
                $error = "❌ Erreur : " . $e->getMessage();
            }
        }
    }
}

// ============================================
// UPDATE - Modifier un utilisateur
// ============================================
if (isset($_POST['modifier'])) {
    $id = intval($_POST['id_utilisateur']);
    $nom = trim($_POST['nom']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $role = $_POST['role'];
    $statut = isset($_POST['statut']) ? 1 : 0;
    
    if (empty($nom) || empty($email)) {
        $error = "⚠️ Le nom et l'email sont obligatoires.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "⚠️ L'adresse email n'est pas valide.";
    } elseif (!empty($password) && strlen($password) < 6) {
        $error = "⚠️ Le mot de passe doit contenir au moins 6 caractères.";
    } elseif (!empty($password) && $password !== $password_confirm) {
        $error = "⚠️ Les mots de passe ne correspondent pas.";
    } else {
        try {
            if (!empty($password)) {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $query = "UPDATE utilisateur SET nom=:nom, email=:email, password=:password, 
                          role=:role, statut=:statut, updated_at=NOW() 
                          WHERE id_utilisateur=:id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':id' => $id, ':nom' => $nom, ':email' => $email,
                    ':password' => $password_hash, ':role' => $role, ':statut' => $statut
                ]);
            } else {
                $query = "UPDATE utilisateur SET nom=:nom, email=:email, 
                          role=:role, statut=:statut, updated_at=NOW() 
                          WHERE id_utilisateur=:id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':id' => $id, ':nom' => $nom, ':email' => $email,
                    ':role' => $role, ':statut' => $statut
                ]);
            }
            $message = "✅ Utilisateur modifié avec succès !";
        } catch (PDOException $e) {
            $error = "❌ Erreur : " . $e->getMessage();
        }
    }
}

// ============================================
// DELETE - Supprimer un utilisateur
// ============================================
if (isset($_GET['supprimer'])) {
    $id = intval($_GET['supprimer']);
    
    // Empêcher de se supprimer soi-même
    if ($id == $_SESSION['user_id']) {
        $error = "⚠️ Vous ne pouvez pas supprimer votre propre compte.";
    } else {
        // Vérifier qu'il reste au moins un admin
        $checkAdmin = $db->prepare("SELECT COUNT(*) FROM utilisateur WHERE role = 'admin' AND id_utilisateur != :id AND statut = 1");
        $checkAdmin->execute([':id' => $id]);
        $checkRole = $db->prepare("SELECT role FROM utilisateur WHERE id_utilisateur = :id");
        $checkRole->execute([':id' => $id]);
        $role_to_delete = $checkRole->fetchColumn();
        
        if ($role_to_delete == 'admin' && $checkAdmin->fetchColumn() == 0) {
            $error = "⚠️ Impossible de supprimer le dernier administrateur.";
        } else {
            try {
                $stmt = $db->prepare("DELETE FROM utilisateur WHERE id_utilisateur = :id");
                $stmt->execute([':id' => $id]);
                $message = "✅ Utilisateur supprimé avec succès !";
            } catch (PDOException $e) {
                $error = "❌ Erreur : " . $e->getMessage();
            }
        }
    }
}

// ============================================
// TOGGLE STATUT - Activer/Désactiver
// ============================================
if (isset($_GET['toggle'])) {
    $id = intval($_GET['toggle']);
    
    if ($id == $_SESSION['user_id']) {
        $error = "⚠️ Vous ne pouvez pas désactiver votre propre compte.";
    } else {
        $stmt = $db->prepare("UPDATE utilisateur SET statut = NOT statut, updated_at = NOW() WHERE id_utilisateur = :id");
        $stmt->execute([':id' => $id]);
        $message = "✅ Statut de l'utilisateur modifié avec succès !";
    }
}

// ============================================
// EDIT - Charger pour modification
// ============================================
if (isset($_GET['modifier'])) {
    $id = intval($_GET['modifier']);
    $stmt = $db->prepare("SELECT * FROM utilisateur WHERE id_utilisateur = :id");
    $stmt->execute([':id' => $id]);
    $user_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ============================================
// READ - Récupérer tous les utilisateurs
// ============================================
$search = $_GET['search'] ?? '';
$filter_role = $_GET['role'] ?? '';
$filter_statut = $_GET['statut_filter'] ?? '';

$query = "SELECT * FROM utilisateur WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (nom LIKE :s1 OR email LIKE :s2)";
    $params[':s1'] = "%{$search}%";
    $params[':s2'] = "%{$search}%";
}

if (!empty($filter_role)) {
    $query .= " AND role = :role";
    $params[':role'] = $filter_role;
}

if ($filter_statut !== '') {
    $query .= " AND statut = :statut";
    $params[':statut'] = intval($filter_statut);
}

$query .= " ORDER BY created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$utilisateurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistiques
$total_users = count($utilisateurs);
$total_admin = $db->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'admin'")->fetchColumn();
$total_formateur = $db->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'formateur'")->fetchColumn();
$total_secretaire = $db->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'secretaire'")->fetchColumn();
$total_gestionnaire = $db->query("SELECT COUNT(*) FROM utilisateur WHERE role = 'gestionnaire'")->fetchColumn();
$total_actifs = $db->query("SELECT COUNT(*) FROM utilisateur WHERE statut = 1")->fetchColumn();
$total_inactifs = $db->query("SELECT COUNT(*) FROM utilisateur WHERE statut = 0")->fetchColumn();
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
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px; margin-bottom: 25px;
    }
    .stat-card {
        background: var(--white); padding: 18px; border-radius: var(--radius);
        display: flex; align-items: center; gap: 12px;
        box-shadow: var(--shadow); border: 1px solid #f0f0f0; transition: transform 0.3s;
    }
    .stat-card:hover { transform: translateY(-3px); }
    .stat-icon {
        width: 45px; height: 45px; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0;
    }
    .stat-icon.admin { background: #fce4ec; color: #c62828; }
    .stat-icon.formateur { background: #f3e5f5; color: #6a1b9a; }
    .stat-icon.secretaire { background: #e3f2fd; color: #1565c0; }
    .stat-icon.gestionnaire { background: #e8f5e9; color: #2e7d32; }
    .stat-icon.actif { background: #d4edda; color: #155724; }
    .stat-icon.inactif { background: #f5f5f5; color: #616161; }
    .stat-val { font-size: 20px; font-weight: bold; color: var(--text); }
    .stat-lbl { font-size: 12px; color: var(--text-light); }

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
    .checkbox-group {
        display: flex; align-items: center; gap: 10px;
    }
    .checkbox-group input[type="checkbox"] {
        width: 18px; height: 18px; cursor: pointer;
    }
    .checkbox-group label { font-size: 14px; color: var(--text); cursor: pointer; text-transform: none; }

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

    .role-badge, .status-badge {
        display: inline-block; padding: 4px 12px; border-radius: 20px;
        font-size: 12px; font-weight: 500;
    }
    .role-admin { background: #fce4ec; color: #c62828; }
    .role-formateur { background: #f3e5f5; color: #6a1b9a; }
    .role-secretaire { background: #e3f2fd; color: #1565c0; }
    .role-gestionnaire { background: #e8f5e9; color: #2e7d32; }
    .status-actif-badge { background: #d4edda; color: #155724; }
    .status-inactif-badge { background: #f5f5f5; color: #616161; }

    .user-avatar {
        width: 38px; height: 38px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: white; font-weight: bold; font-size: 15px; flex-shrink: 0;
    }
    .avatar-admin { background: linear-gradient(135deg, #e74c3c, #c0392b); }
    .avatar-formateur { background: linear-gradient(135deg, #8e44ad, #6c3483); }
    .avatar-secretaire { background: linear-gradient(135deg, #3498db, #2980b9); }
    .avatar-gestionnaire { background: linear-gradient(135deg, #27ae60, #229954); }
    .avatar-vous { border: 3px solid #f1c40f; }

    .text-muted { color: var(--text-light); }
    .text-you { color: #f39c12; font-weight: bold; font-size: 11px; }
    .empty-state { text-align: center; padding: 40px; color: var(--text-light); }
    .empty-icon { font-size: 48px; margin-bottom: 10px; }

    .info-box {
        background: #f0f4ff; padding: 12px 15px; border-radius: 8px;
        font-size: 13px; color: var(--text); margin-top: 15px;
        border-left: 3px solid var(--primary);
    }
    .info-box.warning { background: #fffef5; border-left-color: #f39c12; }
    .password-strength { height: 4px; border-radius: 2px; margin-top: 5px; transition: all 0.3s; }

    @media (max-width: 1100px) {
        .grid-layout { grid-template-columns: 1fr; }
    }
    @media (max-width: 600px) {
        .form-row { grid-template-columns: 1fr; }
        .stats-grid { grid-template-columns: 1fr 1fr; }
    }
    @media (max-width: 400px) {
        .stats-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <h1>👥 Gestion des Utilisateurs</h1>
</div>

<!-- Statistiques -->
<div class="stats-grid">
    <div class="stat-card" onclick="window.location='?role=admin'">
        <div class="stat-icon admin">👑</div>
        <div><div class="stat-val"><?php echo $total_admin; ?></div><div class="stat-lbl">Administrateurs</div></div>
    </div>
    <div class="stat-card" onclick="window.location='?role=formateur'">
        <div class="stat-icon formateur">👨‍🏫</div>
        <div><div class="stat-val"><?php echo $total_formateur; ?></div><div class="stat-lbl">Formateurs</div></div>
    </div>
    <div class="stat-card" onclick="window.location='?role=secretaire'">
        <div class="stat-icon secretaire">📋</div>
        <div><div class="stat-val"><?php echo $total_secretaire; ?></div><div class="stat-lbl">Secrétaires</div></div>
    </div>
    <div class="stat-card" onclick="window.location='?role=gestionnaire'">
        <div class="stat-icon gestionnaire">📊</div>
        <div><div class="stat-val"><?php echo $total_gestionnaire; ?></div><div class="stat-lbl">Gestionnaires</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon actif">✅</div>
        <div><div class="stat-val"><?php echo $total_actifs; ?></div><div class="stat-lbl">Actifs</div></div>
    </div>
    <div class="stat-card">
        <div class="stat-icon inactif">🚫</div>
        <div><div class="stat-val"><?php echo $total_inactifs; ?></div><div class="stat-lbl">Inactifs</div></div>
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
        <h3><?php echo $user_edit ? '✏️ Modifier l\'utilisateur' : '➕ Nouvel utilisateur'; ?></h3>
        <form method="POST" class="form-grid" id="userForm">
            <?php if ($user_edit): ?>
                <input type="hidden" name="id_utilisateur" value="<?php echo $user_edit['id_utilisateur']; ?>">
            <?php endif; ?>
            
            <div class="form-group">
                <label>Nom complet <span class="required">*</span></label>
                <input type="text" name="nom" placeholder="Ex: Henriette Mbula" required
                       value="<?php echo $user_edit ? htmlspecialchars($user_edit['nom']) : ''; ?>">
            </div>
            
            <div class="form-group">
                <label>Email <span class="required">*</span></label>
                <input type="email" name="email" placeholder="Ex: henriette@mcc.cd" required
                       value="<?php echo $user_edit ? htmlspecialchars($user_edit['email']) : ''; ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Mot de passe <?php echo $user_edit ? '' : '<span class="required">*</span>'; ?></label>
                    <input type="password" name="password" id="passwordInput" 
                           placeholder="<?php echo $user_edit ? 'Laisser vide pour ne pas changer' : 'Min. 6 caractères'; ?>"
                           <?php echo $user_edit ? '' : 'required'; ?>
                           onkeyup="checkPasswordStrength()">
                    <div id="passwordStrength" class="password-strength" style="width:0%;"></div>
                </div>
                <div class="form-group">
                    <label>Confirmer <?php echo $user_edit ? '' : '<span class="required">*</span>'; ?></label>
                    <input type="password" name="password_confirm" id="passwordConfirm"
                           placeholder="Répéter le mot de passe"
                           <?php echo $user_edit ? '' : 'required'; ?>
                           onkeyup="checkPasswordMatch()">
                    <small id="passwordMatchMsg" style="font-size:11px;"></small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Rôle</label>
                    <select name="role" required>
                        <option value="admin" <?php echo ($user_edit && $user_edit['role'] == 'admin') ? 'selected' : ''; ?>>👑 Administrateur</option>
                        <option value="formateur" <?php echo ($user_edit && $user_edit['role'] == 'formateur') ? 'selected' : ''; ?>>👨‍🏫 Formateur</option>
                        <option value="secretaire" <?php echo ($user_edit && $user_edit['role'] == 'secretaire') ? 'selected' : ''; ?>>📋 Secrétaire</option>
                        <option value="gestionnaire" <?php echo ($user_edit && $user_edit['role'] == 'gestionnaire') ? 'selected' : ''; ?>>📊 Gestionnaire</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Statut</label>
                    <div class="checkbox-group" style="margin-top: 8px;">
                        <input type="checkbox" name="statut" id="statutCheck" value="1" 
                               <?php echo ($user_edit && $user_edit['statut'] == 1) || !$user_edit ? 'checked' : ''; ?>>
                        <label for="statutCheck">Compte actif</label>
                    </div>
                </div>
            </div>
            
            <div class="btn-group">
                <?php if ($user_edit): ?>
                    <button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button>
                    <a href="utilisateurs.php" class="btn btn-cancel">❌ Annuler</a>
                <?php else: ?>
                    <button type="submit" name="ajouter" class="btn btn-save">➕ Créer l'utilisateur</button>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if ($user_edit && $user_edit['id_utilisateur'] == $_SESSION['user_id']): ?>
            <div class="info-box warning" style="margin-top:15px;">
                ⚠️ Vous modifiez votre propre compte.
            </div>
        <?php endif; ?>
        
        <div class="info-box" style="margin-top:15px;">
            <strong>💡 Rôles disponibles :</strong><br>
            👑 <strong>Admin</strong> : Accès complet<br>
            👨‍🏫 <strong>Formateur</strong> : Gestion des sessions<br>
            📋 <strong>Secrétaire</strong> : Inscriptions et paiements<br>
            📊 <strong>Gestionnaire</strong> : Statistiques et rapports
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- LISTE DES UTILISATEURS -->
    <!-- ============================================ -->
    <div class="card">
        <h3>
            <span>📋 Liste des utilisateurs</span>
            <div class="toolbar">
                <div class="search-box">
                    <span class="search-icon">🔍</span>
                    <input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <form method="GET" id="filterForm" style="display:flex; gap:8px; flex-wrap:wrap;">
                    <select name="role" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous les rôles</option>
                        <option value="admin" <?php echo $filter_role=='admin'?'selected':''; ?>>Admin</option>
                        <option value="formateur" <?php echo $filter_role=='formateur'?'selected':''; ?>>Formateur</option>
                        <option value="secretaire" <?php echo $filter_role=='secretaire'?'selected':''; ?>>Secrétaire</option>
                        <option value="gestionnaire" <?php echo $filter_role=='gestionnaire'?'selected':''; ?>>Gestionnaire</option>
                    </select>
                    <select name="statut_filter" class="filter-select" onchange="document.getElementById('filterForm').submit()">
                        <option value="">Tous</option>
                        <option value="1" <?php echo $filter_statut==='1'?'selected':''; ?>>Actifs</option>
                        <option value="0" <?php echo $filter_statut==='0'?'selected':''; ?>>Inactifs</option>
                    </select>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                </form>
            </div>
        </h3>
        
        <div class="table-container">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Utilisateur</th>
                        <th>Email</th>
                        <th>Rôle</th>
                        <th>Statut</th>
                        <th>Créé le</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($utilisateurs) > 0): ?>
                        <?php foreach ($utilisateurs as $user): 
                            $is_me = ($user['id_utilisateur'] == $_SESSION['user_id']);
                            $avatar_class = '';
                            switch($user['role']) {
                                case 'admin': $avatar_class = 'avatar-admin'; $role_class = 'role-admin'; break;
                                case 'formateur': $avatar_class = 'avatar-formateur'; $role_class = 'role-formateur'; break;
                                case 'secretaire': $avatar_class = 'avatar-secretaire'; $role_class = 'role-secretaire'; break;
                                case 'gestionnaire': $avatar_class = 'avatar-gestionnaire'; $role_class = 'role-gestionnaire'; break;
                            }
                            $initials = strtoupper(substr($user['nom'], 0, 1));
                        ?>
                        <tr style="<?php echo $is_me ? 'background: #fffef5;' : ''; ?>">
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <div class="user-avatar <?php echo $avatar_class; ?> <?php echo $is_me ? 'avatar-vous' : ''; ?>">
                                        <?php echo $initials; ?>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($user['nom']); ?></strong>
                                        <?php if ($is_me): ?>
                                            <span class="text-you">(Vous)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" style="color: var(--primary); text-decoration: none;">
                                    <?php echo htmlspecialchars($user['email']); ?>
                                </a>
                            </td>
                            <td><span class="role-badge <?php echo $role_class; ?>"><?php echo ucfirst($user['role']); ?></span></td>
                            <td>
                                <?php if ($user['statut'] == 1): ?>
                                    <span class="status-badge status-actif-badge">✅ Actif</span>
                                <?php else: ?>
                                    <span class="status-badge status-inactif-badge">🚫 Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted">
                                <?php echo $user['created_at'] ? date('d/m/Y', strtotime($user['created_at'])) : '-'; ?>
                            </td>
                            <td>
                                <div class="btn-group">
                                    <?php if (!$is_me): ?>
                                        <a href="?toggle=<?php echo $user['id_utilisateur']; ?>" 
                                           class="btn btn-sm" 
                                           style="background:<?php echo $user['statut']==1 ? '#e74c3c' : '#27ae60'; ?>; color:white; text-decoration:none;"
                                           title="<?php echo $user['statut']==1 ? 'Désactiver' : 'Activer'; ?>">
                                            <?php echo $user['statut']==1 ? '🚫' : '✅'; ?>
                                        </a>
                                    <?php endif; ?>
                                    <a href="?modifier=<?php echo $user['id_utilisateur']; ?>" class="btn btn-sm" style="background:#f39c12; color:white; text-decoration:none;">✏️</a>
                                    <?php if (!$is_me): ?>
                                        <a href="?supprimer=<?php echo $user['id_utilisateur']; ?>" 
                                           class="btn btn-sm" style="background:#c0392b; color:white; text-decoration:none;"
                                           onclick="return confirm('⚠️ Supprimer définitivement cet utilisateur ?\n\nCette action est irréversible.')">🗑️</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6"><div class="empty-state"><div class="empty-icon">👥</div><p>Aucun utilisateur trouvé</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 15px; font-size: 13px; color: var(--text-light); text-align: right;">
            <?php echo count($utilisateurs); ?> utilisateur(s)
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

// Force du mot de passe
function checkPasswordStrength() {
    const pwd = document.getElementById('passwordInput').value;
    const bar = document.getElementById('passwordStrength');
    let strength = 0;
    
    if (pwd.length >= 6) strength += 25;
    if (pwd.length >= 8) strength += 25;
    if (/[A-Z]/.test(pwd)) strength += 25;
    if (/[0-9!@#$%^&*]/.test(pwd)) strength += 25;
    
    bar.style.width = strength + '%';
    if (strength <= 25) bar.style.background = '#e74c3c';
    else if (strength <= 50) bar.style.background = '#f39c12';
    else if (strength <= 75) bar.style.background = '#3498db';
    else bar.style.background = '#27ae60';
}

// Correspondance des mots de passe
function checkPasswordMatch() {
    const pwd = document.getElementById('passwordInput').value;
    const confirm = document.getElementById('passwordConfirm').value;
    const msg = document.getElementById('passwordMatchMsg');
    
    if (!confirm) { msg.textContent = ''; return; }
    if (pwd === confirm) {
        msg.textContent = '✅ Les mots de passe correspondent';
        msg.style.color = '#27ae60';
    } else {
        msg.textContent = '❌ Les mots de passe ne correspondent pas';
        msg.style.color = '#e74c3c';
    }
}

// Messages
document.addEventListener('DOMContentLoaded', function() {
    const msgs = document.querySelectorAll('[style*="background:#d4edda"], [style*="background:#f8d7da"]');
    msgs.forEach(m => setTimeout(() => { m.style.opacity='0'; m.style.transition='all 0.5s'; setTimeout(()=>m.remove(),500); }, 5000));
});
</script>

</div>
</body>
</html>