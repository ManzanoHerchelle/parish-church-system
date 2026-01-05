# Parish Church Document Request & Booking System

A web-based system for managing document requests and bookings for parish churches.

## Features

### Client Side
- Document requests (Baptismal, Confirmation, Marriage, Death certificates, etc.)
- Booking system (Baptism, Wedding, Mass intentions, etc.)
- Online payment processing (GCash, PayMaya)
- Payment proof upload
- Request tracking
- Email notifications
- PDF certificate downloads

### Admin Side
- Dashboard with statistics
- Document request management
- Booking management
- User management
- Payment verification & gateway integration
- Reports and analytics
- Advanced search & filters
- Online payment gateway configuration
- Transaction monitoring

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

5. **Configure payment gateways** (Optional - for online payments)
   - Go to Admin → Settings → Payment Gateway Configuration
   - Add GCash API credentials (optional)
   - Add PayMaya API credentials (optional)
   - See `PAYMENT_GATEWAY_SETUP.md` for detailed setup

6. **Set permissions** (if on Linux/Mac)
   ```bash
   chmod 755 uploads/
   chmod 755 uploads/documents uploads/payments uploads/attachments
   ```

7. **Start XAMPP**
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
│   └── settings/       # Admin settings including payment gateway config
├── client/             # Client-facing pages (includes checkout)
├── api/                # REST API endpoints (includes payment webhooks)
├── src/
│   └── Services/       # Business logic services (PaymentGatewayService, etc.)
├── handlers/           # Business logic handlers
├── config/             # Configuration files
├── includes/           # Shared components
├── assets/             # CSS, JS, images
├── uploads/            # User uploads
├── PAYMENT_GATEWAY_SETUP.md  # Payment gateway configuration guide
└── database_schema.sql       # Database structure
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
