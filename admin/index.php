<?php
session_start();

const DATA_FILE = __DIR__ . '/../data/routes.json';
const WORD_BANK_FILE = __DIR__ . '/../config/word_bank.php';

if (!file_exists(DATA_FILE)) {
    file_put_contents(DATA_FILE, json_encode([]));
}

$routes = json_decode(file_get_contents(DATA_FILE), true) ?: [];
$wordBank = file_exists(WORD_BANK_FILE) ? require WORD_BANK_FILE : [];

function normalize_path(string $path): string
{
    $path = trim($path);
    if ($path === '' || $path === '/') {
        return '/';
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    // remove trailing slashes but keep root
    $path = rtrim($path, '/');
    return $path === '' ? '/' : $path;
}

function save_routes(array $routes): void
{
    $encoded = json_encode(array_values($routes), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    file_put_contents(DATA_FILE, $encoded, LOCK_EX);
}

function find_route_index(array $routes, string $path): ?int
{
    foreach ($routes as $index => $route) {
        if ($route['path'] === $path) {
            return $index;
        }
    }
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pathInput = $_POST['path'] ?? '';
    $imageUrl = trim($_POST['image_url'] ?? '');
    $waitSeconds = (int)($_POST['wait_seconds'] ?? 0);
    $redirectUrl = trim($_POST['redirect_url'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $normalizedPath = normalize_path($pathInput);

    if ($action === 'delete') {
        $targetPath = normalize_path($_POST['target_path'] ?? '');
        $index = find_route_index($routes, $targetPath);
        if ($index !== null) {
            array_splice($routes, $index, 1);
            save_routes($routes);
            $_SESSION['flash'] = '已删除路由 ' . $targetPath;
        }
        header('Location: index.php');
        exit;
    }

    if ($normalizedPath === '/') {
        $_SESSION['flash'] = '路由不能为空。';
        header('Location: index.php');
        exit;
    }

    if (!filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['flash'] = '跳转 URL 无效。';
        header('Location: index.php' . ($action === 'update' ? '?edit=' . urlencode($normalizedPath) : ''));
        exit;
    }

    if ($imageUrl !== '' && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $_SESSION['flash'] = '展示图片 URL 无效。';
        header('Location: index.php' . ($action === 'update' ? '?edit=' . urlencode($normalizedPath) : ''));
        exit;
    }

    if ($waitSeconds < 0) {
        $_SESSION['flash'] = '等待时间必须为非负整数。';
        header('Location: index.php' . ($action === 'update' ? '?edit=' . urlencode($normalizedPath) : ''));
        exit;
    }

    if ($action === 'create') {
        if (find_route_index($routes, $normalizedPath) !== null) {
            $_SESSION['flash'] = '该路由已存在。';
            header('Location: index.php?edit=' . urlencode($normalizedPath));
            exit;
        }
        $routes[] = [
            'path' => $normalizedPath,
            'image_url' => $imageUrl,
            'wait_seconds' => $waitSeconds,
            'redirect_url' => $redirectUrl,
            'note' => $note,
        ];
        save_routes($routes);
        $_SESSION['flash'] = '已创建路由 ' . $normalizedPath;
        header('Location: index.php');
        exit;
    }

    if ($action === 'update') {
        $originalPath = normalize_path($_POST['original_path'] ?? '');
        $index = find_route_index($routes, $originalPath);
        if ($index === null) {
            $_SESSION['flash'] = '未找到要更新的路由。';
            header('Location: index.php');
            exit;
        }
        if ($normalizedPath !== $originalPath && find_route_index($routes, $normalizedPath) !== null) {
            $_SESSION['flash'] = '新的路由路径已存在，请选择其他路径。';
            header('Location: index.php?edit=' . urlencode($originalPath));
            exit;
        }
        $routes[$index] = [
            'path' => $normalizedPath,
            'image_url' => $imageUrl,
            'wait_seconds' => $waitSeconds,
            'redirect_url' => $redirectUrl,
            'note' => $note,
        ];
        save_routes($routes);
        $_SESSION['flash'] = '已更新路由 ' . $normalizedPath;
        header('Location: index.php');
        exit;
    }
}

$flashMessage = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$editPath = isset($_GET['edit']) ? normalize_path($_GET['edit']) : null;
$editRoute = null;
if ($editPath) {
    $index = find_route_index($routes, $editPath);
    if ($index !== null) {
        $editRoute = $routes[$index];
    }
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>路由管理后台</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <header>
        <h1>路由管理后台</h1>
        <nav><a href="../" target="_blank">访问前台</a></nav>
    </header>

    <?php if ($flashMessage): ?>
        <div class="flash"><?= htmlspecialchars($flashMessage, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>

    <section class="form-section">
        <h2><?= $editRoute ? '编辑路由' : '新建路由'; ?></h2>
        <form method="post" class="route-form">
            <input type="hidden" name="action" value="<?= $editRoute ? 'update' : 'create'; ?>">
            <?php if ($editRoute): ?>
                <input type="hidden" name="original_path" value="<?= htmlspecialchars($editRoute['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            <?php endif; ?>
            <label>
                路由路径
                <input type="text" name="path" id="path-input" value="<?= htmlspecialchars($editRoute['path'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="例如 /@i-do-want//@afford">
            </label>
            <div class="word-bank">
                <span>常用单词：</span>
                <div class="word-buttons">
                    <?php foreach ($wordBank as $word): ?>
                        <button type="button" class="word-button" data-word="<?= htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">@<?= htmlspecialchars($word, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></button>
                    <?php endforeach; ?>
                    <button type="button" id="clear-path" class="secondary">清空</button>
                </div>
            </div>
            <label>
                展示图片 URL
                <input type="url" name="image_url" value="<?= htmlspecialchars($editRoute['image_url'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="https://example.com/banner.jpg">
            </label>
            <label>
                等待时间（秒）
                <input type="number" name="wait_seconds" min="0" value="<?= htmlspecialchars((string)($editRoute['wait_seconds'] ?? 5), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
            </label>
            <label>
                跳转 URL
                <input type="url" name="redirect_url" value="<?= htmlspecialchars($editRoute['redirect_url'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" placeholder="https://example.com/target">
            </label>
            <label>
                备注
                <textarea name="note" rows="2" placeholder="备注信息"><?= htmlspecialchars($editRoute['note'] ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></textarea>
            </label>
            <div class="form-actions">
                <button type="submit" class="primary">保存</button>
                <?php if ($editRoute): ?>
                    <a href="index.php" class="secondary">取消编辑</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section>
        <h2>路由列表</h2>
        <?php if (empty($routes)): ?>
            <p class="empty">目前没有任何路由，赶紧创建一个吧！</p>
        <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>路由</th>
                            <th>展示图片</th>
                            <th>等待时间</th>
                            <th>跳转 URL</th>
                            <th>备注</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routes as $route): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($route['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></code></td>
                                <td>
                                    <?php if (!empty($route['image_url'])): ?>
                                        <a href="<?= htmlspecialchars($route['image_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" target="_blank">查看图片</a>
                                    <?php else: ?>
                                        <span class="muted">无</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$route['wait_seconds']; ?> 秒</td>
                                <td><a href="<?= htmlspecialchars($route['redirect_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" target="_blank">跳转地址</a></td>
                                <td><?= $route['note'] !== '' ? htmlspecialchars($route['note'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '<span class="muted">无</span>'; ?></td>
                                <td class="actions">
                                    <a href="?edit=<?= urlencode($route['path']); ?>">编辑</a>
                                    <form method="post" onsubmit="return confirm('确认删除该路由吗？');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="target_path" value="<?= htmlspecialchars($route['path'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
                                        <button type="submit" class="danger">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <script>
        const pathInput = document.getElementById('path-input');
        const buttons = document.querySelectorAll('.word-button');
        const clearButton = document.getElementById('clear-path');

        function appendWord(word) {
            const current = pathInput.value.trim();
            const segment = '/@' + word;
            if (!current || current === '/') {
                pathInput.value = segment;
                return;
            }
            let next = current;
            if (next[0] !== '/') {
                next = '/' + next;
            }
            next = next.replace(/\/+$/, '');
            pathInput.value = next + segment;
        }

        buttons.forEach(button => {
            button.addEventListener('click', () => appendWord(button.dataset.word));
        });

        clearButton.addEventListener('click', () => {
            pathInput.value = '';
            pathInput.focus();
        });
    </script>
</body>
</html>
