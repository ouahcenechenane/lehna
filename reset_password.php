<?php
/**
 * reset_password.php
 * Utilitaire de réinitialisation du mot de passe admin
 * ⚠️ SUPPRIMER CE FICHIER APRÈS UTILISATION
 */

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = 'Le mot de passe doit faire au moins 6 caractères.';
    } else {
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);

        $pdo  = getPDO();
        $stmt = $pdo->prepare("UPDATE admin SET password = :hash WHERE username = 'admin'");
        $stmt->execute([':hash' => $hash]);

        if ($stmt->rowCount() > 0) {
            $success = true;
            $message = '✅ Mot de passe mis à jour ! Vous pouvez maintenant vous connecter avec le nouveau mot de passe.';
        } else {
            $message = '❌ Erreur : compte admin introuvable dans la base.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Réinitialisation mot de passe – LEHNA</title>
    <style>
        body { font-family: sans-serif; background: #111; color: #eee; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .box { background: #1c1a15; border: 1px solid #2e2b22; border-radius: 12px; padding: 40px; max-width: 420px; width: 90%; }
        h2 { color: #d4a35a; margin-bottom: 8px; }
        p  { color: #888; font-size: .9rem; margin-bottom: 24px; }
        label { display: block; font-size: .8rem; color: #888; margin-bottom: 6px; }
        input { width: 100%; padding: 10px 14px; background: #0f0e0b; border: 1.5px solid #2e2b22; border-radius: 8px; color: #fff; font-size: 1rem; box-sizing: border-box; margin-bottom: 16px; }
        button { width: 100%; padding: 12px; background: #d4a35a; border: none; border-radius: 8px; font-size: 1rem; font-weight: 700; color: #1a1208; cursor: pointer; }
        .msg-ok  { background: rgba(74,222,128,.1); border: 1px solid rgba(74,222,128,.3); color: #4ade80; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .msg-err { background: rgba(248,113,113,.1); border: 1px solid rgba(248,113,113,.3); color: #f87171; padding: 12px; border-radius: 8px; margin-bottom: 16px; }
        .warning { background: rgba(251,191,36,.1); border: 1px solid rgba(251,191,36,.3); color: #fbbf24; padding: 12px; border-radius: 8px; margin-top: 20px; font-size: .82rem; }
    </style>
</head>
<body>
<div class="box">
    <h2>🔑 Réinitialisation</h2>
    <p>Entrez un nouveau mot de passe pour le compte <strong>admin</strong>.</p>

    <?php if ($message): ?>
        <div class="<?= $success ? 'msg-ok' : 'msg-err' ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
        <label>Nouveau mot de passe</label>
        <input type="password" name="new_password" placeholder="Minimum 6 caractères" autofocus required>
        <button type="submit">Réinitialiser le mot de passe</button>
    </form>
    <?php else: ?>
        <a href="<?= APP_URL ?>/admin/login.php" style="display:block;text-align:center;margin-top:16px;color:#d4a35a">→ Aller à la page de connexion</a>
    <?php endif; ?>

    <div class="warning">
        ⚠️ <strong>Supprimez ce fichier après utilisation !</strong><br>
        <code>C:\xampp\htdocs\lehna\reset_password.php</code>
    </div>
</div>
</body>
</html>
