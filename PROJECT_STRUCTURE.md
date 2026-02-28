# Project Structure Documentation

## Folder Organization

This PHP project has been reorganized into a clean, professional structure following best practices.

### Directory Tree

```
LDCDentClinicSys/
├── assets/
│   ├── css/              # All CSS stylesheets
│   │   ├── accountstyle.css
│   │   ├── adminstyle.css
│   │   ├── loginpagestyle.css
│   │   ├── paymentstyle.css
│   │   ├── profilestyle.css
│   │   ├── registerstyle.css
│   │   └── styles.css
│   ├── images/           # All image files (logos, photos, icons)
│   └── js/               # JavaScript files (if any)
├── controllers/          # Backend logic and API endpoints
│   ├── appointmentProcess.php
│   ├── adminCancelAppointment.php
│   ├── chat_api.php
│   ├── getAppointments.php
│   ├── getFeedbacks.php
│   ├── getNotifications.php
│   ├── getClosedDates.php
│   ├── markNotificationRead.php
│   ├── markAllNotificationsRead.php
│   ├── updateCredentials.php
│   ├── updateStaff.php
│   └── [other controller files]
├── database/             # Database configuration
│   └── config.php        # Database connection settings
├── layouts/              # Reusable layout components
│   ├── header.php        # Site header/navigation
│   └── footer.php        # Site footer
├── libraries/            # Third-party libraries
│   ├── fpdf/             # FPDF library for PDF generation
│   └── PhpMailer/        # PHPMailer library for email
├── views/                # Frontend pages and views
│   ├── index.php         # Homepage
│   ├── about.php
│   ├── account.php       # User account page
│   ├── admin.php         # Admin dashboard
│   ├── blogs.php
│   ├── chat.php
│   ├── chatbot.php
│   ├── login.php         # Login page
│   ├── location.php
│   ├── payment.php
│   └── [other view files]
├── uploads/              # User-uploaded files
├── reports/              # Generated reports
└── [SQL files and other root files]
```

## Folder Purposes

### `/assets/`
**Purpose**: Static assets (CSS, JavaScript, images) that are served to the client.

- **`css/`**: All stylesheet files for consistent styling across the application
- **`images/`**: All image assets including logos, service images, icons
- **`js/`**: JavaScript files for client-side functionality

### `/controllers/`
**Purpose**: Backend logic, form processing, API endpoints, and business logic.

Contains files that:
- Process form submissions
- Handle database operations (CRUD)
- Provide API endpoints for AJAX requests
- Manage authentication and authorization
- Handle file uploads and processing

### `/database/`
**Purpose**: Database configuration and connection management.

- **`config.php`**: Contains database connection settings (host, username, password, database name)

### `/layouts/`
**Purpose**: Reusable layout components that are included across multiple pages.

- **`header.php`**: Site header with navigation menu
- **`footer.php`**: Site footer with links and contact information

### `/libraries/`
**Purpose**: Third-party libraries and dependencies.

- **`fpdf/`**: FPDF library for generating PDF documents (receipts, reports)
- **`PhpMailer/`**: PHPMailer library for sending emails

### `/views/`
**Purpose**: Frontend pages that users interact with.

Contains all PHP pages that:
- Display content to users
- Include forms for user input
- Render HTML output
- Handle page-specific logic

## Updated Include/Require Paths

### Database Configuration
```php
// Old path (from views/)
include_once('./login/config.php');

// New path (from views/)
include_once('../database/config.php');

// From controllers/
include_once('../database/config.php');
```

### Layout Files
```php
// Old path
include_once('header.php');
include_once('../header.php');

// New path (from views/)
include_once('../layouts/header.php');
include_once('../layouts/footer.php');
```

### Libraries
```php
// Old path
require '../PhpMailer/src/Exception.php';
require('../fpdf/fpdf.php');

// New path (from controllers/)
require '../libraries/PhpMailer/src/Exception.php';
require '../libraries/fpdf/fpdf.php';
```

### Asset Paths
```php
// CSS files
// Old: <link rel="stylesheet" href="styles.css">
// New: <link rel="stylesheet" href="../assets/css/styles.css">

// Images
// Old: <img src="landerologo.png">
// New: <img src="../assets/images/landerologo.png">
```

### API Endpoints
```php
// Old path (from views/)
fetch('getAppointments.php')

// New path (from views/)
fetch('../controllers/getAppointments.php')
```

## Benefits of This Structure

1. **Separation of Concerns**: Clear separation between frontend (views), backend (controllers), and configuration
2. **Maintainability**: Easy to locate files based on their purpose
3. **Scalability**: Easy to add new features without cluttering the root directory
4. **Security**: Configuration files are separated from public-facing files
5. **Organization**: Related files are grouped together logically
6. **Professional**: Follows industry-standard PHP project structure

## Migration Notes

- All include/require paths have been updated to reflect the new structure
- Asset paths (CSS, images) have been updated in all view files
- API endpoint paths in JavaScript have been updated
- Navigation links have been updated to point to the new view locations

