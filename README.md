# Parish Church Document Request & Booking System

A web-based system for managing document requests and bookings for parish churches.

## Features

### Client Side
- Document requests (Baptismal, Confirmation, Marriage, Death certificates, etc.)
- Booking system (Baptism, Wedding, Mass intentions, etc.)
- Payment proof upload
- Request tracking
- Email notifications

### Admin Side
- Dashboard with statistics
- Document request management
- Booking management
- User management
- Payment verification
- Reports and analytics

## Tech Stack
- **Backend:** PHP, MySQL
- **Frontend:** Bootstrap 5, JavaScript
- **Email:** PHPMailer with Gmail SMTP
- **Server:** XAMPP (Apache, MySQL)

## Installation

### Prerequisites
- XAMPP installed (Apache, MySQL, PHP)
- Node.js and npm (for Bootstrap)
- Git

### Setup Steps

1. **Clone the repository**
   ```bash
   git clone <your-repo-url>
   cd documentSystem
   ```

2. **Install dependencies**
   ```bash
   npm install
   ```

3. **Configure database**
   - Import `database_schema.sql` into MySQL:
     ```bash
     mysql -u root -p < database_schema.sql
     ```
   - Or via command line:
     ```bash
     mysql -u root -e "source /path/to/database_schema.sql"
     ```

4. **Configure email**
   - Copy `config/email_config.example.php` to `config/email_config.php`
   - Update with your Gmail credentials:
     - Generate App Password from Google Account
     - Update `SMTP_USERNAME` and `SMTP_PASSWORD`

5. **Set permissions** (if on Linux/Mac)
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/documents uploads/payments uploads/attachments
   ```

6. **Start XAMPP**
   - Start Apache and MySQL
   - Access: `http://localhost/documentSystem`

## Default Admin Login
- **Email:** admin@parishchurch.com
- **Password:** admin123
- ⚠️ **Change this immediately after first login!**

## Project Structure
```
documentSystem/
├── admin/              # Admin dashboard pages
├── client/             # Client-facing pages
├── api/                # REST API endpoints
├── handlers/           # Business logic
├── config/             # Configuration files
├── includes/           # Shared components
├── assets/             # CSS, JS, images
├── uploads/            # User uploads
└── database_schema.sql # Database structure
```

## Security Notes
- Never commit `config/email_config.php` with real credentials
- Change default admin password immediately
- Use HTTPS in production
- Regularly backup database
- Keep dependencies updated

## Database
- **Name:** parish_church_system
- **Tables:** 12 tables including users, documents, bookings, payments, etc.
- **Default data:** Document types, booking types, admin user, settings

## License
MIT License

## Support
For issues and questions, please open an issue in the repository.
