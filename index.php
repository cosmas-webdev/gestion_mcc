<?php
session_start();

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';

$error = '';
$success = '';
$email_value = '';

// Protection CSRF - Génération d'un token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Limitation des tentatives de connexion
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Vérifier si l'utilisateur est bloqué temporairement
$blocked = false;
$block_time = 300; // 5 minutes de blocage
if ($_SESSION['login_attempts'] >= 5) {
    $time_since_last_attempt = time() - $_SESSION['last_attempt_time'];
    if ($time_since_last_attempt < $block_time) {
        $blocked = true;
        $remaining_time = ceil(($block_time - $time_since_last_attempt) / 60);
        $error = "Compte temporairement bloqué. Réessayez dans {$remaining_time} minute(s).";
    } else {
        // Réinitialiser le compteur après le temps de blocage
        $_SESSION['login_attempts'] = 0;
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$blocked) {
    // Vérification du token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité. Veuillez réessayer.';
    } else {
        // Récupération et nettoyage des données
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $email = filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        $email_value = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        
        // Validation des champs
        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } elseif (strlen($password) < 4) {
            $error = 'Le mot de passe doit contenir au moins 4 caractères.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                if ($db) {
                    $query = "SELECT * FROM utilisateur WHERE email = :email AND statut = 1 LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt->execute();
                    
                    if ($stmt->rowCount() > 0) {
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Vérification du mot de passe
                        $password_valid = password_verify($password, $user['password']);
                        
                        // Support temporaire des anciens mots de passe non hashés (à supprimer en production)
                        if (!$password_valid && $password === $user['password']) {
                            $password_valid = true;
                            // Mise à jour automatique vers le hash
                            $new_hash = password_hash($password, PASSWORD_DEFAULT);
                            $updateStmt = $db->prepare("UPDATE utilisateur SET password = :password WHERE id_utilisateur = :id");
                            $updateStmt->execute([
                                ':password' => $new_hash,
                                ':id' => $user['id_utilisateur']
                            ]);
                        }
                        
                        if ($password_valid) {
                            // Régénération de l'ID de session pour éviter la fixation de session
                            session_regenerate_id(true);
                            
                            // Connexion réussie
                            $_SESSION['user_id'] = $user['id_utilisateur'];
                            $_SESSION['user_nom'] = $user['nom'];
                            $_SESSION['user_role'] = $user['role'];
                            $_SESSION['user_email'] = $user['email'];
                            $_SESSION['login_time'] = time();
                            $_SESSION['last_activity'] = time();
                            
                            // Réinitialiser les tentatives
                            $_SESSION['login_attempts'] = 0;
                            unset($_SESSION['last_attempt_time']);
                            
                            // Régénération du token CSRF
                            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                            
                            // Gestion du "Se souvenir de moi"
                            if ($remember) {
                                $token = bin2hex(random_bytes(32));
                                $expiry = time() + (30 * 24 * 60 * 60); // 30 jours
                                
                                // Stocker le token dans la base de données
                                $updateTokenStmt = $db->prepare("UPDATE utilisateur SET remember_token = :token, updated_at = NOW() WHERE id_utilisateur = :id");
                                $updateTokenStmt->execute([
                                    ':token' => $token,
                                    ':id' => $user['id_utilisateur']
                                ]);
                                
                                // Définir le cookie
                                setcookie('remember_token', $token, $expiry, '/', '', true, true);
                            }
                            
                            // Journalisation de la connexion
                            $logFile = __DIR__ . '/logs/login.log';
                            $logDir = dirname($logFile);
                            if (!is_dir($logDir)) {
                                mkdir($logDir, 0755, true);
                            }
                            $logMessage = date('Y-m-d H:i:s') . " - Connexion réussie - User: {$user['email']} - IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
                            file_put_contents($logFile, $logMessage, FILE_APPEND);
                            
                            // Redirection vers le tableau de bord
                            header('Location: dashboard.php');
                            exit();
                        } else {
                            $_SESSION['login_attempts']++;
                            $_SESSION['last_attempt_time'] = time();
                            $remaining = 5 - $_SESSION['login_attempts'];
                            $error = "Mot de passe incorrect. Il vous reste {$remaining} tentative(s).";
                        }
                    } else {
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt_time'] = time();
                        $remaining = 5 - $_SESSION['login_attempts'];
                        $error = "Email ou mot de passe incorrect. Il vous reste {$remaining} tentative(s).";
                    }
                } else {
                    $error = 'Erreur de connexion à la base de données. Veuillez réessayer plus tard.';
                }
            } catch (PDOException $e) {
                // En production, ne pas afficher les détails de l'erreur
                error_log("Erreur de connexion: " . $e->getMessage());
                $error = 'Une erreur est survenue. Veuillez réessayer plus tard.';
            }
        }
    }
}

// Vérification du cookie "Se souvenir de moi"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($db) {
            $token = $_COOKIE['remember_token'];
            $query = "SELECT * FROM utilisateur WHERE remember_token = :token AND statut = 1 LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':token', $token, PDO::PARAM_STR);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                session_regenerate_id(true);
                $_SESSION['user_id'] = $user['id_utilisateur'];
                $_SESSION['user_nom'] = $user['nom'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                header('Location: dashboard.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Erreur remember token: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MCC Gestion - Système de gestion de centre de formation">
    <meta name="robots" content="noindex, nofollow">
    <title>MCC Gestion - Connexion</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🏫</text></svg>">
    <style>
        /* Styles spécifiques à la page de connexion */
        .login-container {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .login-main {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-box {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 450px;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header .logo {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .login-header h2 {
            color: #2c3e50;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group .input-icon {
            position: relative;
        }
        
        .form-group .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 18px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 12px 12px 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input.error-input {
            border-color: #e74c3c;
        }
        
        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #2c3e50;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
        }
        
        .forgot-password {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .forgot-password:hover {
            color: #764ba2;
            text-decoration: underline;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .btn-login.loading .btn-text {
            display: none;
        }
        
        .btn-login.loading .spinner {
            display: block;
        }
        
        .login-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .login-footer p {
            color: #7f8c8d;
            font-size: 13px;
        }
        
        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
        }
        
        .login-footer a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        
        .quick-links {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        
        .quick-links a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            padding: 5px 10px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        
        .quick-links a:hover {
            background: #f0f0ff;
        }
        
        .password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #95a5a6;
            font-size: 18px;
            background: none;
            border: none;
            padding: 0;
        }
        
        .password-toggle:hover {
            color: #2c3e50;
        }
        
        /* Responsive */
        @media (max-width: 480px) {
            .login-box {
                padding: 25px;
                margin: 10px;
            }
            
            .login-header h2 {
                font-size: 20px;
            }
            
            .remember-forgot {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- Barre de navigation -->
        <nav class="navbar">
            <a href="index.php" class="active">🏠 Accueil</a>
            <a href="apprenants.php">📚 Apprenants</a>
            <a href="formations.php">🎓 Formations</a>
            <a href="inscriptions.php">📝 Inscriptions</a>
            <a href="formateurs.php">👨‍🏫 Formateurs</a>
            <a href="sessions.php">📅 Sessions</a>
            <a href="paiements.php">💰 Paiements</a>
            <a href="certificats.php">📜 Certificats</a>
            <a href="utilisateurs.php">👥 Utilisateurs</a>
        </nav>
        
        <!-- Contenu principal -->
        <main class="login-main">
            <div class="login-box">
                <div class="login-header">
                    <div class="logo">🏫</div>
                    <h2>MCC Gestion</h2>
                    <p>Centre de Formation Professionnelle</p>
                </div>
                
                <!-- Messages d'erreur/succès -->
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
                    <div class="alert alert-info">
                        ⏰ Votre session a expiré. Veuillez vous reconnecter.
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_GET['logout']) && $_GET['logout'] == 1): ?>
                    <div class="alert alert-success">
                        ✅ Vous avez été déconnecté avec succès.
                    </div>
                <?php endif; ?>
                
                <!-- Formulaire de connexion -->
                <form method="POST" id="loginForm" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-group">
                        <label for="email">Adresse email</label>
                        <div class="input-icon">
                            <span>📧</span>
                            <input type="email" 
                                   id="email" 
                                   name="email" 
                                   placeholder="exemple@email.com" 
                                   value="<?php echo $email_value; ?>"
                                   required 
                                   autocomplete="email"
                                   <?php echo $blocked ? 'disabled' : ''; ?>>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Mot de passe</label>
                        <div class="input-icon">
                            <span>🔒</span>
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   placeholder="Votre mot de passe" 
                                   required 
                                   autocomplete="current-password"
                                   <?php echo $blocked ? 'disabled' : ''; ?>>
                            <button type="button" class="password-toggle" onclick="togglePassword()" title="Afficher/Masquer le mot de passe">
                                👁️
                            </button>
                        </div>
                    </div>
                    
                    <div class="remember-forgot">
                        <label class="remember-me">
                            <input type="checkbox" name="remember" value="1">
                            <span>Se souvenir de moi</span>
                        </label>
                        <a href="#" class="forgot-password" onclick="alert('Contactez l\'administrateur pour réinitialiser votre mot de passe.'); return false;">
                            Mot de passe oublié ?
                        </a>
                    </div>
                    
                    <button type="submit" class="btn-login" id="loginButton" <?php echo $blocked ? 'disabled' : ''; ?>>
                        <span class="btn-text">🔐 Se connecter</span>
                        <div class="spinner"></div>
                    </button>
                </form>
                
                <!-- Liens rapides -->
                <div class="login-footer">
                    <p>Liens rapides :</p>
                    <div class="quick-links">
                        <a href="apprenants.php">📚 Apprenants</a>
                        <a href="formations.php">🎓 Formations</a>
                        <a href="inscriptions.php">📝 Inscriptions</a>
                        <a href="setup.php">⚙️ Installation</a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Afficher/Masquer le mot de passe
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleBtn = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }
        
        // Animation du bouton de connexion
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const button = document.getElementById('loginButton');
            button.classList.add('loading');
            
            // Désactiver le bouton pour éviter les doubles soumissions
            setTimeout(() => {
                button.disabled = true;
            }, 100);
        });
        
        // Réactiver le bouton si la page est rechargée avec une erreur
        window.addEventListener('pageshow', function() {
            const button = document.getElementById('loginButton');
            button.classList.remove('loading');
            button.disabled = false;
        });
        
        // Focus automatique sur le champ email
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            } else {
                document.getElementById('password').focus();
            }
        });
        
        // Empêcher la resoumission du formulaire
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>