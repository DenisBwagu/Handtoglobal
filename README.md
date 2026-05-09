# HandToGlobal - Complete Task Management Platform

A comprehensive task management and earning platform with multi-level user system, admin panel, and financial management features.

## 🌟 Features

### User Features
- **Multi-Level System**: Bronze, Silver, Gold, Platinum levels with progressive rewards
- **Task Management**: Complete tasks to earn rewards
- **Task Combos**: Bundle tasks for higher rewards
- **Invitation System**: Referral program with bonus rewards
- **Financial Management**: Deposits, withdrawals, balance tracking
- **User Dashboard**: Real-time activity monitoring and statistics

### Admin Features
- **Complete Admin Panel**: 13 comprehensive admin sections
- **User Management**: View, edit, delete users with balance management
- **Task Management**: Create and manage tasks with level requirements
- **Financial Analysis**: Charts, statistics, transaction tracking
- **Settings Management**: System configuration and customization
- **Employee Management**: HR system with attendance tracking
- **Content Management**: Contacts, testimonials, languages
- **Level Management**: User progression system configuration

### Technical Features
- **Modern UI/UX**: Clean, responsive design with gradient headers
- **Database Integration**: MySQL with proper relationships and indexing
- **Security**: Prepared statements, input validation, session management
- **Real-time Updates**: Live activity tracking and statistics
- **Multi-language Support**: Framework for internationalization

## 🚀 Quick Start

### Prerequisites
- PHP 7.4+
- MySQL 5.7+
- Web server (Apache/Nginx)
- Composer (optional)

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/yourusername/handtoglobal.git
   cd handtoglobal
   ```

2. **Database Setup**
   ```sql
   CREATE DATABASE handtoglobal;
   -- Import the database schema from db.sql or run create_tables.php
   ```

3. **Configuration**
   - Copy `config.example.php` to `config.php`
   - Update database credentials
   - Configure site settings

4. **Web Server Setup**
   - Point your web server to the project directory
   - Ensure `admin/` directory is accessible
   - Set proper file permissions

5. **Access the Platform**
   - User Interface: `http://yourdomain.com`
   - Admin Panel: `http://yourdomain.com/admin`
   - Default Admin: `admin@handtoglobal.com` / `password`

## 📁 Project Structure

```
handtoglobal/
├── admin/                  # Admin panel
│   ├── dashboard.php      # Admin dashboard
│   ├── users.php          # User management
│   ├── user_view.php      # User details
│   ├── tasks.php          # Task management
│   ├── combos.php         # Task combos
│   ├── levels.php         # Level management
│   ├── invitation_codes.php # Invitation system
│   ├── finance_analysis.php # Financial analytics
│   ├── deposits.php       # Deposit management
│   ├── withdrawals.php    # Withdrawal management
│   ├── contacts.php       # Contact management
│   ├── testimonials.php    # Testimonial management
│   ├── settings.php        # System settings
│   ├── employees.php       # Employee management
│   └── languages.php       # Language management
├── api/                    # API endpoints (future)
├── assets/                 # Static assets
├── includes/               # Reusable components
├── config.php              # Configuration file
├── index.php              # Main entry point
├── login.php              # Login page
├── dashboard.php          # User dashboard
├── tasks.php              # Task interface
├── deposits.php           # Deposit interface
├── withdraw.php           # Withdrawal interface
├── transactions.php       # Transaction history
├── records.php            # User records
├── starting.php           # Getting started
├── profile.php            # User profile
├── logout.php             # Logout
├── admin_login.php        # Admin login
├── admin_logout.php       # Admin logout
├── create_tables.php      # Database setup
├── drop_and_recreate.php  # Database reset
├── create_fresh_db.php    # Fresh database creation
├── add_test_users.php     # Test data creation
├── db.sql                 # Database schema
└── README.md              # This file
```

## 🎯 Admin Sections Overview

### Core Admin Functions
- **Dashboard**: System overview and statistics
- **Users**: Complete user management with balance controls
- **Settings**: System configuration and customization

### Financial Management
- **Finance Analysis**: Charts, trends, and financial reporting
- **Deposits**: Deposit approval and management
- **Withdrawals**: Withdrawal processing and tracking
- **Invitation Codes**: Code generation and referral tracking

### Task & Level System
- **Tasks**: Create and manage tasks with level requirements
- **Combos**: Bundle tasks for higher rewards
- **Levels**: Configure user progression system

### Content Management
- **Contacts**: User contact messages and support
- **Testimonials**: User testimonials management
- **Languages**: Multi-language support framework

### HR Management
- **Employees**: Employee database and attendance tracking

## 💾 Database Schema

The platform uses a comprehensive MySQL database with the following key tables:

- **users**: User accounts and profiles
- **admins**: Administrator accounts
- **tasks**: Task definitions and rewards
- **combos**: Task combinations
- **levels**: User progression levels
- **completed_tasks**: Task completion records
- **deposits**: Financial deposits
- **withdrawals**: Withdrawal requests
- **transactions**: Transaction history
- **invitation_codes**: Referral code system
- **contacts**: User contact messages
- **testimonials**: User testimonials
- **languages**: Language management
- **employees**: Employee records
- **settings**: System configuration
- **balance_logs**: Financial transaction logs

## 🔐 Security Features

- **Prepared Statements**: SQL injection prevention
- **Session Management**: Secure user authentication
- **Input Validation**: XSS and CSRF protection
- **Access Control**: Role-based permissions
- **Password Hashing**: Secure password storage

## 🎨 UI/UX Features

- **Modern Design**: Clean, professional interface
- **Responsive Layout**: Mobile-friendly design
- **Interactive Charts**: Real-time data visualization
- **Progress Tracking**: Visual progress indicators
- **Live Updates**: Real-time activity monitoring

## 🌐 Multi-Level System

Users progress through levels based on their balance:

- **Bronze** ($100+): $1.80 per task
- **Silver** ($150+): $2.50 per task  
- **Gold** ($250+): $3.50 per task
- **Platinum** ($500+): $5.00 per task

## 📊 Financial Features

- **Deposit System**: Multiple payment methods
- **Withdrawal Processing**: Secure fund transfers
- **Transaction History**: Complete audit trail
- **Balance Management**: Real-time balance tracking
- **Financial Analytics**: Comprehensive reporting

## 🔧 Configuration

Key configuration options in `config.php`:

```php
// Database settings
define('DB_HOST', 'localhost');
define('DB_NAME', 'handtoglobal');
define('DB_USER', 'username');
define('DB_PASS', 'password');

// Site settings
define('SITE_NAME', 'HandToGlobal');
define('SITE_URL', 'https://yourdomain.com');

// Level thresholds
define('BRONZE_THRESHOLD', 100);
define('SILVER_THRESHOLD', 150);
define('GOLD_THRESHOLD', 250);
define('PLATINUM_THRESHOLD', 500);
```

## 🚀 Deployment

### Production Setup

1. **Server Requirements**
   - PHP 7.4+ with MySQLi/PDO
   - MySQL 5.7+ or MariaDB 10.2+
   - SSL certificate (recommended)
   - Proper file permissions

2. **Environment Configuration**
   - Set production environment variables
   - Configure error reporting
   - Set up SSL and HTTPS
   - Configure backup systems

3. **Performance Optimization**
   - Enable database caching
   - Optimize database queries
   - Implement CDN for static assets
   - Set up monitoring and logging

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## 📝 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 🆘 Support

For support and questions:

- **Email**: support@handtoglobal.com
- **Documentation**: Check the `/docs` directory
- **Issues**: Report on GitHub Issues

## 🔄 Version History

- **v1.0.0**: Initial release with complete admin panel
- **v1.1.0**: Added employee management and levels system
- **v1.2.0**: Enhanced financial analytics and reporting
- **v1.3.0**: Improved UI/UX and mobile responsiveness

---

**Built with ❤️ for the HandToGlobal community**
