import os
import re
import json
import sys
import time

def print_header():
    print("=" * 60)
    print("         LARAVEL TRANSLATION STRING MANAGER")
    print("=" * 60)
    print()

def print_progress_bar(current, total, prefix="", suffix="", length=50):
    percent = (current / total) * 100
    filled_length = int(length * current // total)
    bar = "█" * filled_length + "-" * (length - filled_length)
    print(f"\r{prefix} [{bar}] {percent:.1f}% {suffix}", end="", flush=True)

def extract_translation_strings(file_path):
    pattern = re.compile(r'__\(\s*[\'"]([^\'"]+)[\'"]\s*\)')
    try:
        with open(file_path, 'r', encoding='utf-8') as file:
            content = file.read()
        return pattern.findall(content)
    except Exception as e:
        print(f"\nWarning: Could not read file {file_path}: {e}")
        return []

def scan_directory(directory, file_extension, show_progress=True):
    translation_strings = set()
    files_to_scan = []
    
    # First, collect all files to scan
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith(file_extension):
                files_to_scan.append(os.path.join(root, file))
    
    total_files = len(files_to_scan)
    print(f"\nScanning {total_files} {file_extension} files in {directory}...")
    
    for i, file_path in enumerate(files_to_scan, 1):
        if show_progress:
            print_progress_bar(i, total_files, 
                             prefix=f"Processing", 
                             suffix=f"({i}/{total_files}) {os.path.basename(file_path)}")
        
        strings = extract_translation_strings(file_path)
        translation_strings.update(strings)
        
        # Small delay to show progress (remove in production)
        time.sleep(0.01)
    
    if show_progress:
        print()  # New line after progress bar
    
    return translation_strings

def load_existing_translations(file_path):
    if os.path.exists(file_path):
        try:
            with open(file_path, 'r', encoding='utf-8') as json_file:
                return json.load(json_file)
        except Exception as e:
            print(f"Warning: Could not read {file_path}: {e}")
            return {}
    return {}

def load_exclusion_list(file_path):
    if os.path.exists(file_path):
        try:
            with open(file_path, 'r', encoding='utf-8') as json_file:
                return set(json.load(json_file))
        except Exception as e:
            print(f"Warning: Could not read exclusion file {file_path}: {e}")
            return set()
    return set()

def get_language_files():
    lang_dir = 'lang/'
    if not os.path.exists(lang_dir):
        print(f"Warning: Language directory '{lang_dir}' does not exist.")
        return []
    
    language_files = []
    for file in os.listdir(lang_dir):
        if file.endswith('.json'):
            language_files.append(os.path.join(lang_dir, file))
    
    return language_files

def create_or_update_translation_file(translation_strings, existing_translations, exclusions, output_path):
    new_strings = 0
    for string in translation_strings:
        if string not in existing_translations and string not in exclusions:
            existing_translations[string] = ""
            new_strings += 1
    
    with open(output_path, 'w', encoding='utf-8') as json_file:
        json.dump(existing_translations, json_file, ensure_ascii=False, indent=4)
    
    return new_strings

def find_unused_translations(all_translation_strings, language_files):
    unused_keys = set()
    
    for lang_file in language_files:
        existing_translations = load_existing_translations(lang_file)
        for key in existing_translations.keys():
            if key not in all_translation_strings:
                unused_keys.add(key)
    
    return unused_keys

def remove_unused_translations(unused_keys, language_files):
    for lang_file in language_files:
        existing_translations = load_existing_translations(lang_file)
        removed_count = 0
        
        for key in unused_keys:
            if key in existing_translations:
                del existing_translations[key]
                removed_count += 1
        
        if removed_count > 0:
            with open(lang_file, 'w', encoding='utf-8') as json_file:
                json.dump(existing_translations, json_file, ensure_ascii=False, indent=4)
            print(f"  - Removed {removed_count} unused translations from {os.path.basename(lang_file)}")

def extract_translations():
    print("\n" + "="*60)
    print("                EXTRACTING TRANSLATIONS")
    print("="*60)
    
    # Define directories
    resources_directory = 'resources/'
    app_directory = 'app/'
    output_file = 'lang/en.json'
    exclusion_file = 'py/translation-extract/exception.json'
    
    # Load existing translations and exclusion list
    print("\nLoading existing translations and exclusions...")
    existing_translations = load_existing_translations(output_file)
    exclusions = load_exclusion_list(exclusion_file)
    
    print(f"  - Loaded {len(existing_translations)} existing translations")
    print(f"  - Loaded {len(exclusions)} exclusion strings")
    
    # Extract translation strings from both directories
    blade_strings = scan_directory(resources_directory, '.blade.php')
    php_strings = scan_directory(app_directory, '.php')
    
    # Combine all translation strings
    all_translation_strings = blade_strings.union(php_strings)
    
    # Update en.json file
    print(f"\nUpdating {output_file}...")
    new_strings = create_or_update_translation_file(all_translation_strings, existing_translations, exclusions, output_file)
    
    # Summary
    print("\n" + "-"*60)
    print("                    EXTRACTION SUMMARY")
    print("-"*60)
    print(f"Translation strings found:")
    print(f"  - {len(blade_strings)} from resources/ (.blade.php files)")
    print(f"  - {len(php_strings)} from app/ (.php files)")
    print(f"  - {len(all_translation_strings)} total unique strings")
    print(f"New strings added: {new_strings}")
    print(f"Excluded strings: {len(exclusions)}")
    print(f"Output file: {output_file}")

def remove_unused():
    print("\n" + "="*60)
    print("              REMOVING UNUSED TRANSLATIONS")
    print("="*60)
    
    # Get all language files
    language_files = get_language_files()
    if not language_files:
        print("No language files found in lang/ directory.")
        return
    
    print(f"\nFound {len(language_files)} language files:")
    for lang_file in language_files:
        print(f"  - {os.path.basename(lang_file)}")
    
    # Scan for current translation strings
    print("\nScanning for current translation strings...")
    blade_strings = scan_directory('resources/', '.blade.php')
    php_strings = scan_directory('app/', '.php')
    all_translation_strings = blade_strings.union(php_strings)
    
    # Find unused translations
    print(f"\nAnalyzing unused translations...")
    unused_keys = find_unused_translations(all_translation_strings, language_files)
    
    if not unused_keys:
        print("No unused translations found!")
        return
    
    # Show unused translations
    print(f"\nFound {len(unused_keys)} unused translation keys:")
    print("-" * 40)
    for i, key in enumerate(sorted(unused_keys), 1):
        print(f"{i:3d}. {key}")
    
    # Confirm deletion
    print("-" * 40)
    confirm = input(f"\nDo you want to remove these {len(unused_keys)} unused translations from ALL language files? (y/N): ").strip().lower()
    
    if confirm in ['y', 'yes']:
        print(f"\nRemoving unused translations...")
        remove_unused_translations(unused_keys, language_files)
        print(f"\nSuccessfully removed {len(unused_keys)} unused translations from all language files.")
    else:
        print("Operation cancelled.")

def separate_empty_translations():
    print("\n" + "="*60)
    print("           SEPARATING EMPTY TRANSLATIONS")
    print("="*60)
    
    # Get all language files
    language_files = get_language_files()
    if not language_files:
        print("No language files found in lang/ directory.")
        return
    
    print(f"\nFound {len(language_files)} language files:")
    for lang_file in language_files:
        print(f"  - {os.path.basename(lang_file)}")
    
    # Analyze each file
    separation_plan = {}
    total_empty_count = 0
    
    print(f"\nAnalyzing translation files...")
    for lang_file in language_files:
        translations = load_existing_translations(lang_file)
        
        filled_translations = {}
        empty_translations = {}
        
        for key, value in translations.items():
            if value == "":
                empty_translations[key] = value
            else:
                filled_translations[key] = value
        
        if empty_translations:
            separation_plan[lang_file] = {
                'filled': filled_translations,
                'empty': empty_translations,
                'total': len(translations)
            }
            total_empty_count += len(empty_translations)
        
        print(f"  - {os.path.basename(lang_file)}: {len(filled_translations)} filled, {len(empty_translations)} empty")
    
    if not separation_plan:
        print("\nNo empty translations found in any language files!")
        return
    
    # Show separation preview
    print(f"\nSeparation Preview:")
    print("-" * 60)
    for lang_file, plan in separation_plan.items():
        print(f"{os.path.basename(lang_file)}:")
        print(f"  - {len(plan['filled'])} filled translations (will stay at top)")
        print(f"  - {len(plan['empty'])} empty translations (will move to bottom)")
        
        # Show first few empty keys as preview
        empty_keys = list(plan['empty'].keys())
        for key in empty_keys[:3]:
            print(f"    └─ {key}")
        if len(empty_keys) > 3:
            print(f"    └─ ... and {len(empty_keys) - 3} more")
        print()
    
    print(f"Total empty translations to move: {total_empty_count}")
    
    # Confirm separation
    confirm = input(f"Do you want to separate empty translations in all language files? (y/N): ").strip().lower()
    
    if confirm in ['y', 'yes']:
        print(f"\nSeparating empty translations...")
        
        for lang_file, plan in separation_plan.items():
            # Create new ordered dictionary: filled first, then empty
            reordered_translations = {}
            
            # Add filled translations first (maintain original order)
            reordered_translations.update(plan['filled'])
            
            # Add empty translations at the bottom (sorted alphabetically)
            for key in sorted(plan['empty'].keys()):
                reordered_translations[key] = ""
            
            # Write back to file
            with open(lang_file, 'w', encoding='utf-8') as json_file:
                json.dump(reordered_translations, json_file, ensure_ascii=False, indent=4)
            
            print(f"  - Separated {len(plan['empty'])} empty translations in {os.path.basename(lang_file)}")
        
        print(f"\nSuccessfully separated empty translations in {len(separation_plan)} language files.")
    else:
        print("Operation cancelled.")

def sync_translations():
    print("\n" + "="*60)
    print("              SYNCING TRANSLATIONS")
    print("="*60)
    
    # Get all language files
    language_files = get_language_files()
    if not language_files:
        print("No language files found in lang/ directory.")
        return
    
    print(f"\nFound {len(language_files)} language files:")
    for lang_file in language_files:
        print(f"  - {os.path.basename(lang_file)}")
    
    # Load all translations and collect unique keys
    all_translations = {}
    all_unique_keys = set()
    
    print(f"\nAnalyzing translation files...")
    for lang_file in language_files:
        translations = load_existing_translations(lang_file)
        all_translations[lang_file] = translations
        all_unique_keys.update(translations.keys())
        print(f"  - {os.path.basename(lang_file)}: {len(translations)} keys")
    
    print(f"\nTotal unique keys across all files: {len(all_unique_keys)}")
    
    # Calculate what needs to be added to each file
    sync_plan = {}
    total_keys_to_add = 0
    
    for lang_file in language_files:
        current_keys = set(all_translations[lang_file].keys())
        missing_keys = all_unique_keys - current_keys
        if missing_keys:
            sync_plan[lang_file] = sorted(missing_keys)
            total_keys_to_add += len(missing_keys)
    
    if not sync_plan:
        print("\nAll language files are already synchronized!")
        return
    
    # Show sync preview
    print(f"\nSync Preview:")
    print("-" * 60)
    for lang_file, missing_keys in sync_plan.items():
        print(f"{os.path.basename(lang_file)}: {len(missing_keys)} missing keys")
        for key in missing_keys[:5]:  # Show first 5 keys
            print(f"  + {key}")
        if len(missing_keys) > 5:
            print(f"  ... and {len(missing_keys) - 5} more")
        print()
    
    print(f"Total keys to add: {total_keys_to_add}")
    
    # Confirm sync
    confirm = input(f"Do you want to sync all language files? (y/N): ").strip().lower()
    
    if confirm in ['y', 'yes']:
        print(f"\nSyncing translations...")
        
        for lang_file, missing_keys in sync_plan.items():
            # Add missing keys with empty string values
            for key in missing_keys:
                all_translations[lang_file][key] = ""
            
            # Write back to file
            with open(lang_file, 'w', encoding='utf-8') as json_file:
                json.dump(all_translations[lang_file], json_file, ensure_ascii=False, indent=4)
            
            print(f"  - Added {len(missing_keys)} keys to {os.path.basename(lang_file)}")
        
        print(f"\nSuccessfully synchronized {len(sync_plan)} language files.")
    else:
        print("Operation cancelled.")

def sort_translations():
    print("\n" + "="*60)
    print("               SORTING TRANSLATIONS")
    print("="*60)
    
    # Get all language files
    language_files = get_language_files()
    if not language_files:
        print("No language files found in lang/ directory.")
        return
    
    print(f"\nFound {len(language_files)} language files:")
    for lang_file in language_files:
        print(f"  - {os.path.basename(lang_file)}")
    
    confirm = input(f"\nDo you want to sort all {len(language_files)} language files alphabetically? (y/N): ").strip().lower()
    
    if confirm in ['y', 'yes']:
        sorted_count = 0
        print(f"\nSorting translations...")
        
        for lang_file in language_files:
            existing_translations = load_existing_translations(lang_file)
            if existing_translations:
                # Sort the dictionary by keys
                sorted_translations = dict(sorted(existing_translations.items()))
                
                # Write back to file
                with open(lang_file, 'w', encoding='utf-8') as json_file:
                    json.dump(sorted_translations, json_file, ensure_ascii=False, indent=4)
                
                print(f"  - Sorted {len(sorted_translations)} translations in {os.path.basename(lang_file)}")
                sorted_count += 1
        
        print(f"\nSuccessfully sorted {sorted_count} language files.")
    else:
        print("Operation cancelled.")

def main():
    print_header()
    
    while True:
        print("Please select an option:")
        print("1. Extract new translations")
        print("2. Remove unused translations")
        print("3. Sort translations alphabetically")
        print("4. Sync translations across all languages")
        print("5. Separate empty translations to bottom")
        print("6. Exit")
        print()
        
        choice = input("Enter your choice (1-6): ").strip()
        
        if choice == '1':
            extract_translations()
        elif choice == '2':
            remove_unused()
        elif choice == '3':
            sort_translations()
        elif choice == '4':
            sync_translations()
        elif choice == '5':
            separate_empty_translations()
        elif choice == '6':
            print("\nGoodbye!")
            sys.exit(0)
        else:
            print("Invalid choice. Please enter 1, 2, 3, 4, 5, or 6.")
        
        print("\n" + "="*60)
        input("Press Enter to continue...")
        print()

if __name__ == "__main__":
    main()