<?php
require_once 'auth.php';
require_once 'api.php'; // Reuse getData

$data = getData();
$pinnedCat = $data['pinned_category'] ?? null;
$activeCat = $_GET['cat'] ?? ($pinnedCat ?: 'all');

// Filter links
$links = $data['links'];
if ($activeCat !== 'all') {
    $links = array_filter($links, function($l) use ($activeCat) {
        return in_array($activeCat, $l['categories']);
    });
}

// Sort links by order
usort($links, function($a, $b) {
    return $a['order'] <=> $b['order'];
});

// Get categories that have links
$usedCategories = [];
foreach ($data['links'] as $link) {
    foreach ($link['categories'] as $cat) {
        if (!in_array($cat, $usedCategories)) {
            $usedCategories[] = $cat;
        }
    }
}
sort($usedCategories);

// Move pinned category to the front
if ($pinnedCat && in_array($pinnedCat, $usedCategories)) {
    $usedCategories = array_diff($usedCategories, [$pinnedCat]);
    array_unshift($usedCategories, $pinnedCat);
}

// Check if HTMX request for partial update
$isPartial = isset($_SERVER['HTTP_HX_REQUEST']);

if ($isPartial && isset($_GET['cat'])) {
    renderLinks($links);
    exit;
}

function renderLinks($links) {
    if (empty($links)) {
        echo '<div style="grid-column: 1/-1; text-align: center; color: var(--text-muted); margin-top: 2rem;">Nenhum link encontrado nesta categoria.</div>';
    }
    foreach ($links as $link) {
        $favicon = $link['favicon'] ?: 'https://www.google.com/s2/favicons?sz=64&domain=' . parse_url($link['url'], PHP_URL_HOST);
        echo '
        <a href="' . htmlspecialchars($link['url']) . '" target="_blank" class="link-card">
            <img src="' . htmlspecialchars($favicon) . '" alt="' . htmlspecialchars($link['title']) . '" onerror="this.src=\'https://www.google.com/s2/favicons?sz=64&domain=google.com\'">
            <span>' . htmlspecialchars($link['title']) . '</span>
        </a>';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Central</title>
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
</head>
<body>
    <div class="container">
        <?php if (!isLoggedIn()): ?>
            <!-- Login Screen -->
            <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 80vh;">
                <h1 style="margin-bottom: 2rem;">Central</h1>
                <div class="modal-content" style="display: block; position: static; width: 100%; max-width: 400px;">
                    <h2 style="margin-bottom: 1.5rem; text-align: center;">Acesso Restrito</h2>
                    <form hx-post="api.php?action=login" hx-target="#login-error">
                        <div class="form-group">
                            <label>Senha</label>
                            <input type="password" name="password" required autofocus>
                        </div>
                        <div id="login-error"></div>
                        <button type="submit" style="margin-top: 1rem;">Entrar</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Full Dashboard -->
            <header>
                <h1>Central</h1>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="tab" onclick="toggleSearch()" title="Pesquisar (Ctrl+K)" style="padding: 0.6rem 0.8rem;">🔍</button>
                    <a href="admin.php" class="tab active" style="text-decoration: none;">Gerenciar</a>
                    <button hx-post="api.php?action=logout" class="tab" style="border: none;">Sair</button>
                </div>
            </header>

            <div class="tabs">
                <button 
                    hx-get="index.php?cat=all" 
                    hx-target="#links-container" 
                    hx-push-url="true"
                    class="tab <?= $activeCat === 'all' ? 'active' : '' ?>"
                    onclick="document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); this.classList.add('active')"
                >Tudo</button>
                
                <?php foreach ($usedCategories as $cat): ?>
                    <button 
                        hx-get="index.php?cat=<?= urlencode($cat) ?>" 
                        hx-target="#links-container" 
                        hx-push-url="true"
                        class="tab <?= $activeCat === $cat ? 'active' : '' ?>"
                        onclick="document.querySelectorAll('.tab').forEach(t => t.classList.remove('active')); this.classList.add('active')"
                    ><?= htmlspecialchars($cat) ?></button>
                <?php endforeach; ?>
            </div>

            <div id="links-container" class="links-grid">
                <?php renderLinks($links); ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Search Modal -->
    <div id="searchModal" class="search-modal" onclick="if(event.target === this) toggleSearch()">
        <div class="search-input-container">
            <input type="text" id="searchInput" name="q"
                   placeholder="Pesquisar links..." 
                   hx-get="api.php?action=search" 
                   hx-trigger="keyup changed delay:200ms" 
                   hx-target="#search-results"
                   autocomplete="off">
            <div class="search-hint">Pressione <kbd>ESC</kbd> para fechar</div>
        </div>
        <div id="search-results" class="search-results"></div>
    </div>

    <script>
        function toggleSearch() {
            const modal = document.getElementById('searchModal');
            const input = document.getElementById('searchInput');
            modal.classList.toggle('active');
            if (modal.classList.contains('active')) {
                input.value = '';
                document.getElementById('search-results').innerHTML = '';
                setTimeout(() => input.focus(), 50);
            }
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                toggleSearch();
            }
            if (e.key === 'Escape') {
                const modal = document.getElementById('searchModal');
                if (modal.classList.contains('active')) toggleSearch();
            }
        });
    </script>
</body>
</html>
