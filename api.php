<?php
require_once 'auth.php';

header('Content-Type: text/html; charset=utf-8');

$dataFile = __DIR__ . '/data/links.json';

function getData() {
    global $dataFile;
    if (!file_exists($dataFile)) return ['links' => [], 'categories' => [], 'pinned_category' => null];
    $data = json_decode(file_get_contents($dataFile), true);
    if (!isset($data['pinned_category'])) $data['pinned_category'] = null;
    return $data;
}

function saveData($data) {
    global $dataFile;
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT));
}

$action = $_GET['action'] ?? '';

// Helper to fetch title and favicon
if ($action === 'fetch_info') {
    $url = $_POST['url'] ?? '';
    if (!$url) exit;

    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
        $url = "http://" . $url;
    }

    $title = $url;
    $favicon = "";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
    $html = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);

    if ($html) {
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $nodes = $dom->getElementsByTagName('title');
        if ($nodes->length > 0) {
            $title = $nodes->item(0)->nodeValue;
        }

        // Try to find favicon
        $baseUrl = parse_url($info['url'], PHP_URL_SCHEME) . "://" . parse_url($info['url'], PHP_URL_HOST);
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            $rel = strtolower($link->getAttribute('rel'));
            if (strpos($rel, 'icon') !== false || strpos($rel, 'shortcut') !== false) {
                $favicon = $link->getAttribute('href');
                if (strpos($favicon, 'http') !== 0) {
                    if (strpos($favicon, '/') === 0) {
                        $favicon = $baseUrl . $favicon;
                    } else {
                        $favicon = $baseUrl . '/' . $favicon;
                    }
                }
                break;
            }
        }

        if (!$favicon) {
            $favicon = $baseUrl . "/favicon.ico";
        }
    }

    echo json_encode(['title' => $title, 'favicon' => $favicon]);
    exit;
}

// Authentication
if ($action === 'login') {
    $password = $_POST['password'] ?? '';
    if (checkLogin($password)) {
        header("HX-Refresh: true");
    } else {
        echo '<div style="color: #ff4d4d; margin-top: 10px;">Senha incorreta!</div>';
    }
    exit;
}

if ($action === 'logout') {
    logout();
    header("HX-Redirect: index.php");
    exit;
}

// Link Management (Requires Login)
if (!isLoggedIn() && in_array($action, ['save_link', 'delete_link', 'reorder'])) {
    http_response_code(401);
    exit('Unauthorized');
}

if ($action === 'save_link') {
    $data = getData();
    $id = !empty($_POST['id']) ? $_POST['id'] : uniqid();
    $title = $_POST['title'] ?? '';
    $url = $_POST['url'] ?? '';
    $favicon = $_POST['favicon'] ?? '';
    $cats = $_POST['categories'] ?? ''; // comma separated string
    $categories = array_filter(array_map('trim', explode(',', $cats)));

    $newLink = [
        'id' => $id,
        'title' => $title,
        'url' => $url,
        'favicon' => $favicon,
        'categories' => $categories,
        'order' => count($data['links'])
    ];

    $found = false;
    foreach ($data['links'] as $key => $link) {
        if ($link['id'] === $id) {
            $newLink['order'] = $link['order'];
            $data['links'][$key] = $newLink;
            $found = true;
            break;
        }
    }

    if (!$found) {
        $data['links'][] = $newLink;
    }

    // Update global categories
    foreach ($categories as $cat) {
        if (!in_array($cat, $data['categories'])) {
            $data['categories'][] = $cat;
        }
    }

    saveData($data);
    header("HX-Refresh: true");
    exit;
}

if ($action === 'delete_link') {
    $id = $_POST['id'] ?? '';
    $data = getData();
    $data['links'] = array_values(array_filter($data['links'], function($l) use ($id) {
        return $l['id'] !== $id;
    }));
    saveData($data);
    echo ""; // HTMX will remove the element
    exit;
}

if ($action === 'delete_category') {
    $catToDelete = $_POST['category'] ?? '';
    $data = getData();
    $data['categories'] = array_values(array_filter($data['categories'], function($c) use ($catToDelete) {
        return $c !== $catToDelete;
    }));
    // Remove category from all links too? User said "excluir categorias por esse campo de autocompletar"
    foreach ($data['links'] as $key => $link) {
        $data['links'][$key]['categories'] = array_values(array_filter($link['categories'], function($c) use ($catToDelete) {
            return $c !== $catToDelete;
        }));
    }
    saveData($data);
    header("HX-Refresh: true");
    exit;
}

if ($action === 'reorder') {
    $ids = $_POST['ids'] ?? [];
    $data = getData();
    $newLinks = [];
    foreach ($ids as $index => $id) {
        foreach ($data['links'] as $link) {
            if ($link['id'] === $id) {
                $link['order'] = $index;
                $newLinks[] = $link;
                break;
            }
        }
    }
    $data['links'] = $newLinks;
    saveData($data);
    exit;
}

if ($action === 'search') {
    $query = mb_strtolower($_GET['q'] ?? '', 'UTF-8');
    $data = getData();
    $results = [];
    if ($query !== '') {
        foreach ($data['links'] as $link) {
            $title = mb_strtolower($link['title'], 'UTF-8');
            $url = mb_strtolower($link['url'], 'UTF-8');
            if (strpos($title, $query) !== false || strpos($url, $query) !== false) {
                $results[] = $link;
            }
        }
    }
    
    if (empty($results)) {
        echo '<div style="text-align: center; color: var(--text-muted); margin-top: 2rem;">Nenhum link encontrado.</div>';
    } else {
        echo '<div class="links-grid">';
        foreach ($results as $link) {
            $favicon = $link['favicon'] ?: 'https://www.google.com/s2/favicons?sz=64&domain=' . parse_url($link['url'], PHP_URL_HOST);
            echo '
            <a href="' . htmlspecialchars($link['url']) . '" target="_blank" class="link-card">
                <img src="' . htmlspecialchars($favicon) . '" alt="' . htmlspecialchars($link['title']) . '">
                <span>' . htmlspecialchars($link['title']) . '</span>
            </a>';
        }
        echo '</div>';
    }
    exit;
}

if ($action === 'pin_category') {
    $cat = $_POST['category'] ?? null;
    $data = getData();
    $data['pinned_category'] = ($data['pinned_category'] === $cat) ? null : $cat;
    saveData($data);
    header("HX-Refresh: true");
    exit;
}
?>
