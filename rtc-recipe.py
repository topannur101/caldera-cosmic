import csv

# Open the input CSV file
with open('rtc-recipe-input.csv', 'r') as csv_file:
    csv_reader = csv.DictReader(csv_file)

    # Open the output TXT file
    with open('rtc-recipe-output.txt', 'w') as txt_file:
        # Iterate over each row in the CSV file
        for row in csv_reader:
            # Convert string values to appropriate data types
            row = {k: (float(v) / 100 if k in ['std_min', 'std_max', 'std_mid', 'scale', 'pfc_min', 'pfc_max'] else int(v) if v.isdigit() else v) for k, v in row.items()}

            # Write the row to the TXT file in the desired format
            txt_file.write('[\n')
            for key, value in row.items():
                txt_file.write(f"'{key}' => {repr(value)},\n")
            txt_file.write('],\n')