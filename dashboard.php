<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// ============================================
// STATISTIQUES PRINCIPALES
// ============================================
$total_apprenants = $db->query("SELECT COUNT(*) FROM apprenant")->fetchColumn();
$total_formations = $db->query("SELECT COUNT(*) FROM formation")->fetchColumn();
$total_inscriptions = $db->query("SELECT COUNT(*) FROM inscription")->fetchColumn();
$total_formateurs = $db->query("SELECT COUNT(*) FROM formateur")->fetchColumn();
$total_sessions = $db->query("SELECT COUNT(*) FROM session")->fetchColumn();
$total_paiements = $db->query("SELECT COALESCE(SUM(montant), 0) FROM paiement")->fetchColumn();

// Inscriptions par mois (6 derniers mois)
$inscriptions_par_mois = $db->query("
    SELECT DATE_FORMAT(date_inscription, '%Y-%m') as mois, COUNT(*) as total 
    FROM inscription 
    WHERE date_inscription >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY mois 
    ORDER BY mois ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Formations les plus populaires
$formations_populaires = $db->query("
    SELECT f.nom_formation, COUNT(i.id_inscription) as nb_inscriptions
    FROM formation f
    LEFT JOIN inscription i ON f.id_formation = i.id_formation
    GROUP BY f.id_formation
    ORDER BY nb_inscriptions DESC
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Statut des inscriptions
$inscriptions_actives = $db->query("SELECT COUNT(*) FROM inscription WHERE statut = 'Actif' OR statut = 'En cours'")->fetchColumn();
$inscriptions_terminees = $db->query("SELECT COUNT(*) FROM inscription WHERE statut = 'Terminé' OR statut = 'Termine'")->fetchColumn();

// Paiements du mois en cours
$paiements_mois = $db->query("
    SELECT COALESCE(SUM(montant), 0) 
    FROM paiement 
    WHERE MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE())
")->fetchColumn();

// Dernieres inscriptions
$dernieres_inscriptions = $db->query("
    SELECT i.*, a.nom, a.postnom, a.prenom, f.nom_formation 
    FROM inscription i 
    JOIN apprenant a ON i.id_apprenant = a.id_apprenant 
    JOIN formation f ON i.id_formation = f.id_formation 
    ORDER BY i.date_inscription DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Prochaines sessions
$prochaines_sessions = $db->query("
    SELECT s.*, f.nom_formation, fo.nom as nom_formateur
    FROM session s
    JOIN formation f ON s.id_formation = f.id_formation
    JOIN formateur fo ON s.id_formateur = fo.id_formateur
    WHERE s.date_debut >= CURDATE()
    ORDER BY s.date_debut ASC
    LIMIT 3
")->fetchAll(PDO::FETCH_ASSOC);

// Taux de remplissage (apprenants par formation)
$taux_remplissage = $total_formations > 0 ? round(($total_inscriptions / $total_formations), 1) : 0;
?>

<!-- ============================================ -->
<!-- STYLES SPÉCIFIQUES AU DASHBOARD -->
<!-- ============================================ -->
<style>
    /* Conteneur principal Grid */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: auto;
        gap: 20px;
        margin-top: 20px;
    }

    /* Barre de bienvenue en pleine largeur */
    .welcome-bar {
        grid-column: 1 / -1;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px 30px;
        border-radius: 12px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .welcome-text h1 {
        font-size: 24px;
        margin-bottom: 5px;
    }

    .welcome-text p {
        opacity: 0.9;
        font-size: 14px;
    }

    .welcome-date {
        text-align: right;
        font-size: 14px;
        opacity: 0.9;
    }

    .welcome-date .date-large {
        font-size: 28px;
        font-weight: bold;
        display: block;
    }

    /* Cartes statistiques */
    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: all 0.3s ease;
        cursor: pointer;
        border: 1px solid #f0f0f0;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        border-color: #667eea;
    }

    .stat-icon {
        width: 55px;
        height: 55px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        flex-shrink: 0;
    }

    .stat-info {
        flex: 1;
    }

    .stat-info .stat-value {
        font-size: 24px;
        font-weight: bold;
        color: #2c3e50;
        line-height: 1;
    }

    .stat-info .stat-label {
        font-size: 13px;
        color: #7f8c8d;
        margin-top: 5px;
    }

    /* Couleurs des icônes */
    .icon-apprenants { background: #e8f5e9; color: #27ae60; }
    .icon-formations { background: #e3f2fd; color: #2980b9; }
    .icon-inscriptions { background: #fff3e0; color: #f39c12; }
    .icon-formateurs { background: #f3e5f5; color: #8e44ad; }
    .icon-sessions { background: #e0f7fa; color: #00acc1; }
    .icon-paiements { background: #fce4ec; color: #e74c3c; }

    /* Cartes larges (2 colonnes) */
    .card-wide {
        grid-column: span 2;
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
    }

    .card-full {
        grid-column: 1 / -1;
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        border: 1px solid #f0f0f0;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f2f5;
    }

    .card-header h3 {
        font-size: 18px;
        color: #2c3e50;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .card-header .badge {
        background: #667eea;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    /* Barre de progression */
    .progress-bar {
        width: 100%;
        height: 8px;
        background: #f0f0f0;
        border-radius: 4px;
        margin-top: 8px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        border-radius: 4px;
        transition: width 1s ease;
    }

    /* Mini graphique en barres */
    .mini-chart {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        height: 120px;
        padding: 10px 0;
    }

    .mini-bar {
        flex: 1;
        background: linear-gradient(to top, #667eea, #764ba2);
        border-radius: 4px 4px 0 0;
        position: relative;
        min-height: 5px;
        transition: height 1s ease;
    }

    .mini-bar-label {
        text-align: center;
        font-size: 11px;
        color: #7f8c8d;
        margin-top: 5px;
    }

    .mini-bar-value {
        position: absolute;
        top: -20px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 11px;
        font-weight: bold;
        color: #2c3e50;
    }

    /* Table moderne */
    .table-modern {
        width: 100%;
        border-collapse: collapse;
    }

    .table-modern th {
        background: #f8f9fa;
        padding: 12px 15px;
        text-align: left;
        font-size: 12px;
        text-transform: uppercase;
        color: #7f8c8d;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    .table-modern td {
        padding: 14px 15px;
        border-bottom: 1px solid #f0f0f0;
        font-size: 14px;
    }

    .table-modern tr:hover td {
        background: #f8f9ff;
    }

    /* Statuts */
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }

    .status-actif { background: #d4edda; color: #155724; }
    .status-en-cours { background: #fff3cd; color: #856404; }
    .status-termine { background: #d1ecf1; color: #0c5460; }
    .status-en-attente { background: #f8d7da; color: #721c24; }

    /* Liste sessions */
    .session-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .session-item:last-child {
        border-bottom: none;
    }

    .session-date {
        background: #f0f0ff;
        padding: 8px 12px;
        border-radius: 8px;
        text-align: center;
        font-weight: bold;
        color: #667eea;
        font-size: 12px;
        flex-shrink: 0;
    }

    .session-date .day {
        font-size: 20px;
        display: block;
        line-height: 1;
    }

    .session-info {
        flex: 1;
    }

    .session-info strong {
        display: block;
        color: #2c3e50;
    }

    .session-info small {
        color: #7f8c8d;
        font-size: 12px;
    }

    /* Activité récente */
    .activity-list {
        list-style: none;
        padding: 0;
    }

    .activity-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-top: 5px;
        flex-shrink: 0;
    }

    .activity-content {
        flex: 1;
    }

    .activity-content .activity-text {
        font-size: 14px;
        color: #2c3e50;
    }

    .activity-content .activity-time {
        font-size: 12px;
        color: #95a5a6;
    }

    /* Responsive */
    @media (max-width: 1200px) {
        .dashboard-grid {
            grid-template-columns: repeat(3, 1fr);
        }
        .card-wide {
            grid-column: span 3;
        }
    }

    @media (max-width: 900px) {
        .dashboard-grid {
            grid-template-columns: repeat(2, 1fr);
        }
        .card-wide {
            grid-column: span 2;
        }
        .welcome-bar {
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 600px) {
        .dashboard-grid {
            grid-template-columns: 1fr;
        }
        .card-wide, .card-full {
            grid-column: span 1;
        }
        .stat-card {
            flex-direction: column;
            text-align: center;
        }
        .mini-chart {
            height: 80px;
        }
    }

    /* Animation au chargement */
    .stat-card, .card-wide, .card-full {
        animation: fadeInUp 0.5s ease forwards;
        opacity: 0;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.2s; }
    .stat-card:nth-child(4) { animation-delay: 0.3s; }
    .stat-card:nth-child(5) { animation-delay: 0.4s; }
    .stat-card:nth-child(6) { animation-delay: 0.5s; }
    .stat-card:nth-child(7) { animation-delay: 0.6s; }
</style>

<!-- ============================================ -->
<!-- CONTENU DU DASHBOARD -->
<!-- ============================================ -->

<div class="dashboard-grid">

    <!-- Barre de bienvenue -->
    <div class="welcome-bar">
        <div class="welcome-text">
            <h1>👋 Bonjour, <?php echo htmlspecialchars($_SESSION['user_nom']); ?> !</h1>
            <p>Voici un aperçu de votre centre de formation MCC</p>
        </div>
        <div class="welcome-date">
            <span class="date-large"><?php echo date('d'); ?></span>
            <?php 
                setlocale(LC_TIME, 'fr_FR.UTF-8', 'French_France.1252');
                echo strftime('%B %Y'); 
            ?>
        </div>
    </div>

    <!-- Cartes statistiques -->
    <div class="stat-card" onclick="window.location='apprenants.php'">
        <div class="stat-icon icon-apprenants">📚</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_apprenants; ?></div>
            <div class="stat-label">Apprenants inscrits</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 75%; background: #27ae60;"></div>
            </div>
        </div>
    </div>

    <div class="stat-card" onclick="window.location='formations.php'">
        <div class="stat-icon icon-formations">🎓</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_formations; ?></div>
            <div class="stat-label">Formations disponibles</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 60%; background: #2980b9;"></div>
            </div>
        </div>
    </div>

    <div class="stat-card" onclick="window.location='inscriptions.php'">
        <div class="stat-icon icon-inscriptions">📝</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_inscriptions; ?></div>
            <div class="stat-label">Total inscriptions</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 85%; background: #f39c12;"></div>
            </div>
        </div>
    </div>

    <div class="stat-card" onclick="window.location='formateurs.php'">
        <div class="stat-icon icon-formateurs">👨‍🏫</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_formateurs; ?></div>
            <div class="stat-label">Formateurs actifs</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 50%; background: #8e44ad;"></div>
            </div>
        </div>
    </div>

    <div class="stat-card" onclick="window.location='sessions.php'">
        <div class="stat-icon icon-sessions">📅</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo $total_sessions; ?></div>
            <div class="stat-label">Sessions planifiées</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 40%; background: #00acc1;"></div>
            </div>
        </div>
    </div>

    <div class="stat-card" onclick="window.location='paiements.php'">
        <div class="stat-icon icon-paiements">💰</div>
        <div class="stat-info">
            <div class="stat-value"><?php echo number_format($total_paiements, 0, ',', ' '); ?> FC</div>
            <div class="stat-label">Revenus totaux</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: 90%; background: #e74c3c;"></div>
            </div>
        </div>
    </div>

    <!-- Graphique : Inscriptions par mois -->
    <div class="card-wide">
        <div class="card-header">
            <h3>📊 Évolution des inscriptions</h3>
            <span class="badge">6 derniers mois</span>
        </div>
        <div class="mini-chart">
            <?php 
            $max_inscriptions = 0;
            foreach ($inscriptions_par_mois as $m) {
                if ($m['total'] > $max_inscriptions) $max_inscriptions = $m['total'];
            }
            $mois_fr = ['', 'Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'];
            foreach ($inscriptions_par_mois as $m): 
                $hauteur = $max_inscriptions > 0 ? ($m['total'] / $max_inscriptions * 100) : 5;
                $mois_num = intval(substr($m['mois'], 5, 2));
            ?>
            <div style="flex:1; text-align:center;">
                <div class="mini-bar" style="height: <?php echo $hauteur; ?>%;">
                    <span class="mini-bar-value"><?php echo $m['total']; ?></span>
                </div>
                <div class="mini-bar-label"><?php echo $mois_fr[$mois_num]; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Taux de remplissage -->
    <div class="card-wide">
        <div class="card-header">
            <h3>📈 Indicateurs clés</h3>
            <span class="badge">Ce mois</span>
        </div>
        <div style="display: flex; flex-direction: column; gap: 20px;">
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #2c3e50;">📝 Inscriptions actives</span>
                    <span style="font-weight: bold; color: #27ae60;"><?php echo $inscriptions_actives; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $total_inscriptions > 0 ? ($inscriptions_actives/$total_inscriptions*100) : 0; ?>%; background: #27ae60;"></div>
                </div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #2c3e50;">✅ Formations terminées</span>
                    <span style="font-weight: bold; color: #2980b9;"><?php echo $inscriptions_terminees; ?></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $total_inscriptions > 0 ? ($inscriptions_terminees/$total_inscriptions*100) : 0; ?>%; background: #2980b9;"></div>
                </div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #2c3e50;">💰 Paiements du mois</span>
                    <span style="font-weight: bold; color: #e74c3c;"><?php echo number_format($paiements_mois, 0, ',', ' '); ?> FC</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: 65%; background: #e74c3c;"></div>
                </div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px; color: #2c3e50;">📊 Taux de remplissage</span>
                    <span style="font-weight: bold; color: #8e44ad;"><?php echo $taux_remplissage; ?> apprenants/formation</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min($taux_remplissage * 10, 100); ?>%; background: #8e44ad;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dernières inscriptions -->
    <div class="card-wide">
        <div class="card-header">
            <h3>📋 Dernières inscriptions</h3>
            <a href="inscriptions.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Voir tout →</a>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-modern">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Apprenant</th>
                        <th>Formation</th>
                        <th>Statut</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieres_inscriptions as $inscription): 
                        $status_class = '';
                        $statut_lower = strtolower($inscription['statut']);
                        if (strpos($statut_lower, 'actif') !== false || strpos($statut_lower, 'cours') !== false) $status_class = 'status-en-cours';
                        elseif (strpos($statut_lower, 'termin') !== false) $status_class = 'status-termine';
                        else $status_class = 'status-en-attente';
                    ?>
                    <tr>
                        <td><?php echo date('d/m/Y', strtotime($inscription['date_inscription'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($inscription['nom'] . ' ' . $inscription['prenom']); ?></strong></td>
                        <td><?php echo htmlspecialchars($inscription['nom_formation']); ?></td>
                        <td><span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($inscription['statut']); ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($dernieres_inscriptions)): ?>
                    <tr>
                        <td colspan="4" style="text-align: center; color: #95a5a6; padding: 30px;">Aucune inscription pour le moment</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Prochaines sessions -->
    <div class="card-wide">
        <div class="card-header">
            <h3>📅 Prochaines sessions</h3>
            <a href="sessions.php" style="color: #667eea; text-decoration: none; font-size: 14px;">Voir tout →</a>
        </div>
        <?php foreach ($prochaines_sessions as $session): ?>
        <div class="session-item">
            <div class="session-date">
                <span class="day"><?php echo date('d', strtotime($session['date_debut'])); ?></span>
                <?php echo date('M', strtotime($session['date_debut'])); ?>
            </div>
            <div class="session-info">
                <strong><?php echo htmlspecialchars($session['nom_formation']); ?></strong>
                <small>👨‍🏫 <?php echo htmlspecialchars($session['nom_formateur']); ?> | 🏫 <?php echo htmlspecialchars($session['salle']); ?></small>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($prochaines_sessions)): ?>
        <p style="text-align: center; color: #95a5a6; padding: 20px;">Aucune session à venir</p>
        <?php endif; ?>
    </div>

</div><!-- Fin dashboard-grid -->

</div><!-- Fin container -->
</body>
</html>