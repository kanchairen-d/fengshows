<?php
/**
 * 凤凰秀 PHP 版
 * 
 * 凤凰资讯：http://192.168.1.11:5080/fhx.php?ch=fhzx
 * 凤凰中文：http://192.168.1.11:5080/fhx.php?ch=fhzw
 * 凤凰香港：http://192.168.1.11:5080/fhx.php?ch=fhhk
 */

// ==================== 配置区 ====================

define('CHANNELS', [
    'fhzx' => ['id' => '7c96b084-60e1-40a9-89c5-682b994fb680', 'name' => '凤凰资讯', 'desc' => '资讯直播频道'],
    'fhzw' => ['id' => 'f7f48462-9b13-485b-8101-7b54716411ec', 'name' => '凤凰中文', 'desc' => '中文综合频道'],
    'fhhk' => ['id' => '15e02d92-1698-416c-af2f-3e9a872b4d78', 'name' => '凤凰香港', 'desc' => '香港地区频道'],
]);

define('CONFIG_FILE', __DIR__ . '/.fengshows_config.json');
define('TOKEN_CACHE_FILE', __DIR__ . '/.fengshows_token.json');
define('WAF_CACHE_FILE', __DIR__ . '/.fengshows_waf.txt');

// ==================== 配置读写 ====================

function loadConfig(): array {
    if (!file_exists(CONFIG_FILE)) return [];
    $data = @json_decode(file_get_contents(CONFIG_FILE), true);
    return is_array($data) ? $data : [];
}

function saveConfig(array $data): bool {
    return file_put_contents(CONFIG_FILE, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function deleteConfig(): void {
    if (file_exists(CONFIG_FILE)) @unlink(CONFIG_FILE);
}

function getCredentials(): array {
    $cfg = loadConfig();
    return [
        'phone'    => $cfg['phone'] ?? '',
        'password' => $cfg['password'] ?? '',
    ];
}

// ==================== WAF ====================

function getWafCookies(): string {
    $cacheTTL = 5400; // 1.5h
    if (file_exists(WAF_CACHE_FILE) && (time() - filemtime(WAF_CACHE_FILE)) < $cacheTTL) {
        $cached = file_get_contents(WAF_CACHE_FILE);
        if (!empty($cached)) return $cached;
    }

    $ch = curl_init('https://www.fengshows.com/live');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_NOBODY         => false,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        ],
    ]);
    $resp = curl_exec($ch);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    if ($resp === false) return '';

    $header = substr($resp, 0, $headerSize);
    $cookies = [];
    foreach (explode("\r\n", $header) as $line) {
        if (stripos($line, 'Set-Cookie:') === 0) {
            $value = trim(substr($line, 11));
            $parts = explode(';', $value);
            $cookie = trim($parts[0]);
            if (!empty($cookie) && strpos($cookie, 'HWWAF') === 0) {
                $cookies[] = $cookie;
            }
        }
    }

    $result = implode('; ', $cookies);
    if (!empty($result)) {
        file_put_contents(WAF_CACHE_FILE, $result, LOCK_EX);
    }
    return $result;
}

// ==================== 跟跳 ====================

function resolveRedirect(string $url, int $maxFollow = 3): string {
    $current = $url;
    for ($i = 0; $i < $maxFollow; $i++) {
        $ch = curl_init($current);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_NOBODY         => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER     => [
                'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
                'Referer: https://www.fengshows.com/live',
            ],
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($httpCode >= 301 && $httpCode <= 308) {
            $hdrText = substr($resp, 0, $headerSize);
            $location = '';
            foreach (explode("\r\n", $hdrText) as $line) {
                if (stripos($line, 'Location:') === 0) {
                    $location = trim(substr($line, 9));
                    break;
                }
            }
            if (empty($location)) break;
            if (!preg_match('/^https?:\/\//', $location)) {
                $parsed = parse_url($current);
                $base = $parsed['scheme'] . '://' . $parsed['host'];
                $current = $location[0] === '/' ? $base . $location : $base . '/' . $location;
            } else {
                $current = $location;
            }
        } else {
            break;
        }
    }
    return $current;
}

// ==================== 直播 URL 获取 ====================

function getLiveUrl(string $channelId, string $quality, ?string $token): ?string {
    $url = "https://api.fengshows.cn/hub/live/auth-url?live_qa={$quality}&live_id={$channelId}";
    $headers = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
        'Referer: https://www.fengshows.com/live',
        'Origin: https://www.fengshows.com',
    ];

    $cookies = getWafCookies();
    if (!empty($cookies)) $headers[] = "Cookie: {$cookies}";
    if ($token) $headers[] = "Token: {$token}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp === false) return null;

    $data = json_decode($resp, true);
    if (isset($data['data']['live_url'])) {
        return resolveRedirect($data['data']['live_url']);
    }
    return null;
}

// ==================== 登录 ====================

function login(string $phone, string $password): ?string {
    $url = 'https://m.fengshows.com/api/v3/mp/user/login';
    $body = json_encode(['code' => '86', 'keep_alive' => false, 'password' => $password, 'phone' => $phone]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp === false) return null;

    $data = json_decode($resp, true);
    if (isset($data['message']) && $data['message'] === 'ok' && isset($data['data']['token'])) {
        return $data['data']['token'];
    }
    return null;
}

function validateJwt(string $token): bool {
    $parts = explode('.', $token);
    if (count($parts) !== 3) return false;
    $payloadBase64 = strtr($parts[1], '-_', '+/');
    $payloadBase64 = str_pad($payloadBase64, ceil(strlen($payloadBase64) / 4) * 4, '=', STR_PAD_RIGHT);
    $payload = @json_decode(base64_decode($payloadBase64), true);
    if (!$payload || !isset($payload['exp'])) return true;
    return $payload['exp'] > (time() + 300);
}

function getCachedToken(): ?string {
    if (!file_exists(TOKEN_CACHE_FILE)) return null;
    $data = @json_decode(file_get_contents(TOKEN_CACHE_FILE), true);
    return ($data['token'] ?? null);
}

function saveTokenCache(string $token): void {
    file_put_contents(TOKEN_CACHE_FILE, json_encode(['token' => $token, 'cached_at' => time()]), LOCK_EX);
}

function clearTokenCache(): void {
    if (file_exists(TOKEN_CACHE_FILE)) @unlink(TOKEN_CACHE_FILE);
}

function getValidToken(): ?string {
    $token = getCachedToken();
    if ($token && validateJwt($token)) return $token;

    $creds = getCredentials();
    if (empty($creds['phone']) || empty($creds['password'])) return null;

    $token = login($creds['phone'], $creds['password']);
    if ($token) saveTokenCache($token);
    return $token;
}

// ==================== 频道解析 ====================

function getChannel(): ?array {
    $key = $_GET['ch'] ?? $_GET['channel'] ?? null;
    if ($key) {
        $key = strtolower(trim($key));
        if (isset(CHANNELS[$key])) return CHANNELS[$key];
        return null;
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($requestUri, PHP_URL_PATH);
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $path = substr($path, strlen(dirname($scriptName)));
    $segments = array_values(array_filter(explode('/', $path), fn($s) => $s !== ''));
    $key = $segments[0] ?? '';
    if (isset(CHANNELS[$key])) return CHANNELS[$key];
    return null;
}

// ==================== 路由处理 ====================

// POST 处理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    header('Content-Type: text/html; charset=utf-8');

    if ($action === 'save_settings') {
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($phone) || empty($password)) {
            echo '<!doctype html><html><head><meta charset="utf-8"><title>保存失败</title><meta http-equiv="refresh" content="2;url=?page=settings"></head><body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px"><h2>❌ 保存失败</h2><p>手机号和密码不能为空。</p><a href="?page=settings" style="color:#f97316">← 返回</a></body></html>';
            exit;
        }
        saveConfig(['phone' => $phone, 'password' => $password]);
        clearTokenCache();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>保存成功</title><meta http-equiv="refresh" content="1;url=?page=settings"></head><body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px"><h2>✅ 保存成功！</h2><p>账号已保存，现在可以看 <strong>720p 高清画质</strong> 了。</p><a href="?" style="color:#f97316">← 返回首页</a></body></html>';
            exit;
    }

    if ($action === 'clear_settings') {
        deleteConfig();
        clearTokenCache();
        echo '<!doctype html><html><head><meta charset="utf-8"><title>已清除</title><meta http-equiv="refresh" content="1;url=?page=settings"></head><body style="background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px"><h2>🗑️ 已清除</h2><p>已删除保存的账号，将使用 480p 普通画质。</p><a href="?" style="color:#f97316">← 返回首页</a></body></html>';
        exit;
    }
}

// 设置页面
if (isset($_GET['page']) && $_GET['page'] === 'settings') {
    $cfg = loadConfig();
    $hasCreds = !empty($cfg['phone']) && !empty($cfg['password']);
    $maskedPhone = !empty($cfg['phone']) ? substr($cfg['phone'], 0, 3) . '****' . substr($cfg['phone'], -4) : '';
    $activeSource = $hasCreds ? '配置文件' : '未设置';
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>账号设置 - 凤凰秀 PHP 版</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 20px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", sans-serif;
      background: #0f172a;
      color: #f8fafc;
      min-height: 100vh;
    }
    .wrap { max-width: 520px; margin: 40px auto; padding: 32px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
    h1 { font-size: 26px; margin: 0 0 8px; }
    p { color: #94a3b8; font-size: 14px; margin: 0 0 24px; }
    label { display: block; margin: 16px 0 6px; font-size: 14px; color: #cbd5e1; }
    input {
      width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15);
      background: rgba(0,0,0,.3); color: #fff; font-size: 15px; outline: none;
    }
    input:focus { border-color: #f97316; }
    input::placeholder { color: #64748b; }
    .actions { display: flex; gap: 10px; margin-top: 28px; }
    .btn {
      flex: 1; padding: 12px; border-radius: 14px; border: none; font-size: 15px; font-weight: 600;
      cursor: pointer; text-align: center; text-decoration: none;
    }
    .btn:hover { opacity: .9; }
    .btn-save { background: linear-gradient(135deg, #f97316, #fb7185); color: white; }
    .btn-back { background: rgba(255,255,255,.08); color: #e5e7eb; border: 1px solid rgba(255,255,255,.12); }
    .btn-clear { background: rgba(239,68,68,.15); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
    .status { padding: 10px 14px; border-radius: 12px; margin: 16px 0; font-size: 13px; }
    .ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.25); color: #86efac; }
    .warn { background: rgba(234,179,8,.12); border: 1px solid rgba(234,179,8,.25); color: #fde68a; }
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 44px; }
    .pw-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      cursor: pointer; font-size: 18px; user-select: none; opacity: .6;
    }
    .pw-toggle:hover { opacity: 1; }
  </style>
<script>
function togglePW() {
  var el = document.getElementById('password');
  el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</head>
<body>
  <div class="wrap">
    <h1>⚙️ 账号设置</h1>
    <p>输入凤凰秀账号密码即可解锁 <strong>720p 高清画质</strong>。</p>
    <div class="status <?= $hasCreds ? 'ok' : 'warn' ?>">
      当前凭证来源：<strong><?= $activeSource ?></strong>
      <?= $hasCreds ? '✅ 已配置，可看 720p' : '⚠️ 未配置，仅 480p' ?>
    </div>
    <?php if ($hasCreds && $maskedPhone): ?>
      <p style="color:#94a3b8;font-size:13px">当前账号：<?= $maskedPhone ?></p>
    <?php endif; ?>
    <form method="POST" action="?">
      <input type="hidden" name="action" value="save_settings" />
      <label for="phone">手机号</label>
      <input type="text" id="phone" name="phone" placeholder="186xxxxxxxx" value="<?= htmlspecialchars($cfg['phone'] ?? '') ?>" />
      <label for="password">密码</label>
      <div class="pw-wrap">
        <input type="password" id="password" name="password" placeholder="输入密码" value="<?= htmlspecialchars($cfg['password'] ?? '') ?>" />
        <span class="pw-toggle" onclick="togglePW()">👁️</span>
      </div>
      <div class="actions">
        <button class="btn btn-save" type="submit">💾 保存</button>
        <a class="btn btn-back" href="?">← 返回首页</a>
      </div>
    </form>
    <?php if ($hasCreds): ?>
    <form method="POST" action="?" style="margin-top:8px">
      <input type="hidden" name="action" value="clear_settings" />
      <button class="btn btn-clear" type="submit">🗑️ 清除已保存的账号</button>
    </form>
    <?php endif; ?>
  </div>
</body></html>
<?php
    exit;
}

// ==================== 直播跳转 ====================

$channel = getChannel();
if ($channel) {
    $creds = getCredentials();
    $hasCreds = !empty($creds['phone']) && !empty($creds['password']);

    $token = null;
    $quality = 'hd';
    if ($hasCreds) {
        $token = getValidToken();
        if ($token) $quality = 'fhd';
    }

    $liveUrl = getLiveUrl($channel['id'], $quality, $token);

    // Token 失效时删缓存重新登录，再试一次 fhd
    if (!$liveUrl && $quality === 'fhd') {
        if (file_exists(TOKEN_CACHE_FILE)) @unlink(TOKEN_CACHE_FILE);
        $newToken = getValidToken();
        if ($newToken) {
            $liveUrl = getLiveUrl($channel['id'], 'fhd', $newToken);
        }
        // 还不行才降级 hd
        if (!$liveUrl) {
            $liveUrl = getLiveUrl($channel['id'], 'hd', null);
        }
    }

    if ($liveUrl) {
        header("Location: {$liveUrl}", true, 302);
        exit;
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => '获取直播地址失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== 首页 ====================

$cfg = loadConfig();
$hasCreds = !empty($cfg['phone']) && !empty($cfg['password']);
$quality = $hasCreds ? '720p (高清)' : '480p (普通)';

// 动态获取当前 URL
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $protocol . '://' . $host;
?>
<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>凤凰秀 PHP 版</title>
  <style>
    :root {
      --bg1: #0f172a; --bg2: #111827; --card: rgba(255,255,255,.08);
      --border: rgba(255,255,255,.12); --text: #f8fafc; --muted: #cbd5e1;
      --accent: #f97316; --accent2: #fb7185; --ok: #22c55e; --warn: #eab308;
      --shadow: 0 20px 50px rgba(0,0,0,.35);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
      color: var(--text);
      background: radial-gradient(circle at top left, rgba(249,115,22,.25), transparent 28%), radial-gradient(circle at top right, rgba(251,113,133,.18), transparent 24%), linear-gradient(135deg, var(--bg1), var(--bg2));
      min-height: 100vh;
    }
    .wrap { max-width: 1100px; margin: 0 auto; padding: 48px 20px 64px; }
    .hero {
      padding: 28px 28px 22px; border: 1px solid var(--border); border-radius: 24px;
      background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.05));
      backdrop-filter: blur(12px); box-shadow: var(--shadow);
    }
    .badge {
      display: inline-block; padding: 6px 12px; border-radius: 999px;
      background: rgba(249,115,22,.16); border: 1px solid rgba(249,115,22,.35);
      color: #fed7aa; font-size: 13px; margin-bottom: 16px;
    }
    h1 { margin: 0 0 12px; font-size: clamp(32px, 5vw, 52px); line-height: 1.08; }
    .sub { margin: 0; font-size: 16px; line-height: 1.8; color: var(--muted); max-width: 760px; }
    .meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
    .pill {
      padding: 10px 14px; border-radius: 14px; background: rgba(255,255,255,.06);
      border: 1px solid var(--border); color: #e5e7eb; font-size: 14px;
    }
    .pill a { color: #fff; display: flex; align-items: center; gap: 6px; text-decoration: none; }
    .section-title { margin: 34px 0 16px; font-size: 22px; }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
    .card {
      position: relative; overflow: hidden; border-radius: 22px; padding: 22px;
      background: var(--card); border: 1px solid var(--border); box-shadow: var(--shadow);
      transition: transform .18s ease, border-color .18s ease, background .18s ease;
    }
    .card:hover { transform: translateY(-4px); border-color: rgba(249,115,22,.5); background: rgba(255,255,255,.1); }
    .card::after {
      content: ""; position: absolute; inset: auto -30px -30px auto;
      width: 120px; height: 120px; border-radius: 50%;
      background: radial-gradient(circle, rgba(249,115,22,.25), transparent 70%); pointer-events: none;
    }
    .card h3 { margin: 0 0 10px; font-size: 24px; }
    .card p { margin: 0 0 18px; color: var(--muted); min-height: 48px; }
    .actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .btn {
      display: inline-flex; align-items: center; justify-content: center;
      min-width: 104px; padding: 11px 16px; border-radius: 14px;
      text-decoration: none; font-weight: 600; cursor: pointer; border: none; font-size: 14px;
      transition: opacity .18s ease, transform .18s ease;
    }
    .btn:hover { opacity: .95; transform: translateY(-1px); }
    .btn-primary { background: linear-gradient(135deg, var(--accent), var(--accent2)); color: white; }
    .btn-ghost { background: rgba(255,255,255,.06); color: #fff; border: 1px solid var(--border); }
    .footer {
      margin-top: 30px; padding: 18px 20px; border-radius: 18px;
      background: rgba(255,255,255,.05); border: 1px solid var(--border);
      color: var(--muted); line-height: 1.8;
    }
    .footer a { color: #f97316; }
    code { background: rgba(255,255,255,.08); padding: 2px 8px; border-radius: 8px; color: #fff; }
    .ok-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--ok); box-shadow: 0 0 10px rgba(34,197,94,.9); margin-right: 8px; vertical-align: middle; }
    .warn-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; background: var(--warn); box-shadow: 0 0 10px rgba(234,179,8,.7); margin-right: 8px; vertical-align: middle; }
    @media (max-width: 640px) { .wrap { padding-top: 28px; } .hero, .card { padding: 18px; } }
  </style>
  <script>
    function copyUrl(ch) {
      try {
        const url = '<?= $baseUrl ?>' + '/fhx.php?ch=' + ch;
        const ta = document.createElement('textarea');
        ta.value = url; ta.style.position = 'fixed'; ta.style.left = '-9999px';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('✅ 播放地址已复制');
      } catch(e) { showToast('❌ 复制失败: ' + e.message); }
    }
    function showToast(msg) {
      const el = document.getElementById('toast');
      if (el) { el.textContent = msg; el.style.display = 'block'; setTimeout(function(){el.style.display='none'}, 2000); }
    }
  </script>
</head>
<body>
  <div id="toast" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:999;background:rgba(0,0,0,.85);color:#fff;padding:12px 24px;border-radius:12px;font-size:15px;display:none;pointer-events:none;"></div>
  <div class="wrap">
    <section class="hero">
      <div class="badge">公众号:雾栈手记</div>
      <h1>凤凰秀 PHP 版</h1>
      <p class="sub">配置账号密码即可享受 720p 高清画质，否则默认 480p 普通画质。</p>
      <div class="meta">
        <div class="pill"><span class="<?= $hasCreds ? 'ok-dot' : 'warn-dot' ?>"></span>当前画质：<?= $quality ?></div>
        <div class="pill"><a href="?page=settings">⚙️ 账号设置</a></div>
        <div class="pill"><a href="https://github.com/kanchairen-d/fengshows" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>GitHub</a></div>
      </div>
    </section>

    <h2 class="section-title">频道快捷入口</h2>
    <section class="grid">
      <?php foreach (CHANNELS as $key => $item): ?>
        <article class="card">
          <h3><?= $item['name'] ?></h3>
          <p><?= $item['desc'] ?></p>
          <div class="actions">
            <a class="btn btn-primary" href="?ch=<?= $key ?>">立即播放</a>
            <button class="btn btn-ghost" onclick="copyUrl('<?= $key ?>')">复制播放地址</button>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="footer">
      <div>使用说明：</div>
      <div>1. 在 <a href="?page=settings">⚙️ 设置页面</a> 填入账号密码即可解锁 720p。</div>
      <div>2. 点击频道即可跳转直播流，也可点击复制地址在播放器中播放。</div>
      <div>3. 如需反代，复制地址自动适配当前访问地址。</div>
    </div>
  </div>
</body></html>
<?php