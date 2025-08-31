<?php
// public/index.php
declare(strict_types=1);

// Configuration de base de données
$host = 'sql112.infinityfree.com';
$dbname = 'if0_39833772_snippet';
$username = 'if0_39833772';
$password = 'ZjaOhj0XnTrqW';

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    die('Erreur de connexion à la base de données');
}

// Constantes
define('VALID_CATEGORIES', ['PHP', 'HTML', 'CSS']);

// Fonctions utilitaires
function sanitize_output(string $string): string 
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function is_valid_category(?string $category): bool 
{
    return in_array($category ?? '', VALID_CATEGORIES, true);
}

function safe_redirect(string $location): void 
{
    $clean_location = filter_var($location, FILTER_SANITIZE_URL);
    if ($clean_location) {
        header('Location: ' . $clean_location);
        exit();
    }
}

// Variables pour les messages et erreurs
$success_message = '';
$error_message = '';
$form_errors = [];
$form_data = [];

// Récupération de l'action
$action = $_GET['action'] ?? 'home';
$allowed_actions = ['home', 'create', 'store', 'show', 'edit', 'update', 'delete'];

if (!in_array($action, $allowed_actions)) {
    $action = 'home';
}

// CRÉATION D'UN SNIPPET (POST)
if ($action === 'store' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($title)) {
        $form_errors['title'] = 'Le titre est requis.';
    }
    if (empty($description)) {
        $form_errors['description'] = 'La description est requise.';
    }
    if (!is_valid_category($category)) {
        $form_errors['category'] = 'Catégorie invalide.';
    }
    if (empty($code)) {
        $form_errors['code'] = 'Le code ne peut pas être vide.';
    }

    if (empty($form_errors)) {
        try {
            $stmt = $pdo->prepare('INSERT INTO snippets (title, description, category, code, created_at) VALUES (?, ?, ?, ?, NOW())');
            $success = $stmt->execute([$title, $description, $category, $code]);
            
            if ($success) {
                safe_redirect('./?success=1');
            } else {
                $error_message = 'Erreur lors de l\'ajout du snippet.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur de base de données.';
        }
    } else {
        $form_data = compact('title', 'description', 'category', 'code');
    }
}

// MISE À JOUR D'UN SNIPPET (POST)
if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        safe_redirect('./');
    }

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $code = trim($_POST['code'] ?? '');

    if (empty($title)) {
        $form_errors['title'] = 'Le titre est requis.';
    }
    if (empty($description)) {
        $form_errors['description'] = 'La description est requise.';
    }
    if (!is_valid_category($category)) {
        $form_errors['category'] = 'Catégorie invalide.';
    }
    if (empty($code)) {
        $form_errors['code'] = 'Le code ne peut pas être vide.';
    }

    if (empty($form_errors)) {
        try {
            $stmt = $pdo->prepare('UPDATE snippets SET title = ?, description = ?, category = ?, code = ?, updated_at = NOW() WHERE id = ?');
            $success = $stmt->execute([$title, $description, $category, $code, $id]);
            
            if ($success) {
                safe_redirect("?action=show&id={$id}&updated=1");
            } else {
                $error_message = 'Erreur lors de la mise à jour.';
            }
        } catch (PDOException $e) {
            $error_message = 'Erreur de base de données.';
        }
    } else {
        $form_data = compact('title', 'description', 'category', 'code');
    }
}

// SUPPRESSION D'UN SNIPPET (POST)
if ($action === 'delete' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
    if ($id && $id > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM snippets WHERE id = ?');
            $stmt->execute([$id]);
            safe_redirect('./?deleted=1');
        } catch (PDOException $e) {
            safe_redirect('./?error=delete');
        }
    }
    safe_redirect('./');
}

// Messages depuis l'URL
if (isset($_GET['success'])) {
    $success_message = 'Snippet ajouté avec succès.';
}
if (isset($_GET['updated'])) {
    $success_message = 'Snippet mis à jour avec succès.';
}
if (isset($_GET['deleted'])) {
    $success_message = 'Snippet supprimé avec succès.';
}
if (isset($_GET['error'])) {
    $error_message = 'Une erreur est survenue.';
}

// AFFICHAGE D'UN SNIPPET
$current_snippet = null;
if ($action === 'show') {
    $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        http_response_code(400);
        die('ID invalide');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM snippets WHERE id = ?');
        $stmt->execute([$id]);
        $current_snippet = $stmt->fetch();
        
        if (!$current_snippet) {
            http_response_code(404);
            die('Snippet non trouvé');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        die('Erreur de base de données');
    }
}

// AFFICHAGE D'UN SNIPPET POUR ÉDITION
$edit_snippet = null;
if ($action === 'edit') {
    $id = filter_var($_GET['id'] ?? 0, FILTER_VALIDATE_INT);
    if (!$id || $id <= 0) {
        safe_redirect('./');
    }

    try {
        $stmt = $pdo->prepare('SELECT * FROM snippets WHERE id = ?');
        $stmt->execute([$id]);
        $edit_snippet = $stmt->fetch();
        
        if (!$edit_snippet) {
            http_response_code(404);
            die('Snippet non trouvé');
        }
    } catch (PDOException $e) {
        http_response_code(500);
        die('Erreur de base de données');
    }
}

// LISTE DES SNIPPETS AVEC FILTRE OPTIONNEL
$category_filter = $_GET['category'] ?? 'ALL';
$snippets_params = [];
$where_clause = '';

if (is_valid_category($category_filter)) {
    $where_clause = 'WHERE category = ?';
    $snippets_params[] = $category_filter;
}

try {
    $sql = "SELECT id, title, description, category, created_at FROM snippets {$where_clause} ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($snippets_params);
    $all_snippets = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_snippets = [];
    $error_message = 'Erreur lors du chargement des snippets.';
}

// Fonction pour déterminer la classe Prism.js
function get_prism_class(string $category): string 
{
    $class_map = [
        'PHP' => 'language-php',
        'HTML' => 'language-markup',
        'CSS' => 'language-css'
    ];
    
    return $class_map[$category] ?? 'language-none';
}

// Fonction pour formater la date
function format_date(string $datetime): string 
{
    return date('d/m/Y H:i', strtotime($datetime));
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <title>H24Code - Gestionnaire de Snippets</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        /* CSS de base intégré pour éviter les dépendances externes */
        * { box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif; 
            margin: 0; 
            padding: 20px; 
            background: #f5f5f5; 
            color: #333; 
            line-height: 1.6;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding: 20px; 
            background: white; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn { 
            padding: 10px 16px; 
            background: #e9ecef; 
            color: #495057; 
            text-decoration: none; 
            border-radius: 4px; 
            border: none; 
            cursor: pointer; 
            display: inline-block;
            font-size: 14px;
        }
        .btn:hover { background: #dee2e6; }
        .btn.primary { background: #007bff; color: white; }
        .btn.primary:hover { background: #0056b3; }
        .btn.danger { background: #dc3545; color: white; }
        .btn.danger:hover { background: #c82333; }
        .card { 
            background: white; 
            padding: 20px; 
            border-radius: 8px; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.1); 
            margin-bottom: 20px; 
        }
        .card.success { background: #d4edda; border-left: 4px solid #28a745; }
        .card.error { background: #f8d7da; border-left: 4px solid #dc3545; }
        .field { margin-bottom: 16px; }
        .field label { display: block; margin-bottom: 4px; font-weight: 600; }
        .field input, .field select, .field textarea { 
            width: 100%; 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 4px; 
            font-size: 14px;
        }
        .field textarea { height: 200px; resize: vertical; font-family: 'Courier New', monospace; }
        .error { color: #dc3545; font-size: 12px; margin-top: 4px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
        .tag { 
            display: inline-block; 
            background: #007bff; 
            color: white; 
            padding: 2px 8px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: 600;
        }
        .muted { color: #6c757d; font-size: 14px; }
        .toolbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            margin-bottom: 20px; 
            padding: 16px; 
            background: white; 
            border-radius: 8px; 
        }
        .empty { 
            text-align: center; 
            padding: 60px 20px; 
            color: #6c757d; 
            background: white; 
            border-radius: 8px; 
        }
        pre { 
            background: #2d3748; 
            color: #e2e8f0; 
            padding: 16px; 
            border-radius: 4px; 
            overflow-x: auto; 
            margin: 16px 0;
        }
        code { font-family: 'Courier New', Monaco, monospace; }
        h2 { margin-top: 0; }
        h3 { margin: 8px 0; }
        .footer { 
            text-align: center; 
            margin-top: 40px; 
            padding: 20px; 
            color: #6c757d; 
            font-size: 14px;
        }
        @media (max-width: 768px) {
            .header { flex-direction: column; gap: 12px; }
            .toolbar { flex-direction: column; gap: 12px; align-items: stretch; }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="brand">
                <a class="btn" href="./">⚡ H24Code • Snippets</a>
            </div>
            <nav style="display:flex;gap:10px">
                <a class="btn" href="./">Accueil</a>
                <a class="btn primary" href="?action=create">+ Ajouter</a>
            </nav>
        </header>

        <?php if ($success_message): ?>
            <div class="card success">
                <strong><?= sanitize_output($success_message) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="card error">
                <strong><?= sanitize_output($error_message) ?></strong>
            </div>
        <?php endif; ?>

        <main>
            <?php if ($action === 'home'): ?>
                <div class="toolbar">
                    <div class="muted">Gérez vos snippets de code (PHP, HTML, CSS) facilement.</div>
                    <form method="get" style="display:flex;gap:8px">
                        <input type="hidden" name="action" value="home">
                        <select name="category" onchange="this.form.submit()">
                            <option value="ALL" <?= $category_filter === 'ALL' ? 'selected' : '' ?>>Toutes catégories</option>
                            <?php foreach (VALID_CATEGORIES as $cat): ?>
                                <option value="<?= sanitize_output($cat) ?>" <?= $category_filter === $cat ? 'selected' : '' ?>>
                                    <?= sanitize_output($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>

                <?php if (empty($all_snippets)): ?>
                    <div class="empty">
                        Aucun snippet<?= is_valid_category($category_filter) ? " dans « " . sanitize_output($category_filter) . " »" : '' ?>.
                    </div>
                <?php else: ?>
                    <div class="grid">
                        <?php foreach ($all_snippets as $snippet): ?>
                            <div class="card">
                                <div class="tag"><?= sanitize_output($snippet['category']) ?></div>
                                <h3><a href="?action=show&id=<?= $snippet['id'] ?>" style="text-decoration: none; color: inherit;"><?= sanitize_output($snippet['title']) ?></a></h3>
                                <div class="muted"><?= sanitize_output($snippet['description']) ?></div>
                                <div class="muted" style="margin-top: 12px;">
                                    #<?= $snippet['id'] ?> • <?= format_date($snippet['created_at']) ?>
                                </div>
                                <div style="margin-top: 12px;">
                                    <a class="btn" href="?action=show&id=<?= $snippet['id'] ?>">Voir</a>
                                    <a class="btn" href="?action=edit&id=<?= $snippet['id'] ?>">Modifier</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php elseif ($action === 'create'): ?>
                <h2>Ajouter un snippet</h2>
                <form method="post" action="?action=store" class="card" novalidate>
                    
                    <div class="field">
                        <label for="title">Titre</label>
                        <input type="text" id="title" name="title" value="<?= sanitize_output($form_data['title'] ?? '') ?>" required>
                        <?php if (!empty($form_errors['title'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" value="<?= sanitize_output($form_data['description'] ?? '') ?>" required>
                        <?php if (!empty($form_errors['description'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category" required>
                            <option value="">— Sélectionner —</option>
                            <?php foreach (VALID_CATEGORIES as $cat): ?>
                                <option value="<?= sanitize_output($cat) ?>" <?= ($form_data['category'] ?? '') === $cat ? 'selected' : '' ?>>
                                    <?= sanitize_output($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($form_errors['category'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['category']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="code">Code</label>
                        <textarea id="code" name="code" required placeholder="Collez votre code ici..."><?= sanitize_output($form_data['code'] ?? '') ?></textarea>
                        <?php if (!empty($form_errors['code'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:8px;justify-content:flex-end">
                        <a class="btn" href="./">Annuler</a>
                        <button class="btn primary" type="submit">Enregistrer</button>
                    </div>
                </form>

            <?php elseif ($action === 'edit' && $edit_snippet): ?>
                <h2>Modifier le snippet</h2>
                <form method="post" action="?action=update" class="card">
                    <input type="hidden" name="id" value="<?= $edit_snippet['id'] ?>">
                    
                    <div class="field">
                        <label for="title">Titre</label>
                        <input type="text" id="title" name="title" value="<?= sanitize_output($form_data['title'] ?? $edit_snippet['title']) ?>" required>
                        <?php if (!empty($form_errors['title'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="description">Description</label>
                        <input type="text" id="description" name="description" value="<?= sanitize_output($form_data['description'] ?? $edit_snippet['description']) ?>" required>
                        <?php if (!empty($form_errors['description'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['description']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="category">Catégorie</label>
                        <select id="category" name="category" required>
                            <?php foreach (VALID_CATEGORIES as $cat): ?>
                                <option value="<?= sanitize_output($cat) ?>" <?= ($form_data['category'] ?? $edit_snippet['category']) === $cat ? 'selected' : '' ?>>
                                    <?= sanitize_output($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($form_errors['category'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['category']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="code">Code</label>
                        <textarea id="code" name="code" required><?= sanitize_output($form_data['code'] ?? $edit_snippet['code']) ?></textarea>
                        <?php if (!empty($form_errors['code'])): ?>
                            <div class="error"><?= sanitize_output($form_errors['code']) ?></div>
                        <?php endif; ?>
                    </div>

                    <div style="display:flex;gap:8px;justify-content:flex-end">
                        <a class="btn" href="?action=show&id=<?= $edit_snippet['id'] ?>">Annuler</a>
                        <button class="btn primary" type="submit">Mettre à jour</button>
                    </div>
                </form>

            <?php elseif ($action === 'show' && $current_snippet): ?>
                <div class="card">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap">
                        <div>
                            <div class="tag"><?= sanitize_output($current_snippet['category']) ?></div>
                            <h2 style="margin:8px 0"><?= sanitize_output($current_snippet['title']) ?></h2>
                            <div class="muted"><?= sanitize_output($current_snippet['description']) ?></div>
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap">
                            <a class="btn" href="./">← Retour</a>
                            <a class="btn" href="?action=edit&id=<?= $current_snippet['id'] ?>">Modifier</a>
                            <form method="post" action="?action=delete" onsubmit="return confirm('Êtes-vous sûr de vouloir supprimer ce snippet ?')" style="margin:0">
                                <input type="hidden" name="id" value="<?= $current_snippet['id'] ?>">
                                <button class="btn danger" type="submit">Supprimer</button>
                            </form>
                            <button class="btn" onclick="copyCode('code-<?= $current_snippet['id'] ?>')">Copier le code</button>
                        </div>
                    </div>

                    <pre id="code-<?= $current_snippet['id'] ?>"><code><?= sanitize_output($current_snippet['code']) ?></code></pre>
                </div>
            <?php endif; ?>
        </main>

        <footer class="footer">
            H24Code — Gestionnaire de snippets • <?= date('Y') ?>
        </footer>
    </div>

    <script>
        function copyCode(elementId) {
            const codeElement = document.getElementById(elementId);
            const codeText = codeElement.textContent || codeElement.innerText;
            
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(codeText).then(function() {
                    alert('Code copié dans le presse-papiers !');
                }).catch(function(err) {
                    console.error('Erreur lors de la copie: ', err);
                    fallbackCopyText(codeText);
                });
            } else {
                fallbackCopyText(codeText);
            }
        }
        
        function fallbackCopyText(text) {
            const textArea = document.createElement("textarea");
            textArea.value = text;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            textArea.style.top = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                alert('Code copié dans le presse-papiers !');
            } catch (err) {
                console.error('Erreur lors de la copie: ', err);
                alert('Impossible de copier automatiquement. Veuillez sélectionner et copier manuellement.');
            }
            
            document.body.removeChild(textArea);
        }
    </script>
</body>
</html>