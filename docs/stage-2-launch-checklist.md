# Stage 2 Launch Checklist

Audience: Franco / technical owner.

Complete all items before declaring Stage 2 live.

---

## Security

- [ ] `admin/config.php` is not committed to Git.
- [ ] Temporary password has been replaced with a strong production password.
- [ ] `hash-test.php` has been deleted from the server.
- [ ] `admin-test.php` has been deleted from the server.
- [ ] `data/write-test.txt` has been deleted from the server.
- [ ] `/admin/` requires login — confirm by visiting in a private browser window.
- [ ] Direct access to `admin/config.php` is blocked (returns 403 or access denied).
- [ ] Direct access to `data/backups/` is blocked (returns 403 or shows Access denied page).

## Functionality

- [ ] Preview does not modify `data/current-prices.json`.
- [ ] Publish with validation errors is blocked — verify by leaving a required field empty.
- [ ] Publish with only warnings is allowed — verify by checking dataStatus = live with a TBD price.
- [ ] Publish creates a timestamped backup in `data/backups/` before overwriting.
- [ ] Public page updates after publish — open in a private window and verify.

## Public page

- [ ] Public page still opens if admin is not used.
- [ ] Public page remains fully static (no PHP, no backend dependency).
- [ ] Public page reads updated data from `data/current-prices.json` correctly.

## Git and versioning

- [ ] Latest working version is committed to Git on the `main` branch.
- [ ] `admin/config.php` does not appear in `git status`.
- [ ] Backup JSON files do not appear in `git status`.
- [ ] Git tag is created after final verification:
  ```
  git tag v0.2.0-stage2
  git push origin v0.2.0-stage2
  ```

---

## Notes

- If any checklist item fails, resolve it before going live.
- The Stage 1 tag `v0.1.0-stage1` marks the static-only baseline.
- Stage 2 tag should be `v0.2.0-stage2`.
