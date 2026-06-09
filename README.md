<p align="center">
  <img src="https://img.icons8.com/color/96/sprint-iteration.png" alt="Mini Jira Logo" width="80"/>
</p>

<h1 align="center">Mini Jira — Backend</h1>

<p align="center">
  A REST API for a simplified Jira-like project management tool, built with Symfony 8 and PHP 8.4.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Symfony-8.0-purple?logo=symfony" alt="Symfony"/>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white" alt="MySQL"/>
  <img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker"/>
</p>

> **This repository contains the backend only.** The frontend lives in [mini-jira-front](../mini-jira-front/).

---

## About The Project

Mini Jira is a simplified Jira clone that lets teams manage their projects, backlogs, sprints and tasks, with burnup/burndown charts and velocity tracking.

The backend exposes a JSON REST API consumed by the frontend. It handles JWT authentication, role-based access control (admin/user), teams, projects, issues with an epic → task hierarchy, full sprint lifecycle, comments, subtasks, notifications, and statistics.

### Built With

| Technology | Role |
|---|---|
| [Symfony 8](https://symfony.com/) | PHP framework |
| [Doctrine ORM](https://www.doctrine-project.org/) | Database abstraction |
| [MySQL 8](https://www.mysql.com/) | Database |
| [Lexik JWT](https://github.com/lexik/LexikJWTAuthenticationBundle) | RSA-signed token auth |
| [Nelmio CORS](https://github.com/nelmio/NelmioCorsBundle) | Cross-origin requests |
| [FrankenPHP](https://frankenphp.dev/) | Application server (Docker) |

---

## Getting Started

### Prerequisites

- **Docker** & **Docker Compose** (v2.10+)
- Or: **PHP** >= 8.4, **Composer**, **MySQL** 8.0, **Symfony CLI**

### Installation

**With Docker (recommended):**

```bash
git clone <repo-url>
cd jira-mini-back
docker compose build --pull --no-cache
docker compose up --wait
```

The API is available at `https://localhost`.

**Without Docker:**

```bash
git clone <repo-url>
cd jira-mini-back
composer install
cp .env .env.local        # then fill in DATABASE_URL, JWT_PASSPHRASE, etc.
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
symfony server:start
```

### Environment Variables

| Variable | Description |
|---|---|
| `DATABASE_URL` | MySQL connection string |
| `APP_SECRET` | Symfony secret key |
| `JWT_PASSPHRASE` | RSA key passphrase for JWT signing |
| `MAILER_DSN` | SMTP server for emails |
| `MAILER_FROM` | Sender email address |
| `APP_FRONTEND_URL` | Frontend URL (used in password reset emails) |
| `CORS_ALLOW_ORIGIN` | Regex of allowed origins |

---

## Features

### Authentication & Security
- Email/password login → RSA-signed JWT (payload includes id, firstName, lastName)
- Refresh token via custom `POST /auth/refresh` endpoint
- Password reset by email with expiring token
- Symfony firewall covering all `/api/*` routes
- Roles: `ROLE_ADMIN` (full access) / `ROLE_USER` (own teams only)

### Projects
- Full CRUD
- Project detail with all associated issues and their hierarchy

### Issues (Epics & Tasks)
- Types: `epic`, `story`, `task`, `bug`
- Parent-child hierarchy: epic → tasks
- Statuses: `todo` → `in_progress` → `done`
- Fields: story points, urgency (`low` / `medium` / `high` / `critical`), deadline
- Status change history (`IssueStatusHistory`)
- Backlog: all tasks not assigned to an active sprint

### Sprints
- Full lifecycle: `planned` → `active` → `completed`
- Only one active sprint per team (enforced server-side)
- Sprint close: move unfinished tasks to the next planned sprint or the backlog
- Add/remove issues on a sprint
- Full CRUD (create, update, delete)

### Teams
- Full CRUD
- Member management (add/remove)
- Each sprint belongs to a team

### Comments & Subtasks
- Comments on issues (CRUD)
- Subtasks (checklist) with ordering and `isDone` state

### Notifications
- Event-driven creation (assignment, comment, sprint started/completed…)
- List notifications for the current user
- Mark individual or all as read

### Statistics
- Burnup/burndown per sprint
- Team velocity across past sprints

### Dashboard
- Top 5 most active projects
- Current user's assigned tasks grouped by status

### Administration (ROLE_ADMIN)
- Full CRUD on user accounts
- Role management (user / admin)
- Account activation/deactivation

---

## Project Structure

```
src/
├── Controller/        # API endpoints (Auth, User, Project, Issue, Sprint, Team,
│                      #   Comment, SubTask, Notification, Stats, Dashboard, PasswordReset)
├── Entity/            # Doctrine entities (User, Project, Issue, Sprint, Team,
│                      #   Comment, SubTask, Notification, RefreshToken, IssueStatusHistory)
├── Repository/        # Custom Doctrine queries
├── Service/           # Business logic (StatsService, RefreshTokenService)
├── Security/          # Email/password authenticator → JWT
└── EventListener/     # JWT payload enrichment

config/
├── packages/          # Symfony bundle configuration
├── jwt/               # RSA key pair for JWT signing
└── routes/            # Route definitions

migrations/            # Doctrine migrations (one per logical change)
```

---

## Roadmap

- [x] JWT authentication + refresh token
- [x] Password reset by email
- [x] Project CRUD
- [x] Issues with epic → task hierarchy, urgency and deadline
- [x] Issue status history
- [x] Full sprint lifecycle (planned / active / completed)
- [x] Sprint close with task redistribution
- [x] Team CRUD + member management
- [x] Comments on issues
- [x] Subtasks (checklist)
- [x] In-app notifications
- [x] Burnup/burndown and velocity statistics
- [x] Admin user management
- [ ] File attachments on issues (Document entity + upload)
- [ ] Data export (CSV, PDF)
- [ ] Internationalization (i18n)

---

## Contributing

Contributions are welcome!

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## Top Contributors

<a href="https://github.com/thibaultfradet">
  <img src="https://github.com/thibaultfradet.png" width="50" style="border-radius:50%" alt="thibaultfradet"/>
</a>
