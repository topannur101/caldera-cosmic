# -*- mode: python ; coding: utf-8 -*-

block_cipher = None

# Define the analysis for collecting all files and dependencies
a = Analysis(
    ['main.py'],
    pathex=[],
    binaries=[],
    datas=[
        ('config.json', '.'),
        ('logs', 'logs'),
    ],
    hiddenimports=[
        'tkinter',
        'tkinter.ttk',
        'tkinter.messagebox',
        'tkinter.scrolledtext',
        'tkinter.filedialog',
        'PIL',
        'PIL.Image',
        'PIL.ImageDraw',
        'pystray',
        'flask',
        'flask_cors',
        'psutil',
        'threading',
        'subprocess',
        'json',
        'pathlib',
        'logging',
        'logging.handlers',
        'datetime',
        'time',
        'os',
        'sys',
        'api_server',
        'command_manager',
        'config_manager',
        'gui.main_window',
        'gui.overview_tab',
        'gui.commands_tab',
        'gui.config_tab',
        'gui.logs_tab',
        'gui.command_panel',
    ],
    hookspath=[],
    hooksconfig={},
    runtime_hooks=[],
    excludes=[],
    win_no_prefer_redirects=False,
    win_private_assemblies=False,
    cipher=block_cipher,
    noarchive=False,
)

# Create the PYZ archive
pyz = PYZ(a.pure, a.zipped_data, cipher=block_cipher)

# Create the executable
exe = EXE(
    pyz,
    a.scripts,
    a.binaries,
    a.zipfiles,
    a.datas,
    [],
    name='command_manager',
    debug=False,
    bootloader_ignore_signals=False,
    strip=False,
    upx=True,
    upx_exclude=[],
    runtime_tmpdir=None,
    console=False,  # Set to False to hide console window
    disable_windowed_traceback=False,
    argv_emulation=False,
    target_arch=None,
    codesign_identity=None,
    entitlements_file=None,
    icon='../ldc-worker/icon.ico',  # Use existing icon from ldc-worker
)