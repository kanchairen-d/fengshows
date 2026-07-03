# fenghuangxiu

凤凰秀直播代理 Docker 服务，默认端口 **3233**。

## 功能

- `/fhzx` → 凤凰资讯
- `/fhzw` → 凤凰中文
- `/fhhk` → 凤凰香港
- `/healthz` → 健康检查
- `/` → 美化首页

## 目录说明

```text
fenghuangxiu/
├─ app.js
├─ package.json
├─ Dockerfile
├─ docker-compose.yml
├─ .dockerignore
├─ .gitignore
├─ .env.example
└─ README.md
```

## 本地准备

先复制环境变量文件：

```bash
cp .env.example .env
```

然后编辑 `.env`：

```env
PORT=3233
PHONE=你的手机号
PASSWORD=你的密码
```

> 不配置 `PHONE` 和 `PASSWORD` 也能跑，但通常只能获取普通画质。

---

## Docker Compose 启动

你的环境如果需要 docker 组权限，按你现在的习惯用这个：

```bash
sg docker -c "docker compose up -d --build"
```

查看日志：

```bash
sg docker -c "docker logs -f fengshows"
```

停止：

```bash
sg docker -c "docker compose down"
```

---

## Docker 直接启动

构建镜像：

```bash
sg docker -c "docker build -t fenghuangxiu:latest ."
```

运行容器：

```bash
sg docker -c "docker run -d \
  --name fengshows \
  -p 3233:3233 \
  -e PORT=3233 \
  -e PHONE='你的手机号' \
  -e PASSWORD='你的密码' \
  --restart unless-stopped \
  fenghuangxiu:latest"
```

---

## 访问地址

假设你的 NAS IP 是 `192.168.1.100`：

- `http://192.168.1.100:3233/`
- `http://192.168.1.100:3233/fhzx`
- `http://192.168.1.100:3233/fhzw`
- `http://192.168.1.100:3233/fhhk`
- `http://192.168.1.100:3233/healthz`

---

## 上传到 GitHub

### 1）如果你要改成同名目录，先执行

```bash
cd /vol1/@apphome/trim.openclaw/data/workspace
mv fengshows-docker fenghuangxiu
cd fenghuangxiu
```

### 2）初始化 git

```bash
git init
git add .
git commit -m "init: fenghuangxiu docker service"
```

### 3）GitHub 仓库地址

```text
https://github.com/kanchairen-d/fenghuangxiu.git
```

### 4）绑定远程并推送

```bash
git branch -M main
git remote add origin https://github.com/kanchairen-d/fenghuangxiu.git
git push -u origin main
```

如果你用 SSH：

```bash
git remote add origin git@github.com:kanchairen-d/fenghuangxiu.git
git push -u origin main
```

---

## 更新项目

以后修改后：

```bash
git add .
git commit -m "feat: update service"
git push
```

然后在服务器上重新构建：

```bash
sg docker -c "docker compose up -d --build"
```

---

## 注意事项

1. **不要把 `.env` 上传到 GitHub**
2. 上游接口如果变动，项目可能失效
3. 如果你后面要配域名，可以再接 Nginx/Caddy 反代

---

## 残留说明

项目里只保留运行必需文件，不放临时测试文件、不放下载缓存、不放依赖目录。
