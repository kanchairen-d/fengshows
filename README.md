# 📺 凤凰秀直播代理

> 凤凰卫视三路直播频道（资讯/中文/香港）代理服务。提供 **Docker 版** 和 **PHP 单文件版**，二选一即可。

![Docker](https://img.shields.io/badge/Docker-✓-2496ED?logo=docker&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-✓-777BB4?logo=php&logoColor=white)
![Node](https://img.shields.io/badge/Node-18+-339933?logo=npm&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-yellow)

---

## 🤔 版本选择

| 版本 | 文件 | 环境要求 | 适用场景 |
|------|------|----------|----------|
| **Docker 版** | 完整项目 | Docker | 推荐，开箱即用，含 Web 管理界面 |
| **PHP 版** | `php/fhx.php` 单文件 | PHP 7.4+ + cURL | 已有 PHP 环境，放进去就能用 |

两个版本功能完全一致，频道、画质自适应、账号配置都一样。

---

# 🐳 Docker 版

## 快速启动

### 方式一：Docker Compose（推荐）

```yaml
# docker-compose.yml
services:
  fengshows:
    image: ghcr.io/kanchairen-d/fengshows:latest
    container_name: fengshows
    ports:
      - "3233:3233"
    restart: unless-stopped
```

```bash
docker compose up -d
```

访问 `http://你的IP:3233` → 设置页面配置手机号和密码即可。

---

### 方式二：环境变量配置（开机自配账号）

```bash
cp .env.example .env
```

编辑 `.env`：

```env
PORT=3233
PHONE=186xxxxxxxx
PASSWORD=你的密码
```

```bash
docker compose up -d --build
```

> ⚠️ 不配置账号也能跑，但只能获取普通画质。配置后自动切换高清。

---

### 方式三：纯 Docker 命令

```bash
docker run -d \
  --name fengshows \
  -p 3233:3233 \
  --restart unless-stopped \
  ghcr.io/kanchairen-d/fengshows:latest
```

带账号：

```bash
docker run -d \
  --name fengshows \
  -p 3233:3233 \
  -e PHONE=186xxxxxxxx \
  -e PASSWORD=你的密码 \
  --restart unless-stopped \
  ghcr.io/kanchairen-d/fengshows:latest
```

---

### 方式四：本地构建

```bash
git clone https://github.com/kanchairen-d/fengshows.git
cd fengshows
docker compose up -d --build
```

---

## Docker 频道地址

| 频道 | 路径 | 说明 |
|------|------|------|
| 凤凰资讯 | `/fhzx` | 24h 新闻直播 |
| 凤凰中文 | `/fhzw` | 综合频道 |
| 凤凰香港 | `/fhhk` | 香港版 |
| 首页 | `/` | 频道选择 & 设置页面 |
| 健康检查 | `/healthz` | Docker 健康探测 |

示例：`http://192.168.1.100:3233/fhzx`

---

## Docker 管理

- **配置页面**：浏览器打开首页 → 设置 ⚙️ → 输入手机号和密码
- **清除配置**：设置页面点击「清除凭证」
- **密码显示**：输入框右侧 👁️ 切换明文/密文
- **画质**：配置账号后自动用高清（fhd），否则普通

---

## Docker 其他命令

```bash
# 查看日志
docker logs -f fengshows

# 停止
docker compose down

# 重启
docker compose restart

# 重建镜像并重启
docker compose up -d --build
```

---

# 🐘 PHP 版

> 单文件，放到 PHP Web 服务器的任意目录即可运行。

## 环境要求

- PHP 7.4+
- cURL 扩展（`php-curl`）
- 文件写入权限（运行时保存配置和 token 缓存）

## 安装

### 下载文件

```bash
# 方式一：从 GitHub 下载
wget https://raw.githubusercontent.com/kanchairen-d/fengshows/main/php/fhx.php

# 方式二：或克隆整个仓库
git clone https://github.com/kanchairen-d/fengshows.git
cp fengshows/php/fhx.php /你的网站目录/
```

### 部署到 Web 服务器

**Nginx + PHP-FPM** 示例：

```nginx
server {
    listen 80;
    server_name fengshows.example.com;

    root /var/www/fengshows;
    index fhx.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index fhx.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

**Apache + mod_php**：直接把 `fhx.php` 放到网站目录即可。

---

## PHP 频道地址

| 频道 | 参数 | 说明 |
|------|------|------|
| 凤凰资讯 | `?ch=fhzx` | 24h 新闻直播 |
| 凤凰中文 | `?ch=fhzw` | 综合频道 |
| 凤凰香港 | `?ch=fhhk` | 香港版 |
| 首页 | 无参数 | 频道选择 & 设置页面 |
| 健康检查 | `?action=health` | PHP 状态探测 |

示例：`http://你的服务器/fhx.php?ch=fhzx`

---

## PHP 配置与管理

首次使用，浏览器打开首页 → 设置页面输入手机号和密码。

密码存于同目录下的 `.fengshows_config.json`（自动创建），如需重新配置：

- **Web 方式**：首页 → 设置 → 重新填写
- **手动清除**：删除 `.fengshows_config.json` 文件

---

## PHP 文件说明

部署后同目录会自动生成：

| 文件 | 用途 | 说明 |
|------|------|------|
| `fhx.php` | 主程序 | 部署的核心文件 |
| `.fengshows_config.json` | 账号配置 | 保存手机号和密码 |
| `.fengshows_token.json` | Token 缓存 | 登录态缓存，自动刷新 |
| `.fengshows_waf.txt` | WAF Cookie | 防爬虫 Cookie 缓存 |

> 配置和缓存文件均为自动生成，无需手动创建。删除后会自动重建。

---

# 📦 镜像

Docker 版本预构建镜像：

```
ghcr.io/kanchairen-d/fengshows:latest
```

---

# ❗️ 注意事项

- `.env` 文件和 `.fengshows_config.json` 包含敏感信息，**注意不要公开**
- Docker 版配置存在容器内，重启容器配置保留
- PHP 版配置存在同目录文件，重新部署时记得备份
- 上游接口变动可能导致服务失效
- 建议配合 Nginx/Caddy 反代配置域名和 HTTPS