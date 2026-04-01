````md
# Pirate’s Blood DBMS

## Overview

Pirate’s Blood DBMS is a Blood Donation and Request Management System designed to streamline donor-recipient matching, blood inventory tracking, and event coordination.

The system allows users to log in as donors, recipients, or administrators. It provides real-time matching, request handling, and inventory management, making it suitable for localized deployment such as hospitals, academic institutions, or community-based blood banks. :contentReference[oaicite:0]{index=0}

---

## Features

### User Roles
- Donor
  - Register and manage profile
  - Submit donation availability
- Recipient
  - Request blood with urgency level
  - Track request status
- Administrator
  - Approve or reject requests
  - Manage users and inventory
  - Monitor system activity

### Core Functionalities
- Real-time donor-recipient matching
- Blood inventory tracking
- Dynamic waitlist and prioritization system
- Donation and request history logs
- Event management for blood drives
- Leaderboard for donor recognition
- Statistical reporting and analytics

### Security and Privacy
- Password hashing using secure algorithms
- Role-based access control
- Input validation for data integrity
- Anonymized display of sensitive information
- Activity logging for traceability :contentReference[oaicite:1]{index=1}

---

## Tech Stack

Frontend:
- HTML
- CSS
- JavaScript

Backend:
- PHP (or Python alternative)

Database:
- MySQL (via phpMyAdmin)

Environment:
- XAMPP (Apache, MySQL, PHP)

---

## Database Structure

Key tables in the system:

- login – user authentication and roles  
- blood_inventory – available blood units  
- donate_blood – donation records  
- recipient_form – blood requests  
- blood_log – transaction history  
- events – blood drive events  
- event_registration – participant tracking  
- admin_appeals – user appeals  
- leaderboard – donor statistics :contentReference[oaicite:2]{index=2}  

---

## Installation

1. Clone the repository
```bash
git clone https://github.com/your-username/pirates-blood-dbms.git
cd pirates-blood-dbms
````

2. Setup XAMPP

* Install XAMPP
* Start Apache and MySQL

3. Import the database

* Open phpMyAdmin
* Create a new database
* Import the provided .sql file

4. Configure database connection

```php
$host = "localhost";
$user = "root";
$password = "";
$database = "your_db_name";
```

5. Run the application
   Open in browser:

```
http://localhost/pirates-blood-dbms
```

---

## System Capabilities

* Real-time updates for inventory and requests
* ACID-compliant transactions using MySQL InnoDB
* Optimized queries through indexing and normalization
* Modular design for future scalability and integration 

---

## Limitations

* Local deployment only
* No multi-factor authentication
* Limited scalability for high concurrent users
* Manual backup required

---

## Future Improvements

* Cloud deployment
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

## Acknowledgments

* Lyceum of the Philippines University – Cavite
* Department of Computer Studies (DCS)
* Research references on blood bank systems and healthcare DBMS 

```
```
