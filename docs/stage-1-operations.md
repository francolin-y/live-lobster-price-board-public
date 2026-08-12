# Live Lobster Price Board — Stage 1 Operations

---

## Purpose

The public page is a lightweight static price board designed to be accessible and stable for customers, including customers in Mainland China.

- The Admin Generator creates a valid `current-prices.json` file locally.
- The generator does **not** publish directly to the public website.
- Publishing requires a manual upload of `data/current-prices.json` to the Plesk server.

---

## Public Page

- **Production URL:** https://prices.clarksharbourseafood.com
- **Data source:** `/data/current-prices.json`

The public page is fully static. It reads `current-prices.json` at load time and renders all content from that file.

Keep the public page static and lightweight. Do not add dependencies on Supabase, Vercel APIs, or other external services.

---

## Admin Generator

- **Local path:** `/admin-generator/`
- **Local URL:** http://localhost:8080/admin-generator/

The Admin Generator is an internal tool intended for local use only. It is not deployed publicly.

Capabilities:
- Import an existing `current-prices.json` file
- Edit page status, announcements, contacts, prices, CNF add-ons, and disclaimer
- Validate all fields before export
- Preview the generated JSON
- Export `current-prices.json` (the file to upload)
- Export a timestamped backup for local records

The generator does **not** publish to the public website. Manual upload to Plesk is always required.

---

## Standard Update Workflow

1. Download the latest `current-prices.json` from Plesk, or use the latest approved local file.
2. Run a local server from the project root:
   ```
   python3 -m http.server 8080
   ```
3. Open the Admin Generator:
   ```
   http://localhost:8080/admin-generator/
   ```
4. Import the latest `current-prices.json` using the Import JSON section.
5. Edit the price board data as needed.
6. Check the Validation panel.
7. Fix all validation errors before proceeding.
8. Review any warnings carefully. Warnings do not block export.
9. Export `current-prices.json` using the Export button.
10. Optionally export a timestamped backup for local records.
11. Replace local `data/current-prices.json` with the exported `current-prices.json`.
12. Open the public page locally to verify:
    ```
    http://localhost:8080
    ```
13. Test all three languages: Simplified Chinese, Traditional Chinese, and English.
14. If the local test passes, upload **only** `data/current-prices.json` to Plesk at the path `/data/current-prices.json`.
15. Refresh the production URL:
    ```
    https://prices.clarksharbourseafood.com
    ```
16. Verify the public page shows the updated content correctly.

---

## Files to Upload to Plesk

When updating prices or content only, upload **one file**:

```
data/current-prices.json
```

Do **not** upload:

- Timestamped backup files (e.g. `current-prices-backup-2026-08-12-1430.json`)
- `admin-generator/`
- `docs/`
- Git files (`.git/`, `.gitignore`)
- `.DS_Store` or other local system files

---

## Git Workflow

After each stable patch, commit the changes:

```bash
git status
git add .
git commit -m "Clear commit message describing the change"
```

- Commit after each stable, working state.
- Do not leave many unrelated changes uncommitted.
- Confirm the working tree is clean before any deployment.

---

## Cache Notes

**Admin Generator not reflecting changes?**
Hard refresh the browser:
- Mac: `Command + Shift + R`
- Windows: `Ctrl + Shift + R`

**Public page not showing new content after upload?**
Hard refresh the browser and verify that `data/current-prices.json` was uploaded correctly to Plesk at the path `/data/current-prices.json`.

---

## Stage 1 Completion Checklist

- [ ] Public page opens locally.
- [ ] Public page opens at production URL.
- [ ] Public page reads `data/current-prices.json`.
- [ ] Public page supports Simplified Chinese, Traditional Chinese, and English.
- [ ] Admin Generator opens locally.
- [ ] Admin Generator can import JSON.
- [ ] Admin Generator can edit data.
- [ ] Admin Generator catches validation errors.
- [ ] Admin Generator exports `current-prices.json`.
- [ ] Admin Generator exports timestamped backup.
- [ ] Exported JSON works on local public page.
- [ ] Only `data/current-prices.json` is uploaded to Plesk for content updates.
- [ ] Production page updates after JSON upload.
- [ ] Mainland China access has been checked.
