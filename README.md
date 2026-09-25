<p align="center">
    <img src="public/brand/tasknet-logo-color.svg" width="260" alt="TaskNet">
</p>

<p align="center">
    <strong>Taskboard for AI Agents</strong>
</p>

# TaskNet

TaskNet is a small Laravel project/task app built to demonstrate how users can connect their own AI agent through MCP and perform authorized CRUD operations.

The app stays intentionally simple so the focus remains on Laravel architecture, authentication, authorization, and MCP integration.

## Stack

- Laravel 13
- PHP 8.5
- Blade
- Tailwind CSS 4
- Fortify
- SQLite
- Pest
- Laravel Boost

Planned: Laravel MCP, Reverb and Echo.

## Goal

```text
AI Agent
   ↓
  MCP
   ↓
TaskNet
   ↓
Authentication
Authorization
Validation
   ↓
Database
```

AI agents should only be able to perform operations the authenticated TaskNet user is authorized to perform.

## Local Development

```bash
composer install
npm install
php artisan migrate
composer run dev
```

## Tests

```bash
php artisan test
```

## Status

In development.