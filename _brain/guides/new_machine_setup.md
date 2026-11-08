# NEW MACHINE SETUP GUIDE (GENERAL)

> Use this guide whenever this repository is cloned on a new machine (work PC, laptop, server, or fresh environment).
> This ensures the project + AI system can resume without context loss.

---

# 0. PURPOSE

This guide ensures:
- consistent environment setup across machines
- correct dependency installation
- safe continuation of AI-assisted development
- proper integration with `_brain/` system

This applies to:
- new developers
- CI/CD machines
- AI-assisted environments
- project migration

---

# 1. INSTALL CORE DEPENDENCIES (ONE-TIME SETUP)

Install based on project stack (ONLY what applies):

---

## A. Git (Required)

git --version

Install if missing:
https://git-scm.com/downloads

---

## B. Runtime Environment

### Node.js (if project uses JS/TS)

node -v
npm -v

Install:
https://nodejs.org (LTS version)

---

### Python (if project uses Python)

python --version
pip --version

Install:
https://python.org

---

### PHP (if Laravel or PHP project)

php -v

Install:
https://www.php.net/downloads

---

## C. Package Managers (as needed)

- Composer (PHP)
  https://getcomposer.org

- pip (Python default)

- npm / yarn / pnpm (Node ecosystems)

---

## D. Database (if required)

Install based on project:
- MySQL / MariaDB
- PostgreSQL
- SQLite (no install needed for most cases)

Ensure DB service is running before continuing.

---

# 2. CLONE THE REPOSITORY

git clone <REPO_URL>
cd <PROJECT_FOLDER>

---

# 3. INSTALL PROJECT DEPENDENCIES

Run only what applies:

---

## Node projects

npm install

---

## Python projects

pip install -r requirements.txt

---

## PHP projects

composer install

---

# 4. ENVIRONMENT CONFIGURATION

cp .env.example .env

Then configure based on project type:

- database credentials
- API keys
- service endpoints
- environment mode (dev/prod)

---

## Common Step: Generate Keys (if required)

### PHP (Laravel)

php artisan key:generate

### Node (some frameworks)

Generate .env secrets manually if required

---

# 5. DATABASE SETUP (IF APPLICABLE)

## Create database manually or via tool:
- MySQL Workbench
- phpMyAdmin
- CLI

Then run migrations:

### Laravel

php artisan migrate

### Node ORM (Prisma/Sequelize/TypeORM)

npx prisma migrate dev

### Python (Django)

python manage.py migrate

---

# 6. START DEVELOPMENT SERVER

Run based on stack:

---

## Backend

npm run dev
# or
php artisan serve
# or
python manage.py runserver

---

## Frontend (if separate)

npm run dev

---

# 7. VERIFY SYSTEM STATUS

Check:
- server starts without errors
- database connection works
- environment variables loaded
- frontend builds correctly (if applicable)

---

# 8. RESUME AI DEVELOPMENT (AI SYSTEM INTEGRATION)

Open the project root in a supported coding agent. Its provider adapter reads `AI_BRAIN.md` and selects minimal task context automatically. For browser chat, paste `_brain/CONTINUE_PROMPT.md`, then state the task.

---

# DAILY WORKFLOW (AFTER SETUP)

git pull origin main

Then install updates if needed:

## Node
npm install

## PHP
composer install

## Python
pip install -r requirements.txt

Run migrations if updated:

# PHP
php artisan migrate

# Python
python manage.py migrate

Start services:

# backend
<start command>

# frontend
npm run dev

---

# 9. PUSHING CHANGES

git add .
git commit -m "update"
git push origin main

---

# TROUBLESHOOTING

| Issue | Fix |
|------|-----|
| command not found | restart terminal / check PATH |
| dependency errors | delete node_modules / reinstall |
| DB connection error | ensure DB service is running |
| migration fails | verify .env config |
| port already in use | change port or kill process |
| AI cannot resume | check `_brain/CURRENT_STATE.md` and `_brain/sessions/LATEST_HANDOFF.md` |

---

# 10. IMPORTANT RULE

This project uses a structured AI memory system (`_brain/`).

Never:
- skip `_brain` initialization
- modify system files without state rules
- run full rewrites without confirmation

Always:
- resume via the context selector and latest handoff
- use daily logs and compact active state
- apply incremental changes only
