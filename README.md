# TaskFlow — Enterprise Task Manager

A production-ready, full-stack Kanban task management application built with PHP (MVC), MySQL, HTML, CSS, and Vanilla JavaScript.

---

## Features

- **JWT Authentication** — Register, login, token-based sessions
- **Kanban Board** — Drag & drop cards across To Do / In Progress / Review / Done
- **Full CRUD** — Create, read, update, soft-delete, restore tasks and projects
- **Search & Filters** — Real-time search, filter by status and priority
- **Activity Log** — Timestamped history of all task and project actions
- **Dashboard Stats** — Live task counts per status column
- **Priority Badges** — Urgent / High / Medium / Low with color coding
- **Due Dates** — Overdue highlighting
- **Responsive Design** — Mobile-friendly with collapsible sidebar
- **Accessibility** — ARIA roles, labels, keyboard navigation, focus management

---

## Tech Stack

| Layer     | Technology               |
|-----------|--------------------------|
| Frontend  | HTML5, CSS3, Vanilla JS (ES2022) |
| Backend   | PHP 8.1+, MVC architecture |
| Database  | MySQL 8.0+               |
| Auth      | HS256 JWT (no libraries) |

---

## Project Structure

```
Enterprise_Task_Manager_Source/
├── .env.example                   # Environment variable template
├── .gitignore
├── README.md
│
├── backend/
│   ├── .htaccess                  # URL rewriting
│   ├── config/
│   │   ├── bootstrap.php          # App init, autoloader, CORS, error handler
│   │   ├── database.php           # PDO singleton
│   │   └── env.php                # .env file loader
│   ├── core/
│   │   ├── AuthMiddleware.php     # JWT token validation
│   │   ├── BaseController.php     # Shared controller helpers
│   │   ├── JwtHelper.php          # HS256 JWT encode/decode
│   │   ├── Response.php           # JSON response factory
│   │   └── Router.php             # HTTP method + path router
│   ├── controllers/
│   │   ├── AuthController.php     # Register, login, /me
│   │   ├── TaskController.php     # Full task CRUD + status/reorder
│   │   ├── ProjectController.php  # Full project CRUD
│   │   └── ActivityController.php # Activity log endpoints
│   ├── models/
│   │   ├── UserModel.php
│   │   ├── TaskModel.php
│   │   ├── ProjectModel.php
│   │   └── ActivityModel.php
│   └── routes/
│       └── api.php                # Route definitions
│
├── frontend/
│   ├── public/
│   │   └── index.html             # SPA entry point
│   └── src/
│       ├── css/styles.css         # Complete design system
│       └── js/app.js              # Full SPA logic
│
└── docs/
    └── database-schema.sql        # Complete schema with FK + indexes
```

---

## Setup

### 1. Database
```sql
-- Run in MySQL client
source docs/database-schema.sql
```

### 2. Environment
```bash
cp .env.example .env
# Edit .env with your DB credentials and a strong JWT_SECRET
```

### 3. Web Server
Point your web server document root to the project root.
The `backend/.htaccess` routes all API requests through `routes/api.php`.

Configure your virtual host so:
- `http://localhost/api/*` → `backend/routes/api.php`
- `http://localhost/` → `frontend/public/index.html`

### 4. Update API_BASE
In `frontend/src/js/app.js`, update the `API_BASE` constant to match your server URL:
```js
const API_BASE = 'http://localhost/your-path/backend/routes/api.php';
```

---

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/auth/register` | Create account |
| POST | `/auth/login` | Login, get JWT |
| GET | `/auth/me` | Current user |
| GET | `/projects` | List projects |
| POST | `/projects` | Create project |
| PUT | `/projects/:id` | Update project |
| DELETE | `/projects/:id` | Delete project |
| GET | `/projects/:id/tasks` | List tasks (filterable) |
| POST | `/projects/:id/tasks` | Create task |
| GET | `/tasks/:id` | Get task |
| PUT | `/tasks/:id` | Update task |
| PATCH | `/tasks/:id/status` | Update status (drag & drop) |
| DELETE | `/tasks/:id` | Soft delete |
| PATCH | `/tasks/:id/restore` | Restore deleted task |
| GET | `/projects/:id/activity` | Project activity log |
| GET | `/activity/me` | My activity |
| GET | `/health` | Health check |

---

## Security

- Passwords hashed with `password_hash()` (bcrypt, cost 12)
- All SQL uses PDO prepared statements — no raw queries
- JWT signed with HS256, validated on every protected route
- Input validated and sanitized before DB writes
- HTTP status codes on every response
- DB credentials read from environment — never hardcoded
- Error messages never expose stack traces or DB internals in production
