from PyQt6.QtWidgets import QWidget, QFormLayout, QCheckBox, QSpinBox, QLineEdit

class ConfigEditor(QWidget):
    def __init__(self, config_manager, section):
        super().__init__()
        self.config_manager = config_manager
        self.section = section
        self.config = self.config_manager.get_config(section)
        self.init_ui()

    def init_ui(self):
        layout = QFormLayout()
        self.widgets = {}

        for key, value in self.config.items():
            if isinstance(value, bool):
                widget = QCheckBox()
                widget.setChecked(value)
            elif isinstance(value, int):
                widget = QSpinBox()
                widget.setRange(-1000000, 1000000)
                widget.setValue(value)
            else:
                widget = QLineEdit(str(value))

            layout.addRow(key, widget)
            self.widgets[key] = widget

        self.setLayout(layout)

    def save_changes(self):
        updates = {}
        for key, widget in self.widgets.items():
            if isinstance(widget, QCheckBox):
                updates[key] = widget.isChecked()
            elif isinstance(widget, QSpinBox):
                updates[key] = widget.value()
            else:
                updates[key] = widget.text()

        self.config_manager.update_config(self.section, updates)
        return f"Configuration for {self.section} saved"