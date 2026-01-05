# Setup Guide for Group Members

Follow these steps to set up the Parish Church Document Request and Booking System on your local machine after cloning the repository.

## Prerequisites

Before you begin, make sure you have these installed:

1. **XAMPP** (includes Apache, MySQL, PHP)
   - Download: https://www.apachefriends.org/
   - Version: Latest stable version

2. **Node.js and npm**
   - Download: https://nodejs.org/
   - Version: LTS (Long Term Support)

3. **Git**
   - Download: https://git-scm.com/
   - Or use `winget install Git.Git` (Windows 10/11)

4. **Text Editor/IDE**
   - VS Code (recommended): https://code.visualstudio.com/
   - Or any editor of your choice

---

## Step 1: Clone the Repository

```bash
cd C:\xampp\htdocs
git clone https://github.com/ManzanoHerchelle/parish-church-system.git
cd parish-church-system
```

---

## Step 2: Install Node Dependencies

Install Bootstrap and other npm packages:

```bash
npm install
```

This will create a `node_modules` folder with Bootstrap and dependencies.

---

## Step 3: Install PHPMailer

Download PHPMailer manually:

1. Go to: https://github.com/PHPMailer/PHPMailer/releases
2. Download the latest version (e.g., `PHPMailer-6.9.1.zip`)
3. Extract to: `C:\xampp\htdocs\parish-church-system\includes\PHPMailer-6.9.1\`

**OR** use this PowerShell command:

```powershell
Invoke-WebRequest -Uri "https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip" -OutFile "$env:TEMP\phpmailer.zip"
Expand-Archive -Path "$env:TEMP\phpmailer.zip" -DestinationPath "C:\xampp\htdocs\parish-church-system\includes\" -Force
```

---

## Step 4: Start XAMPP

1. Open **XAMPP Control Panel**
2. Click **Start** on:
   - Apache
   - MySQL

Both should show green "Running" status.

---

## Step 5: Create Database

### Option A: Using Command Line (Recommended)

Open PowerShell and run:

```bash
# Add MySQL to PATH temporarily
$env:Path += ";C:\xampp\mysql\bin"

# Import database
mysql -u root -e "source C:/xampp/htdocs/parish-church-system/database_schema.sql"
```

### Option B: Using phpMyAdmin

1. Open browser: http://localhost/phpmyadmin
2. Click **Import** tab
3. Click **Choose File**
4. Select `database_schema.sql` from the project folder
5. Click **Go**

The database `parish_church_system` will be created with all tables and sample data.

---

## Step 6: Configure Email Settings

### 6.1 Create Email Config File

1. Navigate to `config/` folder
2. Copy `email_config.example.php` to `email_config.php`:

```bash
copy config\email_config.example.php config\email_config.php
```

### 6.2 Get Gmail App Password

You need a Gmail account with App Password:

1. Go to: https://myaccount.google.com/security
2. Enable **2-Step Verification** (if not already enabled)
3. After 2FA is on, go back to Security
4. Click **App passwords**
5. Select:
   - App: **Mail**
   - Device: **Windows Computer** (or Other)
6. Click **Generate**
7. Copy the 16-character password (e.g., `xxxx xxxx xxxx xxxx`)

### 6.3 Update Email Config

Edit `config/email_config.php` and update these lines:

```php
define('SMTP_USERNAME', 'your-email@gmail.com');  // Your Gmail address
define('SMTP_PASSWORD', 'xxxxxxxxxxxxxxxx');      // Your App Password (remove spaces)
define('SMTP_FROM_EMAIL', 'your-email@gmail.com'); // Same as username
```

**Example:**
```php
define('SMTP_USERNAME', 'john.doe@gmail.com');
define('SMTP_PASSWORD', 'abcdefghijklmnop');
define('SMTP_FROM_EMAIL', 'john.doe@gmail.com');
```

---

## Step 7: Test the System

### 7.1 Test Database Connection

Open browser and go to:
```
http://localhost/parish-church-system/
```

If no errors appear, database connection is working!

### 7.2 Test Email (Optional)

Create a test file or use the existing test (if shared):

```bash
# Add PHP to PATH
$env:Path += ";C:\xampp\php"

# Run test
php test_email.php
```

If successful, you should receive an email.

---

## Step 8: Default Login Credentials

### Admin Account
- **URL:** http://localhost/parish-church-system/admin/
- **Email:** admin@parishchurch.com
- **Password:** admin123

⚠️ **Important:** Change this password after first login!

---

## Project Structure

```
parish-church-system/
├── admin/                  # Admin panel (to be built)
├── client/                 # Client pages (to be built)
├── api/                    # API endpoints (to be built)
├── handlers/               # Backend logic
│   └── email_handler.php   # Email sending functions
├── config/
│   ├── database.php        # Database connection
│   ├── email_config.php    # Your email settings (create this)
│   └── email_config.example.php  # Template
├── includes/               # Shared components
│   └── PHPMailer-6.9.1/    # Email library (install this)
├── assets/
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   └── images/             # Images
├── uploads/                # User uploads
│   ├── documents/          # Processed documents
│   ├── payments/           # Payment proofs
│   └── attachments/        # Request attachments
├── node_modules/           # Bootstrap (npm install)
├── database_schema.sql     # Database structure
├── package.json            # npm dependencies
└── README.md               # Project overview
```

---

## Common Issues & Solutions

### Issue 1: "npm: The term 'npm' is not recognized"
**Solution:** Node.js not installed or not in PATH
- Restart PowerShell/Terminal after installing Node.js
- Or manually add to PATH: `$env:Path += ";C:\Program Files\nodejs"`

### Issue 2: "mysql: The term 'mysql' is not recognized"
**Solution:** MySQL not in PATH
- Run: `$env:Path += ";C:\xampp\mysql\bin"`
- Then try the mysql command again

### Issue 3: "php: The term 'php' is not recognized"
**Solution:** PHP not in PATH
- Run: `$env:Path += ";C:\xampp\php"`

### Issue 4: "SMTP Error: Could not authenticate"
**Solution:** Email config issues
- Make sure you're using App Password (not regular Gmail password)
- Remove spaces from App Password: `xxxx xxxx xxxx xxxx` → `xxxxxxxxxxxxxxxx`
- Verify 2FA is enabled on Google account
- Check email address is correct

### Issue 5: "Access denied for user 'root'@'localhost'"
**Solution:** MySQL password required
- If your XAMPP MySQL has a password, update `config/database.php`:
  ```php
  define('DB_PASS', 'your_mysql_password');
  ```

### Issue 6: Apache won't start (Port 80 in use)
**Solution:** Another service using port 80
- Close Skype or other apps using port 80
- Or change Apache port in XAMPP config

### Issue 7: Can't write to uploads folder
**Solution:** Permission issues (mainly on Linux/Mac)
- Windows: Usually no issue
- Linux/Mac: `chmod 755 uploads/ -R`

---

## Database Information

- **Database Name:** `parish_church_system`
- **Tables:** 12 tables
  - users
  - document_types
  - document_requests
  - document_attachments
  - booking_types
  - bookings
  - blocked_dates
  - payments
  - notifications
  - system_settings
  - activity_logs
  - email_logs

### Pre-loaded Data

**Document Types (6):**
- Baptismal Certificate - ₱100
- Confirmation Certificate - ₱100
- Marriage Certificate - ₱150
- Death Certificate - ₱100
- Burial Permit - ₱200
- Letter of Recommendation - ₱50

**Booking Types (7):**
- Baptism - ₱500
- Wedding - ₱5000
- Mass Intention - ₱200
- Confession - Free
- Funeral Service - ₱2000
- Hall Rental - ₱3000
- Chapel Rental - ₱1500

---

## Development Workflow

### Making Changes

1. **Pull latest changes:**
   ```bash
   git pull origin main
   ```

2. **Make your changes**

3. **Stage and commit:**
   ```bash
   git add .
   git commit -m "Description of changes"
   ```

4. **Push to GitHub:**
   ```bash
   git push origin main
   ```

### Important Files NOT to Commit

These are in `.gitignore` and should NEVER be pushed:
- `config/email_config.php` (contains your password!)
- `node_modules/` (too large, reinstall via npm)
- `uploads/*` (user data, sensitive)
- `includes/PHPMailer-6.9.1/` (reinstall manually)

---

## Need Help?

### Quick Checklist

- ✅ XAMPP installed and running (Apache + MySQL)
- ✅ Node.js and npm installed
- ✅ Repository cloned to `C:\xampp\htdocs\`
- ✅ `npm install` completed
- ✅ PHPMailer downloaded to `includes/`
- ✅ Database imported (parish_church_system exists)
- ✅ `config/email_config.php` created and configured
- ✅ Can access http://localhost/parish-church-system/

### Contact

If you encounter issues:
1. Check **Common Issues** section above
2. Ask in your group chat
3. Create an issue on GitHub repository

---

## Next Steps

Once setup is complete:
1. Test admin login with default credentials
2. Change default admin password
3. Explore the database structure
4. Start building the frontend pages
5. Test email notifications

**Happy coding!** 🚀
