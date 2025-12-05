import os
import glob
import sys

def find_and_show_file(basename):
    # Search recursively in 'app' folder
    search_path = os.path.join("../../app", "**", basename)
    matched_files = glob.glob(search_path, recursive=True)

    if not matched_files:
        print(f"No file found with basename '{basename}' in app/")
        return

    # Take the first match
    file_path = matched_files[0]
    print(f"Found file: {file_path}\n")
    print("-" * 50)

    # Print file content
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()
        print(content)

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python show_file.py <basename>")
        sys.exit(1)

    basename = sys.argv[1]
    find_and_show_file(basename)
