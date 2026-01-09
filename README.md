# 🐙 Zarkto's Table - COC 跑团助手

<div align="center">

![Version](https://img.shields.io/badge/version-2.0_Beta-gold)
![License](https://img.shields.io/badge/license-Non--Commercial-red)
![Platform](https://img.shields.io/badge/platform-Web-blue)
![Vue](https://img.shields.io/badge/Vue.js-3.x-green)

*"旧日支配者的低语愈发清晰... 享受您的游戏。"*

[🎮 在线体验] | [🚀 部署教程](#-个人部署教程) | [✨ 功能特性](#-功能特性) | [📖 使用指南](#-使用指南)

</div>

---

## 📖 项目简介

**Zarkto's Table** 是一款基于 Web 技术的沉浸式 **克苏鲁的呼唤 (Call of Cthulhu 7th Edition)** 跑团辅助工具。本项目旨在利用现代 AI 技术（LLM）担任守密人 (Keeper)，为玩家提供随时随地、无需同伴即可进行的跑团体验。

项目完全前端化，无后端服务器依赖（除可选的 Supabase 实时服务），真正做到**开箱即用**。

> **⚠️ 郑重声明**：
> 本项目为 **全开源非商业用途 (Open Source for Non-Commercial Use Only)** 项目。
> 严禁将本项目用于任何形式的商业盈利活动，包括但不限于付费服务、广告植入、会员订阅等。
> 仅供 TRPG 爱好者学习、交流与娱乐。

## ✨ 功能特性

### 🎭 核心体验
- **AI 守密人 (Keeper)**: 接入 OpenAI 接口，由 AI 实时生成剧情、判定检定、演绎 NPC。
- **沉浸式界面**: 精心设计的暗黑克苏鲁风格 UI，配合微动效与氛围音效（规划中）。
- **双模游玩**:
  - **单人模式**: 独自一人探索恐怖谜团，AI 队友辅助。
  - **多人联机**: 基于 Supabase Realtime 的实时联机，邀请好友共同跑团。

### 🛠️ 实用工具
- **角色卡管理**: 支持 JSON/PNG 格式导入，实时追踪 HP/MP/SAN 及技能状态。
- **智能检定系统**: 点击属性/技能直接检定，自动计算成功等级（大成功/成功/困难/极难/大失败）。
- **自动日志**: 所有的跑团对话都会自动记录。
- **异闻酒馆**: 跑团记录一键转小说，支持多种文风（洛夫克拉夫特式、现代惊悚等）导出。
- **思维链 (CoT)**: 可视化 AI 的思考过程，了解 Keeper 的决策逻辑。

## 🚀 个人部署教程

本项目为纯静态网页应用，部署非常简单。您可以选择以下任意一种方式：

### 方式一：GitHub Pages (推荐，最简单)

无需服务器，完全免费。

1. **Fork 本仓库**: 点击右上角的 `Fork` 按钮，将项目复制到您的 GitHub 账号下。
2. **开启 Pages**:
   - 进入您 Fork 后的仓库页面。
   - 点击 `Settings` (设置) -> `Pages` (左侧菜单)。
   - 在 `Build and deployment` -> `Source` 下选择 `Deploy from a branch`。
   - 在 `Branch` 选择 `main` (或 `master`) 分支，文件夹选择 `/ (root)`。
   - 点击 `Save`。
3. **获取链接**: 等待几分钟后，刷新页面，顶端会显示您的部署链接 (如 `https://yourname.github.io/Zarkto-s-Table/`)。

### 方式二：Vercel / Netlify (极速)

1. 登录 Vercel 或 Netlify。
2. 导入您 Fork 的 GitHub 仓库。
3. Build Settings 留空（本项目无需构建命令）。
4. 点击 Deploy 即可获得访问域名。

### 方式三：本地运行

如果您不想部署到公网，也可以在本地运行。

**前提**: 安装了 [Node.js](https://nodejs.org/)。

```bash
# 1. 克隆项目
git clone https://github.com/your-username/Zarkto-s-Table.git

# 2. 进入目录
cd Zarkto-s-Table

# 3. 启动本地服务 (推荐使用 npx serve)
npx serve .
# 或者使用 python
# python -m http.server 8000
```

浏览器访问 `http://localhost:3000` 即可。

## ⚙️ 配置指南

首次打开应用，点击侧边栏底部的 **设置 (Settings)** 图标进行配置。所有配置均存储在您浏览器的本地缓存 (localStorage) 中，不会上传到任何服务器。

### 1. AI 配置 (必填)

要启动游戏，您需要提供兼容 OpenAI 格式的 API。

- **API 地址**: 填写您的 API 服务商地址。
  - 官方: `https://api.openai.com`
  - 第三方代理: `https://your-proxy-domain.com`
- **API Key**: 您的 API 密钥 (`sk-...`)。
- **模型**: 推荐使用 `gpt-4-turbo` 或 `gpt-4o` 以获得最佳叙事体验。`gpt-3.5-turbo` 亦可运行但逻辑能力较弱。

### 2. 多人联机配置 (选填)

如果您需要使用**多人联机**功能，需要自行配置 Supabase（免费额度足够个人使用）。

1. 访问 [Supabase](https://supabase.com/) 并注册账号。
2. 创建一个新项目 (New Project)。
3. 进入项目设置 -> `Project Settings` -> `API`。
4. 复制 `Project URL` 和 `anon` (public) Key。
5. 回到 Zarkto's Table 设置页面，填入：
   - **Supabase URL**: 您的 Project URL
   - **Supabase Key**: 您的 anon Key
6. **重要**: 确保 Supabase 项目开启了 Realtime 功能（默认通常开启）。

## 📖 使用指南

### 快速开始 (单人)

1. **导入模组**: 点击「仪式准备」，选择「单人模式」。上传模组文件（支持 txt/docx/json）或直接粘贴文本。
2. **创建角色**: 导入角色卡（推荐使用 Cthulhu List 等工具导出的 JSON）。
3. **开始仪式**: 确认无误后点击「开始跑团」。
4. **交互**: 在对话框输入行动描述或指令，AI Keeper 将会回应。

### 常用指令

| 指令 | 作用 | 示例 |
|:---|:---|:---|
| `.r [表达式]` | 隐式骰点 | `.r 1d100` |
| `.ra [技能名]` | 技能检定 | `.ra 侦查` |
| `.rc [技能名]` | 对抗检定 | `.rc 力量` |
| `.sc [成功/失败]` | 理智检定 (San Check) | `.sc 1/1d6` |
| `.en [技能名]` | 技能成长标记 | `.en 侦查` |

## 🗂️ 项目结构

```
Zarkto-s-Table/
├── index.html          # 核心入口，包含所有前端逻辑 (Vue3 + Tailwind)
├── README.md           # 说明文档
├── Library/            # (WIP) 密大图书馆 - 模组资源
├── Workshop/           # (WIP) 调查员工坊 - 角色卡工具
└── assets/             # 静态资源 (如果有)
```

## 🛠️ 技术栈

本项目坚持 **"Less is More"** 的原则，未使用复杂的构建工具。

- **Core**: Vue.js 3 (Composition API, CDN)
- **UI**: Tailwind CSS (CDN)
- **Connect**: Supabase Realtime (CDN)
- **Utils**: Marked.js, Mammoth.js, FileSaver.js

## 📜 许可证与免责声明

### 许可证 (License)

本项目代码及设计仅供 **非商业用途 (Non-Commercial)** 使用。
您可以自由地：
- ⬇️ 下载与部署
- 🔧 修改代码供个人使用
- 🤝 与朋友进行游戏

您**不可以**：
- 💰 将本项目打包出售
- 💼 用于商业运营的跑团服务
- 📢 在未授权的情况下移除作者署名

### 免责声明

1. 本工具仅作为 TRPG 辅助工具，剧情内容由 AI 生成，不代表作者立场。
2. 游戏内涉及的“克苏鲁神话”相关名词版权归原版权方所有。
3. 请妥善保管您的 API Key，本项目纯前端运行，Key 仅保存在您本地浏览器中。

---

<div align="center">

Made with 🩸 and 🐙 by **Z4RKTO** & **STA1N**

</div>
