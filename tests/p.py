import os
import glob

# Path to your Unit-Testing folder
folder_path = "./Unit-Testing"

# Recursively find all PHP files
php_files = glob.glob(os.path.join(folder_path, "**", "*.php"), recursive=True)

for file_path in php_files:
    with open(file_path, "r", encoding="utf-8") as f:
        content = f.read()

    # Skip if file already has 'uses(\Mockery::class);'
    if "uses(\\Mockery::class);" in content:
        continue

    # Replace 'use Mockery;' with 'uses(\Mockery::class);'
    if "use Mockery;" in content:
        new_content = content.replace("use Mockery;", "uses(\\Mockery::class);")
        with open(file_path, "w", encoding="utf-8") as f:
            f.write(new_content)
        print(f"Updated: {file_path}")
