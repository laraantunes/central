<?php
require_once 'auth.php';
require_once 'api.php';

if (!isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$data = getData();
$links = $data['links'];
usort($links, function($a, $b) {
    return $a['order'] <=> $b['order'];
});

$allCategories = $data['categories'];
sort($allCategories);
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar - Central</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📑</text></svg>">
    <link rel="stylesheet" href="style.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        .admin-container { max-width: 800px; margin: 0 auto; padding: 2rem; }
        .back-link { display: inline-block; margin-bottom: 1rem; color: var(--primary-color); text-decoration: none; }
        .add-btn { background: var(--primary-color); padding: 0.8rem 1.5rem; border-radius: 10px; border: none; color: white; cursor: pointer; font-weight: 600; margin-bottom: 2rem; }
        .admin-link-card { 
            background: var(--card-bg); 
            border: 1px solid var(--glass-border); 
            border-radius: 12px; 
            padding: 1rem; 
            margin-bottom: 1rem; 
            display: flex; 
            align-items: center; 
            gap: 1rem;
            cursor: grab;
        }
        .admin-link-card:active { cursor: grabbing; }
        .admin-link-card img { width: 32px; height: 32px; border-radius: 6px; }
        .admin-link-card .info { flex-grow: 1; }
        .admin-link-card .title { font-weight: 500; display: block; }
        .admin-link-card .url { font-size: 0.8rem; color: var(--text-muted); }
        .admin-link-card .actions { display: flex; gap: 0.5rem; }
        
        .cat-tag {
            display: inline-block;
            background: rgba(157, 78, 221, 0.2);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            margin-right: 4px;
            border: 1px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <a href="index.php" class="back-link">← Voltar para Home</a>
        <header style="margin-bottom: 2rem;">
            <h1>Gerenciar Links</h1>
            <button onclick="openLinkModal()" class="add-btn">+ Adicionar Link</button>
        </header>

        <div id="links-list">
            <?php foreach ($links as $link): ?>
                <div class="admin-link-card" data-id="<?= $link['id'] ?>">
                    <img src="<?= htmlspecialchars($link['favicon'] ?: 'https://www.google.com/s2/favicons?sz=64&domain=' . parse_url($link['url'], PHP_URL_HOST)) ?>" alt="">
                    <div class="info">
                        <span class="title"><?= htmlspecialchars($link['title']) ?></span>
                        <span class="url"><?= htmlspecialchars($link['url']) ?></span>
                        <div style="margin-top: 5px;">
                            <?php foreach ($link['categories'] as $cat): ?>
                                <span class="cat-tag"><?= htmlspecialchars($cat) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="actions">
                        <button class="icon-btn" onclick="editLink(<?= htmlspecialchars(json_encode($link)) ?>)">✎</button>
                        <button class="icon-btn delete" 
                                hx-post="api.php?action=delete_link" 
                                hx-vals='{"id": "<?= $link['id'] ?>"}'
                                hx-target="closest .admin-link-card"
                                hx-confirm="Tem certeza que deseja excluir este link?"
                                hx-swap="outerHTML">✕</button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 style="margin: 2rem 0 1rem;">Categorias</h2>
        <div style="display: flex; flex-wrap: wrap;">
            <?php 
            $pinnedCat = $data['pinned_category'] ?? null;
            foreach ($allCategories as $cat): 
                $isPinned = ($pinnedCat === $cat);
            ?>
                <div class="cat-manage-item">
                    <button 
                        hx-post="api.php?action=pin_category" 
                        hx-vals='{"category": "<?= htmlspecialchars($cat) ?>"}'
                        class="tab <?= $isPinned ? 'active' : '' ?>"
                        title="<?= $isPinned ? 'Remover fixado' : 'Fixar como padrão' ?>"
                    >
                        <?= $isPinned ? '📌 ' : '' ?><?= htmlspecialchars($cat) ?>
                    </button>
                    <button 
                        hx-post="api.php?action=delete_category"
                        hx-vals='{"category": "<?= htmlspecialchars($cat) ?>"}'
                        hx-confirm="Isso excluirá a categoria '<?= htmlspecialchars($cat) ?>' permanentemente de todos os links. Continuar?"
                        class="delete-btn"
                        title="Excluir categoria"
                    >✕</button>
                </div>
            <?php endforeach; ?>
        </div>
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

    <script>
        const linkModal = document.getElementById('linkModal');
        const linkForm = document.getElementById('linkForm');
        const chipsContainer = document.getElementById('chipsContainer');
        const catInput = document.getElementById('catInput');
        const hiddenCats = document.getElementById('linkCategories');
        let selectedCategories = [];

        function openLinkModal() {
            document.getElementById('modalTitle').innerText = 'Adicionar Link';
            linkForm.reset();
            document.getElementById('linkId').value = '';
            selectedCategories = [];
            renderChips();
            linkModal.classList.add('active');
        }

        function closeLinkModal() {
            linkModal.classList.remove('active');
        }

        function editLink(link) {
            document.getElementById('modalTitle').innerText = 'Editar Link';
            document.getElementById('linkId').value = link.id;
            document.getElementById('linkUrl').value = link.url;
            document.getElementById('linkTitle').value = link.title;
            document.getElementById('linkFavicon').value = link.favicon;
            selectedCategories = link.categories;
            renderChips();
            linkModal.classList.add('active');
        }

        async function fetchLinkInfo(url) {
            if (!url) return;
            if (document.getElementById('linkId').value) return;

            const loader = document.getElementById('urlLoading');
            loader.classList.remove('hidden');

            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), 5000); // 5 seconds timeout

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
                if (e.name === 'AbortError') {
                    console.warn("Fetch timed out");
                } else {
                    console.error("Fetch failed", e);
                }
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
            
            // Allow deleting from global categories if requested
            if (confirm(`Deseja excluir a categoria "${cat}" permanentemente de todas as sugestões?`)) {
                const formData = new FormData();
                formData.append('category', cat);
                fetch('api.php?action=delete_category', { method: 'POST', body: formData });
            }
        }

        // Reordering
        const el = document.getElementById('links-list');
        Sortable.create(el, {
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: function() {
                const ids = Array.from(el.children).map(item => item.getAttribute('data-id'));
                const formData = new FormData();
                ids.forEach(id => formData.append('ids[]', id));
                fetch('api.php?action=reorder', {
                    method: 'POST',
                    body: formData
                });
            }
        });
    </script>
</body>
</html>
