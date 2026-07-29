package main

import (
	"encoding/json"
	"fmt"
	"io"
	"log"
	"net/http"
	"os"
	"strings"
	"sync"
	"time"
	"encoding/base64"
)

// 频道映射
var channels = map[string]struct {
	ID   string
	Name string
	Desc string
}{
	"fhzx": {"7c96b084-60e1-40a9-89c5-682b994fb680", "凤凰资讯", "资讯直播频道"},
	"fhzw": {"f7f48462-9b13-485b-8101-7b54716411ec", "凤凰中文", "中文综合频道"},
	"fhhk": {"15e02d92-1698-416c-af2f-3e9a872b4d78", "凤凰香港", "香港地区频道"},
}

// Token 缓存
var (
	cachedToken   string
	tokenExpireAt int64
	tokenMu       sync.Mutex
)

// 配置
var (
	configPhone     string
	configPassword  string
	configSource    string // "env", "web", "none"
	configMu        sync.RWMutex
)

const configFile = "/data/config.json"

func getConfig() (string, string) {
	configMu.RLock()
	defer configMu.RUnlock()
	return configPhone, configPassword
}

func getConfigSource() string {
	configMu.RLock()
	defer configMu.RUnlock()
	return configSource
}

func setConfig(phone, password string) {
	configMu.Lock()
	configPhone = phone
	configPassword = password
	configSource = "web"
	configMu.Unlock()
	saveConfig()
}

func saveConfig() {
	configMu.RLock()
	defer configMu.RUnlock()
	data, _ := json.Marshal(map[string]string{"phone": configPhone, "password": configPassword})
	os.MkdirAll("/data", 0755)
	os.WriteFile(configFile, data, 0644)
}

func loadConfig() {
	// 环境变量优先
	phone := os.Getenv("PHONE")
	password := os.Getenv("PASSWORD")
	if phone != "" && password != "" {
		configPhone = phone
		configPassword = password
		configSource = "env"
		return
	}
	// 没有环境变量，尝试读取持久化文件
	data, err := os.ReadFile(configFile)
	if err == nil {
		var cfg map[string]string
		if json.Unmarshal(data, &cfg) == nil {
			if cfg["phone"] != "" && cfg["password"] != "" {
				configPhone = cfg["phone"]
				configPassword = cfg["password"]
				configSource = "web"
				return
			}
		}
	}
	configSource = "none"
}

func init() {
	loadConfig()
}

func main() {
	port := os.Getenv("PORT")
	if port == "" {
		port = "3233"
	}

	http.HandleFunc("/", handleIndex)
	http.HandleFunc("/settings", handleSettings)
	http.HandleFunc("/fhzx", handleChannel)
	http.HandleFunc("/fhzw", handleChannel)
	http.HandleFunc("/fhhk", handleChannel)

	log.Printf("凤凰秀 Docker 版启动，监听端口 %s", port)
	log.Fatal(http.ListenAndServe(":"+port, nil))
}

// ====== 首页 ======
func handleIndex(w http.ResponseWriter, r *http.Request) {
	if r.URL.Path != "/" {
		http.NotFound(w, r)
		return
	}

	phone, password := getConfig()
	hasAccount := phone != "" && password != ""
	source := getConfigSource()

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(buildPage(hasAccount, source)))
}

// ====== 频道处理 ======
func handleChannel(w http.ResponseWriter, r *http.Request) {
	key := strings.TrimPrefix(r.URL.Path, "/")
	ch, ok := channels[key]
	if !ok {
		http.Error(w, "无效频道", http.StatusBadRequest)
		return
	}

	phone, password := getConfig()
	quality := "hd"
	token := ""

	if phone != "" && password != "" {
		tok, err := getValidToken(phone, password)
		if err == nil && tok != "" {
			quality = "fhd"
			token = tok
		}
	}

	apiURL := fmt.Sprintf("https://api.fengshows.cn/hub/live/auth-url?live_qa=%s&live_id=%s", quality, ch.ID)
	req, _ := http.NewRequest("GET", apiURL, nil)
	req.Header.Set("User-Agent", "Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36")
	req.Header.Set("Referer", "https://www.fengshows.com/live")
	req.Header.Set("Origin", "https://www.fengshows.com")
	if token != "" {
		req.Header.Set("Token", token)
	}

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		http.Error(w, "请求失败: "+err.Error(), http.StatusInternalServerError)
		return
	}
	defer resp.Body.Close()

	body, _ := io.ReadAll(resp.Body)
	var result struct {
		Status string `json:"status"`
		Data   struct {
			LiveURL string `json:"live_url"`
		} `json:"data"`
		Message string `json:"message"`
	}

	if err := json.Unmarshal(body, &result); err != nil {
		http.Error(w, "解析失败", http.StatusInternalServerError)
		return
	}

	if result.Status == "0" && result.Data.LiveURL != "" {
		http.Redirect(w, r, result.Data.LiveURL, http.StatusFound)
		return
	}

	// FHD 降级到 HD
	if quality == "fhd" {
		fallbackURL := fmt.Sprintf("https://api.fengshows.cn/hub/live/auth-url?live_qa=hd&live_id=%s", ch.ID)
		req2, _ := http.NewRequest("GET", fallbackURL, nil)
		req2.Header.Set("User-Agent", "Mozilla/5.0 (Linux; Android 10; SM-G960U) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.181 Mobile Safari/537.36")
		req2.Header.Set("Referer", "https://www.fengshows.com/live")
		req2.Header.Set("Origin", "https://www.fengshows.com")
		req2.Header.Set("Token", "")

		resp2, _ := client.Do(req2)
		if resp2 != nil {
			defer resp2.Body.Close()
			body2, _ := io.ReadAll(resp2.Body)
			var fallback struct {
				Status string `json:"status"`
				Data   struct {
					LiveURL string `json:"live_url"`
				} `json:"data"`
			}
			if json.Unmarshal(body2, &fallback) == nil && fallback.Status == "0" && fallback.Data.LiveURL != "" {
				http.Redirect(w, r, fallback.Data.LiveURL, http.StatusFound)
				return
			}
		}
	}

	http.Error(w, fmt.Sprintf("获取直播地址失败: %s", result.Message), http.StatusInternalServerError)
}

// ====== 设置页面 ======
func handleSettings(w http.ResponseWriter, r *http.Request) {
	phone, password := getConfig()
	source := getConfigSource()
	msg := ""

	if r.Method == "POST" {
		r.ParseForm()
		p := r.FormValue("phone")
		pw := r.FormValue("password")

		if source == "env" {
			msg = "当前使用环境变量配置，无法通过页面修改。如需切换为页面设置，请先清除环境变量 PHONE 和 PASSWORD 后重启容器。"
		} else if r.FormValue("action") == "clear" {
			setConfig("", "")
			tokenMu.Lock()
			cachedToken = ""
			tokenExpireAt = 0
			tokenMu.Unlock()
			msg = "已清除账号信息"
			source = "none"
		} else if p != "" && pw != "" {
			tok, err := getValidToken(p, pw)
			if err != nil {
				msg = "登录失败: " + err.Error()
			} else if tok == "" {
				msg = "登录失败，请检查账号密码"
			} else {
				setConfig(p, pw)
				msg = "配置成功，当前画质已升级为 720P 高清"
				source = "web"
			}
		} else {
			msg = "请填写手机号和密码"
		}
		phone, password = getConfig()
	}

	w.Header().Set("Content-Type", "text/html; charset=utf-8")
	w.Write([]byte(buildSettingsPage(phone, password, msg, source)))
}

// ====== Token 管理 ======
func getValidToken(phone, password string) (string, error) {
	tokenMu.Lock()
	if cachedToken != "" && time.Now().Unix() < tokenExpireAt-300 {
		tok := cachedToken
		tokenMu.Unlock()
		return tok, nil
	}
	tokenMu.Unlock()

	loginURL := "https://m.fengshows.com/api/v3/mp/user/login"
	payload := map[string]interface{}{
		"code":        "86",
		"keep_alive":  false,
		"password":    password,
		"phone":       phone,
	}
	body, _ := json.Marshal(payload)

	req, _ := http.NewRequest("POST", loginURL, strings.NewReader(string(body)))
	req.Header.Set("Content-Type", "application/json")

	client := &http.Client{Timeout: 10 * time.Second}
	resp, err := client.Do(req)
	if err != nil {
		return "", fmt.Errorf("登录请求失败: %w", err)
	}
	defer resp.Body.Close()

	respBody, _ := io.ReadAll(resp.Body)
	var result struct {
		Message string `json:"message"`
		Data    struct {
			Token string `json:"token"`
		} `json:"data"`
	}

	if err := json.Unmarshal(respBody, &result); err != nil {
		return "", fmt.Errorf("解析登录响应失败: %w", err)
	}

	if result.Message == "ok" && result.Data.Token != "" {
		tokenMu.Lock()
		cachedToken = result.Data.Token
		tokenExpireAt = parseJWTExp(result.Data.Token)
		tokenMu.Unlock()
		return result.Data.Token, nil
	}

	return "", fmt.Errorf("登录失败: %s", result.Message)
}

func parseJWTExp(token string) int64 {
	parts := strings.Split(token, ".")
	if len(parts) != 3 {
		return time.Now().Add(6 * time.Hour).Unix()
	}

	payload := parts[1]
	payload = strings.ReplaceAll(payload, "-", "+")
	payload = strings.ReplaceAll(payload, "_", "/")
	switch len(payload) % 4 {
	case 2:
		payload += "=="
	case 3:
		payload += "="
	}

	decoded, err := base64.StdEncoding.DecodeString(payload)
	if err != nil {
		return time.Now().Add(6 * time.Hour).Unix()
	}

	var claims struct {
		Exp int64 `json:"exp"`
	}
	if json.Unmarshal(decoded, &claims) != nil {
		return time.Now().Add(6 * time.Hour).Unix()
	}

	return claims.Exp
}

// ====== 页面构建 ======
func buildPage(hasAccount bool, source string) string {
	qDot := "warn-dot"
	qText := "当前画质：480p (普通)"
	if hasAccount {
		qDot = "ok-dot"
		qText = "当前画质：720p (高清)"
	}

	var channelsHTML strings.Builder
	keys := []string{"fhzx", "fhzw", "fhhk"}
	for _, key := range keys {
		ch := channels[key]
		channelsHTML.WriteString(fmt.Sprintf(`
        <article class="card">
          <h3>%s</h3>
          <p>%s</p>
          <div class="actions">
            <a class="btn btn-primary" href="/%s">立即播放</a>
            <button class="btn btn-ghost" onclick="copyUrl('%s')">复制播放地址</button>
          </div>
        </article>`, ch.Name, ch.Desc, key, key))
	}

	css := `
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
.sub { margin: 0; font-size: 16px; line-height: 1.8; color: var(--muted); max-width: 760px; }
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
@media (max-width: 640px) { .wrap { padding-top: 28px; } .hero, .card { padding: 18px; } }`

	return fmt.Sprintf(`<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>凤凰秀-Docker版</title>
<style>%s</style>
<script>
function copyUrl(ch){
  var url=window.location.origin+"/"+ch;
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
<div id="toast" style="position:fixed;top:20px;left:50%%;transform:translateX(-50%%);z-index:999;background:rgba(0,0,0,.85);color:#fff;padding:12px 24px;border-radius:12px;font-size:15px;display:none;pointer-events:none;"></div>
<div class="wrap">
  <section class="hero">
    <div class="badge">公众号:雾栈手记</div>
    <h1>凤凰秀<span style="display:inline-block;padding:5px 10px;border-radius:999px;background:rgba(249,115,22,.16);border:1px solid rgba(249,115,22,.35);color:#fed7aa;font-size:0.5em;font-weight:700;margin-left:8px;vertical-align:middle;">Docker 版</span></h1>
    <div class="meta">
      <div class="pill"><span class="%s"></span>%s</div>
      <div class="pill"><a href="/settings">⚙️ 账号设置</a></div>
      <div class="pill"><a href="https://github.com/kanchairen-d/fengshows" target="_blank" rel="noopener"><svg viewBox="0 0 16 16" width="16" height="16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg> GitHub</a></div>
    </div>
  </section>
  <h2 class="section-title">频道快捷入口</h2>
  <section class="grid">%s</section>
  <div class="footer">
    <div>使用说明：</div>
    <div>1. 配置账号有两种方式：在 <a href="/settings">⚙️ 设置页面</a> 在线填写，或通过环境变量 <code>PHONE</code> <code>PASSWORD</code> 设置。</div>
    <div>2. 点击"立即播放"直接跳转直播流，或点击"复制播放地址"在 VLC/PotPlayer 中播放。</div>
    <div>3. 凤凰秀已屏蔽 APTV 等播放器的默认 UA，如遇无法播放，修改播放器 User-Agent 为手机浏览器即可，推荐：<code>Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.230 Mobile Safari/537.36</code></div>
  </div>
</div>
</body>
</html>`, css, qDot, qText, channelsHTML.String())
}

func buildSettingsPage(phone, password, msg string, source string) string {
	hasAccount := phone != "" && password != ""
	sourceLabel := ""
	if source == "env" {
		sourceLabel = "环境变量"
	} else if source == "web" {
		sourceLabel = "页面设置"
	}
	statusHTML := ""
	if hasAccount {
		statusHTML = fmt.Sprintf(`<div class="status ok">✅ 已配置账号（%s），当前画质为 720P 高清</div>`, sourceLabel)
	} else {
		statusHTML = `<div class="status warn">⚠️ 未配置账号，当前画质为 480p 标清</div>`
	}

	msgHTML := ""
	if msg != "" {
		statusClass := "ok"
		if strings.Contains(msg, "失败") || strings.Contains(msg, "错误") {
			statusClass = "warn"
		}
		msgHTML = fmt.Sprintf(`<div class="status %s">%s</div>`, statusClass, msg)
	}

	pwDisplay := password

	return fmt.Sprintf(`<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
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
.wrap { max-width: 520px; margin: 40px auto; padding: 32px; background: rgba(255,255,255,.06); border: 1px solid rgba(255,255,255,.12); border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,.35); }
h1 { font-size: 26px; margin: 0 0 8px; }
p { color: #94a3b8; font-size: 14px; margin: 0 0 24px; }
label { display: block; margin: 16px 0 6px; font-size: 14px; color: #cbd5e1; }
input {
  width: 100%%; padding: 12px 14px; border-radius: 12px; border: 1px solid rgba(255,255,255,.15);
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
  position: absolute; right: 14px; top: 50%%; transform: translateY(-50%%);
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
  %s
  %s
  <form method="post">
    <label>手机号</label>
    <input type="text" name="phone" placeholder="请输入手机号" value="%s">
    <label>密码</label>
    <div class="pw-wrap">
      <input type="password" name="password" id="password" placeholder="请输入密码" value="%s">
      <span class="pw-toggle" onclick="togglePW()">👁️</span>
    </div>
    <div class="actions">
      <button type="submit" class="btn btn-save">保存并验证</button>
      <a href="/" class="btn btn-back">返回首页</a>
    </div>
  </form>
  <form method="post" style="margin-top:12px">
    <input type="hidden" name="action" value="clear">
    <button type="submit" class="btn btn-clear" style="width:100%%">清除账号信息</button>
  </form>
</div>
</body>
</html>`, statusHTML, msgHTML, phone, pwDisplay)
}