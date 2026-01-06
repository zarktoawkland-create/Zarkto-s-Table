# 🐙 Zarkto's Table - COC 跑团助手

<div align="center">

![Version](https://img.shields.io/badge/version-2.0_Beta-gold)
![License](https://img.shields.io/badge/license-MIT-green)
![Platform](https://img.shields.io/badge/platform-Web-blue)

*"旧日支配者的低语愈发清晰... 享受您的游戏。"*

[🎮 在线体验](#在线体验) · [✨ 功能特性](#功能特性) · [🚀 快速开始](#快速开始) · [📖 使用指南](#使用指南)

</div>

---

## 📖 项目简介

**Zarkto's Table** 是一款基于 Web 的 **克苏鲁的呼唤 (Call of Cthulhu 7th Edition)** 跑团辅助工具。由 AI 担任守密人 (Keeper)，为玩家提供沉浸式的单人/多人跑团体验。

无需下载安装，打开浏览器即可开始你的恐怖冒险之旅。

## ✨ 功能特性

### 🎭 核心玩法
- **AI 守密人** - 智能 AI 担任 Keeper，根据模组内容引导剧情发展
- **单人模式** - 独自面对未知的恐惧
- **多人联机** - 与调查员同伴组队探索（基于 Supabase 实时通信）
- **AI 队友托管** - 单人模式也可开启 AI 队友，与你并肩作战

### 📋 调查员管理
- **导入角色卡** - 支持 JSON/PNG 格式的 COC 角色卡导入
- **技能检定** - 点击技能即可快速发起检定 (`.ra 技能名`)
- **理智检定** - 支持 SAN 值检定 (`.sc 成功/失败`)
- **属性追踪** - 实时显示 HP / MP / SAN 状态
- **勋章系统** - 记录调查员的荣耀时刻

### 💾 存档系统
- **云端/本地存档** - 随时保存游戏进度
- **导入/导出** - JSON 格式存档，方便分享与备份
- **多人团回档** - 房主可加载历史存档继续游戏

### 📚 异闻酒馆 (NEW!)
- **跑团记录转小说** - 将跑团对话改编为精彩故事
- **多种文风** - 洛夫克拉夫特式、现代惊悚、调查员日记、黑暗童话
- **视角切换** - 支持第三人称全知/第一人称叙事
- **导出功能** - 支持 Markdown / Word 格式导出

### 🎨 沉浸式界面
- **暗黑主题** - 专为 COC 设计的神秘暗色调 UI
- **响应式设计** - 完美适配 PC / 平板 / 手机
- **Markdown 渲染** - 支持丰富的文本格式
- **思维链展示** - 可选查看 AI 的推理过程 (CoT)

## 🚀 快速开始

### 在线体验

直接访问部署链接即可开始游戏（需自行部署或联系开发者获取）

### 本地运行

```bash
# 克隆仓库
git clone https://github.com/your-username/zarkto-table.git

# 进入项目目录
cd zarkto-table

# 直接用浏览器打开 index.html
# 或使用任意 HTTP 服务器
npx serve .
```

### 配置 API

游戏需要 OpenAI 兼容的 API 来驱动 AI Keeper。在设置页面配置：

- **API URL**: 你的 API 端点（如 `https://api.openai.com`）
- **API Key**: 你的 API 密钥
- **Model**: 使用的模型（推荐 `gpt-4` 或同等级别模型）

## 📖 使用指南

### 开始新游戏

1. 点击侧边栏 **「仪式准备」**
2. 选择 **单人模式** 或 **多人联机**
3. 导入模组文档（支持 .txt / .md / .docx / .json）
4. 导入调查员角色卡（支持 .json / .png）
5. 点击 **「开始跑团」**

### 游戏指令

在对话框中输入以下指令：

| 指令 | 说明 | 示例 |
|------|------|------|
| `.ra 技能名` | 技能检定 | `.ra 侦查` |
| `.rc 技能名` | 对抗检定 | `.rc 说服` |
| `.sc 成功/失败` | 理智检定 | `.sc 0/1d3` |
| `.r 表达式` | 骰点 | `.r 1d100` |
| `.st 属性 值` | 修改属性 | `.st hp -2` |
| `@角色名` | 提及/互动 | `@张三 你怎么看？` |

### 多人联机

1. **创建房间**: 房主点击「多人联机」→「建立新信标」
2. **分享房间号**: 将6位房间号发送给队友
3. **加入房间**: 队友输入房间号加入
4. **分配角色**: 轮流选择要扮演的调查员
5. **开始游戏**: 所有人准备就绪后，房主点击开始

### 结团结算

1. 点击右侧调查员面板的 **「结团/奖励结算」**
2. 填写模组名称和 SAN 回复量
3. 勾选成功使用过的技能进行成长检定
4. 添加勋章和特殊奖励
5. 确认更新角色卡

## 🗂️ 项目结构

```
zarkto-table/
├── index.html          # 主页面（包含所有逻辑）
├── README.md           # 项目说明
├── Library/            # 密大图书馆（模组库）
│   └── index.html
└── Workshop/           # 调查员工坊（角色卡编辑器）
    └── index.html
```

## 🛠️ 技术栈

- **前端框架**: Vue 3 (CDN)
- **样式**: Tailwind CSS (CDN)
- **Markdown**: Marked.js + DOMPurify
- **实时通信**: Supabase Realtime
- **文档处理**: Mammoth.js (docx) / FileSaver.js / html-docx-js

## 🔧 自定义配置

### 修改 AI 提示词

在 `startCocGame` 函数中修改 `systemPrompt` 变量，可以自定义 AI Keeper 的行为风格。

### 添加新文风

在 `tavernState.settings.style` 的选项中添加新的文风模板，并在 `generateNovel` 函数中处理。

## 📝 开发计划

- [ ] 密大图书馆 - 在线模组浏览与下载
- [ ] 调查员工坊 - 在线角色卡创建
- [ ] 骰点动画效果
- [ ] 语音输入/输出
- [ ] 更多 TRPG 规则支持 (DND, CoC Pulp 等)

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

1. Fork 本仓库
2. 创建特性分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 提交 Pull Request

## 📜 许可证

本项目采用 MIT 许可证 - 查看 [LICENSE](LICENSE) 文件了解详情。

## 🙏 致谢

- **Z4RKTO** - 前端开发与设计
- **STA1N** - 后端与多项优化
- [Vue.js](https://vuejs.org/) - 渐进式 JavaScript 框架
- [Tailwind CSS](https://tailwindcss.com/) - 实用优先的 CSS 框架
- [Supabase](https://supabase.com/) - 开源 Firebase 替代品

---

<div align="center">

*Ph'nglui mglw'nafh Cthulhu R'lyeh wgah'nagl fhtagn.*

**愿理智与你同在 🐙**

</div>
