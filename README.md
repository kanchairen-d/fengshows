# 📺 凤凰秀直播代理

> 凤凰卫视三路直播频道（资讯/中文/香港）的 Docker 代理服务，支持 Web 播放、画质自适应、账号密码 Web 配置。

![Docker](https://img.shields.io/badge/Docker-✓-2496ED?logo=docker&logoColor=white)
![Node](https://img.shields.io/badge/Node-18+-339933?logo=npm&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-yellow)

---

## 🚀 快速启动

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

访问 http://你的IP:3233 进入首页 → 设置页面配置手机号和密码即可。

---

### 方式二：环境变量配置

如需容器启动时自动带入账号密码：

```bash
cp .env.example .env
```

编辑 `.env`：

```env
PORT=3233
PHONE=186xxxxxxxx
PASSWORD=你的密码
```

然后启动：

```bash
docker compose up -d --build
```

> ⚠️ 不配置账号也能跑，但只能获取普通画质。配置后自动切换到高清画质。

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

## 📡 频道地址

| 频道 | 路径 | 说明 |
|------|------|------|
| 凤凰资讯 | `/fhzx` | 24h 新闻直播 |
| 凤凰中文 | `/fhzw` | 综合频道 |
| 凤凰香港 | `/fhhk` | 香港版 |
| 首页 | `/` | 频道选择 & 设置 |
| 健康检查 | `/healthz` | Docker 健康探测 |

访问示例：`http://192.168.1.100:3233/fhzx`

---

## 🔧 管理

- **配置页面**：浏览器打开首页 → 设置 ⚙️ → 输入手机号和密码
- **清除配置**：设置页面点击「清除凭证」
- **密码显示**：输入框右侧 👁️ 可切换明文/密文
- **画质切换**：配置账号后自动使用高清（fhd），否则普通

---

## 📦 镜像

预构建镜像从 GitHub Container Registry 拉取：

```
ghcr.io/kanchairen-d/fengshows:latest
```

---

## ⚡ 其他命令

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

## ❗️ 注意

- `.env` 文件包含敏感信息，**不要提交到 GitHub**
- 配置页面保存的凭证存在容器内 `config.json`，重启容器后依然保留
- 上游接口变动可能导致服务失效
- 建议配合 Nginx/Caddy 反代配置域名和 HTTPS