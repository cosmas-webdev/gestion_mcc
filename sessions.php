<?php
require_once 'includes/header.php';
require_once 'config/database.php';

\ = new Database();
\ = \->getConnection();

\ = '';
\ = '';

\ = "Sessions";
\ = substr(\, 0, -1);
?>

<h1>Gestion des <?php echo \; ?></h1>

<?php if (\): ?>
    <div class="message"><?php echo \; ?></div>
<?php endif; ?>
<?php if (\): ?>
    <div class="error"><?php echo \; ?></div>
<?php endif; ?>

<div class="card">
    <h3>Formulaire <?php echo \; ?></h3>
    <p>Ce module est en cours de developpement.</p>
    <p>Il permettra la gestion complete (CRUD) des <?php echo strtolower(\); ?>.</p>
</div>

</div>
</body>
</html>
