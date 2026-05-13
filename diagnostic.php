<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/includes/paths.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Diagnostic - Goo-Bridge</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 800px; margin: 40px auto; padding: 0 20px; }
    .ok { color: #15803d; font-weight: bold; }
    .error { color: #b91c1c; font-weight: bold; }
    .info { background: #f3f4f6; padding: 15px; border-radius: 8px; margin: 10px 0; }
    h1 { color: #16a34a; }
    h2 { margin-top: 30px; border-bottom: 2px solid #e5e7eb; padding-bottom: 5px; }
    code { background: #f9fafb; padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>
  <h1>🔍 Diagnostic Goo-Bridge</h1>
  
  <h2>📊 État de la session</h2>
  <div class="info">
    <?php if (admin_logged_in()): ?>
      <p class="ok">✓ VOUS ÊTES CONNECTÉ</p>
      <p>ID Admin: <?= current_admin_id() ?></p>
      <p>Email: <?= htmlspecialchars(current_admin_email() ?? '', ENT_QUOTES, 'UTF-8') ?></p>
      <p><a href="<?= url('admin/dashboard.php') ?>" style="color: #16a34a; font-weight: bold;">→ Aller au Dashboard</a></p>
    <?php else: ?>
      <p class="error">✗ NON CONNECTÉ</p>
      <p><a href="<?= url('login.php') ?>" style="color: #16a34a; font-weight: bold;">→ Aller à la page de connexion</a></p>
    <?php endif; ?>
  </div>

  <h2>🗄️ Base de données</h2>
  <div class="info">
    <?php
    try {
      $pdo = db();
      echo '<p class="ok">✓ Connexion MySQL OK</p>';
      $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
      echo '<p>Base active: <code>' . htmlspecialchars($db, ENT_QUOTES, 'UTF-8') . '</code></p>';
      
      $admins = $pdo->query('SELECT id, email FROM admins ORDER BY id')->fetchAll();
      echo '<p>Comptes administrateurs (' . count($admins) . '):</p><ul>';
      foreach ($admins as $a) {
        echo '<li>' . htmlspecialchars($a['email'], ENT_QUOTES, 'UTF-8') . ' (ID: ' . $a['id'] . ')</li>';
      }
      echo '</ul>';
    } catch (Throwable $e) {
      echo '<p class="error">✗ Erreur MySQL: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
    }
    ?>
  </div>

  <h2>🔧 Configuration</h2>
  <div class="info">
    <p>BASE_URL: <code><?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>URL actuelle: <code><?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '', ENT_QUOTES, 'UTF-8') ?></code></p>
    <p>Session ID: <code><?= session_id() ?></code></p>
    <p>PHP Version: <code><?= PHP_VERSION ?></code></p>
  </div>

  <h2>🧪 Test de connexion</h2>
  <div class="info">
    <?php if (!admin_logged_in()): ?>
      <form method="post" action="<?= url('login.php') ?>" style="max-width: 400px;">
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
          <input type="email" name="email" value="vrolingmendy0@gmail.com" 
            style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
        </div>
        <div style="margin-bottom: 15px;">
          <label style="display: block; margin-bottom: 5px; font-weight: 600;">Mot de passe</label>
          <input type="password" name="password" value="Passer123"
            style="width: 100%; padding: 8px; border: 1px solid #d1d5db; border-radius: 4px;">
        </div>
        <button type="submit" style="background: #16a34a; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">
          Tester la connexion
        </button>
      </form>
    <?php else: ?>
      <p class="ok">Vous êtes déjà connecté ! <a href="<?= url('logout.php') ?>">Se déconnecter</a> pour tester à nouveau.</p>
    <?php endif; ?>
  </div>

  <p style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 0.9em;">
    <a href="<?= url('index.php') ?>">← Retour au site</a>
  </p>
</body>
</html>
