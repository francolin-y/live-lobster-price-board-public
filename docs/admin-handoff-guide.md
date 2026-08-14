# Admin Handoff Guide

Audience: Boss assistant / internal admin user.

---

## Purpose

The admin page lets authorized staff update the live lobster price board without touching any code. Changes are written to the server and the public page updates immediately after publish.

---

## URLs

| Page | URL |
|------|-----|
| Admin | https://prices.clarksharbourseafood.com/admin/ |
| Public page | https://prices.clarksharbourseafood.com/ |

---

## What you can edit

| Section | Fields |
|---------|--------|
| A · Page Status | Data status (sample / live), Last updated date |
| B · Announcement | Optional multilingual announcement shown at top of public page |
| C · Contacts | WeChat, Email, Phone |
| D · Price Rows | One row per product size; size, prices, status, notes |
| E · CNF Add-ons | Shipping add-on rates by region |
| F · Disclaimer | Optional multilingual disclaimer at bottom of public page |

---

## Standard workflow

1. Log in to the admin page.
2. Review the current data in the form.
3. Update the fields that need to change.
4. Click **Preview Changes**.
5. Read the validation errors in section G.
6. Fix all errors shown in red.
7. Read the warnings in yellow — these do not block publish but may need attention.
8. When there are no red errors, click **Publish to Public Page**.
9. Confirm the browser prompt.
10. Open the public page and verify the update is live.

---

## Field notes

### Data status
- **sample** — shows a sample data banner on the public page. Use this while setting up.
- **live** — shows real data to customers. Use this for production.

### Last updated
Always update this field to reflect when the data was last changed. It is shown on the public page. Format: `YYYY-MM-DD HH:MM AST` or similar.

### Announcement
Optional. Leave all three language fields blank to hide the announcement section on the public page.

### Price row — Size
One field only. Enter the size as it should appear. Example: `1.25–1.50 lb`

### Price row — Status
Selected from a dropdown. Options:

| Dropdown label | Meaning |
|----------------|---------|
| Available | In stock, ready to order |
| Limited | In stock but limited supply |
| Sold Out | Not currently available |
| Contact Sales First | Customer must contact sales before ordering |
| Pre-order Only | Not in stock; accepting pre-orders |

### Price row — Internal ID
Auto-generated from the size. Do not edit unless instructed. Editing unnecessarily can break tracking.

### Notes, Disclaimer, Announcement
All optional. Leave blank to hide those sections on the public page.

---

## Errors vs. warnings

| Type | Color | Blocks publish? |
|------|-------|----------------|
| Error | Red | Yes — fix before publishing |
| Warning | Yellow | No — review, then publish if acceptable |

---

## Common mistakes

- **Editing but forgetting to publish.** Preview shows the output but does not save it. You must click Publish to go live.
- **Publishing without checking the public page.** Always open the public page after publishing to confirm the update is visible.
- **Editing the Internal ID.** Leave it alone unless instructed otherwise.
- **Leaving Last Updated unchanged.** Update this field every time you publish new prices.
- **Selecting the wrong status.** Double-check the status dropdown before publishing.

---

## Emergency

If something looks wrong on the public page after publishing, **stop and contact Franco immediately**. Do not keep publishing trying to fix it — each publish overwrites the previous data.

Franco's contact: ask your manager.
