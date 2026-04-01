Here is a clean, **plain and copy-pasteable `README.md`** without emojis:

---

# Pirate’s Blood DBMS

## Overview

Pirate’s Blood DBMS is a Blood Donation and Request Management System designed to streamline donor-recipient matching, blood inventory tracking, and event coordination. The system allows users to log in as donors, recipients, or administrators and provides real-time matching based on compatibility, availability, and urgency. 

It is intended for local deployment in academic or community settings where access to centralized blood bank systems may be limited.

---

## Features

### User Roles

* Donor

  * Register and manage profile
  * Submit donation availability
* Recipient

  * Request blood
  * Track request status
* Administrator

  * Approve or reject requests
  * Manage users and inventory
  * Monitor system activity

### Core Functionalities

* Real-time donor-recipient matching
* Blood inventory tracking
* Dynamic waitlist and prioritization system
* Donation and request history logs
* Event management for blood drives
* Leaderboard for donor recognition
* Statistical reporting and analytics

### Security and Privacy

* Password hashing using secure algorithms
* Role-based access control
* Input validation for data integrity
* Anonymization of sensitive data
* Activity logging for traceability 

---

## Tech Stack

Frontend:

* HTML
* CSS
* JavaScript

Backend:

* PHP (or Python alternative)

Database:

* MySQL (via phpMyAdmin)

Environment:

* XAMPP (Apache, MySQL, PHP)

---

## Database Structure

Main tables used in the system:

* login – user authentication and roles
* blood_inventory – available blood units
* donate_blood – donation records
* recipient_form – blood requests
* blood_log – transaction history
* events – blood drive events
* event_registration – participant tracking
* admin_appeals – user appeals
* leaderboard – donor statistics 

---

## Installation

1. Clone the repository
   git clone [https://github.com/your-username/pirates-blood-dbms.git](https://github.com/your-username/pirates-blood-dbms.git)
   cd pirates-blood-dbms

2. Setup XAMPP

* Install XAMPP
* Start Apache and MySQL

3. Import database

* Open phpMyAdmin
* Create a new database
* Import the provided .sql file

4. Configure database connection
   Update your config file:
   $host = "localhost";
   $user = "root";
   $password = "";
   $database = "your_db_name";

5. Run the system
   Open a browser and go to:
   [http://localhost/pirates-blood-dbms](http://localhost/pirates-blood-dbms)

---

## System Capabilities

* Real-time updates for inventory and requests
* ACID-compliant transactions using MySQL InnoDB
* Optimized queries through indexing and normalization
* Designed for scalability and future integration 

---

## Limitations

* Local deployment only
* No advanced authentication (e.g., multi-factor authentication)
* Limited support for high concurrent users
* Manual backup required

---

## Future Improvements

* Cloud deployment support
* Integration with hospital systems or APIs
* Multi-factor authentication
* Mobile-friendly interface
* Notification system (SMS or email)
* Advanced analytics dashboard

---

## Developers

* Perez, Clarinze Aundreka
* Abante, Marjinel C.
* Peruda, Zenia Faye B.
* Yahiya, Merhaya A. 

---

## License

This project is developed for academic purposes. Usage and modification are allowed with proper credit.

---

If you want, I can also make a **GitHub-optimized version (with badges and sections like screenshots/demo)** or a **short one-page version for submission**.
