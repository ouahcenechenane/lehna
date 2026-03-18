<?php
/**
 * admin/login.php
 * Page de connexion administrateur
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Si déjà connecté → rediriger vers dashboard
if (!empty($_SESSION['admin_id'])) {
    redirect(APP_URL . '/admin/index.php');
}

$error   = '';
$expired = isset($_GET['expired']);

// ── Traitement du formulaire ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeString($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Veuillez remplir tous les champs.';
    } else {
        $stmt = getPDO()->prepare('SELECT * FROM admin WHERE username = :u LIMIT 1');
        $stmt->execute([':u' => $username]);
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            // ✅ Authentification réussie
            session_regenerate_id(true);
            $_SESSION['admin_id']        = $admin['id'];
            $_SESSION['admin_username']  = $admin['username'];
            $_SESSION['admin_nom']       = $admin['nom'];
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['last_activity']   = time();

            $dest = $_SESSION['redirect_after_login'] ?? APP_URL . '/admin/index.php';
            unset($_SESSION['redirect_after_login']);
            redirect($dest);
        } else {
            // Temporisation pour freiner le bruteforce
            sleep(1);
            $error = 'Identifiants incorrects.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion Admin – <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/admin.css">
    <style>
        /* ── Page login : centrage + carte ── */
        body.login-page {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-dark);
            background-image: radial-gradient(ellipse 80% 60% at 50% -10%, rgba(212,163,90,.18) 0%, transparent 70%);
        }
        .login-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 52px 44px 44px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 24px 80px rgba(0,0,0,.45);
            animation: fadeUp .5s ease both;
        }
        .login-logo {
            text-align: center;
            margin-bottom: 36px;
        }
        .login-logo .brand-icon {
            width: 64px; height: 64px;
            background: var(--gold);
            border-radius: 18px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 16px;
            box-shadow: 0 8px 24px rgba(212,163,90,.35);
        }
        .login-logo h1 {
            font-family: 'Playfair Display', serif;
            font-size: 1.5rem;
            color: var(--text-primary);
            margin: 0;
            line-height: 1.3;
        }
        .login-logo p {
            color: var(--text-muted);
            font-size: .85rem;
            margin: 6px 0 0;
        }
        .login-card .form-group { margin-bottom: 20px; }
        .login-card label {
            display: block;
            font-size: .8rem;
            font-weight: 600;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-bottom: 8px;
        }
        .login-card input {
            width: 100%;
            background: var(--bg-dark);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            padding: 13px 16px;
            font-size: .95rem;
            color: var(--text-primary);
            transition: border-color .2s, box-shadow .2s;
            box-sizing: border-box;
        }
        .login-card input:focus {
            outline: none;
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(212,163,90,.18);
        }
        .btn-login {
            width: 100%;
            background: var(--gold);
            color: #1a1208;
            font-weight: 700;
            font-size: 1rem;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            margin-top: 10px;
            transition: transform .15s, box-shadow .15s, opacity .15s;
            letter-spacing: .02em;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(212,163,90,.4);
        }
        .btn-login:active { transform: translateY(0); }
        .alert-error {
            background: rgba(239,68,68,.12);
            border: 1px solid rgba(239,68,68,.35);
            color: #fca5a5;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: .88rem;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-expired {
            background: rgba(245,158,11,.12);
            border: 1px solid rgba(245,158,11,.3);
            color: #fcd34d;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: .88rem;
            margin-bottom: 20px;
        }
        .login-hint {
            text-align: center;
            color: var(--text-muted);
            font-size: .78rem;
            margin-top: 24px;
            padding-top: 20px;
            border-top: 1px solid var(--border);
        }
        @keyframes fadeUp {
            from { opacity:0; transform:translateY(20px); }
            to   { opacity:1; transform:translateY(0);    }
        }
    </style>
</head>
<body class="login-page">

<div class="login-card">
    <div class="login-logo">
        <div class="brand-icon">🛒</div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>Espace Administration</p>
    </div>

    <?php if ($expired): ?>
        <div class="alert-expired">⚠️ Votre session a expiré. Reconnectez-vous.</div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert-error">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">

        <div class="form-group">
            <label for="username">Identifiant</label>
            <input type="text" id="username" name="username"
                   value="<?= isset($_POST['username']) ? e($_POST['username']) : '' ?>"
                   placeholder="admin" autocomplete="username" required autofocus>
        </div>

        <div class="form-group">
            <label for="password">Mot de passe</label>
            <input type="password" id="password" name="password"
                   placeholder="••••••••" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn-login">Se connecter →</button>
    </form>

    <p class="login-hint">🔐 Accès réservé aux administrateurs<br>
        <span style="color:var(--gold)">Admin par défaut : admin / Admin@1234</span>
    </p>
</div>

</body>
</html>
