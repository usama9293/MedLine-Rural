# MedLine Rural

## 1. Project Overview

MedLine Rural is a web-based medical supply and delivery management system designed for rural healthcare clinics. The system supports clinic operations where shortages, urgent replenishment needs, and cold-chain delivery risks need to be managed in a controlled way across multiple user roles.

The completed project implements the following operational areas:

- Clinic inventory
- Supply requests
- Triage and prioritisation
- Shipments
- Vehicles
- Drivers
- Telemetry
- Cold-chain monitoring
- Alerts and notifications
- Role-based dashboards
- Authentication and authorization

## 2. Key Features

The implemented system includes the following core features:

- Authentication for Admin, Dispatcher, Driver, and Clinic Staff users
- Role-based access control using PHP session checks and `requireRole()`
- Clinic inventory management and stock visibility
- Inventory usage recording for clinic staff
- Supply request creation and request tracking
- Triage and urgency scoring for requests
- Vehicle management and status tracking
- Driver management and assignment checks
- Shipment creation, assignment, status updates, and delivery flow
- Shipment status workflow: `Pending` → `Dispatched` → `In Transit` → `Delivered`
- Telemetry submission with vehicle and shipment validation
- Temperature monitoring and cold-chain validation
- Telemetry alert generation and notification creation
- Notifications for Admin and Dispatcher users
- Dashboard summaries and operational alerts
- Automatic inventory replenishment when delivered shipments complete a request

## 3. Technology Stack

The implemented technology stack in this project is:

- PHP
- MySQL / MariaDB compatible database
- PDO for database access
- JavaScript
- HTML and CSS
- Bootstrap 5
- REST-style PHP API endpoints
- Apache via XAMPP local hosting
- phpMyAdmin for database administration

The project is designed for local development using XAMPP and is also prepared for a production database connection in the PHP config file.

## 4. System Architecture

The application follows a simple layered architecture:

```text
Users
↓
Role-Based Frontend
↓
PHP REST-style APIs
↓
Authentication / Validation / Business Logic
↓
MySQL Database
```

Key implementation details:

- Frontend pages are located under `admin/`, `clinic/`, `dispatcher/`, `driver/`, and `frontend/`.
- Access to protected pages is enforced through `config/auth.php`.
- API logic is grouped under `api/` and uses PHP controllers for each business module.
- Database access is handled through `config/db.php` using PDO.
- Input validation is handled in `config/validation.php` and per-module checks.

Telemetry flow implemented in the project:

```text
Driver / Dispatcher / Admin
↓
/api/telemetry/create.php
↓
Validation (vehicle, shipment, temperature, GPS)
↓
MySQL telemetry_events table
↓
Temperature threshold check (2°C to 8°C safe range)
↓
telemetry_alerts + notifications
```

## 5. User Roles

| Role         | Main Responsibilities                                                                            |
| ------------ | ------------------------------------------------------------------------------------------------ |
| Admin        | Manages users, clinics, vehicles, drivers, requests, inventory, shipments, and system alerts.    |
| Dispatcher   | Reviews and prioritises requests, manages shipments, validates assignments, and monitors alerts. |
| Driver       | Updates shipment status and submits telemetry for assigned shipments.                            |
| Clinic Staff | Creates supply requests, records inventory usage and views clinic inventory.                     |

These access patterns are defined by `requireRole(...)` checks across the PHP files in the project.

## 6. Project Structure

The project contains the following main directories and files:

- `admin/` – Admin dashboard and admin management screens for users, clinics, vehicles, drivers, shipments, inventory, notifications, and requests.
- `api/` – Backend PHP API modules organised by domain, including authentication, clinics, drivers, inventory, requests, shipments, telemetry, triage, users, vehicles, notifications, dashboard, and roles.
- `clinic/` – Clinic Staff dashboard and request pages.
- `dispatcher/` – Dispatcher dashboard, request queues, shipments, and notifications.
- `driver/` – Driver dashboard and shipment tracking screens.
- `frontend/` – Login page, shared assets, and test pages for request/shipment workflows.
- `config/` – Application configuration and security helpers:
  - `db.php` – PDO database connection logic
  - `auth.php` – session management and role checks
  - `validation.php` – JSON input validation helper
  - `response.php` – standard JSON response structure
  - `clinic_management.php` and `user_management.php` – domain validation helpers
- `database/` – SQL database schema and seed data:
  - `schema.sql` – database tables
  - `seed.sql` – initial role values; no accounts are seeded
- `assets/` – Shared project assets.
- `index.html` – root landing page in the repository.

Important entry points:

- `frontend/login.php` – application login page
- `api/auth/login.php` – login processing endpoint
- `api/auth/logout.php` – logout endpoint
- `admin/dashboard.php`, `dispatcher/dashboard.php`, `driver/dashboard.php`, `clinic/dashboard.php` – role-based landing pages

## 7. Prerequisites

To run the project locally, the following is required:

- XAMPP installed with Apache and MySQL enabled
- PHP 8.0 or newer recommended
- MySQL / MariaDB service running
- A browser such as Chrome, Edge, or Firefox
- phpMyAdmin for database creation and import

The codebase uses PHP 8-compatible syntax, including the `match` expression in `frontend/components/auth-layout.php`, so a PHP 8 runtime is appropriate for local development.

## 8. Local Installation / Setup

### Step 1 — Install XAMPP

Install XAMPP and ensure the following services are available:

- Apache
- MySQL

### Step 2 — Place the project

Place the project in the XAMPP web directory:

```text
C:\xampp\htdocs\medline-rural
```

The application is expected to run from the project folder name `medline-rural`.

### Step 3 — Start services

Start these services in XAMPP:

- Apache → Start
- MySQL → Start

### Step 4 — Create the database

Open phpMyAdmin in the browser, for example:

```text
http://localhost/phpmyadmin
```

Create a new database named:

```text
medline_rural
```

### Step 5 — Import database schema

Import the SQL schema file:

```text
database/schema.sql
```

This creates the tables required by the project.

### Step 6 — Seed initial data

Import the seed file:

```text
database/seed.sql
```

This file creates the application roles only; it does not create an administrator account.

### Step 7 — Configure the database

Copy `config/db.example.php` to `config/db.local.php` and edit the local copy with the connection settings for your MySQL database. The local file is excluded from Git. Alternatively, configure the `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD` environment variables; `DB_PORT` is optional and defaults to `3306`. Environment variables take precedence over values in the local file.

### Step 8 — Create your local administrator

Choose an administrator password locally and generate a password hash using the PHP executable included with XAMPP. In PowerShell, run:

```powershell
& 'C:\xampp\php\php.exe' -r '$password = readline("Choose an administrator password: "); echo password_hash($password, PASSWORD_DEFAULT), PHP_EOL;'
```

In phpMyAdmin, run the following query after replacing each placeholder with your own name, email address, and locally generated hash:

```sql
INSERT INTO users (role_id, clinic_id, full_name, email, password_hash, status)
SELECT role_id, NULL, 'YOUR_NAME', 'YOUR_EMAIL', 'YOUR_LOCALLY_GENERATED_HASH', 'Active'
FROM roles
WHERE role_name = 'Admin';
```

Keep your chosen password and hash out of the repository. The login endpoint verifies passwords using PHP's password verification function.

## 9. Configuration

Database credentials must be supplied through environment variables or a local `config/db.local.php` file copied from `config/db.example.php`. Each developer must create their own local configuration for their database; the repository contains no production credentials. No session cookies or tokens are included.

## 10. Running the Application

After the database is created and imported, open the login page in a browser:

```text
http://localhost/medline-rural/frontend/login.php
```

The login page is the primary entry point for the application. The login form submits to:

```text
/api/auth/login.php
```

After successful login, the application redirects the user to the appropriate dashboard based on the role:

- Clinic Staff → `clinic/dashboard.php`
- Dispatcher → `dispatcher/dashboard.php`
- Driver → `driver/dashboard.php`
- Admin → `admin/dashboard.php`

## 11. Database Setup

The database schema in `database/schema.sql` contains the following major tables:

- `roles`
- `clinics`
- `users`
- `items`
- `inventory`
- `supply_requests`
- `request_items`
- `vehicles`
- `drivers`
- `shipments`
- `triage_scores`
- `telemetry_events`
- `notifications`
- `audit_logs`
- `telemetry_alerts`

These tables support the implemented workflow for role-based access, request creation, triage, shipping, vehicle assignment, telemetry capture, alerting, and notifications.

## 12. Testing Instructions

This section documents the major testing areas that are supported by the project.

### Functional Testing

1. Login
   - Open `http://localhost/medline-rural/frontend/login.php`.
   - Sign in with a valid seeded or created account.
   - Confirm the user is redirected to the correct dashboard.

2. Inventory
   - Log in as Clinic Staff.
   - Open the clinic inventory view.
   - Check item stock levels and days-remaining calculations.
   - Record stock usage through the inventory usage API.

3. Supply request
   - Log in as Clinic Staff.
   - Submit a supply request via the request creation workflow.
   - Confirm the request is saved with `Pending` status.

4. Triage
   - Log in as Dispatcher or Admin.
   - Review the queue and verify urgency scoring is applied.
   - Update request status to `Approved` or `Cancelled` only if the current status allows the transition.

5. Shipment creation
   - Log in as Dispatcher or Admin.
   - Create a shipment from an approved request.
   - Confirm the shipment is associated with a valid vehicle and driver.

6. Vehicle and driver assignment
   - Validate vehicles exist and have valid statuses.
   - Validate drivers are assigned to real user accounts.
   - Confirm duplicate or invalid assignments fail validation.

7. Dispatch
   - Update a shipment status to `Dispatched`.
   - Confirm the timestamp and status transition rules are respected.

8. Driver status update
   - Driver role can update only shipments assigned to that driver.
   - Attempting to update unassigned shipments should fail with an authorization response.

9. Telemetry
   - Submit telemetry using the telemetry API with a valid `vehicle_id` and `shipment_id`.
   - Confirm the event is inserted into `telemetry_events`.

10. Delivery

- Update a shipment status to `Delivered`.
- Confirm the status transition is valid and the supply request is completed.

11. Inventory replenishment

- When a shipment is delivered and the request is completed, the ordered items are added back to the clinic inventory.
- Confirm the quantity is increased in `inventory` after delivery.

### Role-Based Access Control Testing

The project uses explicit role checks in the PHP files.

- Admin access: `requireRole('Admin')` or additional allowed roles.
- Dispatcher access: `requireRole('Dispatcher', 'Admin')` on operational APIs.
- Driver access: `requireRole('Driver', 'Dispatcher', 'Admin')` for telemetry and shipment interactions.
- Clinic Staff access: `requireRole('Clinic Staff')` for request and inventory APIs.
- Unauthenticated access: `requireLogin()` returns HTTP 401.
- Unauthorized access: `requireRole(...)` returns HTTP 403.

### Security Testing

The code includes the following implementation checks that can be validated by testing:

- Prepared SQL statements through PDO queries
- Input validation through `filter_var()` and JSON validation helpers
- Session-based authentication through `config/auth.php`
- `session_regenerate_id(true)` on login
- `HttpOnly` and `SameSite=Lax` session cookies
- Role enforcement before protected functionality
- Clinic-scoped authorization for Clinic Staff data access
- Input rejection for invalid JSON, invalid IDs, invalid temperatures, invalid dates, and bad quantities

### Telemetry / Cold-Chain Testing

The safe temperature range implemented in the code is:

- Minimum: `2.00°C`
- Maximum: `8.00°C`

Safe telemetry test flow:

- Submit a telemetry event with `temperature = 5.0`.
- Expected result: telemetry is stored without alert generation.

Unsafe telemetry test flow:

- Submit a telemetry event with `temperature = 1.0` or `temperature = 9.0`.
- Expected result: an alert is created in `telemetry_alerts` and notifications are created for Admin and Dispatcher users.

### End-to-End Testing

The implemented end-to-end flow supported by the codebase is:

```text
Inventory
→ Supply Request
→ Triage
→ Approval
→ Shipment
→ Vehicle + Driver
→ Dispatch
→ In Transit
→ Telemetry
→ Alert
→ Delivery
→ Inventory Replenishment
```

This flow is reflected in the request, shipment, telemetry, and inventory APIs.

## 13. API Overview

The backend exposes REST-style PHP APIs under `api/`.

| Module         | Endpoint                                 | Method | Purpose                            |
| -------------- | ---------------------------------------- | ------ | ---------------------------------- |
| Authentication | `/api/auth/login.php`                    | POST   | Login user and create session      |
| Authentication | `/api/auth/logout.php`                   | GET    | Destroy session                    |
| Users          | `/api/users/list.php`                    | GET    | List users                         |
| Users          | `/api/users/create.php`                  | POST   | Create user                        |
| Users          | `/api/users/details.php`                 | GET    | View user details                  |
| Users          | `/api/users/update.php`                  | POST   | Update user details                |
| Users          | `/api/users/status.php`                  | POST   | Toggle user status                 |
| Users          | `/api/users/reset-password.php`          | POST   | Reset password                     |
| Clinics        | `/api/clinics/list.php`                  | GET    | List clinics                       |
| Clinics        | `/api/clinics/create.php`                | POST   | Create clinic                      |
| Clinics        | `/api/clinics/details.php`               | GET    | View clinic details                |
| Clinics        | `/api/clinics/update.php`                | POST   | Update clinic                      |
| Clinics        | `/api/clinics/status.php`                | POST   | Update clinic status               |
| Inventory      | `/api/inventory/get.php`                 | GET    | View clinic inventory              |
| Inventory      | `/api/inventory/add_stock.php`           | POST   | Add stock to inventory             |
| Inventory      | `/api/inventory/record_usage.php`        | POST   | Record stock consumption           |
| Inventory      | `/api/inventory/stock_risk.php`          | GET    | View stock risk summary            |
| Requests       | `/api/requests/create.php`               | POST   | Create supply request              |
| Requests       | `/api/requests/get.php`                  | GET    | View requests                      |
| Requests       | `/api/requests/details.php`              | GET    | View request details               |
| Requests       | `/api/requests/update_status.php`        | POST   | Update request status              |
| Triage         | `/api/triage/calculate.php`              | POST   | Calculate urgency                  |
| Triage         | `/api/triage/approve.php`                | POST   | Approve request                    |
| Triage         | `/api/triage/process.php`                | POST   | Process triage                     |
| Triage         | `/api/triage/queue.php`                  | GET    | View triage queue                  |
| Vehicles       | `/api/vehicles/list.php`                 | GET    | List vehicles                      |
| Vehicles       | `/api/vehicles/create.php`               | POST   | Create vehicle                     |
| Vehicles       | `/api/vehicles/details.php`              | GET    | View vehicle details               |
| Vehicles       | `/api/vehicles/update-status.php`        | POST   | Update vehicle status              |
| Drivers        | `/api/drivers/list.php`                  | GET    | List drivers                       |
| Drivers        | `/api/drivers/create.php`                | POST   | Create driver                      |
| Drivers        | `/api/drivers/details.php`               | GET    | View driver details                |
| Drivers        | `/api/drivers/status.php`                | POST   | Update driver status               |
| Drivers        | `/api/drivers/available-users.php`       | GET    | View available driver users        |
| Shipments      | `/api/shipments/list.php`                | GET    | List shipments                     |
| Shipments      | `/api/shipments/create.php`              | POST   | Create shipment                    |
| Shipments      | `/api/shipments/get.php`                 | GET    | View shipment                      |
| Shipments      | `/api/shipments/get_tracking.php`        | GET    | View shipment tracking             |
| Shipments      | `/api/shipments/dispatch.php`            | POST   | Dispatch shipment                  |
| Shipments      | `/api/shipments/update_status.php`       | POST   | Update shipment status             |
| Shipments      | `/api/shipments/validate-assignment.php` | POST   | Validate vehicle/driver assignment |
| Telemetry      | `/api/telemetry/create.php`              | POST   | Record telemetry                   |
| Telemetry      | `/api/telemetry/get.php`                 | GET    | Retrieve telemetry                 |
| Telemetry      | `/api/telemetry/check_alert.php`         | GET    | Check open alert                   |
| Telemetry      | `/api/telemetry/get_alerts.php`          | GET    | View alerts                        |
| Telemetry      | `/api/telemetry/update_alert_status.php` | POST   | Resolve or update alert status     |
| Notifications  | `/api/notifications/get.php`             | GET    | View notifications                 |
| Notifications  | `/api/notifications/create.php`          | POST   | Create notifications               |
| Notifications  | `/api/notifications/mark-read.php`       | POST   | Mark notification as read          |
| Dashboards     | `/api/dashboard/summary.php`             | GET    | Dashboard summary                  |
| Dashboards     | `/api/dashboard/alerts.php`              | GET    | Dashboard alerts                   |
| Dashboards     | `/api/dashboard/telemetry.php`           | GET    | Dashboard telemetry summary        |
| Roles          | `/api/roles/list.php`                    | GET    | List application roles             |

## 14. Authentication and Security

The implemented security model is as follows:

- Session-based authentication is used in `config/auth.php`.
- `requireLogin()` returns HTTP 401 for unauthenticated users.
- `requireRole()` checks the user role and returns HTTP 403 for unauthorized users.
- `session_regenerate_id(true)` is called immediately after login.
- Session cookies are configured with `httponly` and `samesite=Lax`.
- Cookies are marked secure only when the request is HTTPS.
- `config/validation.php` verifies that request bodies exist and are valid JSON.
- Prepared SQL statements are used for database queries to reduce SQL injection risk.
- Role-specific validation is enforced on the API side and in the session.
- The code also avoids using a client-supplied clinic ID for Clinic Staff actions when the session already contains a clinic association.

This is a server-side authorization model and is consistent with the project’s backend architecture.

## 15. Telemetry and Cold-Chain Monitoring

Telemetry is implemented as an API-based workflow rather than physical IoT hardware integration. The system accepts telemetry payloads through `/api/telemetry/create.php`.

Confirmed telemetry fields in the implementation:

- `vehicle_id`
- `shipment_id`
- `event_type`
- `temperature`
- `latitude`
- `longitude`
- `recorded_at`

Telemetry validation includes:

- numeric temperature values only
- latitude validation between `-90` and `90`
- longitude validation between `-180` and `180`
- shipment validation for assigned vehicles
- driver authorization checks for assigned shipments
- safe range validation for temperature alerts

The safe range enforced by the current implementation is:

- Lower bound: `2.00°C`
- Upper bound: `8.00°C`

When the temperature is outside this range, the system:

1. inserts a row into `telemetry_events`
2. creates a row in `telemetry_alerts`
3. generates a notification for Admin and Dispatcher users

## 16. Deployment

This repository is structured for local deployment in XAMPP and uses PHP and MySQL with Apache. The source code includes a production-style database configuration in `config/db.php`, but the repository does not contain a complete production deployment guide or public deployment URL. If a production environment is used, the credentials and database configuration must be sanitized before submission.

## 17. Troubleshooting

Common setup issues and fixes:

- Apache does not start: check whether another service is using ports 80 or 443 and confirm XAMPP Apache is running.
- MySQL does not start: verify the MySQL service is enabled and that no conflicting local MySQL installation is using the same ports.
- Database connection fails: check the DB host, database name, username, and password in `config/db.php`.
- Unknown database: confirm that the database was created as `medline_rural` and that `database/schema.sql` was imported successfully.
- API requests fail: confirm the project is served from the correct folder and the session is valid.
- Login fails: confirm the database seed was imported and the seeded user is active.
- Incorrect project URL: use `http://localhost/medline-rural/frontend/login.php` for local access.
- PHP errors: confirm PHP 8 compatibility and that XAMPP is using the correct PHP runtime.

## 18. Security / Submission Requirements

### Security / Submission Requirements

The source-code ZIP submitted for ICT907 Assessment 3 must NOT contain:

- Passwords
- API keys
- Access tokens
- Private credentials
- Database passwords
- Session secrets
- Confidential data
- Private configuration files containing secrets

Before submission, review the complete ZIP and remove any local or production secrets. The most important file to sanitize is:

- `config/db.php`

The project also contains seeded credentials in:

- `database/seed.sql`

These should be reviewed and replaced or removed when preparing a submission package.

## 19. Testing Checklist

Use the following checklist when validating the project locally:

- [ ] Apache running
- [ ] MySQL running
- [ ] Database created
- [ ] Schema imported
- [ ] Seed data imported if applicable
- [ ] Database configuration completed
- [ ] Login works
- [ ] Admin access tested
- [ ] Dispatcher access tested
- [ ] Driver access tested
- [ ] Clinic Staff access tested
- [ ] Inventory tested
- [ ] Supply request tested
- [ ] Triage tested
- [ ] Shipment tested
- [ ] Telemetry tested
- [ ] Alert tested
- [ ] Delivery tested
- [ ] Inventory replenishment tested

## 20. Project Status

This README documents the completed implementation of the MedLine Rural project as submitted for ICT907 Assessment 3.

The project implements a functional PHP/MySQL supply-management and cold-chain monitoring workflow with role-based access, request processing, shipment management, and telemetry alerting.

Known limitation:

- Telemetry is simulated through the API layer and is not connected to physical IoT hardware.
- Seeded development credentials should not be treated as production credentials.
- Any hardcoded production connection values must be sanitized before zip submission.

## 21. Authors / Group

- Akash Divleen
- Wajahat Ali Khan
- Virender
- Khatija
- Akash
- Sangam
