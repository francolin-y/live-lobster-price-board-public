# Live Lobster Price Board — Stage 2 Admin Architecture

---

## Core Principle

**Dynamic admin, static public page.**

- The admin backend may be dynamic.
- The public customer page must remain static.
- The public page should continue reading only `/data/current-prices.json`.
- Customer-side performance and Mainland China accessibility must not depend on admin backend availability.

---

## Target Architecture

```
Admin login
→ read existing /data/current-prices.json
→ edit fields in admin UI
→ validate data
→ publish
→ backup old JSON
→ write new /data/current-prices.json
→ public page displays updated data
```

---

## Public Page Rules

- Public page remains static HTML/CSS/JS.
- Public page reads same-origin `/data/current-prices.json`.
- Public page must not require database access.
- Public page must not require admin authentication.
- Public page must not depend on external APIs, external CDNs, Supabase, Firebase, Vercel APIs, or Google services.
- If the admin backend is down, the public page must still open with the latest published JSON.

---

## Admin Backend Responsibilities

- Login protection.
- Read current `/data/current-prices.json`.
- Edit page status, announcement, contacts, price rows, CNF add-ons, and disclaimer.
- Validate required fields.
- Block publish when validation errors exist.
- Allow warnings without blocking publish.
- Create timestamped backup before overwriting JSON.
- Write the new `/data/current-prices.json`.
- Show publish success or failure clearly.
- Keep a simple change log if practical.

---

## Minimum Safe Version

The first backend version must satisfy only:

- One admin login.
- File-based JSON editing (no database).
- Backup before publish.
- Manual rollback by restoring a backup JSON.
- No public page architecture change.

---

## Future Enhancements

- Multiple admin accounts.
- Role-based permissions.
- Change history.
- One-click rollback.
- Draft vs published status.
- Email notification after publish.
- Change comparison before publish.

---

## Risks to Avoid

- Do not make the public page depend on a live database query.
- Do not make the public page fetch data from a third-party API.
- Do not expose the admin page without login.
- Do not allow direct overwrite without backup.
- Do not let timestamped backup files become the live public price file.
- Do not store passwords in plain text.

---

## Recommended Stage 2 Build Order

1. Confirm server-side technology available on Plesk.
2. Build protected admin login.
3. Build server-side read/write for `/data/current-prices.json`.
4. Reuse current Admin Generator UI logic where practical.
5. Add backup-before-publish.
6. Add publish confirmation.
7. Test locally or on staging.
8. Deploy under `/admin/`.
9. Verify public page still works if admin backend is unavailable.
10. Keep Stage 1 manual JSON workflow as fallback.
