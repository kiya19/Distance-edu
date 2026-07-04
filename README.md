# Online Distance Education Management System Prototype

This is a plain PHP/MySQL WAMP prototype for the graduation project report. It implements role-based workflows for New Abyssinia College's Distance Education Management System.

## Requirements

- WAMP Server with Apache, MySQL, and PHP 7.4 or newer
- PHP PDO MySQL extension enabled
- A browser such as Chrome, Firefox, or Edge

## Installation on WAMP

1. Copy the `prototype` folder into your WAMP web root and rename it to `dems`.
   Example: `C:\wamp64\www\dems`
2. Open phpMyAdmin.
3. Import `database/dems.sql`.
4. Check `config/database.php`.
   The default values are:
   - database: `dems`
   - username: `root`
   - password: empty
   - base URL: `/dems/public`
5. Start Apache and MySQL.
6. Open `http://localhost/dems/public/`.

## Demo Accounts

Every seeded account uses the password `demo123`.

| Role | Username |
|---|---|
| Administrator | `admin` |
| Student | `student` |
| Instructor | `instructor` |
| CDE Officer | `cde` |
| Registrar Officer | `registrar` |
| Finance Staff | `finance` |
| Department Head | `depthead` |
| Academic Vice President | `avp` |
| College Dean | `dean` |

The seed password values are stored as legacy SHA-256 hashes only so the SQL file can be imported without running PHP. On first successful login, the application automatically upgrades the user's password to PHP `password_hash()` format.

## Implemented Workflows

- Session login and logout
- Role-based dashboard links
- Administrator user account creation and activation/deactivation
- Course registration and instructor assignment
- Module upload, listing, and download
- Assignment publishing, student submission, and submission download
- Grade recording and department-head approval
- Announcements and news posting
- Finance payment control and student payment view
- Academic schedules and reporting summaries
- Student feedback and staff response tracking
- Profile update and password change

## Folder Structure

| Folder | Purpose |
|---|---|
| `app/` | Shared PHP bootstrap, database connection, helpers, authorization, upload utilities, layout |
| `config/` | Database and base URL configuration |
| `database/` | MySQL schema and seeded demo data |
| `public/` | Browser-facing PHP pages and CSS |
| `uploads/` | Demo and uploaded files for modules, assignments, and submissions |

## Presentation Checklist

- Import the database and confirm all demo users can log in.
- Log in as `admin` and show user/course management.
- Log in as `instructor` and show module upload, assignment publishing, submissions, and grade entry.
- Log in as `student` and show module download, assignment submission, grade view, payment status, announcements, and feedback.
- Log in as `finance` and show payment control.
- Log in as `depthead` and show grade approval.
- Log in as `registrar`, `avp`, or `dean` and show reports and schedules.
