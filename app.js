import express from "express";
import fs from "fs";
import path from "path";

const app = express();
const PORT = Number(process.env.PORT || 3233);

// 配置文件路径
const CONFIG_FILE = path.resolve("config.json");

const CHANNELS = {
  fhzx: { id: "7c96b084-60e1-40a9-89c5-682b994fb680", name: "凤凰资讯", desc: "资讯直播频道" },
  fhzw: { id: "f7f48462-9b13-485b-8101-7b54716411ec", name: "凤凰中文", desc: "中文综合频道" },
  fhhk: { id: "15e02d92-1698-416c-af2f-3e9a872b4d78", name: "凤凰香港", desc: "香港地区频道" },
};

let cachedToken = null;

// 解析 POST body
app.use(express.urlencoded({ extended: false }));
app.use(express.json());

/**
 * 读取配置文件
 */
function loadConfig() {
  try {
    if (fs.existsSync(CONFIG_FILE)) {
      return JSON.parse(fs.readFileSync(CONFIG_FILE, "utf8"));
    }
  } catch (e) {
    console.error("loadConfig failed:", e.message);
  }
  return {};
}

/**
 * 保存配置文件
 */
function saveConfig(data) {
  try {
    fs.writeFileSync(CONFIG_FILE, JSON.stringify(data, null, 2), "utf8");
    return true;
  } catch (e) {
    console.error("saveConfig failed:", e.message);
    return false;
  }
}

/**
 * 获取凭证（优先配置文件，再环境变量）
 */
function getCredentials() {
  const cfg = loadConfig();
  const phone = cfg.phone || process.env.PHONE || "";
  const password = cfg.password || process.env.PASSWORD || "";
  return { phone, password };
}

// ==================== 页面 ====================

app.get("/", (req, res) => {
  const cfg = loadConfig();
  const hasCreds = !!(cfg.phone && cfg.password);
  const credsFromEnv = !!(process.env.PHONE && process.env.PASSWORD);
  const quality = (hasCreds || credsFromEnv) ? "720p (高清)" : "480p (普通)";

  res.type("html").send(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>凤凰秀 Docker 版</title>
  <style>
    :root {
      --bg1: #0f172a;
      --bg2: #111827;
      --card: rgba(255,255,255,.08);
      --border: rgba(255,255,255,.12);
      --text: #f8fafc;
      --muted: #cbd5e1;
      --accent: #f97316;
      --accent2: #fb7185;
      --ok: #22c55e;
      --warn: #eab308;
      --shadow: 0 20px 50px rgba(0,0,0,.35);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
      color: var(--text);
      background:
        radial-gradient(circle at top left, rgba(249,115,22,.25), transparent 28%),
        radial-gradient(circle at top right, rgba(251,113,133,.18), transparent 24%),
        linear-gradient(135deg, var(--bg1), var(--bg2));
      min-height: 100vh;
    }
    .wrap {
      max-width: 1100px;
      margin: 0 auto;
      padding: 48px 20px 64px;
    }
    .hero {
      padding: 28px 28px 22px;
      border: 1px solid var(--border);
      border-radius: 24px;
      background: linear-gradient(180deg, rgba(255,255,255,.10), rgba(255,255,255,.05));
      backdrop-filter: blur(12px);
      box-shadow: var(--shadow);
    }
    .badge {
      display: inline-block;
      padding: 6px 12px;
      border-radius: 999px;
      background: rgba(249,115,22,.16);
      border: 1px solid rgba(249,115,22,.35);
      color: #fed7aa;
      font-size: 13px;
      margin-bottom: 16px;
    }
    h1 {
      margin: 0 0 12px;
      font-size: clamp(32px, 5vw, 52px);
      line-height: 1.08;
    }
    .sub {
      margin: 0;
      font-size: 16px;
      line-height: 1.8;
      color: var(--muted);
      max-width: 760px;
    }
    .meta {
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
      margin-top: 22px;
    }
    .pill {
      padding: 10px 14px;
      border-radius: 14px;
      background: rgba(255,255,255,.06);
      border: 1px solid var(--border);
      color: #e5e7eb;
      font-size: 14px;
    }
    .section-title {
      margin: 34px 0 16px;
      font-size: 22px;
    }
    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 18px;
    }
    .card {
      position: relative;
      overflow: hidden;
      border-radius: 22px;
      padding: 22px;
      background: var(--card);
      border: 1px solid var(--border);
      box-shadow: var(--shadow);
      transition: transform .18s ease, border-color .18s ease, background .18s ease;
    }
    .card:hover {
      transform: translateY(-4px);
      border-color: rgba(249,115,22,.5);
      background: rgba(255,255,255,.1);
    }
    .card::after {
      content: "";
      position: absolute;
      inset: auto -30px -30px auto;
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(249,115,22,.25), transparent 70%);
      pointer-events: none;
    }
    .card h3 {
      margin: 0 0 10px;
      font-size: 24px;
    }
    .card p {
      margin: 0 0 18px;
      color: var(--muted);
      min-height: 48px;
    }
    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 104px;
      padding: 11px 16px;
      border-radius: 14px;
      text-decoration: none;
      font-weight: 600;
      transition: opacity .18s ease, transform .18s ease;
      cursor: pointer;
      border: none;
      font-size: 14px;
    }
    .btn:hover { opacity: .95; transform: translateY(-1px); }
    .btn-primary {
      background: linear-gradient(135deg, var(--accent), var(--accent2));
      color: white;
    }
    .btn-warn {
      background: linear-gradient(135deg, #8b5cf6, #6366f1);
      color: white;
    }
    .btn-ghost {
      background: rgba(255,255,255,.06);
      color: #fff;
      border: 1px solid var(--border);
    }
    .footer {
      margin-top: 30px;
      padding: 18px 20px;
      border-radius: 18px;
      background: rgba(255,255,255,.05);
      border: 1px solid var(--border);
      color: var(--muted);
      line-height: 1.8;
    }
    code {
      background: rgba(255,255,255,.08);
      padding: 2px 8px;
      border-radius: 8px;
      color: #fff;
    }
    .ok-dot {
      display: inline-block;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--ok);
      box-shadow: 0 0 10px rgba(34,197,94,.9);
      margin-right: 8px;
      vertical-align: middle;
    }
    .warn-dot {
      display: inline-block;
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--warn);
      box-shadow: 0 0 10px rgba(234,179,8,.7);
      margin-right: 8px;
      vertical-align: middle;
    }
    @media (max-width: 640px) {
      .wrap { padding-top: 28px; }
      .hero, .card { padding: 18px; }
    }
  </style>
  <script>
    async function copyUrl(ch) {
      try {
        // 复制自己代理的地址（端口变了、反代了都自动适配）
        const url = window.location.origin + '/' + ch;
        const ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed';
        ta.style.left = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showToast('✅ 播放地址已复制');
      } catch (e) {
        showToast('❌ 复制失败: ' + e.message);
      }
    }
    function togglePW() {
      var el = document.getElementById('password');
      el.type = el.type === 'password' ? 'text' : 'password';
    }
    function showToast(msg) {
      const el = document.getElementById('toast');
      if (el) { el.textContent = msg; el.style.display = 'block'; el.style.opacity = '1'; setTimeout(() => { el.style.opacity = '0'; setTimeout(() => el.style.display = 'none', 300); }, 2000); }
    }
  </script>
</head>
<body>
  <div id="toast" style="position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:999;background:rgba(0,0,0,.85);color:#fff;padding:12px 24px;border-radius:12px;font-size:15px;display:none;pointer-events:none;"></div>
  <div class="wrap">
    <section class="hero">
      <div class="badge">公众号:雾栈手记</div>
      <h1>凤凰秀 Docker 版</h1>
      <p class="sub">
        配置账号密码即可享受 720p 高清画质，否则默认 480p 普通画质。
      </p>
      <div class="meta">
        <div class="pill"><span class="${hasCreds || credsFromEnv ? 'ok-dot' : 'warn-dot'}"></span>当前画质：${quality}</div>
        <div class="pill">端口：<code>${PORT}</code></div>
        <div class="pill"><a style="color:#fff" href="/settings">⚙️ 账号设置</a></div>
        <div class="pill"><a style="color:#fff;display:flex;align-items:center;gap:6px;text-decoration:none" href="https://github.com/kanchairen-d/fengshows" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>GitHub</a></div>
      </div>
    </section>

    <h2 class="section-title">频道快捷入口</h2>
    <section class="grid">
      ${Object.entries(CHANNELS).map(([key, item]) => `
        <article class="card">
          <h3>${item.name}</h3>
          <p>${item.desc}</p>
          <div class="actions">
            <a class="btn btn-primary" href="/${key}">立即播放</a>
            <button class="btn btn-ghost" onclick="copyUrl('${key}')">复制播放地址</button>
          </div>
        </article>
      `).join("")}
    </section>

    <div class="footer">
      <div>使用说明：</div>
      <div>1. 在 <a href="/settings" style="color:#f97316">⚙️ 设置页面</a> 填入账号密码即可解锁 720p（优先级高于环境变量）。</div>
      <div>2. 也可在创建容器时通过环境变量 <code>PHONE</code> / <code>PASSWORD</code> 配置，两者二选一。</div>
      <div>3. 点击频道即可跳转直播流，也可点击复制地址在播放器中播放。</div>
      <div>4. 如需反代，可把本服务挂到你的域名下，复制地址自动适配。</div>
    </div>
  </div>
</body>
</html>`);
});

app.get("/healthz", (req, res) => {
  res.json({ ok: true, service: "fengshows-docker", port: PORT, time: new Date().toISOString() });
});

// ==================== 设置页面 ====================

app.get("/settings", (req, res) => {
  const cfg = loadConfig();
  const hasCreds = !!(cfg.phone && cfg.password);
  const maskedPhone = cfg.phone ? cfg.phone.replace(/(\d{3})\d{4}(\d{4})/, "$1****$2") : "";
  const credsFromEnv = !!(process.env.PHONE && process.env.PASSWORD);
  const activeSource = hasCreds ? "配置文件" : (credsFromEnv ? "环境变量" : "未设置");

  res.type("html").send(`<!doctype html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>账号设置 - 凤凰秀 Docker 版</title>
  <style>
    * { box-sizing: border-box; }
    body {
      margin: 0; padding: 20px;
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "PingFang SC", sans-serif;
      background: #0f172a;
      color: #f8fafc;
      min-height: 100vh;
    }
    .wrap {
      max-width: 520px;
      margin: 40px auto;
      padding: 32px;
      background: rgba(255,255,255,.06);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 24px;
      box-shadow: 0 20px 50px rgba(0,0,0,.35);
    }
    h1 { font-size: 26px; margin: 0 0 8px; }
    p { color: #94a3b8; font-size: 14px; margin: 0 0 24px; }
    label {
      display: block;
      margin: 16px 0 6px;
      font-size: 14px;
      color: #cbd5e1;
    }
    input {
      width: 100%;
      padding: 12px 14px;
      border-radius: 12px;
      border: 1px solid rgba(255,255,255,.15);
      background: rgba(0,0,0,.3);
      color: #fff;
      font-size: 15px;
      outline: none;
      transition: border-color .15s;
    }
    input:focus { border-color: #f97316; }
    input::placeholder { color: #64748b; }
    .pw-wrap { position: relative; }
    .pw-wrap input { padding-right: 44px; }
    .pw-toggle {
      position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
      cursor: pointer; font-size: 18px; user-select: none; opacity: .6;
    }
    .pw-toggle:hover { opacity: 1; }
    .actions { display: flex; gap: 10px; margin-top: 28px; }
    .btn {
      flex: 1;
      padding: 12px;
      border-radius: 14px;
      border: none;
      font-size: 15px;
      font-weight: 600;
      cursor: pointer;
      text-align: center;
      text-decoration: none;
      transition: opacity .18s;
    }
    .btn:hover { opacity: .9; }
    .btn-save { background: linear-gradient(135deg, #f97316, #fb7185); color: white; }
    .btn-back { background: rgba(255,255,255,.08); color: #e5e7eb; border: 1px solid rgba(255,255,255,.12); }
    .btn-clear { background: rgba(239,68,68,.15); color: #fca5a5; border: 1px solid rgba(239,68,68,.3); }
    .status {
      padding: 10px 14px;
      border-radius: 12px;
      margin: 16px 0 0;
      font-size: 13px;
    }
    .ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.25); color: #86efac; }
    .warn { background: rgba(234,179,8,.12); border: 1px solid rgba(234,179,8,.25); color: #fde68a; }
    .msg {
      padding: 10px 14px;
      border-radius: 12px;
      margin-bottom: 16px;
      font-size: 14px;
    }
    .msg-ok { background: rgba(34,197,94,.12); border: 1px solid rgba(34,197,94,.25); color: #86efac; }
    .msg-err { background: rgba(239,68,68,.12); border: 1px solid rgba(239,68,68,.25); color: #fca5a5; }
    code { background: rgba(255,255,255,.08); padding: 2px 6px; border-radius: 4px; }
  </style>
</head>
<body>
  <div class="wrap">
    <h1>⚙️ 账号设置</h1>
    <p>输入凤凰秀账号密码即可解锁 <strong>720p 高清画质</strong>，优先级高于容器环境变量配置。</p>

    <div class="status ${hasCreds || credsFromEnv ? 'ok' : 'warn'}">
      当前凭证来源：<strong>${activeSource}</strong> 
      ${hasCreds || credsFromEnv ? '✅ 已配置，可看 720p' : '⚠️ 未配置，仅 480p'}
    </div>

    <form method="POST" action="/settings">
      <label for="phone">手机号</label>
      <input type="text" id="phone" name="phone" placeholder="186xxxxxxxx" value="${cfg.phone || ''}" />

      <label for="password">密码</label>
      <div class="pw-wrap">
        <input type="password" id="password" name="password" placeholder="输入密码" value="${cfg.password || ''}" />
        <span class="pw-toggle" onclick="togglePW()">👁️</span>
      </div>

      <div class="actions">
        <button class="btn btn-save" type="submit">💾 保存</button>
        <a class="btn btn-back" href="/">← 返回首页</a>
      </div>
    </form>

    <form method="POST" action="/settings/clear" style="margin-top:8px">
      <button class="btn btn-clear" type="submit">🗑️ 清除已保存的账号</button>
    </form>
  </div>
</body>
</html>`);
});

app.post("/settings", (req, res) => {
  const { phone, password } = req.body;

  if (!phone || !password) {
    return res.type("html").send(`<!doctype html>
<html><head><meta charset="utf-8"><title>保存失败</title>
<style>body{background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px;}.btn{display:inline-block;padding:10px 20px;background:rgba(255,255,255,.08);color:#e5e7eb;border-radius:12px;text-decoration:none;margin-top:16px;}</style>
<body>
  <h2>❌ 保存失败</h2>
  <p>手机号和密码不能为空。</p>
  <a class="btn" href="/settings">← 返回设置</a>
</body></html>`);
  }

  saveConfig({ phone: phone.trim(), password });

  // 清除缓存的 token，下次请求重新登录
  cachedToken = null;

  res.type("html").send(`<!doctype html>
<html><head><meta charset="utf-8"><title>保存成功</title>
<style>body{background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px;}.btn{display:inline-block;padding:10px 20px;background:linear-gradient(135deg,#f97316,#fb7185);color:white;border-radius:12px;text-decoration:none;margin-top:16px;}</style>
<body>
  <h2>✅ 保存成功！</h2>
  <p>账号已保存，现在可以看 <strong>720p 高清画质</strong> 了。</p>
  <a class="btn" href="/">← 返回首页</a>
</body></html>`);
});

app.post("/settings/clear", (req, res) => {
  try {
    fs.unlinkSync(CONFIG_FILE);
  } catch (e) { /* ignore */ }
  cachedToken = null;

  res.type("html").send(`<!doctype html>
<html><head><meta charset="utf-8"><title>已清除</title>
<style>body{background:#0f172a;color:#f8fafc;font-family:sans-serif;padding:40px;}.btn{display:inline-block;padding:10px 20px;background:rgba(255,255,255,.08);color:#e5e7eb;border-radius:12px;text-decoration:none;margin-top:16px;}</style>
<body>
  <h2>🗑️ 已清除</h2>
  <p>已删除保存的账号，将使用 480p 普通画质。</p>
  <a class="btn" href="/">← 返回首页</a>
</body></html>`);
});

// ==================== 直播跳转 ====================

// WAF cookie 缓存（2 分钟刷新，避免触发频率限制）
let wafCookies = null;
let wafCookieExpiry = 0;

async function getWafCookies() {
  const now = Math.floor(Date.now() / 1000);
  if (wafCookies && now < wafCookieExpiry) return wafCookies;
  try {
    const resp = await fetch("https://www.fengshows.com/live", {
      headers: {
        "User-Agent": "Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36",
      },
      redirect: "manual",
    });
    const cookies = resp.headers.getSetCookie?.() || [];
    if (cookies.length > 0) {
      wafCookies = cookies.map(c => c.split(";")[0].trim()).filter(Boolean).join("; ");
      wafCookieExpiry = now + 120; // 2 分钟缓存
      return wafCookies;
    }
    const setCookie = resp.headers.get("set-cookie") || "";
    const parsed = setCookie.split(",").map(c => c.split(";")[0]).filter(Boolean).join("; ");
    if (parsed) {
      wafCookies = parsed;
      wafCookieExpiry = now + 120;
      return wafCookies;
    }
  } catch (e) {
    console.error("getWafCookies failed:", e.message);
  }
  return "";
}

async function resolveRedirect(url, maxFollow = 3) {
  let current = url;
  for (let i = 0; i < maxFollow; i++) {
    const resp = await fetch(current, {
      method: "GET",
      headers: {
        "User-Agent": "Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36",
        "Referer": "https://www.fengshows.com/live",
      },
      redirect: "manual",
    });
    if (resp.status >= 301 && resp.status <= 308) {
      const location = resp.headers.get("location");
      if (!location) break;
      current = new URL(location, current).href;
    } else {
      break;
    }
  }
  return current;
}

/**
 * 获取直播流最终 URL（内部逻辑，不返回 HTTP 响应）
 * 缓存 30 秒避免重复请求
 */
const streamUrlCache = {};

async function getStreamUrl(channelId) {
  const cacheKey = channelId;
  const cached = streamUrlCache[cacheKey];
  if (cached && cached.expiry > Date.now()) {
    return cached.url;
  }
  const { phone, password } = getCredentials();
  let token = null;
  let quality = "hd";

  if (phone && password) {
    token = await getValidToken(phone, password);
    if (token) quality = "fhd";
  }

  const headers = {
    "User-Agent": "Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36",
    "Referer": "https://www.fengshows.com/live",
    "Origin": "https://www.fengshows.com",
  };

  if (token) headers.Token = token;

  const cookies = await getWafCookies();
  if (cookies) headers.Cookie = cookies;

  try {
    const apiUrl = `https://api.fengshows.cn/hub/live/auth-url?live_qa=${quality}&live_id=${channelId}`;
    const response = await fetch(apiUrl, { headers });
    const data = await response.json();

    if (data?.data?.live_url) {
      const finalUrl = await resolveRedirect(data.data.live_url);
      streamUrlCache[cacheKey] = { url: finalUrl, expiry: Date.now() + 30000 };
      return finalUrl;
    }

    if (quality === "fhd") {
      const fallbackUrl = `https://api.fengshows.cn/hub/live/auth-url?live_qa=hd&live_id=${channelId}`;
      const fbHeaders = { ...headers };
      delete fbHeaders.Token;
      const fbRes = await fetch(fallbackUrl, { headers: fbHeaders });
      const fbData = await fbRes.json();
      if (fbData?.data?.live_url) {
        const finalUrl = await resolveRedirect(fbData.data.live_url);
        streamUrlCache[cacheKey] = { url: finalUrl, expiry: Date.now() + 30000 };
        return finalUrl;
      }
    }
  } catch (e) {
    console.error("getStreamUrl failed:", e.message);
  }
  return null;
}

// 返回纯文本播放地址（供复制功能使用）
app.get("/url/:id", async (req, res) => {
  const channel = CHANNELS[req.params.id];
  if (!channel) {
    return res.status(400).type("text").send("Invalid Channel ID");
  }
  const finalUrl = await getStreamUrl(channel.id);
  if (finalUrl) {
    return res.type("text").send(finalUrl);
  }
  res.status(500).type("text").send("获取失败");
});

app.get("/:id", async (req, res) => {
  const channel = CHANNELS[req.params.id];
  if (!channel) {
    return res.status(400).json({ error: "Invalid Channel ID", supported: Object.keys(CHANNELS) });
  }

  try {
    const finalUrl = await getStreamUrl(channel.id);
    if (finalUrl) {
      return res.redirect(302, finalUrl);
    }
    return res.status(500).json({ error: "获取直播地址失败" });
  } catch (error) {
    return res.status(500).json({ error: error.message || "unknown error" });
  }
});

async function getValidToken(phone, password) {
  if (cachedToken && validateJWT(cachedToken)) {
    return cachedToken;
  }

  const loginUrl = "https://m.fengshows.com/api/v3/mp/user/login";
  const body = {
    code: "86",
    keep_alive: false,
    password,
    phone,
  };

  try {
    const response = await fetch(loginUrl, {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    });

    const data = await response.json();

    if (data?.message === "ok" && data?.data?.token) {
      cachedToken = data.data.token;
      return cachedToken;
    }
  } catch (error) {
    console.error("Login failed:", error);
  }

  return null;
}

function validateJWT(token) {
  try {
    const parts = token.split(".");
    if (parts.length !== 3) return false;

    let payloadBase64 = parts[1].replace(/-/g, "+").replace(/_/g, "/");
    while (payloadBase64.length % 4) {
      payloadBase64 += "=";
    }

    const payload = JSON.parse(Buffer.from(payloadBase64, "base64").toString("utf8"));
    const exp = payload.exp;

    if (!exp) return true;
    return exp > Math.floor(Date.now() / 1000) + 300;
  } catch {
    return false;
  }
}

app.listen(PORT, () => {
  console.log(`fengshows-docker listening on port ${PORT}`);
});