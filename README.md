<p align="center">
  <img src="https://img.icons8.com/color/96/sprint-iteration.png" alt="Mini Jira Logo" width="80"/>
</p>

<h1 align="center">Mini Jira — Backend</h1>

<p align="center">
  A RESTful API powering a simplified Jira clone, built with Symfony 8 and PHP 8.4.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Symfony-8.0-purple?logo=symfony" alt="Symfony"/>
  <img src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white" alt="PHP"/>
  <img src="https://img.shields.io/badge/MySQL-8.0-4479A1?logo=mysql&logoColor=white" alt="MySQL"/>
  <img src="https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white" alt="Docker"/>
</p>

> **This repository contains the backend only.** The frontend lives in [mini-jira-front](../mini-jira-front/).

<!-- Add a screenshot of the API docs or Postman collection here -->
<!-- ![API Screenshot](docs/screenshot.png) -->

---

## About The Project

Mini Jira Backend exposes a complete REST API for project management. It handles user authentication, project & issue tracking, sprint planning, and admin operations. The frontend consumes this API to deliver a full Jira-like experience.

### Built With

| Technology | Role |
|---|---|
| [Symfony 8](https://symfony.com/) | PHP framework |
| [Doctrine ORM](https://www.doctrine-project.org/) | Database abstraction |
| [API Platform](https://api-platform.com/) | API infrastructure |
| [MySQL 8](https://www.mysql.com/) | Database |
| [Lexik JWT](https://github.com/lexik/LexikJWTAuthenticationBundle) | Token-based auth |
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
cp .env .env.local        # then edit DATABASE_URL, MAILER_DSN, etc.
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
| `JWT_PASSPHRASE` | JWT RSA key passphrase |
| `MAILER_DSN` | SMTP server for emails |
| `APP_FRONTEND_URL` | Frontend URL (used in reset emails) |
| `MAILER_FROM` | Sender email address |

---

## Usage

### Authentication
- Login with email & password, receive a JWT token
- Forgot password & reset password via email link

### Projects
- Create, edit, delete projects
- View project details with all associated issues

### Issues
- Create epics, tasks, bugs with parent-child hierarchy
- Update status (To Do → In Progress → Done), assignee, story points
- Fetch backlog (unassigned to active sprint)

### Sprints
- Fetch active sprint with its issues
- List all sprints ordered by date
- Assign/unassign issues to sprints

### Users (Admin)
- Full CRUD on user accounts
- Role management (user / admin)

### Dashboard
- Top 5 most active projects with issue counts
- Current user's assigned tasks grouped by status

---

## Project Structure

```
src/
├── Controller/        # API endpoints (User, Project, Issue, Sprint, Dashboard, PasswordReset)
├── Entity/            # Doctrine entities (User, Project, Issue, Sprint, Comment)
├── Repository/        # Custom database queries (backlog, top projects, assigned tasks)
├── Security/          # Login authenticator (email/password → JWT)
├── EventListener/     # JWT payload enrichment (adds user info to token)
└── Kernel.php

config/
├── packages/          # Symfony bundle configs (security, JWT, doctrine, CORS, mailer)
├── jwt/               # RSA key pair for JWT signing
└── routes/            # Route definitions

migrations/            # Doctrine database migrations
```

---

## Roadmap

- [x] User authentication (login, password reset)
- [x] Project CRUD
- [x] Issue management with hierarchy (epic → task)
- [x] Sprint & backlog management
- [x] Dashboard with project metrics
- [x] Admin user management
- [ ] Statistics & analytics endpoints
- [ ] Full sprint CRUD (create, edit, close sprints)
- [ ] Comment system on issues
- [ ] Activity log / audit trail
- [ ] Internationalization support (i18n)
- [ ] File attachments on issues
- [ ] Export data (CSV, PDF)

---

## Contributing

Contributions are welcome! Here's how:

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
