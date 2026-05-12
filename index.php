<?php
require_once 'auth.php';
require_once 'api.php'; // Reuse getData

$data = getData();
$pinnedCat = $data['pinned_category'] ?? null;
$activeCat = $_GET['cat'] ?? ($pinnedCat ?: 'all');
$allCategories = $data['categories'] ?? [];
sort($allCategories);

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
        $favicon = $link['favicon'] ?: 'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2280%22>🔗</text></svg>';
        echo '
        <a href="' . htmlspecialchars($link['url']) . '" target="_blank" class="link-card">
            <img src="' . htmlspecialchars($favicon) . '" alt="' . htmlspecialchars($link['title']) . '" onerror="this.src=\'data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2280%22>🔗</text></svg>\'">
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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📑</text></svg>">
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
                    <button class="tab" onclick="openLinkModal()" title="Adicionar Link (Ctrl+I)" style="padding: 0.6rem 0.8rem;">📄</button>
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

    <!-- Link Modal -->
    <div id="linkModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <h2 id="modalTitle" style="margin-bottom: 1.5rem;">Adicionar Link</h2>
            <form id="linkForm" hx-post="api.php?action=save_link">
                <input type="hidden" name="id" id="linkId">
                
                <div class="form-group">
                    <label>URL</label>
                    <div class="input-with-loader">
                        <input type="url" name="url" id="linkUrl" placeholder="https://..." required 
                               onblur="fetchLinkInfo(this.value)">
                        <span id="urlLoading" class="spinner hidden"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label>Título</label>
                    <input type="text" name="title" id="linkTitle" required>
                </div>

                <div class="form-group">
                    <label>Favicon URL</label>
                    <input type="text" name="favicon" id="linkFavicon">
                </div>

                <div class="form-group">
                    <label>Categorias (Digite e pressione Enter)</label>
                    <div style="position: relative;">
                        <input type="text" id="catInput" list="allCats" placeholder="Ex: Trabalho, Social...">
                        <datalist id="allCats">
                            <?php foreach ($allCategories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <input type="hidden" name="categories" id="linkCategories">
                    <div id="chipsContainer" class="chips-container"></div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" id="saveBtn">Salvar</button>
                    <button type="button" class="btn-secondary" onclick="closeLinkModal()">Cancelar</button>
                </div>
            </form>
        </div>
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

        // Link Modal Logic
        const linkModal = document.getElementById('linkModal');
        const linkForm = document.getElementById('linkForm');
        const chipsContainer = document.getElementById('chipsContainer');
        const catInput = document.getElementById('catInput');
        const hiddenCats = document.getElementById('linkCategories');
        let selectedCategories = [];

        function openLinkModal() {
            linkForm.reset();
            document.getElementById('linkId').value = '';
            selectedCategories = [];
            renderChips();
            linkModal.classList.add('active');
            setTimeout(() => document.getElementById('linkUrl').focus(), 50);
        }

        function closeLinkModal() {
            linkModal.classList.remove('active');
        }

        async function fetchLinkInfo(url) {
            if (!url) return;
            const loader = document.getElementById('urlLoading');
            loader.classList.remove('hidden');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000);

            const formData = new FormData();
            formData.append('url', url);

            try {
                const response = await fetch('api.php?action=fetch_info', {
                    method: 'POST',
                    body: formData,
                    signal: controller.signal
                });
                const data = await response.json();
                if (data.title) document.getElementById('linkTitle').value = data.title;
                if (data.favicon) document.getElementById('linkFavicon').value = data.favicon;
            } catch (e) {
                console.warn("Fetch info failed", e);
            } finally {
                clearTimeout(timeoutId);
                loader.classList.add('hidden');
            }
        }

        catInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const val = catInput.value.trim();
                if (val && !selectedCategories.includes(val)) {
                    selectedCategories.push(val);
                    renderChips();
                }
                catInput.value = '';
            }
        });

        function renderChips() {
            chipsContainer.innerHTML = '';
            selectedCategories.forEach(cat => {
                const chip = document.createElement('div');
                chip.className = 'chip';
                chip.innerHTML = `${cat} <span class="close" onclick="removeCategory('${cat}')">✕</span>`;
                chipsContainer.appendChild(chip);
            });
            hiddenCats.value = selectedCategories.join(',');
        }

        function removeCategory(cat) {
            selectedCategories = selectedCategories.filter(c => c !== cat);
            renderChips();
        }

        document.addEventListener('keydown', (e) => {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                toggleSearch();
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                openLinkModal();
            }
            if (e.key === 'Escape') {
                const searchModal = document.getElementById('searchModal');
                const lModal = document.getElementById('linkModal');
                if (searchModal.classList.contains('active')) toggleSearch();
                if (lModal.classList.contains('active')) closeLinkModal();
            }
        });
    </script>
</body>
</html>
