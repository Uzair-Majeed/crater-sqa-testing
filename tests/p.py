import os

# Path to your Unit-Testing folder
TEST_FOLDER = r"E:\BS Software Engineering\Semester V\SQE\SQE_Project\crater-sqa-testing\tests\Unit-Testing"

# The afterEach block to append
AFTER_EACH_BLOCK = "afterEach(function () {\n    Mockery::close();\n});"

for root, dirs, files in os.walk(TEST_FOLDER):
    for file in files:
        if file.endswith(".php"):
            file_path = os.path.join(root, file)

            with open(file_path, "r", encoding="utf-8") as f:
                content = f.read()

            # Only append if the block doesn't already exist
            if "afterEach" not in content:
                with open(file_path, "a", encoding="utf-8") as f:
                    f.write("\n" + AFTER_EACH_BLOCK + "\n")
                print(f"Appended afterEach block to: {file_path}")
            else:
                print(f"afterEach block already exists in: {file_path}")
