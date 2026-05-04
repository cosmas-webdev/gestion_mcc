<?php
session_start();

// Redirection si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once 'config/database.php';

$error = '';
$email_value = '';

// Protection CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Limitation des tentatives (5 essais max, blocage 5 minutes)
if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

$blocked = false;
$block_time = 300;

if ($_SESSION['login_attempts'] >= 5) {
    $time_since = time() - $_SESSION['last_attempt_time'];
    if ($time_since < $block_time) {
        $blocked = true;
        $remaining = ceil(($block_time - $time_since) / 60);
        $error = "⏳ Compte bloqué. Réessayez dans {$remaining} min.";
    } else {
        $_SESSION['login_attempts'] = 0;
    }
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '⚠️ Session expirée, rafraîchissez la page.';
    } else {
        $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        $email_value = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        
        if (!$email || empty($password)) {
            $error = '⚠️ Email et mot de passe requis.';
        } else {
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                $stmt = $db->prepare("SELECT * FROM utilisateur WHERE email = :email AND statut = 1 LIMIT 1");
                $stmt->execute([':email' => $email]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    
                    $_SESSION['user_id'] = $user['id_utilisateur'];
                    $_SESSION['user_nom'] = $user['nom'];
                    $_SESSION['user_role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    $_SESSION['login_attempts'] = 0;
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    if ($remember) {
                        $token = bin2hex(random_bytes(32));
                        $db->prepare("UPDATE utilisateur SET remember_token = :token WHERE id_utilisateur = :id")
                           ->execute([':token' => $token, ':id' => $user['id_utilisateur']]);
                        setcookie('remember_token', $token, time() + 86400 * 30, '/', '', true, true);
                    }
                    
                    header('Location: dashboard.php');
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $_SESSION['last_attempt_time'] = time();
                    $remaining = 5 - $_SESSION['login_attempts'];
                    $error = "❌ Identifiants incorrects. {$remaining} tentative(s) restante(s).";
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error = '⚠️ Erreur système. Réessayez.';
            }
        }
    }
}

// Auto-connexion via cookie remember
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $stmt = $db->prepare("SELECT * FROM utilisateur WHERE remember_token = :token AND statut = 1 LIMIT 1");
        $stmt->execute([':token' => $_COOKIE['remember_token']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id_utilisateur'];
            $_SESSION['user_nom'] = $user['nom'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['login_time'] = time();
            header('Location: dashboard.php');
            exit();
        }
    } catch (PDOException $e) {
        error_log("Remember token error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MCC Gestion - Connexion</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%), #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        /* Fond décoratif */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 50%, rgba(102,126,234,0.06) 0%, transparent 50%),
                        radial-gradient(circle at 70% 50%, rgba(118,75,162,0.04) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        /* Carte de connexion */
        .login-card {
            position: relative;
            z-index: 1;
            background: white;
            border-radius: 20px;
            padding: 45px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.06), 0 2px 10px rgba(0,0,0,0.03);
            animation: fadeUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* En-tête */
        .brand {
            text-align: center;
            margin-bottom: 32px;
        }

        .brand .logo-icon {
            font-size: 48px;
            margin-bottom: 8px;
            display: block;
        }

        .brand h1 {
            font-size: 24px;
            color: #1e293b;
            font-weight: 700;
            letter-spacing: -0.3px;
        }

        .brand p {
            font-size: 13px;
            color: #94a3b8;
            margin-top: 4px;
        }

        /* Messages */
        .msg {
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { opacity: 0; transform: translateX(-10px); }
            to { opacity: 1; transform: translateX(0); }
        }

        .msg-error { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
        .msg-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .msg-info { background: #eff6ff; color: #1e40af; border: 1px solid #bfdbfe; }

        /* Champs */
        .field {
            margin-bottom: 18px;
        }

        .field label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 6px;
            letter-spacing: 0.2px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap .ico {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 16px;
            pointer-events: none;
            opacity: 0.7;
        }

        .input-wrap input {
            width: 100%;
            padding: 13px 16px 13px 46px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            color: #1e293b;
            background: #f8fafc;
            transition: all 0.2s ease;
            outline: none;
            font-family: inherit;
        }

        .input-wrap input:focus {
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.06);
        }

        .input-wrap input::placeholder { color: #94a3b8; }
        .input-wrap input:disabled { opacity: 0.5; cursor: not-allowed; }

        .toggle-pwd {
            position: absolute;
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 16px;
            color: #94a3b8;
            padding: 6px;
            border-radius: 6px;
            transition: all 0.2s;
        }

        .toggle-pwd:hover { color: #475569; background: #f1f5f9; }

        /* Options */
        .options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            font-size: 13px;
        }

        .options .remember {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #475569;
            cursor: pointer;
            user-select: none;
        }

        .options .remember input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #667eea;
            border-radius: 4px;
        }

        .options .forgot {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
            cursor: pointer;
        }

        .options .forgot:hover { color: #5a6fd6; text-decoration: underline; }

        /* Bouton */
        .btn {
            width: 100%;
            padding: 14px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-family: inherit;
            letter-spacing: 0.2px;
        }

        .btn:hover { 
            background: #5a6fd6; 
            transform: translateY(-2px); 
            box-shadow: 0 8px 25px rgba(102,126,234,0.3); 
        }

        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; box-shadow: none; }

        /* Pied de page */
        .login-footer {
            text-align: center;
            margin-top: 28px;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .login-footer p {
            font-size: 12px;
            color: #94a3b8;
            line-height: 1.6;
        }

        .login-footer a {
            color: #667eea;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .login-footer a:hover { color: #5a6fd6; }

        /* Modal mot de passe oublié */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(2px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-overlay.active { display: flex; }

        .modal-box {
            background: white;
            border-radius: 16px;
            padding: 35px 30px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            animation: scaleIn 0.3s ease;
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
        }

        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-box .modal-icon { font-size: 40px; margin-bottom: 10px; }
        .modal-box h3 { font-size: 18px; color: #1e293b; margin-bottom: 8px; }
        .modal-box p { font-size: 13px; color: #64748b; margin-bottom: 8px; line-height: 1.6; }
        .modal-box .contact-email {
            display: inline-block;
            background: #f0f4ff;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            font-size: 14px;
            margin: 8px 0;
            transition: all 0.2s;
        }
        .modal-box .contact-email:hover { background: #e0e7ff; }
        .modal-box .btn-close {
            margin-top: 15px;
            padding: 10px 30px;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            color: #475569;
            font-weight: 500;
            transition: all 0.2s;
        }
        .modal-box .btn-close:hover { background: #e2e8f0; }

        /* Responsive */
        @media (max-width: 480px) {
            .login-card { padding: 35px 24px; border-radius: 16px; }
            .brand .logo-icon { font-size: 40px; }
            .brand h1 { font-size: 20px; }
            .options { flex-direction: column; gap: 12px; align-items: flex-start; }
        }
    </style>
</head>
<body>

    <!-- Carte de connexion -->
    <div class="login-card">

        <!-- En-tête -->
        <div class="brand">
            <span class="logo-icon">🏫</span>
            <h1>MCC Gestion</h1>
            <p>Centre de Formation Professionnelle</p>
        </div>

        <!-- Messages -->
        <?php if ($error): ?>
            <div class="msg msg-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="msg msg-info">⏰ Session expirée. Veuillez vous reconnecter.</div>
        <?php endif; ?>

        <?php if (isset($_GET['logout'])): ?>
            <div class="msg msg-success">✅ Vous avez été déconnecté avec succès.</div>
        <?php endif; ?>

        <!-- Formulaire -->
        <form method="POST" id="loginForm" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="field">
                <label for="email">Adresse email</label>
                <div class="input-wrap">
                    <span class="ico">📧</span>
                    <input type="email" id="email" name="email" 
                           placeholder="admin@mcc.cd"
                           value="<?php echo $email_value; ?>" 
                           required
                           <?php echo $blocked ? 'disabled' : ''; ?>>
                </div>
            </div>

            <div class="field">
                <label for="password">Mot de passe</label>
                <div class="input-wrap">
                    <span class="ico">🔒</span>
                    <input type="password" id="password" name="password" 
                           placeholder="••••••••" 
                           required
                           <?php echo $blocked ? 'disabled' : ''; ?>>
                    <button type="button" class="toggle-pwd" onclick="togglePassword()" title="Afficher/Masquer">👁️</button>
                </div>
            </div>

            <div class="options">
                <label class="remember">
                    <input type="checkbox" name="remember"> Se souvenir de moi
                </label>
                <a class="forgot" onclick="openForgotModal()">Mot de passe oublié ?</a>
            </div>

            <button type="submit" class="btn" id="loginBtn" <?php echo $blocked ? 'disabled' : ''; ?>>
                🔐 Se connecter
            </button>
        </form>

        <!-- Pied -->
        <div class="login-footer">
            <p>© <?php echo date('Y'); ?> MCC Gestion. Tous droits réservés.</p>
        </div>

    </div>

    <!-- Modal Mot de passe oublié -->
    <div class="modal-overlay" id="forgotModal">
        <div class="modal-box">
            <div class="modal-icon">🔑</div>
            <h3>Mot de passe oublié ?</h3>
            <p>Veuillez contacter l'administratrice :</p>
            <p style="font-weight: 600; color: #1e293b; font-size: 15px;">Henriette Mbula Christelle</p>
            <a href="mailto:henriettembulachristelle2@gmail.com" class="contact-email">
                ✉️ henriettembulachristelle2@gmail.com
            </a>
            <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">
                Elle vous assistera pour réinitialiser votre accès.
            </p>
            <button class="btn-close" onclick="closeForgotModal()">Fermer</button>
        </div>
    </div>

    <script>
        // Afficher/Masquer mot de passe
        function togglePassword() {
            const pwd = document.getElementById('password');
            const btn = document.querySelector('.toggle-pwd');
            if (pwd.type === 'password') {
                pwd.type = 'text';
                btn.textContent = '🙈';
            } else {
                pwd.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // Modal mot de passe oublié
        function openForgotModal() {
            document.getElementById('forgotModal').classList.add('active');
        }

        function closeForgotModal() {
            document.getElementById('forgotModal').classList.remove('active');
        }

        // Fermer le modal en cliquant à l'extérieur
        document.getElementById('forgotModal').addEventListener('click', function(e) {
            if (e.target === this) closeForgotModal();
        });

        // Fermer avec la touche Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeForgotModal();
        });

        // Animation bouton connexion
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            if (!btn.disabled) {
                btn.textContent = '⏳ Connexion en cours...';
                btn.disabled = true;
            }
        });

        // Focus automatique
        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('email');
            if (email && !email.value && !email.disabled) {
                email.focus();
            } else {
                const pwd = document.getElementById('password');
                if (pwd && !pwd.disabled) pwd.focus();
            }
        });

        // Empêcher la resoumission
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>
</body>
</html>