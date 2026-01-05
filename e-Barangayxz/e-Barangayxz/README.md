# e-Barangayxz — Quick start
1. Start XAMPP: enable Apache and MySQL.

2. Open these pages in your browser (use `:PORT` if Apache uses a nonstandard port):

- Homepage: `http://localhost/e-Barangayxz/index.html`
- Staff login: `http://localhost/e-Barangayxz/staff-login.html`
- Staff dashboard: `http://localhost/e-Barangayxz/staff-dashboard.html`
- Super-admin: `http://localhost/e-Barangayxz/super-admin.html`

3. Default credentials (local/dev only):

- Super Admin: `superadmin@gmail.com` / `Superadmin123!`
- New staff default: `Admin123!` (each staff logs in with their email)

4. Quick checks if something doesn't work:

- Use the `http://localhost/e-Barangayxz/...` URL (don’t open PHP pages via VS Code Live Server).
- Confirm `db.php` matches your MySQL settings (host, user, password, dbname).
- If Super Admin password fails, open: `http://localhost/e-Barangayxz/update_superadmin_password.php` to reset it to `Superadmin123!`.

5. Debug tips:

- Open browser DevTools → Network to see requests to `get_staff.php` and other endpoints.
- Check `phpMyAdmin` to confirm `staff_accounts` rows exist and `status = 'active'` for visible staff.

