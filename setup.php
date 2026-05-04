<?php
$host = "127.0.0.1";
$username = "root";
$password = "";

// ============================================
// ÉTAPE 1 : Vérification de l'environnement
// ============================================
$errors = [];
$warnings = [];

// Vérifier la version PHP
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    $errors[] = "PHP 7.4 ou supérieur requis. Version actuelle : " . PHP_VERSION;
}

// Vérifier les extensions PHP requises
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'session'];
foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $errors[] = "Extension PHP manquante : {$ext}";
    }
}

// Vérifier si PDO MySQL est disponible
if (!in_array('mysql', PDO::getAvailableDrivers())) {
    $errors[] = "Le driver PDO MySQL n'est pas disponible.";
}

// Si des erreurs bloquantes, les afficher
if (!empty($errors)) {
    showErrorPage($errors, []);
    exit;
}

// ============================================
// ÉTAPE 2 : Installation
// ============================================
try {
    // Connexion au serveur MySQL
    $conn = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    
    // Vérifier la version MySQL
    $mysql_version = $conn->query("SELECT VERSION()")->fetchColumn();
    if (version_compare($mysql_version, '5.7.0', '<')) {
        $warnings[] = "MySQL 5.7+ recommandé. Version actuelle : {$mysql_version}";
    }
    
    // Créer la base de données
    $conn->exec("CREATE DATABASE IF NOT EXISTS mcc_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->exec("USE mcc_gestion");
    
    // ============================================
    // Création des tables
    // ============================================
    $tables_created = [];
    $tables_existing = [];
    
    // Table apprenant
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS apprenant (
            id_apprenant INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(50) NOT NULL,
            postnom VARCHAR(50),
            prenom VARCHAR(50) NOT NULL,
            sexe ENUM('M', 'F') DEFAULT 'M',
            date_naissance DATE,
            telephone VARCHAR(20),
            email VARCHAR(100),
            adresse VARCHAR(150),
            INDEX idx_nom (nom),
            INDEX idx_prenom (prenom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'apprenant'; else $tables_existing[] = 'apprenant';
    
    // Table formation
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS formation (
            id_formation INT PRIMARY KEY AUTO_INCREMENT,
            nom_formation VARCHAR(100) NOT NULL,
            duree VARCHAR(30) NOT NULL,
            cout DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            niveau VARCHAR(30) DEFAULT 'Debutant',
            description TEXT,
            INDEX idx_nom_formation (nom_formation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'formation'; else $tables_existing[] = 'formation';
    
    // Table formateur
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS formateur (
            id_formateur INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            specialite VARCHAR(100) NOT NULL,
            telephone VARCHAR(20),
            email VARCHAR(100),
            INDEX idx_nom_formateur (nom)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'formateur'; else $tables_existing[] = 'formateur';
    
    // Table inscription
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS inscription (
            id_inscription INT PRIMARY KEY AUTO_INCREMENT,
            date_inscription DATE NOT NULL,
            statut VARCHAR(30) DEFAULT 'En attente',
            id_apprenant INT NOT NULL,
            id_formation INT NOT NULL,
            FOREIGN KEY (id_apprenant) REFERENCES apprenant(id_apprenant) ON DELETE RESTRICT,
            FOREIGN KEY (id_formation) REFERENCES formation(id_formation) ON DELETE RESTRICT,
            INDEX idx_date (date_inscription),
            INDEX idx_statut (statut)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'inscription'; else $tables_existing[] = 'inscription';
    
    // Table session
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS session (
            id_session INT PRIMARY KEY AUTO_INCREMENT,
            date_debut DATE NOT NULL,
            date_fin DATE NOT NULL,
            horaire VARCHAR(50),
            salle VARCHAR(50),
            id_formation INT NOT NULL,
            id_formateur INT NOT NULL,
            FOREIGN KEY (id_formation) REFERENCES formation(id_formation) ON DELETE RESTRICT,
            FOREIGN KEY (id_formateur) REFERENCES formateur(id_formateur) ON DELETE RESTRICT,
            INDEX idx_dates (date_debut, date_fin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'session'; else $tables_existing[] = 'session';
    
    // Table paiement
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS paiement (
            id_paiement INT PRIMARY KEY AUTO_INCREMENT,
            montant DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            date_paiement DATE NOT NULL,
            mode_paiement VARCHAR(50) DEFAULT 'Especes',
            solde_restant DECIMAL(10,2) DEFAULT 0.00,
            id_inscription INT NOT NULL,
            FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription) ON DELETE RESTRICT,
            INDEX idx_date_paiement (date_paiement)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'paiement'; else $tables_existing[] = 'paiement';
    
    // Table certificat
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS certificat (
            id_certificat INT PRIMARY KEY AUTO_INCREMENT,
            date_delivrance DATE NOT NULL,
            mention VARCHAR(50),
            id_inscription INT NOT NULL UNIQUE,
            FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription) ON DELETE RESTRICT,
            INDEX idx_date_delivrance (date_delivrance)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'certificat'; else $tables_existing[] = 'certificat';
    
    // Table utilisateur
    $result = $conn->exec("
        CREATE TABLE IF NOT EXISTS utilisateur (
            id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','formateur','secretaire','gestionnaire') DEFAULT 'secretaire',
            statut TINYINT(1) DEFAULT 1,
            email_verified_at DATETIME NULL,
            remember_token VARCHAR(100) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    if ($result === 0) $tables_created[] = 'utilisateur'; else $tables_existing[] = 'utilisateur';
    
    // ============================================
    // Insertion des données par défaut
    // ============================================
    $data_inserted = [];
    
    // Admin par défaut
    $stmt = $conn->prepare("SELECT COUNT(*) FROM utilisateur WHERE email = ?");
    $stmt->execute(['admin@mcc.cd']);
    if ($stmt->fetchColumn() == 0) {
        $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->prepare("INSERT INTO utilisateur (nom, email, password, role) VALUES (?, ?, ?, ?)")
             ->execute(['Administrateur', 'admin@mcc.cd', $password_hash, 'admin']);
        $data_inserted[] = 'Admin (admin@mcc.cd / admin123)';
    }
    
    // Formateurs de test
    $stmt = $conn->prepare("SELECT COUNT(*) FROM formateur");
    if ($stmt->fetchColumn() == 0) {
        $conn->prepare("INSERT INTO formateur (nom, specialite, telephone, email) VALUES (?, ?, ?, ?)")
             ->execute(['Jean Dupont', 'Informatique', '+243123456789', 'jean@example.com']);
        $conn->prepare("INSERT INTO formateur (nom, specialite, telephone, email) VALUES (?, ?, ?, ?)")
             ->execute(['Marie Lambert', 'Gestion', '+243987654321', 'marie@example.com']);
        $data_inserted[] = '2 formateurs de test';
    }
    
    // Formations de test
    $stmt = $conn->prepare("SELECT COUNT(*) FROM formation");
    if ($stmt->fetchColumn() == 0) {
        $conn->prepare("INSERT INTO formation (nom_formation, duree, cout, niveau, description) VALUES (?, ?, ?, ?, ?)")
             ->execute(['Développement Web', '6 mois', 150.00, 'Débutant', 'Formation complète en développement web full stack (HTML, CSS, JavaScript, PHP, MySQL)']);
        $conn->prepare("INSERT INTO formation (nom_formation, duree, cout, niveau, description) VALUES (?, ?, ?, ?, ?)")
             ->execute(['Bureautique', '3 mois', 80.00, 'Débutant', 'Formation en outils bureautiques (Word, Excel, PowerPoint, Outlook)']);
        $conn->prepare("INSERT INTO formation (nom_formation, duree, cout, niveau, description) VALUES (?, ?, ?, ?, ?)")
             ->execute(['Réseaux Informatiques', '4 mois', 120.00, 'Intermédiaire', 'Formation en administration réseaux et sécurité informatique']);
        $data_inserted[] = '3 formations de test';
    }
    
    // Créer le dossier logs
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // ============================================
    // Afficher la page de succès
    // ============================================
    showSuccessPage($tables_created, $tables_existing, $data_inserted, $warnings, $mysql_version, PHP_VERSION);
    
} catch (PDOException $e) {
    $error_details = [];
    $error_code = $e->getCode();
    
    switch ($error_code) {
        case 1045:
            $error_details[] = "Accès refusé : vérifiez le nom d'utilisateur et le mot de passe MySQL.";
            break;
        case 2002:
            $error_details[] = "Impossible de se connecter au serveur MySQL. Vérifiez que MySQL est démarré.";
            break;
        case 1049:
            $error_details[] = "Base de données introuvable.";
            break;
        default:
            $error_details[] = $e->getMessage();
    }
    
    showErrorPage([$e->getMessage()], $error_details);
}

// ============================================
// FONCTIONS D'AFFICHAGE
// ============================================

function showSuccessPage($created, $existing, $data, $warnings, $mysql_ver, $php_ver) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Installation réussie - MCC Gestion</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background: linear-gradient(135deg, #667eea15, #764ba215), #f5f6fa;
                min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
                padding: 20px;
            }
            .setup-card {
                background: white;
                border-radius: 20px;
                padding: 45px 40px;
                max-width: 580px;
                width: 100%;
                box-shadow: 0 25px 80px rgba(0,0,0,.06);
                animation: fadeUp .5s ease;
            }
            @keyframes fadeUp { from { opacity:0; transform:translateY(30px); } to { opacity:1; transform:translateY(0); } }
            .success-icon { font-size: 56px; text-align: center; margin-bottom: 10px; }
            h1 { text-align: center; color: #1e293b; font-size: 24px; margin-bottom: 6px; }
            .subtitle { text-align: center; color: #94a3b8; font-size: 14px; margin-bottom: 28px; }
            
            .info-section {
                background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px;
                padding: 18px 20px; margin-bottom: 20px;
            }
            .info-section h3 { font-size: 14px; color: #334155; margin-bottom: 10px; }
            .info-section .row { display: flex; justify-content: space-between; font-size: 13px; padding: 4px 0; }
            .info-section .row .label { color: #64748b; }
            .info-section .row .value { color: #1e293b; font-weight: 500; }
            
            .credentials-box {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: white; border-radius: 12px; padding: 20px;
                margin-bottom: 24px; text-align: center;
            }
            .credentials-box h3 { font-size: 14px; margin-bottom: 12px; opacity: .9; }
            .credentials-box .cred { 
                background: rgba(255,255,255,.15); border-radius: 8px;
                padding: 10px; margin-bottom: 8px; font-size: 14px;
            }
            .credentials-box .cred strong { display: block; font-size: 11px; opacity: .7; margin-bottom: 2px; text-transform: uppercase; letter-spacing: .5px; }
            
            .checklist { margin-bottom: 20px; }
            .checklist .item { display: flex; align-items: center; gap: 8px; font-size: 13px; padding: 6px 0; color: #334155; }
            .checklist .item .icon { font-size: 16px; }
            
            .warnings { background: #fef9c3; border: 1px solid #fde047; border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; font-size: 13px; color: #854d0e; }
            .warnings ul { list-style: none; padding: 0; }
            .warnings li { padding: 3px 0; }
            .warnings li::before { content: '⚠️ '; }
            
            .btn {
                display: block; width: 100%; text-align: center;
                padding: 14px; background: #667eea; color: white;
                text-decoration: none; border-radius: 12px;
                font-size: 15px; font-weight: 600;
                transition: all .2s;
            }
            .btn:hover { background: #5a6fd6; transform: translateY(-2px); box-shadow: 0 8px 25px rgba(102,126,234,.3); }
            .btn-secondary {
                display: block; text-align: center; margin-top: 10px;
                padding: 10px; color: #64748b; text-decoration: none;
                font-size: 13px; border-radius: 8px; transition: all .2s;
            }
            .btn-secondary:hover { background: #f1f5f9; color: #334155; }
            
            .footer { text-align: center; margin-top: 20px; font-size: 11px; color: #94a3b8; }
            @media (max-width: 500px) { .setup-card { padding: 30px 22px; } }
        </style>
    </head>
    <body>
        <div class="setup-card">
            <div class="success-icon">✅</div>
            <h1>Installation réussie !</h1>
            <p class="subtitle">MCC Gestion est prêt à être utilisé</p>
            
            <?php if (!empty($warnings)): ?>
            <div class="warnings">
                <ul><?php foreach ($warnings as $w): ?><li><?php echo htmlspecialchars($w); ?></li><?php endforeach; ?></ul>
            </div>
            <?php endif; ?>
            
            <div class="credentials-box">
                <h3>🔑 Identifiants de connexion</h3>
                <div class="cred"><strong>Email</strong>admin@mcc.cd</div>
                <div class="cred"><strong>Mot de passe</strong>admin123</div>
                <p style="font-size:11px;opacity:.7;margin-top:8px;">⚠️ Pensez à changer le mot de passe après la première connexion</p>
            </div>
            
            <div class="info-section">
                <h3>📊 Résumé de l'installation</h3>
                <div class="checklist">
                    <?php foreach ($created as $t): ?>
                    <div class="item"><span class="icon">🆕</span> Table <strong><?php echo $t; ?></strong> créée</div>
                    <?php endforeach; ?>
                    <?php foreach ($existing as $t): ?>
                    <div class="item"><span class="icon">✅</span> Table <strong><?php echo $t; ?></strong> existante</div>
                    <?php endforeach; ?>
                    <?php foreach ($data as $d): ?>
                    <div class="item"><span class="icon">📝</span> <?php echo $d; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="row"><span class="label">Base de données</span><span class="value">mcc_gestion</span></div>
                <div class="row"><span class="label">MySQL</span><span class="value">v<?php echo $mysql_ver; ?></span></div>
                <div class="row"><span class="label">PHP</span><span class="value">v<?php echo $php_ver; ?></span></div>
                <div class="row"><span class="label">Collation</span><span class="value">utf8mb4_unicode_ci</span></div>
            </div>
            
            <a href="index.php" class="btn">🚀 Accéder à la connexion</a>
            <a href="setup.php" class="btn-secondary">🔄 Réinstaller</a>
            
            <div class="footer">© <?php echo date('Y'); ?> MCC Gestion · Tous droits réservés</div>
        </div>
    </body>
    </html>
    <?php
}

function showErrorPage($errors, $details) {
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Erreur d'installation - MCC Gestion</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
                background: #fef2f2; min-height: 100vh;
                display: flex; align-items: center; justify-content: center;
                padding: 20px;
            }
            .error-card {
                background: white; border-radius: 20px; padding: 45px 40px;
                max-width: 560px; width: 100%;
                box-shadow: 0 25px 80px rgba(0,0,0,.08); border: 2px solid #fecaca;
            }
            .error-icon { font-size: 56px; text-align: center; margin-bottom: 10px; }
            h1 { text-align: center; color: #991b1b; font-size: 22px; margin-bottom: 20px; }
            .error-list {
                background: #fef2f2; border: 1px solid #fecaca; border-radius: 12px;
                padding: 16px 20px; margin-bottom: 20px;
            }
            .error-list h3 { font-size: 13px; color: #991b1b; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .3px; }
            .error-list ul { list-style: none; padding: 0; }
            .error-list li { font-size: 13px; color: #7f1d1d; padding: 4px 0; }
            .error-list li::before { content: '❌ '; }
            
            .help-box {
                background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 12px;
                padding: 16px 20px; margin-bottom: 20px; font-size: 13px; color: #0c4a6e;
            }
            .help-box h3 { color: #0369a1; margin-bottom: 10px; font-size: 14px; }
            .help-box p { margin-bottom: 6px; }
            .help-box code { background: #e0f2fe; padding: 2px 6px; border-radius: 4px; font-size: 12px; }
            
            .btn { display: block; text-align: center; padding: 12px; background: #667eea; color: white; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 14px; }
            .btn:hover { background: #5a6fd6; }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">❌</div>
            <h1>Erreur d'installation</h1>
            
            <div class="error-list">
                <h3>Erreurs détectées</h3>
                <ul>
                    <?php foreach ($errors as $e): ?>
                    <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($details)): ?>
            <div class="error-list">
                <h3>Détails techniques</h3>
                <ul>
                    <?php foreach ($details as $d): ?>
                    <li><?php echo htmlspecialchars($d); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="help-box">
                <h3>💡 Solutions possibles</h3>
                <p>1. Vérifiez que <strong>MySQL</strong> est démarré dans XAMPP/WAMP</p>
                <p>2. Vérifiez les identifiants dans <code>config/database.php</code></p>
                <p>3. Assurez-vous que le port MySQL (3306) n'est pas bloqué</p>
                <p>4. Vérifiez que PHP <?php echo PHP_VERSION; ?> est compatible</p>
            </div>
            
            <a href="setup.php" class="btn">🔄 Réessayer l'installation</a>
        </div>
    </body>
    </html>
    <?php
}
?>