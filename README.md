🩸 Pirate’s Blood DBMS

A Blood Donation and Request Management System designed to streamline donor-recipient matching, blood inventory tracking, and event coordination.

📌 Overview

Pirate’s Blood DBMS is a web-based system that facilitates efficient blood donation and transfusion processes. It allows users to register as donors, recipients, or administrators, providing real-time matching, request handling, and inventory management.

The system is designed especially for localized deployment (e.g., hospitals, academic institutions, or communities) where access to centralized blood bank systems may be limited.

🎯 Key Features
🧑‍🤝‍🧑 User Roles
Donor
Register and manage profile
Submit donation availability
Recipient
Request blood with urgency level
Track request status
Administrator
Approve/reject requests
Manage users and inventory
Monitor system activity
🩸 Core Functionalities
🔍 Real-time donor-recipient matching
📦 Blood inventory tracking
⏳ Dynamic waitlist & prioritization system
📊 Leaderboard for top donors
📝 Donation & request history logs
📅 Event management for blood drives
📈 Statistical reporting and analytics
🔐 Security & Privacy
Passwords stored using secure hashing (bcrypt)
Role-based access control
Input validation to prevent invalid data
Sensitive data is anonymized when displayed
Activity logging for traceability
🏗️ Tech Stack
Frontend
HTML
CSS
JavaScript
Backend
PHP (or Python alternative)
Database
MySQL (managed via phpMyAdmin)
Environment
XAMPP (Apache + MySQL + PHP)
🗄️ Database Structure

The system uses a relational database design with key tables:

login – user authentication and roles
blood_inventory – available blood units
donate_blood – donation records
recipient_form – blood requests
blood_log – transaction history
events – blood drive events
event_registration – participant tracking
admin_appeals – user appeals
leaderboard – donor statistics
⚙️ Installation Guide
1. Clone the Repository
git clone https://github.com/your-username/pirates-blood-dbms.git
cd pirates-blood-dbms
2. Setup XAMPP
Install XAMPP
Start:
Apache
MySQL
3. Import Database
Open phpMyAdmin
Create a new database
Import the provided .sql file
4. Configure Project
Place project folder in:
xampp/htdocs/
Update database connection file:
$host = "localhost";
$user = "root";
$password = "";
$database = "your_db_name";
5. Run the System

Open browser:

http://localhost/pirates-blood-dbms
📊 System Capabilities
Handles real-time updates for inventory and requests
Supports ACID-compliant transactions via MySQL InnoDB
Optimized queries using indexing and normalization
Designed for scalability and future API integration
⚠️ Limitations
Local deployment (no cloud hosting by default)
No advanced authentication (e.g., 2FA)
Limited scalability for high concurrent users
Manual backup required
🚀 Future Improvements
🌐 Cloud deployment support
🔗 Integration with hospital systems / APIs
🔐 Multi-factor authentication
📱 Mobile-friendly interface
📩 SMS/Email notifications
📊 Advanced analytics dashboard
👨‍💻 Developers
Perez, Clarinze Aundreka
Abante, Marjinel C.
Peruda, Zenia Faye B.
Yahiya, Merhaya A.
📄 License

This project is developed for academic purposes. Usage and modification are allowed with proper credit.

📌 Acknowledgments
Lyceum of the Philippines University – Cavite
Department of Computer Studies (DCS)
Research references on blood bank systems and healthcare DBMS
