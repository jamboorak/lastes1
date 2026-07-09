# Villa Soledad Garden Resort - Booking System

A complete PHP-based resort booking management system with real-time availability checking, admin dashboard, and responsive design.

## 🚀 Features

- **Room & Cottage Management**: Manage rooms, cottages, pools, and food menu items
- **Real-time Availability**: Live availability checking and booking limits
- **Admin Dashboard**: Comprehensive management interface for:
  - Reservation management
  - Room/Cottage/Pool management with image uploads
  - Food menu management
  - Booking records and statistics
  - Guest reviews and feedback
- **User Authentication**: 
  - Email/Password registration and login
  - Google OAuth integration
  - Email verification
- **Booking System**:
  - Check-in/check-out date selection
  - Multi-item reservations
  - Real-time price calculation
  - Reservation status tracking
- **Image Uploads**: Upload images for rooms, cottages, pools, and food items
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile devices
- **Email Notifications**: SMTP integration for reservation confirmations and notifications

## 📋 Prerequisites

- PHP 7.4+
- MySQL 5.7+
- Apache with mod_rewrite enabled
- Composer (optional, for PHP dependencies)

## ⚙️ Installation & Setup

### 1. Clone and Configure

```bash
git clone https://github.com/jimboopogi01-a11y/latest.git
cd latest
```

### 2. Set Up Credentials

The system uses `.gitignore` to protect sensitive files. You need to create local configuration files:

#### Database Configuration

```bash
cp config/database.php.example config/database.php
```

Edit `config/database.php` and add your database credentials:

```php
private $host = 'localhost';        // Your database host
private $username = 'root';         // Your database username
private $password = '';             // Your database password
private $database = 'resort_db';    // Database name (will be created)
```

#### Application Configuration

```bash
cp config/config.php.example config/config.php
```

Edit `config/config.php` and add your credentials:

```php
// SMTP Settings (Gmail)
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');  // Gmail App Password, not regular password

// Site Settings
define('SITE_EMAIL', 'your-resort-email@example.com');
define('SITE_PHONE', '+63-XXX-XXX-XXXX');

// Google OAuth Credentials
define('GOOGLE_CLIENT_ID', 'your-google-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', 'your-google-client-secret');
```

### 3. Initialize Database

Visit in your browser (or run setup scripts):

```
http://localhost/restorts/setup_database.php
http://localhost/restorts/setup_reservation_limits.php
```

These scripts will:
- Create database tables
- Seed default rooms, cottages, pools, and food items
- Set up reservation limits

### 4. Email Configuration (Gmail SMTP)

1. Enable 2-Step Verification on your Gmail account
2. Create an App Password: https://myaccount.google.com/apppasswords
3. Use the App Password (not your Gmail password) in `config/config.php`

### 5. Google OAuth Setup

1. Go to https://console.cloud.google.com/
2. Create a new project
3. Enable Google+ API
4. Create OAuth 2.0 credentials (Desktop/Web application)
5. Add authorized redirect URI: `http://localhost/restorts/google-callback.php`
6. Copy Client ID and Client Secret to `config/config.php`

## 📁 Directory Structure

```
├── admin/                  # Admin dashboard files
├── api/                    # REST API endpoints
├── config/                 # Configuration files
├── controllers/            # Business logic controllers
├── css/                    # Stylesheets
├── images/                 # Resort images and food photos
├── includes/               # Reusable PHP includes
├── js/                     # JavaScript files
├── models/                 # Data models
├── views/                  # Public view templates
├── vendor/                 # Composer dependencies (if using)
├── setup_database.php      # Database initialization script
└── setup_reservation_limits.php # Reservation limits setup
```

## 🔐 Security Notes

⚠️ **IMPORTANT**: The following files are protected by `.gitignore` and should NEVER be committed to version control:

- `config/config.php` - Contains SMTP, OAuth, and email credentials
- `config/database.php` - Contains database credentials
- `.env` - Environment variables
- `uploads/` - User-uploaded files
- `admin/uploads/` - Admin-uploaded images

Always use the `.example` files as templates.

## 📝 Usage

### For Users

1. Visit `http://localhost/restorts/`
2. Browse available rooms and cottages
3. Create an account or use Google OAuth
4. Make a reservation with desired dates and items
5. Confirm email verification
6. View bookings in "My Bookings"

### For Admins

1. Visit `http://localhost/restorts/admin/`
2. Login with admin credentials
3. Use dashboard to:
   - View and manage reservations
   - Upload images for rooms, cottages, pools, foods
   - Update pricing and descriptions
   - View statistics and reports
   - Manage guest reviews

## 🖼️ Uploading Images

Images for rooms, cottages, pools, and food items are stored in:
- `admin/uploads/` - Uploaded via admin panel

The system automatically handles:
- Image resizing and optimization
- Unique filename generation (prevents overwriting)
- Correct path storage for display on public pages

## 📊 Database Tables

- `users` - Guest user accounts
- `user_accounts` - Admin/staff accounts
- `rooms` - Room inventory
- `cottages` - Cottage inventory
- `pools` - Pool information
- `foods` - Menu items
- `reservations` - Booking records
- `reservation_items` - Items in each reservation
- `reservation_limits` - Daily booking caps
- `reviews` - Guest reviews
- `bookings` - Legacy booking records

## 🐛 Troubleshooting

### Images Not Showing

1. Ensure `admin/uploads/` directory exists with write permissions
2. Check that image paths in database start with `admin/uploads/`
3. Verify images were uploaded to correct directory

### Email Not Sending

1. Verify SMTP credentials in `config/config.php`
2. Check Gmail has 2-Step Verification enabled
3. Use Gmail App Password (not regular password)
4. Test with `admin/test_email.php`

### Database Connection Error

1. Ensure MySQL is running
2. Verify `config/database.php` has correct credentials
3. Check database user has all privileges on `resort_db`

### Google OAuth Not Working

1. Verify Client ID and Secret in `config/config.php`
2. Check redirect URI matches in Google Cloud Console
3. Ensure OAuth scope includes email and profile

## 📈 Version History

- **v1.0** - Initial release with core booking functionality
- **v1.1** - Added centralized room configuration, fixed image upload paths
- **v1.2** - Enhanced admin dashboard, improved availability checking

## 📄 License

This project is for educational and commercial use.

## 👨‍💻 Support

For issues or questions, please contact: jimboopogi01@gmail.com

---

**Last Updated**: July 9, 2026
