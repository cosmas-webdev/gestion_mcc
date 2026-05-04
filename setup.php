<?php
$host = "127.0.0.1";
$username = "root";
$password = "";

try {
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Creer la base de donnees
    $conn->exec("CREATE DATABASE IF NOT EXISTS mcc_gestion CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
    $conn->exec("USE mcc_gestion");
    
    // Creer les tables
    $conn->exec("
        CREATE TABLE IF NOT EXISTS apprenant (
            id_apprenant INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(50),
            postnom VARCHAR(50),
            prenom VARCHAR(50),
            sexe VARCHAR(10),
            date_naissance DATE,
            telephone VARCHAR(20),
            email VARCHAR(100),
            adresse VARCHAR(150)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS formation (
            id_formation INT PRIMARY KEY AUTO_INCREMENT,
            nom_formation VARCHAR(100),
            duree VARCHAR(30),
            cout DECIMAL(10,2),
            niveau VARCHAR(30),
            description TEXT
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS inscription (
            id_inscription INT PRIMARY KEY AUTO_INCREMENT,
            date_inscription DATE,
            statut VARCHAR(30),
            id_apprenant INT,
            id_formation INT,
            FOREIGN KEY (id_apprenant) REFERENCES apprenant(id_apprenant),
            FOREIGN KEY (id_formation) REFERENCES formation(id_formation)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS formateur (
            id_formateur INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100),
            specialite VARCHAR(100),
            telephone VARCHAR(20),
            email VARCHAR(100)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS session (
            id_session INT PRIMARY KEY AUTO_INCREMENT,
            date_debut DATE,
            date_fin DATE,
            horaire VARCHAR(50),
            salle VARCHAR(50),
            id_formation INT,
            id_formateur INT,
            FOREIGN KEY (id_formation) REFERENCES formation(id_formation),
            FOREIGN KEY (id_formateur) REFERENCES formateur(id_formateur)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS paiement (
            id_paiement INT PRIMARY KEY AUTO_INCREMENT,
            montant DECIMAL(10,2),
            date_paiement DATE,
            mode_paiement VARCHAR(50),
            solde_restant DECIMAL(10,2),
            id_inscription INT,
            FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS certificat (
            id_certificat INT PRIMARY KEY AUTO_INCREMENT,
            date_delivrance DATE,
            mention VARCHAR(50),
            id_inscription INT UNIQUE,
            FOREIGN KEY (id_inscription) REFERENCES inscription(id_inscription)
        )
    ");
    
    $conn->exec("
        CREATE TABLE IF NOT EXISTS utilisateur (
            id_utilisateur INT PRIMARY KEY AUTO_INCREMENT,
            nom VARCHAR(100) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            role ENUM('admin','formateur','secretaire','gestionnaire') DEFAULT 'secretaire',
            statut TINYINT(1) DEFAULT 1,
            email_verified_at DATETIME,
            remember_token VARCHAR(100),
            created_at TIMESTAMP NULL,
            updated_at TIMESTAMP NULL
        )
    ");
    
    // Inserer un utilisateur admin par defaut
    $password_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT IGNORE INTO utilisateur (nom, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Administrateur', 'admin@mcc.cd', $password_hash, 'admin']);
    
    // Inserer quelques donnees de test
    $stmt = $conn->prepare("INSERT IGNORE INTO formateur (nom, specialite, telephone, email) VALUES (?, ?, ?, ?)");
    $stmt->execute(['Jean Dupont', 'Informatique', '+243123456789', 'jean@example.com']);
    $stmt->execute(['Marie Lambert', 'Gestion', '+243987654321', 'marie@example.com']);
    
    $stmt = $conn->prepare("INSERT IGNORE INTO formation (nom_formation, duree, cout, niveau, description) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['Developpement Web', '6 mois', 500.00, 'Debutant', 'Formation en developpement web full stack']);
    $stmt->execute(['Bureautique', '3 mois', 200.00, 'Debutant', 'Formation en outils bureautiques']);
    $stmt->execute(['Reseaux', '4 mois', 400.00, 'Intermediaire', 'Formation en administration reseaux']);
    
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Installation MCC Gestion</title>
        <style>
            body { font-family: Arial; background: #f0f2f5; display: flex; justify-content: center; align-items: center; height: 100vh; }
            .container { background: white; padding: 40px; border-radius: 10px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; max-width: 500px; }
            h2 { color: #667eea; }
            .success { color: #27ae60; font-size: 48px; }
            .btn { display: inline-block; margin-top: 20px; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
            .info { background: #e8f4fd; padding: 15px; margin: 20px 0; border-radius: 5px; text-align: left; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="success">OK</div>
            <h2>Installation reussie!</h2>
            <p>La base de donnees et les tables ont ete crees avec succes.</p>
            <div class="info">
                <strong>Informations de connexion:</strong><br>
                Email: admin@mcc.cd<br>
                Mot de passe: admin123
            </div>
            <a href="index.php" class="btn">Acceder a la connexion</a>
        </div>
    </body>
    </html>';
    
} catch(PDOException $e) {
    echo '<div style="color: red; padding: 20px;">';
    echo '<h2>Erreur d\'installation</h2>';
    echo '<p>' . $e->getMessage() . '</p>';
    echo '</div>';
}
?>
