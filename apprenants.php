<?php
require_once 'includes/header.php';
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// CREATE
if (isset($_POST['ajouter'])) {
    $query = "INSERT INTO apprenant (nom, postnom, prenom, sexe, date_naissance, telephone, email, adresse) 
              VALUES (:nom, :postnom, :prenom, :sexe, :date_naissance, :telephone, :email, :adresse)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':nom' => $_POST['nom'],
        ':postnom' => $_POST['postnom'],
        ':prenom' => $_POST['prenom'],
        ':sexe' => $_POST['sexe'],
        ':date_naissance' => $_POST['date_naissance'],
        ':telephone' => $_POST['telephone'],
        ':email' => $_POST['email'],
        ':adresse' => $_POST['adresse']
    ]);
    $message = "Apprenant ajoute avec succes!";
}

// UPDATE
if (isset($_POST['modifier'])) {
    $query = "UPDATE apprenant SET nom=:nom, postnom=:postnom, prenom=:prenom, sexe=:sexe, 
              date_naissance=:date_naissance, telephone=:telephone, email=:email, adresse=:adresse 
              WHERE id_apprenant=:id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':id' => $_POST['id_apprenant'],
        ':nom' => $_POST['nom'],
        ':postnom' => $_POST['postnom'],
        ':prenom' => $_POST['prenom'],
        ':sexe' => $_POST['sexe'],
        ':date_naissance' => $_POST['date_naissance'],
        ':telephone' => $_POST['telephone'],
        ':email' => $_POST['email'],
        ':adresse' => $_POST['adresse']
    ]);
    $message = "Apprenant modifie avec succes!";
}

// DELETE
if (isset($_GET['supprimer'])) {
    $query = "DELETE FROM apprenant WHERE id_apprenant = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([':id' => $_GET['supprimer']]);
    $message = "Apprenant supprime avec succes!";
}

// READ
$apprenants = $db->query("SELECT * FROM apprenant ORDER BY nom, postnom")->fetchAll(PDO::FETCH_ASSOC);
?>

<h1>Gestion des Apprenants</h1>

<?php if ($message): ?>
    <div class="message"><?php echo $message; ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="error"><?php echo $error; ?></div>
<?php endif; ?>

<div class="card">
    <h3>Ajouter / Modifier un Apprenant</h3>
    <form method="POST">
        <input type="hidden" name="id_apprenant" id="id_apprenant">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <input type="text" name="nom" placeholder="Nom *" required>
            <input type="text" name="postnom" placeholder="Postnom">
            <input type="text" name="prenom" placeholder="Prenom *" required>
            <select name="sexe">
                <option value="M">Masculin</option>
                <option value="F">Feminin</option>
            </select>
            <input type="date" name="date_naissance">
            <input type="text" name="telephone" placeholder="Telephone">
            <input type="email" name="email" placeholder="Email">
            <input type="text" name="adresse" placeholder="Adresse">
        </div>
        <div style="margin-top: 15px;">
            <button type="submit" name="ajouter" class="btn-ajouter">+ Ajouter</button>
            <button type="submit" name="modifier" class="btn-modifier">Modifier</button>
        </div>
    </form>
</div>

<div class="card">
    <h3>Liste des Apprenants</h3>
    <input type="text" id="recherche" placeholder="Rechercher un apprenant..." style="margin-bottom: 20px; padding: 10px; width: 100%; border: 2px solid #e0e0e0; border-radius: 5px;">
    <div style="overflow-x: auto;">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Nom</th>
                    <th>Postnom</th>
                    <th>Prenom</th>
                    <th>Sexe</th>
                    <th>Telephone</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($apprenants as $app): ?>
                <tr>
                    <td><?php echo $app['id_apprenant']; ?></td>
                    <td><?php echo htmlspecialchars($app['nom']); ?></td>
                    <td><?php echo htmlspecialchars($app['postnom']); ?></td>
                    <td><?php echo htmlspecialchars($app['prenom']); ?></td>
                    <td><?php echo $app['sexe']; ?></td>
                    <td><?php echo htmlspecialchars($app['telephone']); ?></td>
                    <td><?php echo htmlspecialchars($app['email']); ?></td>
                    <td>
                        <button onclick='modifierApprenant(<?php echo json_encode($app); ?>)' class="btn-modifier">Modifier</button>
                        <a href="?supprimer=<?php echo $app['id_apprenant']; ?>" onclick="return confirm('Confirmer la suppression?')" class="btn-supprimer" style="padding: 8px 10px; text-decoration: none; color: white; border-radius: 5px;">Supprimer</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('recherche').addEventListener('keyup', function() {
    let filter = this.value.toLowerCase();
    let rows = document.querySelectorAll('tbody tr');
    rows.forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(filter) ? '' : 'none';
    });
});

function modifierApprenant(app) {
    document.getElementById('id_apprenant').value = app.id_apprenant;
    document.querySelector('input[name="nom"]').value = app.nom;
    document.querySelector('input[name="postnom"]').value = app.postnom || '';
    document.querySelector('input[name="prenom"]').value = app.prenom;
    document.querySelector('select[name="sexe"]').value = app.sexe;
    document.querySelector('input[name="date_naissance"]').value = app.date_naissance || '';
    document.querySelector('input[name="telephone"]').value = app.telephone || '';
    document.querySelector('input[name="email"]').value = app.email || '';
    document.querySelector('input[name="adresse"]').value = app.adresse || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

</div>
</body>
</html>
