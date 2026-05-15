# Textile Quality Management System (Textile QMS)

A comprehensive, professional-grade Quality Management System (QMS) specifically designed for the textile industry. This platform streamlines quality control, inspection workflows, shift management, and personnel tracking, providing real-time analytics and role-based access control.

![Dashboard Preview](https://via.placeholder.com/800x450.png?text=Textile+QMS+Dashboard+Preview)

## 🚀 Key Features

### 📊 Quality Dashboard
- Real-time overview of inspection metrics.
- Visual data representation of quality trends and defect rates.
- Quick access to critical system alerts and notifications.

### 🔍 Inspection Management
- **Full Lifecycle**: Create, edit, and track fabric inspections.
- **Defect Tracking**: Categorize defects with counts, types, and photographic evidence.
- **Grading System**: Automatic or manual grading (Passed, Rejected, On Hold).
- **Barcode/QR Support**: Integration for batch and material tracking.

### 🕒 Shift & Workforce Management
- **Shift Scheduling**: Assign and manage personnel shifts.
- **Attendance Tracking**: Monitor team presence and shift timing.
- **Handovers**: Seamless shift-to-shift communication and status reports.

### 🛡️ Advanced Security & RBAC
- **Granular Permissions**: Role-based access control (RBAC) down to individual features.
- **Role Management**: Define custom roles (General Manager, Quality Manager, Supervisor, etc.).
- **Audit Logs**: Comprehensive logging of user actions for accountability.

### 📁 HR & Personnel Directory
- **Secure Directory**: Manage employee profiles, contact info, and joining details.
- **Export Capabilities**: Export personnel data for administrative use.

### 🌓 Modern UI/UX
- **Dark/Light Mode**: Toggle between themes for comfort during different shifts.
- **Responsive Design**: Fully functional on desktop and tablet browsers.
- **Notification System**: Real-time alerts for critical events.

## 🛠️ Technology Stack

- **Backend**: Native PHP 8.x
- **Database**: MySQL / MariaDB
- **Frontend**: HTML5, CSS3 (Custom Design System), JavaScript (Vanilla)
- **Icons**: Font Awesome 6.4.0
- **Authentication**: Secure Password Hashing (Bcrypt)

## 📦 Installation

### Prerequisites
- PHP >= 8.0
- MySQL >= 5.7
- Web Server (Apache/Nginx) - *Recommended: XAMPP for local development*

### Step-by-Step Setup

1. **Clone the Repository**
   ```bash
   git clone https://github.com/your-username/TextileQMS.git
   cd TextileQMS
   ```

2. **Database Configuration**
   - Create a database named `textile_qms` in your MySQL environment.
   - Import the initial schema:
     ```bash
     mysql -u root -p textile_qms < database.sql
     ```
   - (Optional) Run schema updates if necessary:
     ```bash
     mysql -u root -p textile_qms < update_schema.sql
     ```

3. **Configure Connection**
   - Open `db.php` and update your database credentials if they differ from the default:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "textile_qms";
     ```

4. **Launch**
   - Move the project folder to your web root (e.g., `htdocs` for XAMPP).
   - Access via browser: `http://localhost/TextileQMS`

### Default Credentials
- **Username**: `admin`
- **Password**: `admin123`

## 📂 Project Structure

```text
TextileQMS/
├── api/                # Backend API endpoints (Mark as read, etc.)
├── setup/              # Database migration and fix scripts
├── uploads/            # Storage for defect photos and attachments
├── db.php              # Database connection configuration
├── dashboard.php       # Main analytics interface
├── inspections.php     # Inspection management module
├── personnel.php       # HR and employee directory
├── shifts.php          # Shift management and scheduling
├── roles.php           # RBAC and permissions management
├── style.css           # Centralized modern design system
└── layout_header.php   # Reusable UI components (Sidebar/Nav)
```

## 🤝 Contributing
Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License
This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---
*Built with ❤️ for the Textile Industry.*
