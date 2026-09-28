# AuthPortal - Full Stack Authentication & Profile Management System

> **Internship Assignment Implementation**  
> Flow: **Register &rarr; Login &rarr; Profile**

---

## 📌 1. Project Requirements & Compliance Checklist

The assignment specifies strict rules where non-compliance leads to rejection. Here is how each requirement has been implemented:

| # | Requirement | Implementation Details | Status |
|---|---|---|:---:|
| 1 | **Code Separation** (HTML, JS, CSS, PHP in separate files) | Zero inline CSS or JS. `*.html` in root, `*.css` in `css/`, `*.js` in `js/`, `*.php` in `php/`. | ✅ Strictly Compliant |
| 2 | **Pure jQuery AJAX** (Strictly no traditional form submission) | All forms intercept `submit` with `e.preventDefault()` and use `$.ajax(...)` exclusively. | ✅ Strictly Compliant |
| 3 | **Bootstrap Responsive Design** | Designed with Bootstrap 5 grid, cards, form-floating, flex utilities, and custom CSS. Fully responsive. | ✅ Strictly Compliant |
| 4 | **Dual Database Storage** | **MySQL** stores registered credentials (`id`, `name`, `email`, `password_hash`).<br>**MongoDB** stores profile details (`age`, `dob`, `contact`, `city`, `address`, `bio`). | ✅ Strictly Compliant |
| 5 | **MySQL Prepared Statements ONLY** | No dynamic string concatenation or plain queries. All queries strictly use `$conn->prepare(...)` and `$stmt->bind_param(...)`. | ✅ Strictly Compliant |
| 6 | **Browser `localStorage` Session** (No PHP `session_start()`) | PHP session is completely omitted. Upon login, backend returns a secure token stored in `localStorage.setItem('auth_token', token)`. | ✅ Strictly Compliant |
| 7 | **Redis Backend Session Store** | Redis stores session keys `session:<token>` with a 24-hour TTL. Validated on every protected request. | ✅ Strictly Compliant |

---

## 📁 2. Mandatory Folder Structure

```
login/
├── assets/
│   └── logo.svg
├── css/
│   └── style.css          # Custom styling & glassmorphism
├── js/
│   ├── index.js           # Redirects logged-in users
│   ├── login.js           # jQuery AJAX login & localStorage session
│   ├── profile.js         # jQuery AJAX profile fetch, edit & logout
│   └── register.js        # jQuery AJAX signup validation & submission
├── php/
│   ├── db.php             # Unified MySQL, Redis & MongoDB configuration
│   ├── login.php          # Login endpoint (MySQL prepared stmt + Redis)
│   ├── profile.php        # Profile endpoint (Redis check + MongoDB CRUD)
│   └── register.php       # Register endpoint (MySQL prepared stmt + Mongo init)
├── index.html             # Landing page
├── login.html             # Login page
├── profile.html           # User profile page
├── register.html          # Registration page
├── schema.sql             # MySQL schema script
└── README.md              # Project documentation & Cloud guide
```

---

## 🌐 3. Cloud Architecture: What To Do Cloud-Wise

When interviewers evaluate your submission, or if you want to deploy on the cloud, here are the recommended cloud options:

### A. Free Cloud Database Services (Recommended)
Instead of running heavy databases locally, you can use generous free cloud tiers. This makes your project runnable from anywhere with zero local installation hassles:

1. **MongoDB Cloud (MongoDB Atlas)**:
   - Go to [MongoDB Atlas](https://www.mongodb.com/atlas) &rarr; Create a free **M0 Sandbox** cluster.
   - Go to **Database Access** &rarr; Create a user (e.g., `dbuser` with password).
   - Go to **Network Access** &rarr; Add IP `0.0.0.0/0` (allow access from anywhere).
   - Click **Connect** &rarr; Choose **Drivers (PHP)** &rarr; Copy the connection string:
     ```
     mongodb+srv://<username>:<password>@cluster0.abcde.mongodb.net/?retryWrites=true&w=majority
     ```
   - In `php/db.php`, update `MONGO_URI` with this connection string.

2. **Redis Cloud (Upstash or Redis.com)**:
   - Go to [Upstash Redis](https://upstash.com/) or [Redis Cloud](https://redis.io/cloud/).
   - Create a free database instance.
   - You will get:
     - **Endpoint (Host)**: e.g., `us1-swift-civet-12345.upstash.io`
     - **Port**: e.g., `6379`
     - **Password**: e.g., `AbCdEf12345...`
   - In `php/db.php`, update `REDIS_HOST`, `REDIS_PORT`, and `REDIS_PASS`.

3. **MySQL Cloud (Aiven / Clever Cloud / Railway)**:
   - Go to [Aiven](https://aiven.io/) or [Clever Cloud](https://www.clever-cloud.com/) or [Railway](https://railway.app/).
   - Spin up a free MySQL instance.
   - Copy Host, Port, User, and Password into `php/db.php` (`MYSQL_HOST`, `MYSQL_USER`, `MYSQL_PASS`, `MYSQL_PORT`).

### B. Cloud Hosting for the Web Application
If the submission expects a live working URL:
- **Render.com / Railway.app**: Create a Web Service connected to your GitHub repository.
- **InfinityFree / 000webhost**: Free PHP/MySQL shared hosting supporting custom PHP scripts and HTML/CSS/JS.

---

## ⚙️ 4. Local Execution & Testing Guide

If you want to run and test locally using **XAMPP**:

1. **Copy Project to XAMPP**:
   Place this `login` folder inside your XAMPP `htdocs` directory:
   ```
   C:\xampp\htdocs\login\
   ```

2. **Start Services**:
   - Open **XAMPP Control Panel** &rarr; Start **Apache** and **MySQL**.
   - Start **Redis** (`C:\Program Files\Redis\redis-server.exe` or Windows Service `Redis`).
   - Start **MongoDB** (run `mongod` or Windows Service `MongoDB`).

3. **Configure Database Credentials**:
   In [php/db.php](file:///e:/login/php/db.php), verify your MySQL user and password (default in XAMPP is user: `'root'` with no password `''`).

4. **Access in Browser**:
   Open:
   ```
   http://localhost/login/index.html
   ```

---

## 🧪 5. Testing the Complete Flow

1. **Register (`register.html`)**:
   - Fill in Full Name, Email, and Password.
   - Notice the button displays a spinner without any page refresh (pure jQuery AJAX).
   - MySQL executes a prepared statement and inserts the user record.
   - MongoDB initializes an empty profile document for this user.
   - User is automatically redirected to `login.html`.

2. **Login (`login.html`)**:
   - Enter registered Email and Password.
   - Backend queries MySQL using prepared statement and verifies bcrypt password hash.
   - Backend generates an authentication token and saves it in **Redis** with 24h TTL.
   - jQuery AJAX receives the token and stores it in **`localStorage.setItem('auth_token', ...)`**.
   - User is redirected to `profile.html`.

3. **Profile (`profile.html`)**:
   - Page verifies `auth_token` in `localStorage`.
   - Sends jQuery AJAX request to `php/profile.php`.
   - Backend validates the token from **Redis**.
   - Loads basic user details from MySQL and profile fields (Age, DOB, Contact, Address, Bio) from **MongoDB**.
   - User edits fields and clicks **"Save to MongoDB"**. Data is updated via jQuery AJAX.
   - Clicking **"Log Out"** clears `localStorage` and deletes the active session from Redis.
