# 安全部署说明

1. `config.local.php` 只放在虚拟主机服务器上，不要上传到公开 GitHub。
2. 公开仓库只保留 `config.example.php`，里面只能写占位符。
3. 如果网页和 PHP 接口在同一个域名下，`allowed_origins` 保持空数组即可。
4. 如果网页在 A 域名、PHP 在 B 域名，把 A 域名完整写进 `config.local.php` 的 `allowed_origins`，不要写 `*`。
5. `db_api.php`、`room_api.php` 和 `library_api.php` 会自动创建需要的数据表。
6. 之前已经公开过的数据库密码和 API Key，请去服务商后台重置一次。
7. 前端不再内置 AI Key 或生图 Token。要继续用 AI，请在设置里填自己的 API 地址、Key 和模型；头像生图也需要填写自己的生图 API 地址和 Key。
8. 生图接口支持 URL 模板：`{prompt}` 或 `{tag}` 会替换为提示词，`{key}` 或 `{token}` 会替换为密钥；更安全的做法是后续加 PHP 代理接口。
