// File: resources/js/task-table.js
// Enhanced TaskTable class for Excel-like task management

class TaskTable {
    constructor(containerId, options = {}) {
        this.container = containerId;
        this.options = {
            teams: options.teams || [],
            projects: options.projects || [],
            taskTypes: options.taskTypes || [],
            users: options.users || [],
            canAssign: options.canAssign || false,
            componentId: options.componentId,
            ...options
        };
        this.table = null;
        this.saveTimeout = null;
        this.isTableReady = false;
        this.pendingData = null;
        this.init();
    }

    init() {
        // Destroy existing table if it exists
        if (this.table) {
            this.table.destroy();
        }

        // Initialize Tabulator with Excel-like configuration
        this.table = new Tabulator(this.container, {
            height: "500px",
            layout: "fitColumns",
            placeholder: "Tidak ada tugas. Klik 'Tambah Baris' untuk membuat tugas baru.",
            
            // Excel-like features
            editTrigger: "click",
            selectableRows: true,
            clipboard: true,
            clipboardPasteAction: "insert",
            
            // Validation and persistence
            validationMode: "highlight",
            cellEdited: this.handleCellEdit.bind(this),
            cellEditCancelled: this.handleCellCancel.bind(this),
            
            // Styling
            tooltips: true,
            tooltipGenerationMode: "hover",
            
            // Column definitions
            columns: this.getColumnDefinitions(),
            
            // Row data - will be loaded after table is built
            data: [],
            
            // Responsive
            responsiveLayout: "collapse",
            responsiveLayoutCollapseStartOpen: false,
            
            // Sorting - remove created_at since it might not exist in data
            // initialSort: [
            //     {column: "created_at", dir: "desc"}
            // ]
        });

        // Set up table built event to handle initial data loading
        this.table.on("tableBuilt", () => {
            this.isTableReady = true;
            // Load any pending data
            if (this.pendingData) {
                this.table.setData(this.pendingData);
                this.pendingData = null;
            }
        });

        this.setupEventListeners();
    }

    getColumnDefinitions() {
        let columns = [
            {
                title: "Judul Tugas",
                field: "title",
                editor: "input",
                validator: ["required", "minLength:3"],
                headerFilter: "input",
                width: 200,
                cellEdited: this.markRowModified.bind(this),
                tooltip: function(cell) {
                    return cell.getValue() || "Klik untuk mengedit judul tugas";
                }
            },
            {
                title: "Deskripsi", 
                field: "description",
                editor: "textarea",
                formatter: this.formatDescription.bind(this),
                width: 180,
                cellEdited: this.markRowModified.bind(this),
                tooltip: function(cell) {
                    return cell.getValue() || "Klik untuk menambah deskripsi";
                }
            },
            {
                title: "Proyek",
                field: "project_id",
                editor: "list",
                editorParams: {
                    values: this.getProjectOptions(),
                    placeholder: "Pilih proyek..."
                },
                formatter: this.formatProject.bind(this),
                validator: ["required"],
                headerFilter: "list",
                headerFilterParams: {
                    values: this.getProjectOptions()
                },
                width: 150,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Tipe",
                field: "type_id", 
                editor: "list",
                editorParams: {
                    values: this.getTypeOptions(),
                    placeholder: "Pilih tipe..."
                },
                formatter: this.formatType.bind(this),
                validator: ["required"], // REQUIRED field
                headerFilter: "list",
                headerFilterParams: {
                    values: this.getTypeOptions()
                },
                width: 120,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Status",
                field: "status",
                editor: "list",
                editorParams: {
                    values: {
                        "todo": "Todo",
                        "in_progress": "Dalam Proses", 
                        "review": "Review",
                        "done": "Selesai"
                    }
                },
                formatter: this.formatStatus.bind(this),
                headerFilter: "list",
                headerFilterParams: {
                    values: {
                        "": "Semua Status",
                        "todo": "Todo",
                        "in_progress": "Dalam Proses",
                        "review": "Review", 
                        "done": "Selesai"
                    }
                },
                width: 130,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Prioritas",
                field: "priority",
                editor: "list",
                editorParams: {
                    values: {
                        "low": "Rendah",
                        "medium": "Sedang",
                        "high": "Tinggi",
                        "urgent": "Mendesak"
                    }
                },
                formatter: this.formatPriority.bind(this),
                headerFilter: "list",
                headerFilterParams: {
                    values: {
                        "": "Semua Prioritas",
                        "low": "Rendah",
                        "medium": "Sedang",
                        "high": "Tinggi",
                        "urgent": "Mendesak"
                    }
                },
                width: 110,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Mulai",
                field: "start_date",
                editor: "date",
                formatter: this.formatDate.bind(this),
                validator: ["required"],
                width: 110,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Selesai",
                field: "end_date", 
                editor: "date",
                formatter: this.formatDate.bind(this),
                validator: ["required"], // REQUIRED field based on migration
                width: 110,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Estimasi (jam)",
                field: "estimated_hours",
                editor: "number",
                editorParams: {
                    min: 0,
                    max: 999,
                    step: 0.5
                },
                formatter: this.formatHours.bind(this),
                width: 120,
                cellEdited: this.markRowModified.bind(this)
            },
            {
                title: "Aksi",
                field: "actions",
                formatter: this.formatActions.bind(this),
                cellClick: this.handleActionClick.bind(this),
                headerSort: false,
                width: 80,
                responsive: 0 // Always visible
            }
        ];

        // Add assignment column only if user can assign
        if (this.options.canAssign) {
            columns.splice(5, 0, {
                title: "Ditugaskan",
                field: "assigned_to",
                editor: "list",
                editorParams: {
                    values: this.getUserOptions(),
                    placeholder: "Pilih pengguna..."
                },
                formatter: this.formatUser.bind(this),
                headerFilter: "list",
                headerFilterParams: {
                    values: this.getUserOptions()
                },
                width: 130,
                cellEdited: this.markRowModified.bind(this)
            });
        }

        return columns;
    }

    // Get project options for dropdown (policy-filtered)
    getProjectOptions() {
        let options = {"": "Pilih proyek..."};
        this.options.projects.forEach(project => {
            options[project.id] = `${project.name} (${project.tsk_team.name})`;
        });
        return options;
    }

    // Get task type options
    getTypeOptions() {
        let options = {"": "Tanpa tipe"};
        this.options.taskTypes.forEach(type => {
            options[type.id] = type.name;
        });
        return options;
    }

    // Get user options (team members only)
    getUserOptions() {
        let options = {"": "Tidak ditugaskan"};
        this.options.users.forEach(user => {
            options[user.id] = user.name;
        });
        return options;
    }

    // Formatters
    formatProject(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">Pilih proyek</span>';
        
        // Try to find project in options first
        const project = this.options.projects.find(p => p.id == value);
        if (project) {
            return `${project.name} <span class="text-neutral-500">(${project.tsk_team.name})</span>`;
        }
        
        // For existing data, try to get project info from row data
        const rowData = cell.getRow().getData();
        if (rowData.tsk_project) {
            return `${rowData.tsk_project.name} <span class="text-neutral-500">(${rowData.tsk_project.tsk_team.name})</span>`;
        }
        
        // Fallback - just show the ID
        return `<span class="text-orange-600">Proyek ID: ${value}</span>`;
    }

    formatType(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">Pilih tipe</span>';
        
        // Try to find type in options first
        const type = this.options.taskTypes.find(t => t.id == value);
        if (type) {
            return `<span class="inline-flex px-2 py-1 text-xs bg-neutral-100 text-neutral-700 rounded">${type.name}</span>`;
        }
        
        // For existing data, try to get type info from row data
        const rowData = cell.getRow().getData();
        if (rowData.tsk_type) {
            return `<span class="inline-flex px-2 py-1 text-xs bg-neutral-100 text-neutral-700 rounded">${rowData.tsk_type.name}</span>`;
        }
        
        // Fallback
        return `<span class="text-orange-600">Tipe ID: ${value}</span>`;
    }

    formatUser(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">Tidak ditugaskan</span>';
        
        // Try to find user in options first
        const user = this.options.users.find(u => u.id == value);
        if (user) {
            return user.name;
        }
        
        // For existing data, try to get user info from row data
        const rowData = cell.getRow().getData();
        if (rowData.assignee) {
            return rowData.assignee.name;
        }
        
        // Fallback
        return `<span class="text-orange-600">User ID: ${value}</span>`;
    }

    formatPriority(cell) {
        const value = cell.getValue();
        const priorityMap = {
            'low': '<span class="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Rendah</span>',
            'medium': '<span class="inline-flex px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Sedang</span>',
            'high': '<span class="inline-flex px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded-full">Tinggi</span>',
            'urgent': '<span class="inline-flex px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Mendesak</span>'
        };
        return priorityMap[value] || '<span class="text-neutral-400">-</span>';
    }

    formatStatus(cell) {
        const value = cell.getValue();
        const statusMap = {
            'todo': '<span class="inline-flex px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">Todo</span>',
            'in_progress': '<span class="inline-flex px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded-full">Dalam Proses</span>',
            'review': '<span class="inline-flex px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Review</span>',
            'done': '<span class="inline-flex px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Selesai</span>'
        };
        return statusMap[value] || '<span class="text-neutral-400">-</span>';
    }

    formatDate(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">-</span>';
        
        try {
            const date = new Date(value);
            const today = new Date();
            const isOverdue = date < today && cell.getField() === 'end_date' && 
                             cell.getRow().getData().status !== 'done';
            
            const formatted = date.toLocaleDateString('id-ID', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric'
            });
            
            return isOverdue ? 
                `<span class="text-red-600 font-semibold">${formatted}</span>` : 
                formatted;
        } catch (e) {
            return value;
        }
    }

    formatHours(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">-</span>';
        return `${parseFloat(value)} jam`;
    }

    formatDescription(cell) {
        const value = cell.getValue();
        if (!value) return '<span class="text-neutral-400">Klik untuk menambah deskripsi</span>';
        
        // Truncate long descriptions
        if (value.length > 50) {
            return `<span title="${value}">${value.substring(0, 50)}...</span>`;
        }
        return value;
    }

    formatActions(cell) {
        const rowData = cell.getRow().getData();
        const isModified = rowData._modified;
        const isNew = !rowData.id;
        
        if (isNew) {
            // New task - show create/cancel
            return `
                <div class="flex space-x-1">
                    <button class="action-create text-green-600 hover:text-green-800 p-1 rounded" title="Buat Tugas">
                        <i class="icon-check text-sm"></i>
                    </button>
                    <button class="action-cancel text-gray-600 hover:text-gray-800 p-1 rounded" title="Batal">
                        <i class="icon-x text-sm"></i>
                    </button>
                </div>
            `;
        } else {
            // Existing task - show save/delete with modified indicator
            const saveClass = isModified ? 'text-orange-600 hover:text-orange-800' : 'text-green-600 hover:text-green-800';
            const saveIcon = isModified ? 'icon-clock' : 'icon-check';
            const saveTitle = isModified ? 'Ada perubahan - klik untuk simpan' : 'Tersimpan';
            
            return `
                <div class="flex space-x-1">
                    <button class="action-save ${saveClass} p-1 rounded" title="${saveTitle}">
                        <i class="${saveIcon} text-sm"></i>
                    </button>
                    <button class="action-delete text-red-600 hover:text-red-800 p-1 rounded" title="Hapus">
                        <i class="icon-trash text-sm"></i>
                    </button>
                </div>
            `;
        }
    }

    // Event handlers
    handleCellEdit(cell) {
        this.markRowModified(cell);
        
        // Auto-save after delay
        clearTimeout(this.saveTimeout);
        this.saveTimeout = setTimeout(() => {
            const row = cell.getRow();
            const data = row.getData();
            if (data.id && data._modified) {
                this.saveTask(row);
            }
        }, 1500); // 1.5 second delay
    }

    handleCellCancel(cell) {
        // Reset modified flag if user cancels edit
        const row = cell.getRow();
        const data = row.getData();
        if (data._modified) {
            row.update({...data, _modified: false});
        }
    }

    markRowModified(cell) {
        const row = cell.getRow();
        const data = row.getData();
        row.update({...data, _modified: true});
        
        // Update action column to show modified state
        row.reformat();
    }

    handleActionClick(e, cell) {
        const target = e.target.closest('button');
        if (!target) return;
        
        const row = cell.getRow();
        const action = target.className.match(/action-(\w+)/)?.[1];
        
        switch(action) {
            case 'create':
                this.createTask(row);
                break;
            case 'save':
                this.saveTask(row);
                break;
            case 'delete':
                this.deleteTask(row);
                break;
            case 'cancel':
                this.cancelTask(row);
                break;
        }
        
        // Prevent cell edit on action click
        e.stopPropagation();
    }

    // CRUD operations
    async createTask(row) {
        const data = row.getData();
        
        // Validate required fields (based on migration)
        if (!data.title || !data.project_id || !data.start_date || !data.end_date || !data.type_id) {
            this.showError('Judul, proyek, tipe, tanggal mulai, dan tanggal selesai wajib diisi');
            return;
        }

        // Show loading state
        row.update({...data, _loading: true});
        row.reformat();

        try {
            const component = window.Livewire.find(this.options.componentId);
            const response = await component.call('createTask', data);
            
            if (response.success) {
                // Update row with new task data including relationships
                row.update({
                    ...response.task, 
                    _modified: false,
                    _loading: false
                });
                this.showSuccess(response.message);
            } else {
                row.update({...data, _loading: false});
                this.showError(response.message || 'Gagal membuat tugas');
            }
        } catch (error) {
            row.update({...data, _loading: false});
            this.showError('Terjadi kesalahan saat membuat tugas');
            console.error('Create task error:', error);
        }
    }

    async saveTask(row) {
        const data = row.getData();
        
        if (!data.id) {
            // This is a new task, use create instead
            return this.createTask(row);
        }

        // Show loading state
        row.update({...data, _loading: true});
        row.reformat();

        try {
            const component = window.Livewire.find(this.options.componentId);
            const response = await component.call('updateTask', data.id, data);
            
            if (response.success) {
                row.update({...data, _modified: false, _loading: false});
                row.reformat();
                this.showSuccess(response.message);
            } else {
                row.update({...data, _loading: false});
                this.showError(response.message || 'Gagal menyimpan tugas');
            }
        } catch (error) {
            row.update({...data, _loading: false});
            this.showError('Terjadi kesalahan saat menyimpan tugas');
            console.error('Save task error:', error);
        }
    }

    async deleteTask(row) {
        const data = row.getData();
        
        if (!confirm('Yakin ingin menghapus tugas ini?')) return;
        
        try {
            const component = window.Livewire.find(this.options.componentId);
            const response = await component.call('deleteTask', data.id);
            
            if (response.success) {
                row.delete();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message || 'Gagal menghapus tugas');
            }
        } catch (error) {
            this.showError('Terjadi kesalahan saat menghapus tugas');
            console.error('Delete task error:', error);
        }
    }

    cancelTask(row) {
        row.delete();
    }

    // Table operations
    addNewRow() {
        const today = new Date().toISOString().split('T')[0];
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        const tomorrowStr = tomorrow.toISOString().split('T')[0];
        
        // Get default type ID if available
        const defaultTypeId = this.options.taskTypes.length > 0 ? this.options.taskTypes[0].id : "";
        
        const newRowData = {
            title: "",
            description: "",
            project_id: "",
            type_id: defaultTypeId, // Set default type since it's required
            assigned_to: "",
            status: "todo",
            priority: "medium", // Default priority
            start_date: today,
            end_date: tomorrowStr, // Default to tomorrow since it's required
            estimated_hours: "",
            _modified: false,
            _loading: false
        };

        // Only add row if table is ready
        if (this.isTableReady && this.table) {
            this.table.addRow(newRowData, true); // Add to top
            
            // Focus on the title field of the new row
            setTimeout(() => {
                const rows = this.table.getRows();
                if (rows.length > 0) {
                    const firstRow = rows[0];
                    const titleCell = firstRow.getCell('title');
                    if (titleCell) {
                        titleCell.edit();
                    }
                }
            }, 100);
        } else {
            this.showError('Tabel belum siap. Silakan tunggu sebentar dan coba lagi.');
        }
    }

    loadTasks(tasks) {
        // Add flags to existing tasks and ensure relationship data is available
        const formattedTasks = tasks.map(task => ({
            ...task,
            description: task.desc, // Map desc to description for consistency
            _modified: false,
            _loading: false,
            // Ensure relationship data is preserved for formatters
            tsk_project: task.tsk_project || null,
            tsk_type: task.tsk_type || null,
            assignee: task.assignee || null,
            creator: task.creator || null
        }));
        
        // Check if table is ready
        if (this.isTableReady && this.table) {
            this.table.setData(formattedTasks);
        } else {
            // Store data to load when table is ready
            this.pendingData = formattedTasks;
        }
    }

    // Keyboard shortcuts and event listeners
    setupEventListeners() {
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Only handle shortcuts when table container is in view
            const container = document.querySelector(this.container);
            if (!container || !this.isElementInViewport(container)) return;
            
            // Ctrl+N to add new row
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                this.addNewRow();
            }
            
            // Ctrl+S to save all modified rows
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                this.saveAllModified();
            }
            
            // Escape to cancel current edit
            if (e.key === 'Escape') {
                // Tabulator handles this automatically
            }
        });

        // Handle window resize for responsive behavior
        window.addEventListener('resize', () => {
            if (this.table) {
                this.table.redraw();
            }
        });
    }

    isElementInViewport(el) {
        const rect = el.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    async saveAllModified() {
        if (!this.isTableReady || !this.table) {
            this.showError('Tabel belum siap');
            return;
        }

        const modifiedRows = this.table.getData().filter(row => row._modified && row.id);
        
        if (modifiedRows.length === 0) {
            this.showInfo('Tidak ada perubahan untuk disimpan');
            return;
        }

        let saved = 0;
        for (const rowData of modifiedRows) {
            try {
                const row = this.table.getRow(rowData.id);
                await this.saveTask(row);
                saved++;
            } catch (error) {
                console.error('Error saving row:', error);
            }
        }
        
        this.showSuccess(`${saved} tugas berhasil disimpan`);
    }

    // Utility methods
    showSuccess(message) {
        if (window.toast) {
            window.toast(message, { type: 'success' });
        } else {
            console.log('SUCCESS:', message);
        }
    }

    showError(message) {
        if (window.toast) {
            window.toast(message, { type: 'danger' });
        } else {
            console.error('ERROR:', message);
        }
    }

    showInfo(message) {
        if (window.toast) {
            window.toast(message, { type: 'info' });
        } else {
            console.log('INFO:', message);
        }
    }

    // Cleanup
    destroy() {
        if (this.table) {
            this.table.destroy();
            this.table = null;
        }
        
        if (this.saveTimeout) {
            clearTimeout(this.saveTimeout);
            this.saveTimeout = null;
        }
        
        this.isTableReady = false;
        this.pendingData = null;
    }
}

// Export for use in Blade templates
window.TaskTable = TaskTable;