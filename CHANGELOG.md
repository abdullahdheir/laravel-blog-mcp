# Changelog

## 0.1.0 - 2026-10-08

- Stateless MCP server over Streamable HTTP (JSON responses): `initialize`, `ping`, `tools/list`, `tools/call`, batches and notifications.
- Static bearer-token protection; the endpoint answers 404 when no token is configured.
- Blog tools: `list_categories`, `list_posts`, `get_post`, `create_post`, `update_post`, `create_external_post`.
- `PostStore` contract and an Eloquent implementation driven by config (model classes, column names, status values).
- Unicode-friendly slugs (Arabic stays Arabic), unique and never purely numeric.
- Optional features switch themselves off when your table lacks the column: slug, notify_subscribers, categories, external posts.
