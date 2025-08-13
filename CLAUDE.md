# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Common Development Commands

### PHP/Laravel Commands
- **Development server**: `php artisan serve`
- **Clear caches**: `php artisan cache:clear && php artisan config:clear && php artisan view:clear`
- **Database migration**: `php artisan migrate`
- **Database seeding**: `php artisan db:seed`
- **Queue worker**: `php artisan queue:work`
- **Generate application key**: `php artisan key:generate`
- **Storage symlink**: `php artisan storage:link`
- **Tinker console**: `php artisan tinker`
- **Code formatting**: `./vendor/bin/pint` (Laravel Pint)

### Frontend Commands
- **Development build**: `npm run dev`
- **Production build**: `npm run build`
- **Install dependencies**: `npm install`

### Testing
- **Run all tests**: `./vendor/bin/phpunit`
- **Run specific test suite**: `./vendor/bin/phpunit --testsuite=Feature`
- **Run single test**: `./vendor/bin/phpunit tests/Feature/ExampleTest.php`

### Batch Scripts (Windows)
- **Queue worker**: `bat/queue-work.bat`
- **Schedule worker**: `bat/schedule-work.bat`
- **Insights polling**: `bat/ins-clm-poll.bat`, `bat/ins-rtc-fetch.bat`

## Architecture Overview

### Framework and Stack
- **Laravel 12** with **PHP 8.2+**
- **Livewire Volt** for reactive UI components (no traditional controllers)
- **MySQL** database with extensive migrations
- **Vite** for asset bundling with **TailwindCSS**
- **Chart.js** for data visualization
- **Modbus TCP client** for industrial equipment integration

### Core Application Structure

#### Insights System (Manufacturing Monitoring)
The application's core is a manufacturing insights platform with multiple specialized modules:

- **OMV (Open Mill Validation)**: Rubber mixing process monitoring with recipe management, real-time amperage tracking, and energy calculations
- **CTC (Calendar Thickness Control)**: Thickness monitoring for rubber calendering with machine performance analytics
- **STC (Stabilization Temperature Control)**: Environmental monitoring for temperature and humidity in stabilization chambers
- **RDC (Rheometer Data Collection)**: Automated rheometer testing with TC10/TC90 analysis
- **LDC (Leather Data Collection)**: Hide/leather quality inspection system
- **CLM (Climate Monitoring)**: Environmental monitoring across production areas

#### Inventory Management System
Comprehensive consumables tracking with:
- Multi-unit stock management with automatic conversions
- Area-based authorization system
- Circulation workflow (deposit/withdrawal) with approval processes
- Multi-currency support with automatic calculations
- Advanced analytics including stock aging and predictive reordering

#### Project and Task Management
- Team-based project organization
- Task assignment and tracking with multiple status states
- Permission-based access control
- Dashboard analytics for project progress

#### User Management and Authorization
- Role-based access control with area-specific permissions
- User preferences stored in database and loaded to session
- Photo management with automatic resizing
- Multi-language support (EN, ID, KO, VI)

### Key Design Patterns

#### Livewire Volt Architecture
- All UI logic is handled through Livewire Volt components (no traditional controllers)
- Components are located in `resources/views/livewire/` with corresponding PHP logic
- Route definitions use `Volt::route()` for direct component routing

#### Model Relationships
- Extensive use of Eloquent relationships for complex data structures
- Authorization models (`*Auth`) for granular permission control
- Audit trailing for critical operations

#### Industrial Integration
- Modbus TCP client for real-time equipment data collection
- Energy calculation formulas embedded in business logic
- Background job processing for data polling and calculations

#### API Design
- RESTful API endpoints for external integrations
- Resource transformers for consistent API responses
- Token-based authentication for external clients

### Database Schema
- **Migration-driven development** with extensive migration files
- **Prefixed table naming**: `ins_*` (insights), `inv_*` (inventory), `tsk_*` (tasks), `pjt_*` (projects)
- **Soft deletes** and **timestamps** on most entities
- **JSON columns** for flexible data storage (compositions, preferences)

### Critical Business Logic

#### Energy Calculations (OMV)
Formula: `√3 × Current × Voltage × Time × Power Factor × Calibration Factor / 1000`
- Standard voltage: 380V
- Power factor: 0.85
- Calibration factor: 0.8

#### Stock Management (Inventory)
- Multi-unit calculations with automatic conversions
- Currency conversion with configurable exchange rates
- Aging analysis based on stock movement patterns

#### Manufacturing Process Control
- Recipe-based operations with timing controls
- Real-time sensor data validation
- Automatic quality assessments with threshold monitoring

### Security Considerations
- User ID `1` is always superuser with full permissions
- Area-based access control for inventory operations
- Token-based API authentication
- File upload restrictions and image processing security

### Development Notes
- **Environment setup**: Copy `.env.example` to `.env` and configure database/cache settings
- **Storage requirements**: Ensure `storage/app/public` is writable for file uploads
- **Queue processing**: Redis recommended for production, database acceptable for development
- **Background services**: Multiple polling commands for real-time data collection
- **Asset compilation**: Uses Vite with TailwindCSS and various chart libraries

### Python Integration
- External Python scripts in `py/` directory for specialized processing
- Face detection and image processing capabilities
- Data conversion utilities for legacy systems