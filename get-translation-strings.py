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

def create_en_json(translation_strings, output_path):
    translations = {string: "" for string in translation_strings}
    with open(output_path, 'w', encoding='utf-8') as json_file:
        json.dump(translations, json_file, ensure_ascii=False, indent=4)

# Define directories and output file
views_directory = 'resources/translate'
output_file = 'en.json'

# Extract translation strings from both directories
blade_strings = scan_directory(views_directory, '.blade.php')

# Create en.json file
create_en_json(blade_strings, output_file)

print(f"en.json file created with {len(blade_strings)} translation strings.")
