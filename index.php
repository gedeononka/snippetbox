<?php
// public/index.php
declare(strict_types=1);
session_start();

// chemins
require_once __DIR__ . '/../includes/db.php'; // $pdo disponible

const CATEGORIES = ['PHP','HTML','CSS'];

// CSRF token
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// helpers
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function is_valid_cat(?string $c): bool { return in_array($c ?? '', CATEGORIES, true); }
function redirect(string $to): never { header("Location: $to"); exit; }

$action = $_GET['action'] ?? 'home';

// STORE (POST)
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));

    $errors = [];
    if ($title === '') $errors['title'] = 'Titre requis.';
    if ($description === '') $errors['description'] = 'Description requise.';
    if (!is_valid_cat($category)) $errors['category'] = 'Catégorie invalide.';
    if ($code === '') $errors['code'] = 'Le code ne peut pas être vide.';

    if ($errors) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = ['title'=>$title,'description'=>$description,'category'=>$category,'code'=>$code];
        redirect('?action=create');
    }

    $stmt = $pdo->prepare('INSERT INTO snippets (title, description, category, code) VALUES (:title,:description,:category,:code)');
    $stmt->execute([
        ':title' => $title,
        ':description' => $description,
        ':category' => $category,
        ':code' => $code,
    ]);

    $_SESSION['flash'] = 'Snippet ajouté.';
    redirect('./');
}

// UPDATE (POST)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) redirect('./');

    $title = trim((string)($_POST['title'] ?? ''));
    $description = trim((string)($_POST['description'] ?? ''));
    $category = trim((string)($_POST['category'] ?? ''));
    $code = trim((string)($_POST['code'] ?? ''));

    $errors = [];
    if ($title === '') $errors['title'] = 'Titre requis.';
    if ($description === '') $errors['description'] = 'Description requise.';
    if (!is_valid_cat($category)) $errors['category'] = 'Catégorie invalide.';
    if ($code === '') $errors['code'] = 'Le code ne peut pas être vide.';

    if ($errors) {
        $_SESSION['errors'] = $errors;
        $_SESSION['old'] = compact('title','description','category','code');
        redirect("?action=edit&id=$id");
    }

    $stmt = $pdo->prepare('UPDATE snippets SET title=:t, description=:d, category=:c, code=:code WHERE id=:id');
    $stmt->execute([
        ':t'=>$title, ':d'=>$description, ':c'=>$category, ':code'=>$code, ':id'=>$id
    ]);

    $_SESSION['flash'] = 'Snippet mis à jour.';
    redirect("?action=show&id=$id");
}

// DELETE (POST)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        $stmt = $pdo->prepare('DELETE FROM snippets WHERE id=?');
        $stmt->execute([$id]);
        $_SESSION['flash'] = 'Snippet supprimé.';
    }
    redirect('./');
}

// SHOW
$snippet = null;
if ($action === 'show') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) { http_response_code(400); exit('Invalid id'); }
    $stmt = $pdo->prepare('SELECT * FROM snippets WHERE id = ?');
    $stmt->execute([$id]);
    $snippet = $stmt->fetch();
    if (!$snippet) { http_response_code(404); exit('Snippet not found'); }
}

// HOME: list with optional filter
$filter = $_GET['category'] ?? 'ALL';
$params = [];
$where = '';
if (is_valid_cat($filter)) {
    $where = 'WHERE category = :cat';
    $params[':cat'] = $filter;
}
$stmt = $pdo->prepare("SELECT id,title,description,category,created_at FROM snippets $where ORDER BY id DESC");
$stmt->execute($params);
$snippets = $stmt->fetchAll();

// old & errors
$old = $_SESSION['old'] ?? [];
$errors = $_SESSION['errors'] ?? [];
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['old'], $_SESSION['errors'], $_SESSION['flash']);

function prism_class(string $cat): string {
    return match($cat) {
        'PHP' => 'language-php',
        'HTML' => 'language-markup',
        'CSS' => 'language-css',
        default => 'language-none'
    };
}
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>H24Code - Snippet App</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="assets/style.css">
  <link rel="stylesheet" href="https://unpkg.com/prismjs/themes/prism-tomorrow.css">
  <script src="https://unpkg.com/prismjs/prism.js" defer></script>
  <script src="https://unpkg.com/prismjs/components/prism-php.min.js" defer></script>
  <script src="https://unpkg.com/prismjs/components/prism-markup.min.js" defer></script>
  <script src="https://unpkg.com/prismjs/components/prism-css.min.js" defer></script>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="brand"><a class="btn" href="./">⚡ H24Code • Snippets</a></div>
      <div style="display:flex;gap:10px">
        <a class="btn" href="./">Accueil</a>
        <a class="btn primary" href="?action=create">+ Ajouter</a>
      </div>
    </div>

    <?php if ($flash): ?>
      <div class="card" style="margin-bottom:12px"><strong><?= e($flash) ?></strong></div>
    <?php endif; ?>

    <?php if ($action === 'home'): ?>
      <div class="toolbar">
        <div class="muted">Ajoute et consulte des bouts de code librement (PHP / HTML / CSS).</div>
        <form method="get" style="display:flex;gap:8px">
          <input type="hidden" name="action" value="home">
          <select name="category" onchange="this.form.submit()">
            <option value="ALL" <?= $filter==='ALL' ? 'selected' : '' ?>>Toutes catégories</option>
            <?php foreach (CATEGORIES as $c): ?>
              <option value="<?= e($c) ?>" <?= $filter===$c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </form>
      </div>

      <?php if (empty($snippets)): ?>
        <div class="empty">Aucun snippet<?= is_valid_cat($filter) ? " dans « ".e($filter)." »" : '' ?>.</div>
      <?php else: ?>
        <div class="grid cards">
          <?php foreach ($snippets as $s): ?>
            <a class="card" href="?action=show&id=<?= (int)$s['id'] ?>">
              <div class="tag"><?= e($s['category']) ?></div>
              <h3 style="margin:8px 0"><?= e($s['title']) ?></h3>
              <div class="muted"><?= e($s['description']) ?></div>
              <div class="muted">#<?= (int)$s['id'] ?> • <?= e(substr($s['created_at'],0,16)) ?></div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php elseif ($action === 'create'): ?>
      <h2>Ajouter un snippet</h2>
      <form method="post" action="?action=store" class="card" novalidate>
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <div class="field">
          <label>Titre</label>
          <input name="title" value="<?= e($old['title'] ?? '') ?>" required>
          <?php if(!empty($errors['title'])): ?><div class="error"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label>Description</label>
          <input name="description" value="<?= e($old['description'] ?? '') ?>" required>
          <?php if(!empty($errors['description'])): ?><div class="error"><?= e($errors['description']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label>Catégorie</label>
          <select name="category" required>
            <option value="">— Sélectionner —</option>
            <?php foreach (CATEGORIES as $c): ?>
              <option value="<?= e($c) ?>" <?= ($old['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if(!empty($errors['category'])): ?><div class="error"><?= e($errors['category']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label>Code</label>
          <textarea name="code" required placeholder="Colle ton code ici..."><?= e($old['code'] ?? '') ?></textarea>
          <?php if(!empty($errors['code'])): ?><div class="error"><?= e($errors['code']) ?></div><?php endif; ?>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
          <a class="btn" href="./">Annuler</a>
          <button class="btn primary" type="submit">Enregistrer</button>
        </div>
      </form>

    <?php elseif ($action === 'edit'): 
        $id = (int)($_GET['id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM snippets WHERE id=?");
        $stmt->execute([$id]);
        $editSnippet = $stmt->fetch();
        if (!$editSnippet) { http_response_code(404); exit('Snippet not found'); }
    ?>
      <h2>Modifier le snippet</h2>
      <form method="post" action="?action=update" class="card">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
        <input type="hidden" name="id" value="<?= (int)$editSnippet['id'] ?>">
        <div class="field">
          <label>Titre</label>
          <input name="title" value="<?= e($old['title'] ?? $editSnippet['title']) ?>" required>
          <?php if(!empty($errors['title'])): ?><div class="error"><?= e($errors['title']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label>Description</label>
          <input name="description" value="<?= e($old['description'] ?? $editSnippet['description']) ?>" required>
          <?php if(!empty($errors['description'])): ?><div class="error"><?= e($errors['description']) ?></div><?php endif; ?>
        </div>
        <div class="field">
          <label>Catégorie</label>
          <select name="category" required>
            <?php foreach (CATEGORIES as $c): ?>
              <option value="<?= e($c) ?>" <?= ($old['category'] ?? $editSnippet['category'])===$c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Code</label>
          <textarea name="code" required><?= e($old['code'] ?? $editSnippet['code']) ?></textarea>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
          <a class="btn" href="?action=show&id=<?= (int)$editSnippet['id'] ?>">Annuler</a>
          <button class="btn primary" type="submit">Mettre à jour</button>
        </div>
      </form>

    <?php elseif ($action === 'show' && $snippet): ?>
      <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
          <div>
            <div class="tag"><?= e($snippet['category']) ?></div>
            <h2 style="margin:8px 0"><?= e($snippet['title']) ?></h2>
            <div class="muted"><?= e($snippet['description']) ?></div>
          </div>
          <div style="display:flex;gap:8px">
            <a class="btn" href="./">← Retour</a>
            <a class="btn" href="?action=edit&id=<?= (int)$snippet['id'] ?>">Modifier</a>
            <form method="post" action="?action=delete" onsubmit="return confirm('Supprimer ce snippet ?')" style="margin:0">
              <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
              <input type="hidden" name="id" value="<?= (int)$snippet['id'] ?>">
              <button class="btn danger" type="submit">Supprimer</button>
            </form>
            <button class="btn" data-copy-target="#code-<?= (int)$snippet['id'] ?>">Copier le code</button>
          </div>
        </div>

        <pre style="margin-top:12px" id="code-<?= (int)$snippet['id'] ?>"><code class="<?= e(prism_class($snippet['category'])) ?>"><?= e($snippet['code']) ?></code></pre>
      </div>
    <?php endif; ?>

    <div class="footer">H24Code — Snippet app • <?= date('Y') ?></div>
  </div>

  <script src="assets/app.js" defer></script>
</body>
</html>
