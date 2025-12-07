import os

# Configuration
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
INPUT_DIR = os.path.join(PROJECT_ROOT, "tests/results-openai")
OUTPUT_FILE = os.path.join(PROJECT_ROOT, "combined_unit_test_results.txt")

def merge_files():
    # Check if input directory exists
    if not os.path.exists(INPUT_DIR):
        print(f"Error: Directory '{INPUT_DIR}' not found.")
        return

    # Get list of files
    try:
        files = [f for f in os.listdir(INPUT_DIR) if os.path.isfile(os.path.join(INPUT_DIR, f))]
        files.sort() # Sort alphabetically for consistent output
    except Exception as e:
        print(f"Error listing files: {e}")
        return

    count = 0
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as outfile:
        for filename in files:
            file_path = os.path.join(INPUT_DIR, filename)
            try:
                with open(file_path, 'r', encoding='utf-8') as infile:
                    content = infile.read()
                    
                    # Write header with filename and newlines
                    outfile.write("\n" * 3) # Some new lines as requested
                    outfile.write("=" * 50 + "\n")
                    outfile.write(f"FILE: {filename}\n")
                    outfile.write("=" * 50 + "\n")
                    outfile.write("\n")
                    
                    # Write content
                    outfile.write(content)
                    
                    # Trailing newline to separate from next header if needed
                    outfile.write("\n")
                    
                    print(f"Processed: {filename}")
                    count += 1
            except Exception as e:
                print(f"Error reading {filename}: {e}")

    print(f"\nSuccessfully merged {count} files into '{OUTPUT_FILE}'")

if __name__ == "__main__":
    merge_files()
