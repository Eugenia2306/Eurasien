Eurasien Gesellschaft: static multipage site (Phase 1)
Source of truth: eurasien_gesellschaft_54.html
Rebuild: python tools/build_static_site.py

Contents
- Public brochure pages (Home, Mission, Partner, Regionen, Mediathek, ...)
- Additive CSS: assets/css/fluid-layout.css
- WordPress bridge: assets/js/app-urls.js + app-bridge.js
  Login, membership checkout, and event booking CTAs go to /app/ (Phase 2)
- Apache: .htaccess (HTTPS + mitglieder/*.html redirects into /app/)

cPanel deploy
1. Open File Manager (or FTP) for the staging subdomain document root.
2. Upload EVERYTHING inside this folder into the document root.
   Do not upload the parent "static-site" folder name; upload its contents
   so that index.html sits at /index.html.
3. Confirm .htaccess uploaded (show hidden files in File Manager).
4. Install WordPress later at /app/ (see ../wp-app/INSTALL.txt).
5. After Woo products exist, set product IDs in assets/js/app-urls.js
   (EG_APP.setProductIds) or edit checkoutReader / checkoutVerein / checkoutEvent.

Smoke test (after upload)
- / and DE/EN toggle
- /partner.html partner cards open accordion sections
- /regionen.html map
- /mediathek.html video archive deep-link
- /anmelden.html Anmelden button leaves static site for /app/mein-konto/
- Membership pay buttons leave for /app/warenkorb/?add-to-cart=...

Public pages in this build: 26
