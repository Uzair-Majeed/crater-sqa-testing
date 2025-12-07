import os

# Configuration
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
INPUT_DIR = os.path.join(PROJECT_ROOT, "tests/results")
OUTPUT_FILE = os.path.join(PROJECT_ROOT, "combined_integration_test_results.txt")

def merge_files():
    # Check if input directory exists
    if not os.path.exists(INPUT_DIR):
        print(f"Error: Directory '{INPUT_DIR}' not found.")
        return

    # Gather files recursively
    file_list = []
    try:
        for root, dirs, files in os.walk(INPUT_DIR):
            for file in files:
                if file.endswith(".txt"):
                    # Store relative path for sorting and display
                    full_path = os.path.join(root, file)
                    rel_path = os.path.relpath(full_path, INPUT_DIR)
                    file_list.append(rel_path)
        
        file_list.sort() # Sort alphabetically for consistent output
    except Exception as e:
        print(f"Error walking directory: {e}")
        return

    count = 0
    with open(OUTPUT_FILE, 'w', encoding='utf-8') as outfile:
        for rel_path in file_list:
            file_path = os.path.join(INPUT_DIR, rel_path)
            try:
                with open(file_path, 'r', encoding='utf-8') as infile:
                    content = infile.read()
                    
                    # Write header with relative filename and newlines
                    outfile.write("\n" * 3) 
                    outfile.write("=" * 50 + "\n")
                    outfile.write(f"FILE: {rel_path}\n")
                    outfile.write("=" * 50 + "\n")
                    outfile.write("\n")
                    
                    # Write content
                    outfile.write(content)
                    
                    # Trailing newline to separate from next header if needed
                    outfile.write("\n")
                    
                    print(f"Processed: {rel_path}")
                    count += 1
            except Exception as e:
                print(f"Error reading {rel_path}: {e}")

    print(f"\nSuccessfully merged {count} files into '{OUTPUT_FILE}'")

if __name__ == "__main__":
    merge_files()
