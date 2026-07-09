# Resort Booking System

A modern resort booking website with authentication, booking management, and review system.

## Features

- **Responsive Design**: Works on all devices
- **User Authentication**: Login and registration system
- **Booking Management**: Reserve cottages and rooms
- **Review System**: User reviews with star ratings
- **Modern UI**: Beautiful gradient design with smooth animations

## Installation

### Prerequisites
- XAMPP or similar web server with PHP and MySQL
- Web browser

### Setup Instructions

1. **Place the files in your web server directory**
   ```
   Copy all files to: C:\xampp\htdocs\resort\
   ```

2. **Start your web server**
   - Start Apache and MySQL from XAMPP control panel

3. **Set up the database**
   - Open your browser and go to: `http://localhost/resort/setup_database.php`
   - This will create the database and required tables

4. **Access the website**
   - Go to: `http://localhost/resort/`

## File Structure

```
resort/
├── index.php
├── google-auth.php
├── google-callback.php
├── config/
│   ├── config.php
│   ├── database.php
│   └── google-maps.php
├── css/
│   └── style.css
├── js/
│   └── script.js
├── images/
├── admin/
├── api/
├── controllers/
├── includes/
├── models/
├── views/
└── README.md
```

## Database Tables

- **users**: User accounts and authentication
- **bookings**: Booking records
- **reviews**: User reviews and ratings
- **cottages**: Cottage information
- **rooms**: Room information

## Usage

1. **Register a new account** or use Google login
2. **Login** with your credentials
3. **Browse facilities**: View cottages, pools, and rooms
4. **Leave reviews** and manage reservations

## Customization

### Adding Images
Place your resort images in the `images/` folder.

### Modifying Colors
Edit the CSS in `css/style.css`.

## Support

For issues and questions:
1. Check the XAMPP logs for server errors
2. Ensure MySQL is running
3. Verify file permissions
4. Check browser console for JavaScript errors

## License

This project is open source and available under the MIT License.
