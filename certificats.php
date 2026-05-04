<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = ''; $error = ''; $certificat_edit = null;

// CREATE
if (isset($_POST['ajouter'])) {
    $id_inscription = intval($_POST['id_inscription']);
    $date_delivrance = $_POST['date_delivrance'];
    $mention = trim($_POST['mention']);
    if (empty($id_inscription) || empty($date_delivrance)) { $error = "⚠️ Veuillez remplir tous les champs obligatoires."; }
    else {
        $checkCert = $db->prepare("SELECT COUNT(*) FROM certificat WHERE id_inscription = :id");
        $checkCert->execute([':id' => $id_inscription]);
        if ($checkCert->fetchColumn() > 0) { $error = "⚠️ Un certificat a déjà été délivré pour cette inscription."; }
        else {
            try {
                $db->prepare("INSERT INTO certificat (date_delivrance, mention, id_inscription) VALUES (:date, :mention, :inscription)")
                   ->execute([':date'=>$date_delivrance, ':mention'=>$mention, ':inscription'=>$id_inscription]);
                $new_id = $db->lastInsertId();
                $message = "✅ Certificat délivré ! <a href='imprimer.php?id={$new_id}' target='_blank' style='background:#1a5276;color:white;padding:6px 14px;border-radius:4px;text-decoration:none;font-size:13px;'>📄 Imprimer le PDF</a>";
            } catch (PDOException $e) { $error = "❌ Erreur : " . $e->getMessage(); }
        }
    }
}

// UPDATE
if (isset($_POST['modifier'])) {
    try {
        $db->prepare("UPDATE certificat SET date_delivrance=:date, mention=:mention, id_inscription=:inscription WHERE id_certificat=:id")
           ->execute([':id'=>intval($_POST['id_certificat']), ':date'=>$_POST['date_delivrance'], ':mention'=>trim($_POST['mention']), ':inscription'=>intval($_POST['id_inscription'])]);
        $message = "✅ Certificat modifié !";
    } catch (PDOException $e) { $error = "❌ Erreur : " . $e->getMessage(); }
}

// DELETE
if (isset($_GET['supprimer'])) {
    try { $db->prepare("DELETE FROM certificat WHERE id_certificat = :id")->execute([':id'=>intval($_GET['supprimer'])]); $message = "✅ Supprimé !"; }
    catch (PDOException $e) { $error = "❌ Erreur : " . $e->getMessage(); }
}

// EDIT
if (isset($_GET['modifier'])) {
    $stmt = $db->prepare("SELECT c.*, a.nom, a.prenom, a.postnom, f.nom_formation FROM certificat c JOIN inscription i ON c.id_inscription=i.id_inscription JOIN apprenant a ON i.id_apprenant=a.id_apprenant JOIN formation f ON i.id_formation=f.id_formation WHERE c.id_certificat=:id");
    $stmt->execute([':id'=>intval($_GET['modifier'])]);
    $certificat_edit = $stmt->fetch(PDO::FETCH_ASSOC);
}

// LISTE
$search = $_GET['search'] ?? ''; $filter_mention = $_GET['mention'] ?? ''; $date_debut = $_GET['date_debut'] ?? ''; $date_fin = $_GET['date_fin'] ?? '';
$query = "SELECT c.*, a.nom, a.postnom, a.prenom, f.nom_formation, f.duree, f.niveau FROM certificat c JOIN inscription i ON c.id_inscription=i.id_inscription JOIN apprenant a ON i.id_apprenant=a.id_apprenant JOIN formation f ON i.id_formation=f.id_formation WHERE 1=1";
$params = [];
if(!empty($search)){$query.=" AND (a.nom LIKE :s1 OR a.prenom LIKE :s2 OR a.postnom LIKE :s3 OR f.nom_formation LIKE :s4 OR c.mention LIKE :s5)";$params[':s1']="%{$search}%";$params[':s2']="%{$search}%";$params[':s3']="%{$search}%";$params[':s4']="%{$search}%";$params[':s5']="%{$search}%";}
if(!empty($filter_mention)){$query.=" AND c.mention = :mention";$params[':mention']=$filter_mention;}
if(!empty($date_debut)){$query.=" AND c.date_delivrance >= :d1";$params[':d1']=$date_debut;}
if(!empty($date_fin)){$query.=" AND c.date_delivrance <= :d2";$params[':d2']=$date_fin;}
$query.=" ORDER BY c.date_delivrance DESC";
$stmt=$db->prepare($query);$stmt->execute($params);$certificats=$stmt->fetchAll(PDO::FETCH_ASSOC);
$inscriptions_terminees=$db->query("SELECT i.id_inscription, a.nom, a.prenom, a.postnom, f.nom_formation FROM inscription i JOIN apprenant a ON i.id_apprenant=a.id_apprenant JOIN formation f ON i.id_formation=f.id_formation WHERE i.id_inscription NOT IN (SELECT id_inscription FROM certificat) ORDER BY a.nom")->fetchAll(PDO::FETCH_ASSOC);
$mentions=$db->query("SELECT DISTINCT mention FROM certificat WHERE mention!='' ORDER BY mention")->fetchAll(PDO::FETCH_COLUMN);
$total_certificats=count($certificats);
$certificats_annee=$db->query("SELECT COUNT(*) FROM certificat WHERE YEAR(date_delivrance)=YEAR(CURDATE())")->fetchColumn();
$certificats_mois=$db->query("SELECT COUNT(*) FROM certificat WHERE MONTH(date_delivrance)=MONTH(CURDATE()) AND YEAR(date_delivrance)=YEAR(CURDATE())")->fetchColumn();
$inscriptions_sans_certificat=(int)$db->query("SELECT COUNT(*) FROM inscription WHERE id_inscription NOT IN (SELECT id_inscription FROM certificat)")->fetchColumn();
?>
<style>
    :root{--primary:#667eea;--gold:#c9a84c;--success:#27ae60;--warning:#f39c12;--danger:#e74c3c;--info:#3498db;--bg:#f0f2f5;--white:#fff;--text:#2c3e50;--text-light:#7f8c8d;--border:#e0e0e0;--radius:12px;--shadow:0 2px 10px rgba(0,0,0,.05)}
    .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:25px;flex-wrap:wrap;gap:15px}
    .page-header h1{font-size:26px;color:var(--text);display:flex;align-items:center;gap:10px}
    .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:15px;margin-bottom:25px}
    .stat-card{background:var(--white);padding:20px;border-radius:var(--radius);display:flex;align-items:center;gap:15px;box-shadow:var(--shadow);border:1px solid #f0f0f0;transition:transform .3s}
    .stat-card:hover{transform:translateY(-3px)}
    .stat-icon{width:50px;height:50px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0}
    .stat-icon.gold{background:#fef9e7;color:#7d6608}.stat-icon.blue{background:#eaf0f6;color:#1a5276}
    .stat-icon.green{background:#eafaf1;color:#1e8449}.stat-icon.orange{background:#fef5e7;color:#b9770e}
    .stat-val{font-size:22px;font-weight:bold;color:var(--text)}.stat-lbl{font-size:13px;color:var(--text-light)}
    .grid-layout{display:grid;grid-template-columns:420px 1fr;gap:20px}
    .card{background:var(--white);padding:25px;border-radius:var(--radius);box-shadow:var(--shadow);border:1px solid #f0f0f0}
    .card.sticky{position:sticky;top:20px;height:fit-content}
    .card h3{font-size:18px;color:var(--text);margin-bottom:20px;padding-bottom:15px;border-bottom:2px solid #f0f2f5;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .form-grid{display:grid;gap:15px}.form-group{display:flex;flex-direction:column;gap:5px}
    .form-group label{font-size:12px;font-weight:600;color:var(--text);text-transform:uppercase;letter-spacing:.5px}
    .form-group .required{color:var(--danger)}
    .form-group input,.form-group select{padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:14px;transition:all .3s;background:#fafafa}
    .form-group input:focus,.form-group select:focus{outline:none;border-color:var(--primary);background:white;box-shadow:0 0 0 3px rgba(102,126,234,.1)}
    .btn{padding:10px 20px;border:none;border-radius:8px;cursor:pointer;font-size:14px;font-weight:500;transition:all .3s;display:flex;align-items:center;gap:6px;justify-content:center;text-decoration:none}
    .btn-save{background:#1a5276;color:white;flex:1;font-weight:600}.btn-save:hover{background:#154360;transform:translateY(-2px)}
    .btn-cancel{background:#f0f0f0;color:#666}.btn-print{background:#1a5276;color:white;padding:6px 12px;border-radius:6px;font-size:12px}
    .btn-print:hover{background:#154360;color:white}.btn-sm{padding:5px 10px;font-size:11px}.btn-group{display:flex;gap:8px}
    .toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center}
    .search-box{position:relative;flex:1;min-width:180px}
    .search-box input{width:100%;padding:10px 12px 10px 38px;border:2px solid var(--border);border-radius:8px;font-size:14px}
    .search-box .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%)}
    .filter-select{padding:10px 12px;border:2px solid var(--border);border-radius:8px;font-size:14px;background:white;cursor:pointer}
    .table-container{overflow-x:auto}.table-modern{width:100%;border-collapse:collapse;font-size:14px}
    .table-modern thead th{background:#f8f9fa;padding:14px 15px;text-align:left;font-size:12px;text-transform:uppercase;color:var(--text-light);font-weight:600;letter-spacing:.5px;white-space:nowrap;border-bottom:2px solid var(--border)}
    .table-modern tbody td{padding:14px 15px;border-bottom:1px solid #f0f0f0;vertical-align:middle}
    .table-modern tbody tr:hover{background:#f8f9ff}.table-modern tbody tr:nth-child(even){background:#fafafa}.table-modern tbody tr:nth-child(even):hover{background:#f8f9ff}
    .mention-badge{display:inline-block;padding:5px 14px;border-radius:4px;font-size:12px;font-weight:600;letter-spacing:.5px}
    .mention-excellence{background:#fef9e7;color:#7d6608;border:1px solid #c9a84c}
    .mention-distinction{background:#eaf0f6;color:#1a3c5e;border:1px solid #2c3e50}
    .mention-satisfaction{background:#eafaf1;color:#1e8449;border:1px solid #27ae60}
    .mention-passable{background:#fef5e7;color:#b9770e;border:1px solid #d4ac0d}
    .mention-defaut{background:#f5f5f5;color:#616161}
    .certificat-id{font-family:'Courier New',monospace;background:#f0f0f0;padding:3px 8px;border-radius:4px;font-size:13px;font-weight:bold}
    .text-muted{color:var(--text-light)}.empty-state{text-align:center;padding:40px;color:var(--text-light)}.empty-icon{font-size:48px;margin-bottom:10px}
    .quick-info{background:#fef9e7;border:1px solid #c9a84c;padding:12px 15px;border-radius:8px;font-size:13px;color:var(--text);margin-top:15px}
    @media(max-width:1100px){.grid-layout{grid-template-columns:1fr}.card.sticky{position:static}}
    @media(max-width:600px){.stats-grid{grid-template-columns:1fr 1fr}.toolbar{flex-direction:column}}
    @media(max-width:400px){.stats-grid{grid-template-columns:1fr}}
</style>

<div class="page-header"><h1>📜 Certificats · MCC Gestion</h1></div>
<div class="stats-grid">
    <div class="stat-card"><div class="stat-icon gold">📜</div><div><div class="stat-val"><?php echo $total_certificats; ?></div><div class="stat-lbl">Délivrés</div></div></div>
    <div class="stat-card"><div class="stat-icon blue">📅</div><div><div class="stat-val"><?php echo $certificats_annee; ?></div><div class="stat-lbl">Cette année</div></div></div>
    <div class="stat-card"><div class="stat-icon green">✅</div><div><div class="stat-val"><?php echo $certificats_mois; ?></div><div class="stat-lbl">Ce mois</div></div></div>
    <div class="stat-card"><div class="stat-icon orange">⏳</div><div><div class="stat-val"><?php echo $inscriptions_sans_certificat; ?></div><div class="stat-lbl">En attente</div></div></div>
</div>
<?php if($message):?><div style="background:#d4edda;color:#155724;padding:12px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #28a745"><?php echo $message; ?></div><?php endif; ?>
<?php if($error):?><div style="background:#f8d7da;color:#721c24;padding:12px 20px;border-radius:8px;margin-bottom:20px;border-left:4px solid #dc3545"><?php echo $error; ?></div><?php endif; ?>

<div class="grid-layout">
    <div class="card sticky">
        <h3><?php echo $certificat_edit?'✏️ Modifier':'🏆 Délivrer un certificat'; ?></h3>
        <form method="POST" class="form-grid">
            <?php if($certificat_edit):?><input type="hidden" name="id_certificat" value="<?php echo $certificat_edit['id_certificat'];?>"><?php endif; ?>
            <div class="form-group"><label>Inscription <span class="required">*</span></label>
                <select name="id_inscription" required>
                    <option value="">-- Sélectionner --</option>
                    <?php $liste=$inscriptions_terminees;if($certificat_edit){$found=false;foreach($liste as $l)if($l['id_inscription']==$certificat_edit['id_inscription'])$found=true;if(!$found)array_unshift($liste,['id_inscription'=>$certificat_edit['id_inscription'],'nom'=>$certificat_edit['nom'],'prenom'=>$certificat_edit['prenom'],'postnom'=>$certificat_edit['postnom'],'nom_formation'=>$certificat_edit['nom_formation']]);}
                    foreach($liste as $ins):$sel=($certificat_edit&&$certificat_edit['id_inscription']==$ins['id_inscription'])?'selected':'';?>
                    <option value="<?php echo $ins['id_inscription'];?>" <?php echo $sel;?>><?php echo htmlspecialchars($ins['nom'].' '.$ins['prenom'].' - '.$ins['nom_formation']);?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="date_delivrance" required value="<?php echo $certificat_edit?$certificat_edit['date_delivrance']:date('Y-m-d');?>"></div>
            <div class="form-group"><label>Mention</label>
                <select name="mention"><option value="">-- Choisir --</option>
                    <option value="Excellence" <?php echo ($certificat_edit&&$certificat_edit['mention']=='Excellence')?'selected':'';?>>🌟 Excellence (90-100%)</option>
                    <option value="Distinction" <?php echo ($certificat_edit&&$certificat_edit['mention']=='Distinction')?'selected':'';?>>⭐ Distinction (80-89%)</option>
                    <option value="Satisfaction" <?php echo ($certificat_edit&&$certificat_edit['mention']=='Satisfaction')?'selected':'';?>>✅ Satisfaction (70-79%)</option>
                    <option value="Passable" <?php echo ($certificat_edit&&$certificat_edit['mention']=='Passable')?'selected':'';?>>✔️ Passable (60-69%)</option>
                </select>
            </div>
            <div class="btn-group"><?php if($certificat_edit):?><button type="submit" name="modifier" class="btn btn-save">💾 Enregistrer</button><a href="certificats.php" class="btn btn-cancel">❌ Annuler</a><?php else:?><button type="submit" name="ajouter" class="btn btn-save">🏆 Délivrer le certificat</button><?php endif;?></div>
        </form>
        <?php if($inscriptions_sans_certificat>0&&!$certificat_edit):?><div class="quick-info">⚠️ <strong><?php echo $inscriptions_sans_certificat;?></strong> inscription(s) en attente</div><?php endif;?>
    </div>
    
    <div class="card">
        <h3><span>📋 Certificats délivrés</span>
            <div class="toolbar">
                <div class="search-box"><span class="search-icon">🔍</span><input type="text" id="searchInput" placeholder="Rechercher..." value="<?php echo htmlspecialchars($search);?>"></div>
                <form method="GET" id="filterForm" style="display:flex;gap:8px;flex-wrap:wrap">
                    <select name="mention" class="filter-select" onchange="this.form.submit()"><option value="">Toutes</option><?php foreach($mentions as $m):?><option value="<?php echo htmlspecialchars($m);?>" <?php echo $filter_mention==$m?'selected':'';?>><?php echo htmlspecialchars($m);?></option><?php endforeach;?></select>
                    <input type="date" name="date_debut" class="filter-select" value="<?php echo $date_debut;?>" onchange="this.form.submit()">
                    <input type="date" name="date_fin" class="filter-select" value="<?php echo $date_fin;?>" onchange="this.form.submit()">
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search);?>">
                </form>
            </div>
        </h3>
        <div class="table-container"><table class="table-modern"><thead><tr><th>N° Certificat</th><th>Apprenant</th><th>Formation</th><th>Date</th><th>Mention</th><th>Actions</th></tr></thead><tbody>
            <?php if(count($certificats)>0):foreach($certificats as $cert):$mc='mention-defaut';switch($cert['mention']){case'Excellence':$mc='mention-excellence';break;case'Distinction':$mc='mention-distinction';break;case'Satisfaction':$mc='mention-satisfaction';break;case'Passable':$mc='mention-passable';break;}$num='CERT-'.date('Y',strtotime($cert['date_delivrance'])).'-'.str_pad($cert['id_certificat'],4,'0',STR_PAD_LEFT);?>
            <tr>
                <td><span class="certificat-id"><?php echo $num;?></span></td>
                <td><strong><?php echo htmlspecialchars($cert['nom'].' '.$cert['prenom']);?></strong><?php if($cert['postnom']):?><br><small class="text-muted"><?php echo htmlspecialchars($cert['postnom']);?></small><?php endif;?></td>
                <td><?php echo htmlspecialchars($cert['nom_formation']);?><br><small class="text-muted"><?php echo htmlspecialchars($cert['duree']);?> | <?php echo htmlspecialchars($cert['niveau']);?></small></td>
                <td><?php echo date('d/m/Y',strtotime($cert['date_delivrance']));?></td>
                <td><?php if($cert['mention']):?><span class="mention-badge <?php echo $mc;?>"><?php echo htmlspecialchars($cert['mention']);?></span><?php else:?><span class="text-muted">-</span><?php endif;?></td>
                <td><div class="btn-group"><a href="imprimer.php?id=<?php echo $cert['id_certificat'];?>" class="btn btn-print btn-sm" target="_blank">📄 PDF</a><a href="?modifier=<?php echo $cert['id_certificat'];?>" class="btn btn-sm" style="background:#f39c12;color:white;text-decoration:none">✏️</a><a href="?supprimer=<?php echo $cert['id_certificat'];?>" class="btn btn-sm" style="background:#e74c3c;color:white;text-decoration:none" onclick="return confirm('Supprimer ?')">🗑️</a></div></td>
            </tr>
            <?php endforeach;else:?><tr><td colspan="6"><div class="empty-state"><div class="empty-icon">📜</div><p>Aucun certificat</p></div></td></tr><?php endif;?>
        </tbody></table></div>
    </div>
</div>

<script>
let t;document.getElementById('searchInput').addEventListener('keyup',function(){clearTimeout(t);let v=this.value;t=setTimeout(()=>{let u=new URL(window.location);v?u.searchParams.set('search',v):u.searchParams.delete('search');window.location=u.toString()},500)});
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('[style*="background:#d4edda"],[style*="background:#f8d7da"]').forEach(m=>setTimeout(()=>{m.style.opacity='0';m.style.transition='all .5s';setTimeout(()=>m.remove(),500)},5000))});
</script>
</div></body></html>