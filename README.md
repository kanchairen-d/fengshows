# 凤凰秀 (FengShows) 直播代理

> 公众号：雾栈手记

凤凰秀直播频道代理，支持 Docker 部署、PHP 单文件部署、Cloudflare Workers 部署。

## 功能

- 代理凤凰秀三个直播频道：凤凰资讯、凤凰中文、凤凰香港
- 支持 480p（无需登录）和 720p（需登录账号）两种画质
- 账号密码可通过页面设置或环境变量配置
- 内置 Web 管理界面，可在线配置、查看频道、复制播放地址
- 多版本支持，适配不同部署环境

## 版本说明

### 1. Docker 版（Go 语言）

基于 Go 语言构建，单二进制部署，性能最优。

**文件位置：** `Docker版/`

**部署方法：**
```bash
cd Docker版

# 方式一：使用 docker-compose（推荐）
docker-compose up -d

# 方式二：手动构建并运行
docker build -t fengshows .
docker run -d \
  --name fengshows \
  -p 3233:3233 \
  -v $(pwd)/data:/data \
  fengshows

# 方式三：使用 ghcr.io 镜像
docker run -d \
  --name fengshows \
  -p 3233:3233 \
  -v $(pwd)/data:/data \
  ghcr.io/kanchairen-d/fengshows:latest
```

**配置方式：**
- 方式一：设置环境变量 `PHONE` 和 `PASSWORD`（优先级高）
- 方式二：访问 `http://IP:3233/settings` 页面在线填写

**访问地址：** `http://IP:3233`

---

### 2. PHP 版（单文件）

单文件 PHP 程序，无需数据库，无需框架，任何支持 PHP 的 Web 服务器均可运行。

**文件位置：** `PHP版/fhx.php`

**部署方法：**
```bash
# 将 fhx.php 放到任意 PHP 环境（Apache/Nginx）的 Web 目录即可
# 例如：
cp PHP版/fhx.php /var/www/html/fhx.php
```

**配置方式：**
- 访问 `http://IP/fhx.php?page=settings` 页面在线填写账号密码

**访问地址：** `http://IP/fhx.php`

---

### 3. Cloudflare Workers 版

基于 Cloudflare Workers 部署，无需服务器，全球加速。

**文件位置：** `CF版/fhx.txt`

**部署方法：**
1. 登录 Cloudflare Dashboard
2. 进入 Workers & Pages
3. 创建新的 Worker
4. 将 `CF版/fhx.txt` 中的代码复制到 Worker 编辑器中
5. 点击保存并部署

**配置方式：**
- 在 Cloudflare Workers 后台 → 设置 → 变量和环境变量
- 添加 `PHONE` 和 `PASSWORD` 变量

**访问地址：** 你的 Worker 域名

---

## 频道列表

| 频道 | 标识 | 说明 |
|------|------|------|
| 凤凰资讯 | `fhzx` | 资讯直播频道 |
| 凤凰中文 | `fhzw` | 中文综合频道 |
| 凤凰香港 | `fhhk` | 香港地区频道 |

## 使用说明

1. **画质说明：**
   - 不配置账号时，默认 480p 标清播放
   - 配置账号后，自动升级为 720p 高清播放

2. **播放器兼容性：**
   - 凤凰秀已屏蔽 APTV 等播放器的默认 User-Agent
   - 如遇无法播放，请在播放器中修改 User-Agent 为手机浏览器
   - 推荐 UA：
     ```
     Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.230 Mobile Safari/537.36
     ```

3. **播放地址：**
   - 点击"立即播放"直接跳转直播流
   - 点击"复制播放地址"可在 VLC、PotPlayer 等播放器中播放

## 技术栈

- **Docker 版：** Go + Docker 多阶段构建
- **PHP 版：** PHP 原生（需 curl 扩展）
- **CF 版：** JavaScript (Cloudflare Workers)

## 常见问题

**Q：为什么播放只有 480p？**
A：需要配置凤凰秀账号（手机号+密码）才能解锁 720p 高清画质。

**Q：APTV 无法播放怎么办？**
A：修改 APTV 的 User-Agent 为手机浏览器 UA（见上方推荐 UA）。

**Q：配置保存后不生效？**
A：检查配置文件是否正确写入。Docker 版检查 `data/config.json`，PHP 版检查同目录下的 `.fengshows_config.json`。

**Q：登录 API 频繁调用被限流？**
A：等待一段时间后再试，避免频繁保存配置。

## License

MIT