import glob

php_files = glob.glob("../app/**/*.php", recursive=True)
print(f"Total PHP files found: {len(php_files)}")

# Optional: print file names
for f in php_files:
    print(f)
