<?php
/**
 * 凤凰秀 PHP 版
 * 公众号:雾栈手记
 * 
 * 使用 GET 参数路由，兼容任何 PHP 环境，无需 nginx 配置
 * 
 * /fhx.php              → 首页
 * /fhx.php?page=settings → 设置页面
 * /fhx.php?ch=fhzx       → 凤凰资讯
 * /fhx.php?ch=fhzw       → 凤凰中文
 * /fhx.php?ch=fhhk       → 凤凰香港
 */

const CONFIG_FILE = __DIR__ . '/.fengshows_config.json';
const TOKEN_FILE = __DIR__ . '/.fengshows_token.json';

// 频道映射
$CHANNELS = [
    'fhzx' => ['id' => '7c96b084-60e1-40a9-89c5-682b994fb680', 'name' => '凤凰资讯', 'desc' => '资讯直播频道'],
    'fhzw' => ['id' => 'f7f48462-9b13-485b-8101-7b54716411ec', 'name' => '凤凰中文', 'desc' => '中文综合频道'],
    'fhhk' => ['id' => '15e02d92-1698-416c-af2f-3e9a872b4d78', 'name' => '凤凰香港', 'desc' => '香港地区频道'],
];

// ====== 路由 ======
$action = $_GET['action'] ?? '';
$page = $_GET['page'] ?? '';
$ch = $_GET['ch'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handleSettingsPost();
} elseif ($action === 'clear') {
    handleClear();
} elseif ($page === 'settings') {
    handleSettingsPage();
} elseif ($ch !== '' && isset($CHANNELS[$ch])) {
    handleChannel($ch);
} else {
    handleIndex();
}

// ====== 首页 ======
function handleIndex() {
    global $CHANNELS;
    $config = loadConfig();
    $hasAccount = !empty($config['phone']) && !empty($config['password']);

    $qDot = $hasAccount ? 'ok-dot' : 'warn-dot';
    $qText = $hasAccount ? '当前画质：720p (高清)' : '当前画质：480p (普通)';

    $channelsHtml = '';
    foreach ($CHANNELS as $key => $ch) {
        $channelsHtml .= <<<HTML
        <article class="card">
          <h3>{$ch['name']}</h3>
          <p>{$ch['desc']}</p>
          <div class="actions">
            <a class="btn btn-primary" href="?ch={$key}">立即播放</a>
            <button class="btn btn-ghost" onclick="copyUrl('{$key}')">复制播放地址</button>
          </div>
        </article>
HTML;
    }

    $css = <<<CSS
:root {
  --bg1: #0f172a; --bg2: #111827; --card: rgba(255,255,255,.08);
  --border: rgba(255,255,255,.12); --text: #f8fafc; --muted: #cbd5e1;
  --accent: #f97316; --accent2: #fb7185; --ok: #22c55e; --warn: #eab308;
  --shadow: 0 20px 50px rgba(0,0,0,.35);
}
* { box-sizing: border-box; margin:0; padding:0; }
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
h1 { margin: 0 0 12px; font-size: clamp(32px, 5vw, 52px); line-height: 1.08; white-space: nowrap; }
.meta { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
.pill {
  padding: 10px 14px; border-radius: 14px; background: rgba(255,255,255,.06);
  border: 1px solid var(--border); color: #e5e7eb; font-size: 14px;
}
.pill a { color: var(--muted); text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.pill a:hover { color: var(--accent); }
.section-title { font-size: 18px; font-weight: 600; margin: 32px 0 14px; }
.grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
.card {
  position: relative; overflow: hidden; padding: 24px; border-radius: 20px;
  background: rgba(255,255,255,.04); border: 1px solid var(--border);
  transition: all .2s ease;
}
.card:hover { background: rgba(255,255,255,.07); border-color: rgba(249,115,22,.3); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,.25); }
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
CSS;

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>凤凰秀-PHP版</title>
<style>{$css}</style>
<script>
function copyUrl(ch){
  var url=window.location.origin+window.location.pathname+"?ch="+ch;
  try{
    var ta=document.createElement("textarea");ta.value=url;ta.style.position="fixed";ta.style.left="-9999px";
    document.body.appendChild(ta);ta.select();document.execCommand("copy");document.body.removeChild(ta);
    showToast("播放地址已复制");
  }catch(e){showToast("复制失败: "+e.message)}
}
function showToast(msg){
  var el=document.getElementById("toast");if(el){el.textContent=msg;el.style.display="block";
  setTimeout(function(){el.textContent="";el.style.display="none"},2000)}
}
</script>
</head>
<body>
<div id="toast" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:999;background:rgba(0,0,0,.85);color:#fff;padding:12px 24px;border-radius:12px;font-size:15px;display:none;pointer-events:none;"></div>
<div class="wrap">
  <section class="hero">
    <div class="badge">公众号:雾栈手记</div>
    <h1>凤凰秀<span style="display:inline-block;padding:5px 10px;border-radius:999px;background:rgba(249,115,22,.16);border:1px solid rgba(249,115,22,.35);color:#fed7aa;font-size:0.5em;font-weight:700;margin-left:8px;vertical-align:middle;">PHP 版</span></h1>
    <div class="meta">
      <div class="pill"><span class="{$qDot}"></span>{$qText}</div>
      <div class="pill"><a href="?page=settings">⚙️ 账号设置</a></div>
      <div class="pill"><a href="https://github.com/kanchairen-d/fengshows" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg> GitHub</a></div>
    </div>
  </section>
  <h2 class="section-title">频道快捷入口</h2>
  <section class="grid">{$channelsHtml}</section>
  <div class="footer">
    <div>使用说明：</div>
    <div>1. 在 <a href="?page=settings">⚙️ 设置页面</a> 填写手机号和密码，即可解锁 720P 高清画质。</div>
    <div>2. 点击"立即播放"直接跳转直播流，或点击"复制播放地址"在 VLC/PotPlayer 中播放。</div>
    <div>3. 凤凰秀已屏蔽 APTV 等播放器的默认 UA，如遇无法播放，修改播放器 User-Agent 为手机浏览器即可，推荐：<code>Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.230 Mobile Safari/537.36</code></div>
  </div>
</div>
</body>
</html>
HTML;
}

// ====== 设置页面 ======
function handleSettingsPage() {
    $config = loadConfig();
    $phone = $config['phone'] ?? '';
    $password = $config['password'] ?? '';
    $hasAccount = $phone !== '' && $password !== '';
    $pwDisplay = $password;

    if ($hasAccount) {
        $statusHtml = '<div class="status ok">✅ 已配置账号，当前画质为 720P 高清</div>';
    } else {
        $statusHtml = '<div class="status warn">⚠️ 未配置账号，当前画质为 480p 标清</div>';
    }

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>凤凰秀-PHP版</title>
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
function togglePW(){
  var el=document.getElementById('password');
  el.type=el.type==='password'?'text':'password';
}
</script>
</head>
<body>
<div class="wrap">
  <h1>⚙️ 账号设置</h1>
  <p>配置凤凰秀账号，解锁 720P 高清画质</p>
  {$statusHtml}
  <form method="post">
    <label>手机号</label>
    <input type="text" name="phone" placeholder="请输入手机号" value="{$phone}">
    <label>密码</label>
    <div class="pw-wrap">
      <input type="password" name="password" id="password" placeholder="请输入密码" value="{$pwDisplay}">
      <span class="pw-toggle" onclick="togglePW()">👁️</span>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-save">保存并验证</button>
      <a href="{$_SERVER['SCRIPT_NAME']}" class="btn btn-back">返回首页</a>
    </div>
  </form>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="btn btn-clear" style="width:100%">清除账号信息</button>
  </form>
</div>
</body>
</html>
HTML;
}

// ====== 设置处理 POST ======
function handleSettingsPost() {
    $action = $_POST['action'] ?? '';

    if ($action === 'clear') {
        saveConfig('', '');
        clearToken();
        $msg = '已清除账号信息';
        $msgType = 'ok';
    } else {
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        if ($phone !== '' && $password !== '') {
            $token = login($phone, $password);
            if ($token) {
                saveConfig($phone, $password);
                $msg = '配置成功，当前画质已升级为 720P 高清';
                $msgType = 'ok';
            } else {
                $msg = '登录失败，请检查账号密码';
                $msgType = 'warn';
            }
        } else {
            $msg = '请填写手机号和密码';
            $msgType = 'warn';
        }
    }

    // 显示结果页
    $config = loadConfig();
    $phone = $config['phone'] ?? '';
    $password = $config['password'] ?? '';
    $hasAccount = $phone !== '' && $password !== '';
    $pwDisplay = $password;

    if ($hasAccount) {
        $statusHtml = '<div class="status ok">✅ 已配置账号，当前画质为 720P 高清</div>';
    } else {
        $statusHtml = '<div class="status warn">⚠️ 未配置账号，当前画质为 480p 标清</div>';
    }

    $msgHtml = "<div class=\"status {$msgType}\">{$msg}</div>";

    echo <<<HTML
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>凤凰秀-PHP版</title>
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
function togglePW(){
  var el=document.getElementById('password');
  el.type=el.type==='password'?'text':'password';
}
</script>
</head>
<body>
<div class="wrap">
  <h1>⚙️ 账号设置</h1>
  <p>配置凤凰秀账号，解锁 720P 高清画质</p>
  {$statusHtml}
  {$msgHtml}
  <form method="post">
    <label>手机号</label>
    <input type="text" name="phone" placeholder="请输入手机号" value="{$phone}">
    <label>密码</label>
    <div class="pw-wrap">
      <input type="password" name="password" id="password" placeholder="请输入密码" value="{$pwDisplay}">
      <span class="pw-toggle" onclick="togglePW()">👁️</span>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-save">保存并验证</button>
      <a href="{$_SERVER['SCRIPT_NAME']}" class="btn btn-back">返回首页</a>
    </div>
  </form>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="btn btn-clear" style="width:100%">清除账号信息</button>
  </form>
</div>
</body>
</html>
HTML;
}

// ====== 清除 ======
function handleClear() {
    saveConfig('', '');
    clearToken();
    header('Location: ' . $_SERVER['SCRIPT_NAME']);
    exit;
}

// ====== 频道处理 ======
function handleChannel($key) {
    global $CHANNELS;
    $ch = $CHANNELS[$key];
    $config = loadConfig();
    $hasAccount = !empty($config['phone']) && !empty($config['password']);

    $quality = 'hd';
    $token = '';

    if ($hasAccount) {
        $tok = getValidToken($config['phone'], $config['password']);
        if ($tok) {
            $quality = 'fhd';
            $token = $tok;
        }
    }

    $apiUrl = "https://api.fengshows.cn/hub/live/auth-url?live_qa={$quality}&live_id={$ch['id']}";
    $headers = [
        'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
        'Referer: https://www.fengshows.com/live',
        'Origin: https://www.fengshows.com',
    ];
    if ($token) {
        $headers[] = "Token: {$token}";
    }

    $chCurl = curl_init();
    curl_setopt_array($chCurl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($chCurl);
    curl_close($chCurl);

    $result = json_decode($body, true);

    if ($result && $result['status'] === '0' && !empty($result['data']['live_url'])) {
        header('Location: ' . $result['data']['live_url'], true, 302);
        exit;
    }

    // FHD 降级到 HD
    if ($quality === 'fhd') {
        $fallbackUrl = "https://api.fengshows.cn/hub/live/auth-url?live_qa=hd&live_id={$ch['id']}";
        $fallbackHeaders = [
            'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
            'Referer: https://www.fengshows.com/live',
            'Origin: https://www.fengshows.com',
            'Token: ',
        ];

        $chCurl2 = curl_init();
        curl_setopt_array($chCurl2, [
            CURLOPT_URL => $fallbackUrl,
            CURLOPT_HTTPHEADER => $fallbackHeaders,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body2 = curl_exec($chCurl2);
        curl_close($chCurl2);

        $result2 = json_decode($body2, true);
        if ($result2 && $result2['status'] === '0' && !empty($result2['data']['live_url'])) {
            header('Location: ' . $result2['data']['live_url'], true, 302);
            exit;
        }
    }

    $msg = $result['message'] ?? '获取直播地址失败';
    http_response_code(500);
    echo "{$msg}";
}

// ====== 登录 ======
function login($phone, $password) {
    $loginUrl = 'https://m.fengshows.com/api/v3/mp/user/login';
    $payload = json_encode([
        'code' => '86',
        'keep_alive' => false,
        'password' => $password,
        'phone' => $phone,
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $loginUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36',
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ]);
    $body = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($body, true);
    if ($result && $result['message'] === 'ok' && !empty($result['data']['token'])) {
        $token = $result['data']['token'];
        saveToken($token);
        return $token;
    }
    return null;
}

// ====== Token 管理 ======
function getValidToken($phone, $password) {
    $tokenData = loadToken();

    if ($tokenData && !empty($tokenData['token'])) {
        $exp = parseJWTExp($tokenData['token']);
        $now = time();
        $token = $tokenData['token'];

        if ($now < $exp - 300) {
            return $token;
        }
    }

    return login($phone, $password);
}

function parseJWTExp($token) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return time() + 21600;
    }

    $payload = $parts[1];
    $payload = strtr($payload, '-_', '+/');
    $payload = str_pad($payload, strlen($payload) % 4 ? 4 - strlen($payload) % 4 + strlen($payload) : strlen($payload), '=');

    $decoded = base64_decode($payload, true);
    if ($decoded === false) {
        return time() + 21600;
    }

    $claims = json_decode($decoded, true);
    return $claims['exp'] ?? time() + 21600;
}

// ====== 配置持久化 ======
function loadConfig() {
    if (!file_exists(CONFIG_FILE)) {
        return ['phone' => '', 'password' => ''];
    }
    $data = @file_get_contents(CONFIG_FILE);
    if ($data === false) {
        return ['phone' => '', 'password' => ''];
    }
    $cfg = json_decode($data, true);
    if (!is_array($cfg)) {
        return ['phone' => '', 'password' => ''];
    }
    return $cfg;
}

function saveConfig($phone, $password) {
    $dir = dirname(CONFIG_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $data = json_encode(['phone' => $phone, 'password' => $password], JSON_UNESCAPED_UNICODE);
    @file_put_contents(CONFIG_FILE, $data);
}

function loadToken() {
    if (!file_exists(TOKEN_FILE)) {
        return null;
    }
    $data = @file_get_contents(TOKEN_FILE);
    if ($data === false) {
        return null;
    }
    $tok = json_decode($data, true);
    return is_array($tok) ? $tok : null;
}

function saveToken($token) {
    $dir = dirname(TOKEN_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $data = json_encode(['token' => $token], JSON_UNESCAPED_UNICODE);
    @file_put_contents(TOKEN_FILE, $data);
}

function clearToken() {
    if (file_exists(TOKEN_FILE)) {
        @unlink(TOKEN_FILE);
    }
}