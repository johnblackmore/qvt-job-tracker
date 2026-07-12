# MCP Client Setup

The QVT Job Tracker exposes an MCP (Model Context Protocol) server so staff
can drive the admin tools from a chat client. The server runs in two flavours:

| Transport | When to use | Endpoint |
|-----------|-------------|----------|
| **Local / stdio** (Claude Desktop, custom agents) | You want one user pinned to the session, no network exposure | Spawned by `php artisan mcp:start qvt --user=email` |
| **HTTP / JSON-RPC** (OpenCode, Cursor, remote agents) | Multiple users, web-based clients | `POST /mcp/qvt` on this app |

Both transports require the caller to be a staff member with the `admin` role.
The HTTP transport also requires a Sanctum bearer token.

---

## 1. Generate a Sanctum API token

Only required for the **HTTP** transport. Local transport resolves the user
from the `--user` flag instead.

1. Log in to the QVT staff admin at `https://quantockvantech.com/login`
2. Open **Settings → API Tokens** (`/settings/api-tokens`)
3. Create a new token with a descriptive name (e.g. `OpenCode-laptop`)
4. Copy the plaintext token **immediately** — it is never shown again

> Treat the token like a password. Anyone holding it has full admin access
> via the MCP server, so rotate it from the same page if it leaks.

---

## 2. Claude Desktop (local / stdio)

Claude Desktop uses the `mcpServers` config. Add this to your
`claude_desktop_config.json` (macOS: `~/Library/Application Support/Claude/`):

```json
{
  "mcpServers": {
    "qvt": {
      "command": "php",
      "args": [
        "/Users/johnblackmore/Sites/qvt-job-tracker/artisan",
        "mcp:start",
        "qvt",
        "--user=admin@quantockvantech.com"
      ]
    }
  }
}
```

**Replace:**

- The path to `artisan` with the absolute path on your machine
- `admin@quantockvantech.com` with the email of the staff admin user you
  want Claude to act as

Restart Claude Desktop. The QVT tools will appear in the tool picker
(the 🔌 icon). Trade prices are visible because Claude is acting as a
staff user — never share this config.

---

## 3. OpenCode (HTTP / JSON-RPC)

OpenCode supports remote MCP servers. Add this to your opencode config
(`~/.config/opencode/config.json` or per-project `.opencode/opencode.json`):

```json
{
  "mcp": {
    "qvt": {
      "type": "remote",
      "url": "http://localhost:8000/mcp/qvt",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN_HERE",
        "Accept": "application/json"
      },
      "enabled": true
    }
  }
}
```

**Replace:**

- `http://localhost:8000` with the public URL in production
  (e.g. `https://admin.quantockvantech.com`)
- The bearer token with the one from step 1

OpenCode sends JSON-RPC 2.0 requests to the endpoint. The server is
protected by:

- `mcp.sanctum` middleware (validates the bearer token)
- `role:admin` middleware (must have the admin role)
- `throttle:mcp` middleware (60 requests / minute per user)

If the user is not an admin, the server returns `403`. If the user is an
installer (or has no role), no tools are registered.

---

## 4. Cursor (HTTP / JSON-RPC)

Cursor reads MCP servers from `~/.cursor/mcp.json`:

```json
{
  "mcpServers": {
    "qvt": {
      "url": "http://localhost:8000/mcp/qvt",
      "headers": {
        "Authorization": "Bearer YOUR_SANCTUM_TOKEN_HERE"
      }
    }
  }
}
```

Same caveats as OpenCode: use a per-machine token and rotate it regularly.

---

## 5. Generic JSON-RPC example (curl)

You can drive the server from any HTTP client. The protocol is JSON-RPC 2.0.

**List all available tools:**

```bash
curl -X POST http://localhost:8000/mcp/qvt \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 1,
    "method": "tools/list",
    "params": {"per_page": 50}
  }'
```

**Call a tool:**

```bash
curl -X POST http://localhost:8000/mcp/qvt \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 2,
    "method": "tools/call",
    "params": {
      "name": "get-customer-tool",
      "arguments": {"id": 42}
    }
  }'
```

**Read a resource template:**

```bash
curl -X POST http://localhost:8000/mcp/qvt \
  -H "Authorization: Bearer YOUR_SANCTUM_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc": "2.0",
    "id": 3,
    "method": "resources/read",
    "params": {"uri": "qvt://customers/42"}
  }'
```

---

## 6. Common errors

| HTTP | Cause | Fix |
|------|-------|-----|
| `401` | Missing or invalid bearer token | Regenerate from API Tokens page |
| `403` | Authenticated but not an admin | Promote the user to `admin` role in the database |
| `429` | Rate limit hit (60 / min) | Wait 60 seconds, or raise the limit in `AppServiceProvider::boot()` |
| `500` (with `error.message` containing "This action requires confirmation") | Write tool called without `preview=true` or `confirmed=true` | Always preview first, then call again with `confirmed=true` |
| `404` on the URL returned by a tool | The named route is missing | Check `routes/web.php` for the relevant resource (e.g. `customers.show`) |

---

## 7. Available tools (28)

Run `tools/list` to see the full schema. The tools are grouped:

- **Customers** — list, get, search, create, update, delete
- **Products** — list, get, search
- **Quotes** — create, create-from-template, add-line-item, update-status
- **Orders** — list, get, create, update-status, update-deposit, schedule-installation
- **Enquiries** — list, create, link-to-customer, respond
- **Communication** — send-quote-email, download-quote-pdf
- **Dashboard** — get-dashboard-stats, get-quote-activity, get-weekly-summary

Every write tool uses the **preview / confirmed** pattern:

```json
{"preview": true,  "confirmed": false}   // returns a preview (no DB change)
{"preview": false, "confirmed": false}   // returns an error: "needs confirmation"
{"preview": false, "confirmed": true}    // executes the action
```

This prevents the LLM from accidentally deleting a customer or sending an
email without staff confirmation.

---

## 8. Resources & prompts

- **Resources** (3): `qvt://customers/{id}`, `qvt://quotes/{id}`, `qvt://orders/{id}`
  — read-only contextual data, exposed via `resources/read`
- **Prompts** (2): `quote-assistant` and `weekly-report-generator` — reusable
  system prompt templates, exposed via `prompts/get`

List resource templates:

```bash
curl -X POST http://localhost:8000/mcp/qvt -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","id":1,"method":"resources/templates/list"}'
```

---

## 9. Local development

Start the local server in a terminal:

```bash
php artisan mcp:start qvt --user=admin@example.com
```

The server listens on stdio. Use MCP Inspector (`npx @modelcontextprotocol/inspector`)
to test it interactively.

Run the test suite to verify the server end-to-end:

```bash
php artisan test --compact tests/Feature/Mcp/
```

The suite covers auth, rate limits, trade-price safety, schema, and
happy paths for every tool.
