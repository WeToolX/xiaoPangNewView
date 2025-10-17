<?php
const DATA_FILE = __DIR__ . '/data/routes.json';

if (!function_exists('str_ends_with')) {
    function str_ends_with(string $haystack, string $needle): bool
    {
        $length = strlen($needle);
        if ($length === 0) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}

if (!file_exists(DATA_FILE)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo '路由数据文件不存在。';
    exit;
}

$routes = json_decode(file_get_contents(DATA_FILE), true) ?: [];

function normalize_request_path(string $path): string
{
    $path = urldecode(parse_url($path, PHP_URL_PATH) ?? '/');
    if ($path === '') {
        $path = '/';
    }
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
        if ($path === '') {
            $path = '/';
        }
    }
    return $path;
}

$requestPath = normalize_request_path($_SERVER['REQUEST_URI'] ?? '/');

header('Content-Type: text/html; charset=UTF-8');

if ($requestPath === '/' || $requestPath === '') {
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>图片跳转路由</title><style>body{font-family:Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#f5f7fb;margin:0;color:#2d3436;}main{max-width:560px;text-align:center;padding:2rem;background:#fff;border-radius:16px;box-shadow:0 12px 36px rgba(0,0,0,0.08);}a{color:#0984e3;text-decoration:none;}a:hover{text-decoration:underline;}</style></head><body><main><h1>图片跳转路由系统</h1><p>请访问后台创建个性化路由，或输入已配置的路由地址。</p><p><a href="/admin/">打开后台管理</a></p></main></body></html>';
    exit;
}

$matchedRoute = null;
foreach ($routes as $route) {
    if (($route['path'] ?? '') === $requestPath) {
        $matchedRoute = $route;
        break;
    }
}

if (!$matchedRoute) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><title>未找到路由</title><style>body{font-family:Helvetica,Arial,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;background:#2d3436;margin:0;color:#fff;}main{text-align:center;padding:2rem;}a{color:#ffeaa7;text-decoration:none;}a:hover{text-decoration:underline;}</style></head><body><main><h1>404 未找到</h1><p>抱歉，没有找到对应的跳转配置。</p><p><a href="/admin/">前往后台</a></p></main></body></html>';
    exit;
}

$imageUrl = $matchedRoute['image_url'] ?? '';
$waitSeconds = max(0, (int)($matchedRoute['wait_seconds'] ?? 0));
$redirectUrl = $matchedRoute['redirect_url'] ?? '';
$note = $matchedRoute['note'] ?? '';

?><!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>即将跳转</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if ($redirectUrl && $waitSeconds > 0): ?>
        <meta http-equiv="refresh" content="<?= htmlspecialchars((string)$waitSeconds, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>;url=<?= htmlspecialchars($redirectUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>">
    <?php endif; ?>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #111827;
            color: #e5e7eb;
            font-family: "Helvetica Neue", Arial, sans-serif;
        }
        main {
            text-align: center;
            padding: 2.5rem 2rem;
            background: rgba(17, 24, 39, 0.8);
            border-radius: 16px;
            box-shadow: 0 20px 45px rgba(0, 0, 0, 0.35);
            max-width: 640px;
            width: 100%;
        }
        img {
            max-width: 100%;
            border-radius: 12px;
            margin-bottom: 1.5rem;
        }
        .countdown {
            font-size: 1.1rem;
            margin: 1rem 0;
        }
        a {
            color: #60a5fa;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .note {
            margin-top: 1rem;
            color: #9ca3af;
        }
        .actions {
            margin-top: 1.5rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .button {
            padding: 0.6rem 1.2rem;
            border-radius: 999px;
            background: #2563eb;
            color: #fff;
            display: inline-block;
            transition: background 0.2s ease;
        }
        .button:hover {
            background: #1d4ed8;
        }
    </style>
</head>
<body>
<main>
    <h1>即将跳转</h1>
    <?php if ($imageUrl): ?>
        <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" alt="展示图片">
    <?php endif; ?>
    <?php if ($waitSeconds > 0): ?>
        <p class="countdown"><span id="seconds"><?= (int)$waitSeconds; ?></span> 秒后跳转到目标页面…</p>
    <?php else: ?>
        <p class="countdown">正在为您跳转到目标页面…</p>
    <?php endif; ?>
    <div class="actions">
        <?php if ($redirectUrl): ?>
            <a class="button" href="<?= htmlspecialchars($redirectUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?>" id="redirect-link">立即跳转</a>
        <?php endif; ?>
        <a href="/admin/">返回后台</a>
    </div>
    <?php if ($note !== ''): ?>
        <div class="note">备注：<?= htmlspecialchars($note, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></div>
    <?php endif; ?>
</main>
<script>
    const wait = <?= $waitSeconds; ?>;
    const redirectUrl = <?= json_encode($redirectUrl, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
    const secondsEl = document.getElementById('seconds');

    if (wait > 0 && redirectUrl) {
        let remaining = wait;
        const timer = setInterval(() => {
            remaining -= 1;
            if (remaining <= 0) {
                clearInterval(timer);
                window.location.href = redirectUrl;
            }
            if (secondsEl) {
                secondsEl.textContent = Math.max(0, remaining);
            }
        }, 1000);
    } else if (redirectUrl) {
        window.location.replace(redirectUrl);
    }
</script>
</body>
</html>
