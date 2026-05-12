# MyDesignAssistants Portfolio System
## A. Moeed — mydesignassistants.com

Complete PHP/MySQL portfolio + admin panel for interior designer A. Moeed.

---

## 📁 File Structure

```
portfolio/
├── index.php              ← Main portfolio (frontend)
├── config.php             ← DB config & helpers
├── database.sql           ← Database schema + seed data
├── sitemap.php            ← SEO sitemap (auto-generated)
├── robots.txt             ← Search engine rules
├── .htaccess              ← Apache config (caching, security)
│
├── admin/
│   ├── login.php          ← Admin login
│   ├── dashboard.php      ← Stats + charts
│   ├── projects.php       ← Add/edit/delete projects
│   ├── testimonials.php   ← Add/edit/delete testimonials
│   ├── messages.php       ← Contact inbox + Gmail reply
│   ├── settings.php       ← Site settings + profile image
│   ├── logout.php
│   ├── _header.php        ← Layout partial
│   ├── _footer.php        ← Layout partial
│   └── .htaccess
│
├── api/
│   └── project.php        ← JSON endpoint for project modal
│
└── uploads/
    ├── projects/          ← Project thumbnails & gallery images
    ├── testimonials/      ← Client review photos
    └── avatars/           ← Profile photo (profile.png)
```

---

## 🚀 Installation

### Step 1: Upload Files
Upload the entire `portfolio/` folder to your web hosting root (public_html or www).

### Step 2: Create Database
1. Log in to cPanel → phpMyAdmin
2. Create a new database: `mydesignassistants`
3. Import `database.sql`

### Step 3: Configure Database
Edit `config.php`:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'mydesignassistants');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
```

### Step 4: Set Permissions
```bash
chmod 755 uploads/
chmod 755 uploads/projects/
chmod 755 uploads/testimonials/
chmod 755 uploads/avatars/
```

### Step 5: Upload Profile Photo
- Go to Admin → Settings
- Upload your profile photo
- OR manually place photo at: `uploads/avatars/profile.png`

### Step 6: Admin Login
- URL: `https://mydesignassistants.com/admin/login.php`
- Username: `moeed`
- Password: `password` ← **CHANGE THIS IMMEDIATELY**

### Step 7: Change Admin Password
Run this SQL in phpMyAdmin:
```sql
UPDATE admin_users 
SET password = '$2y$10$YOUR_HASH_HERE' 
WHERE username = 'moeed';
```

Generate hash with PHP:
```php
echo password_hash('YourNewPassword123!', PASSWORD_DEFAULT);
```

---

## ✅ Features Checklist

### Portfolio Frontend
- [x] Elegant hero with Three.js geometric background
- [x] Animated profile photo with decorative frame
- [x] Stats bar (Years, Projects, Satisfaction, Countries)
- [x] About section with rich text
- [x] Portfolio grid with hover overlays
- [x] Project modal with gallery + lightbox (click to enlarge, X to close)
- [x] Services strip (dark background)
- [x] Testimonials with Swiper carousel
- [x] Star ratings (1–5, supports 0.5 steps)
- [x] Client photos, country, platform badges (Upwork/Fiverr)
- [x] Brand/Software logos section
- [x] Contact form → saves to database
- [x] Upwork + Fiverr logos in hero
- [x] Custom cursor
- [x] Loading screen
- [x] AOS scroll animations
- [x] Marquee text strip

### Admin Panel
- [x] Secure login with session
- [x] Dashboard with stats + Chart.js bar chart
- [x] Projects: Add/Edit/Delete with rich text description (Quill editor)
- [x] Projects: Thumbnail + multiple gallery images upload
- [x] Projects: Software used, features, category, featured toggle
- [x] Testimonials: Add/Edit/Delete
- [x] Testimonials: Star rating selector (icon buttons: 1, 1.5, 2...5)
- [x] Testimonials: Client photo upload, country, platform
- [x] Messages: Full inbox with read/unread, star important
- [x] Messages: One-click Gmail reply (opens compose window pre-filled)
- [x] Messages: Quick reply text box → Gmail
- [x] Messages: Delete messages
- [x] Settings: Hero text, about text, email, Upwork/Fiverr URLs
- [x] Settings: Profile image upload

### Technical
- [x] SEO: meta tags, OG tags, Twitter card, Schema.org JSON-LD
- [x] SEO: Canonical URL for mydesignassistants.com
- [x] SEO: Auto-generated sitemap.php
- [x] SEO: robots.txt
- [x] Performance: Apache browser caching (.htaccess)
- [x] Performance: GZIP compression
- [x] Security: PDO prepared statements (SQL injection proof)
- [x] Security: Session-protected admin
- [x] Security: File upload validation
- [x] Security: .htaccess blocks config.php and SQL file access

---

## 🎨 Design System

**Fonts:** Cormorant Garamond (display) + Jost (body)

**Colors:**
- Cream: `#F5F0E8` — backgrounds
- Sand/Gold: `#C9A96E` — accents, highlights
- Charcoal: `#2C2C2C` — text, dark elements
- Sage: `#7A8C7E` — secondary text
- Terracotta: `#C17B5C` — error states

---

## 📧 Gmail Reply Setup
When clicking "Reply via Gmail" in the Messages panel:
1. It opens Gmail in a new tab
2. The recipient email is pre-filled
3. "Re: [Subject]" is pre-filled
4. The original message is quoted at the bottom

**No API key needed** — uses Gmail's web compose URL.

---

## 🔒 Security Checklist (Before Going Live)
- [ ] Change admin password
- [ ] Set strong `SECRET_KEY` in config.php
- [ ] Enable HTTPS and uncomment force-HTTPS in .htaccess
- [ ] Update DB credentials in config.php
- [ ] Delete `database.sql` from server after import

---

## 📞 Support
Built for A. Moeed — MyDesignAssistants.com
