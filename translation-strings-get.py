import os
import re
import json

def extract_translation_strings(file_path):
    pattern = re.compile(r'__\(\s*[\'"]([^\'"]+)[\'"]\s*\)')
    with open(file_path, 'r', encoding='utf-8') as file:
        content = file.read()
    return pattern.findall(content)

def scan_directory(directory, file_extension):
    translation_strings = set()
    for root, _, files in os.walk(directory):
        for file in files:
            if file.endswith(file_extension):
                file_path = os.path.join(root, file)
                translation_strings.update(extract_translation_strings(file_path))
    return translation_strings

def load_existing_translations(file_path):
    if os.path.exists(file_path):
        with open(file_path, 'r', encoding='utf-8') as json_file:
            return json.load(json_file)
    return {}

def load_exclusion_list(file_path):
    if os.path.exists(file_path):
        with open(file_path, 'r', encoding='utf-8') as json_file:
            return set(json.load(json_file))
    return set()

def create_en_json(translation_strings, existing_translations, exclusions, output_path):
    for string in translation_strings:
        if string not in existing_translations and string not in exclusions:
            existing_translations[string] = ""
    with open(output_path, 'w', encoding='utf-8') as json_file:
        json.dump(existing_translations, json_file, ensure_ascii=False, indent=4)

# Define directories and output file
views_directory = 'resources/'
output_file = 'lang/en.json'
exclusion_file = 'translation-strings-exception.json'

# Load existing translations and exclusion list
existing_translations = load_existing_translations(output_file)
exclusions = load_exclusion_list(exclusion_file)

# Extract translation strings from the directory
blade_strings = scan_directory(views_directory, '.blade.php')

# Create en.json file with new strings excluding those in the exclusion list
create_en_json(blade_strings, existing_translations, exclusions, output_file)

print(f"en.json file updated with {len(blade_strings)} new translation strings, excluding exceptions.")
