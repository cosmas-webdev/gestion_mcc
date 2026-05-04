<?php
session_start();

// Récupérer les infos avant destruction
$user_nom = $_SESSION['user_nom'] ?? 'Utilisateur';
$user_id = $_SESSION['user_id'] ?? null;

// Supprimer le cookie "Se souvenir de moi" si présent
if (isset($_COOKIE['remember_token'])) {
    // Supprimer le token de la base de données si possible
    if ($user_id) {
        try {
            require_once 'config/database.php';
            $database = new Database();
            $db = $database->getConnection();
            $stmt = $db->prepare("UPDATE utilisateur SET remember_token = NULL WHERE id_utilisateur = :id");
            $stmt->execute([':id' => $user_id]);
        } catch (Exception $e) {
            // Silencieux : la session sera détruite de toute façon
        }
    }
    
    // Supprimer le cookie
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Journalisation de la déconnexion
try {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $logMessage = date('Y-m-d H:i:s') . " - Déconnexion - User: {$user_nom} - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'inconnue') . "\n";
    file_put_contents($logDir . '/login.log', $logMessage, FILE_APPEND);
} catch (Exception $e) {
    // Silencieux
}

// Détruire complètement la session
$_SESSION = [];

// Supprimer le cookie de session
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Détruire la session
session_destroy();

// Redirection propre avec message
header('Location: index.php?logout=1');
exit();
?>