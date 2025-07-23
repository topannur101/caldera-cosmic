# Task Management Module - Implementation Summary

## Database Structure

### Tables Created
- `tsk_teams` - Teams with name, short_name (e.g., "Digitalization", "DGT")
- `tsk_projects` - Projects belonging to teams
- `tsk_items` - Individual tasks within projects
- `tsk_auths` - User permissions per team (JSON perms field)

### Key Relationships
- Teams → Projects (one-to-many)
- Projects → Tasks (one-to-many)
- Users ↔ Teams (many-to-many via tsk_auths)
- Tasks → Users (assigned_to, created_by)

### Permission System (Team-Specific Action-Based)
- `task-assign` - Assign tasks to other people within their team
- `task-manage` - Edit/delete tasks which are NOT their own within their team
- `project-manage` - Create and archive projects within their team

**Important**: All permissions are **team-specific**. Users can only perform actions within teams they belong to.

## Routes Structure
```
/tasks → tasks.index (landing page)
/tasks/dashboard → tasks.dashboard
/tasks/projects/* → project management
/tasks/items/* → task management (NO /create route - uses slideover)
/tasks/board → board.index (project selector)
/tasks/board/{project_id} → board.project (kanban)
/tasks/manage → manage.index
/tasks/manage/teams → team management
/tasks/manage/auths → permission management
```

## Task Creation Pattern
- **ALL task creation uses slideover** (following STC pattern)
- Component: `tasks/items/create.blade.php`
- Trigger: `x-on:click.prevent="$dispatch('open-slide-over', 'task-create'); $dispatch('task-create')"`
- Context-aware: Auto-populates project when triggered from project pages
- Redirect logic: Redirects to task list unless already on task list page

## Policy Implementation
- **TskItemPolicy** - Handles all task-related authorization
- **Superuser access**: User ID 1 has full access (before method)
- **Team membership**: Users must be team members to access team resources
- **Permission hierarchy**: 
  - Any team member can create tasks
  - Task creators/assignees can edit their own tasks
  - Users with `task-manage` can edit/delete any task in their team

## Models
- `TskTeam` - Basic team model
- `TskProject` - Projects with status, priority, dates
- `TskItem` - Tasks with status (todo/in_progress/review/done)
- `TskAuth` - User permissions with JSON perms field

### Comment Integration
Uses existing comment system with `model_name` and `model_id`:
- Projects: `ComItem::where('model_name', 'TskProject')->where('model_id', $id)`
- Tasks: `ComItem::where('model_name', 'TskItem')->where('model_id', $id)`

## Components Created

### Navigation
- `nav-task.blade.php` - Main nav (Dasbor, Projects, Tasks)
- `nav-task-sub.blade.php` - Sub nav with back button

### Main Pages
- `tasks/index.blade.php` - Landing page (copy of projects.index)
- `tasks/dashboard/index.blade.php` - User dashboard with stats
- `tasks/projects/index.blade.php` - Project listing with filters
- `tasks/projects/create.blade.php` - Create project form
- `tasks/items/index.blade.php` - Task listing (list/board toggle)
- `tasks/items/create.blade.php` - Task creation form with policy-based authorization

### Board Views
- `tasks/board/index.blade.php` - Project selector for boards
- `tasks/board/project.blade.php` - Kanban board for specific project

### Management Area (Following OMV Pattern)
- `tasks/manage/index.blade.php` - Management dashboard
- `tasks/manage/teams.blade.php` - Team CRUD
- `tasks/manage/auths.blade.php` - Permission management

## Key Features Implemented

### Task Status Flow
todo → in_progress → review → done

### Permission-Based UI
- Task assignment field only shows if user has `task-assign` permission
- Project dropdown shows all projects from user's teams
- Management area accessible to all with permission checks inside

### Team-Specific Authorization
- **Task Creation**: Any team member can create tasks in their team projects
- **Task Assignment**: Users with `task-assign` permission can assign to team members
- **Task Editing**: Task creators, assignees, or users with `task-manage` permission
- **Task Deletion**: Task creators or users with `task-manage` permission

### Integration Points
- Comment system ready (`model_name`/`model_id`)
- File upload ready (via existing com_files)
- User photos and employee IDs integrated

## Backend TODO List

### Phase 1: Core Functionality
1. ✅ Implement TskItemPolicy for authorization
2. ✅ Update task creation form with policy-based checks
3. Create team and auth management modals
4. Add policy authorization to other task operations
5. Add proper error handling

### Phase 2: Advanced Features
1. Drag-and-drop for Kanban board
2. Real-time updates
3. Task assignment notifications
4. Dashboard statistics calculation
5. Search and filtering logic

### Phase 3: Polish
1. Task detail views with comments
2. Project detail views
3. Team member management
4. Activity tracking
5. Performance optimization

## Models Structure Summary

### TskTeam
```php
- id, name, short_name, desc, is_active
- Relations: tsk_projects, tsk_auths, users
```

### TskProject  
```php
- id, name, desc, code, tsk_team_id, user_id
- status (active/completed/on_hold/cancelled)
- priority (low/medium/high/urgent)
- start_date, end_date
- Relations: tsk_team, user, tsk_items
```

### TskItem
```php
- id, title, desc, tsk_project_id
- created_by, assigned_to
- status (todo/in_progress/review/done)
- priority, end_date, estimated_hours, actual_hours
- Relations: tsk_project, creator, assignee
```

### TskAuth
```php
- user_id, tsk_team_id, perms (JSON), is_active
- Relations: user, tsk_team
- Methods: hasPermission(), isLeader(), isMember()
```

## File Locations
```
app/Policies/TskItemPolicy.php (NEW)
app/Models/TskTeam.php
app/Models/TskProject.php
app/Models/TskItem.php
app/Models/TskAuth.php

resources/views/components/nav-task.blade.php
resources/views/components/nav-task-sub.blade.php

resources/views/livewire/tasks/index.blade.php
resources/views/livewire/tasks/dashboard/index.blade.php
resources/views/livewire/tasks/projects/index.blade.php
resources/views/livewire/tasks/projects/create.blade.php
resources/views/livewire/tasks/items/index.blade.php
resources/views/livewire/tasks/items/create.blade.php (UPDATED)
resources/views/livewire/tasks/board/index.blade.php
resources/views/livewire/tasks/board/project.blade.php
resources/views/livewire/tasks/manage/index.blade.php
resources/views/livewire/tasks/manage/teams.blade.php
resources/views/livewire/tasks/manage/auths.blade.php

database/migrations/*_create_tsk_*_tables.php
```

## Next Steps for Backend Implementation
1. Register TskItemPolicy in AuthServiceProvider
2. Add policy authorization to task editing/deletion operations
3. Test complete workflow: team → project → task → assignment
4. Create team and auth management modals
5. Implement data loading in remaining Livewire components