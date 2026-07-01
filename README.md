# 🚀 Project Tracker

Project Tracker is a role-aware project workspace for teams that need one clean place to plan work, assign people, review progress, log effort, and keep everyone aligned without drowning in spreadsheets.

It is built with Laravel, Livewire, Tailwind CSS, and Chart.js, and it is designed around a simple idea: every user should land exactly where their work makes sense. A team lead sees leadership tools. A member sees their assigned work. An admin sees the system controls. A client sees the project view that matters to them.

## ✨ What Makes It Shine

Project Tracker is not just a task list. It is a project command center with structure, accountability, and just enough polish to make daily tracking feel less like paperwork.

- **🧭 Role-based project entry**: users see the projects they are involved in, then open the right dashboard based on their role in that project.
- **🔁 Flexible team roles**: a person can lead one team and still be a member of another, without the system getting confused.
- **📅 Project timelines**: scheduled work and actual start-to-end activity are shown together, including early starts, late work, and member-specific progress.
- **✅ Task assignment workflow**: team leads can assign tasks, set dates, priorities, members, and track status as work moves forward.
- **📝 Journal and time logs**: members can record work logs, including general work, so analytics reflect real effort instead of only task status.
- **📊 Analytics for team leads**: velocity, completed work, workload, progress, and timeline data help leads understand what is moving and what needs attention.
- **🔍 Journal review**: team leads can review submitted work logs and keep accountability visible.
- **⭐ Member evaluations**: team leads can evaluate members, while members can view their own evaluation history.
- **🔔 Notifications**: important updates surface through the notification system so users do not have to hunt for changes.
- **🛠️ Admin controls**: admins can manage users, projects, teams, premade teams, assignments, and overall workspace structure.

## 🧩 Main Areas

### 🛠️ Admin

The admin side is where the workspace is shaped. Admins can create and manage projects, users, teams, premade teams, and task assignments. This is the control room for keeping the system organized.

### 🧑‍💼 Team Lead

The team lead side focuses on execution. Leads can monitor dashboards, manage tasks, review journals, inspect analytics, view project timelines, and evaluate members.

### 🙋 Member

The member side keeps the experience focused. Members can view assigned work, filter by team or project, log time, write journal entries, and check evaluations they have received.

### 🤝 Client

The client side gives project stakeholders a cleaner project-facing view without exposing internal management clutter.

## 🏗️ Tech Stack

- **⚙️ Backend**: Laravel 13
- **🎨 Frontend**: Livewire 3, Blade, Tailwind CSS
- **📈 Charts and calendars**: Chart.js, FullCalendar
- **🔐 Authentication**: Laravel Jetstream and Sanctum
- **⚡ Build tooling**: Vite
- **🗄️ Database**: MySQL by default, with Laravel migrations for schema management

## 🚦 Getting Started

Clone the project, install dependencies, configure your environment, and run the app locally.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

For active development, you can run the Laravel server and Vite separately:

```bash
php artisan serve
npm run dev
```

Or use the project development script if your environment supports it:

```bash
composer run dev
```

## 🌱 Environment Notes

Update `.env` with your local database credentials before running migrations.

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_database_name
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
```

If the app reports a missing Vite manifest in production-style mode, build the frontend assets:

```bash
npm run build
```

## 🧪 Validation

Run the test suite with:

```bash
php artisan test
```

You can also clear and rebuild compiled views when working on Blade-heavy changes:

```bash
php artisan view:clear
php artisan view:cache
```

## 🌟 The Vibe

Project Tracker is built for teams that want clarity without chaos: project cards that open the right workspace, timelines that show what actually happened, analytics that tell a useful story, and role-based dashboards that feel intentional instead of stitched together.

In short: less guessing, more momentum.
