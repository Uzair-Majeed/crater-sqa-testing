import os
import glob
import aiofiles
import asyncio
import re
from dotenv import load_dotenv
from google import genai

# ------------------ CONFIG ------------------
MODEL_NAME = "gemini-2.0-flash-exp"
RATE_LIMIT_SECONDS = 6        # ~10 requests per minute
INPUT_DIR = "tests/Unit-Testing"
OUTPUT_DIR = "tests/results"

# Ensure output folder exists
os.makedirs(OUTPUT_DIR, exist_ok=True)

# Load API key
load_dotenv()
API_KEY = os.getenv("HUSSNAIN_GOOGLE_GEMINI_API_KEY")

# Initialize Gemini client
client = genai.Client(api_key=API_KEY) if API_KEY else None


# ------------------ FUNCTIONS ------------------
def extract_initials(filename: str) -> str:
    """
    Extract capital letters from filename to create test case prefix.
    Example: 'CustomerController-Test.php' -> 'CCT'
    """
    # Remove .php extension and split by dash/underscore
    base = filename.replace('.php', '').replace('-Test', '')
    
    # Extract capital letters
    capitals = ''.join([c for c in base if c.isupper()])
    
    # If no capitals found, use first 3 chars
    if not capitals:
        capitals = base[:3].upper()
    
    return capitals


async def generate_ieee_test_cases(file_path: str, code: str, test_prefix: str) -> bool:
    """Generate IEEE-format test cases using Gemini API."""
    if not client:
        print("❌ Gemini client not initialized.")
        return False

    prompt = f"""
You are a software testing expert. Analyze the following PHP Pest test file and generate IEEE 829-2008 standard test case documentation.

For EACH test case in the file, generate the following format:

================================================================================
Test Case ID: {test_prefix}-001
Title: [Brief title of what the test does]
Objective: [What this test verifies]
Preconditions:
- Application running
- Database seeded
- [Any other preconditions]

Test Steps:
1. [Step 1]
2. [Step 2]
3. [Step 3]

Test Data:
- [Input data used]
- [Expected values]

Expected Result:
[What should happen]

Actual Result:
(To be filled after execution)

Status:
Pass / Fail

Severity:
High / Medium / Low

================================================================================

IMPORTANT RULES:
1. Generate ONE test case block for EACH test() or it() function in the code
2. Increment the test case number (001, 002, 003, etc.) for each test
3. Use the prefix "{test_prefix}" for all test case IDs
4. Be specific and detailed in test steps
5. Extract actual test data from the code
6. Determine severity based on what's being tested (auth/payment = High, UI = Medium, etc.)
7. Keep format EXACTLY as shown above
8. Separate each test case with the === line
9. Do NOT include code, only documentation
10. If a test has multiple assertions, list them as separate expected results

FILE TO ANALYZE:
{code}

Generate the IEEE test case documentation now:
"""

    try:
        response = await asyncio.to_thread(
            lambda: client.models.generate_content(
                model=MODEL_NAME,
                contents=prompt
            )
        )
    except Exception as e:
        print(f"❌ Gemini API Error: {e}")
        return False

    test_cases = response.text.strip()
    if not test_cases:
        print("⚠️ Model returned empty content.")
        return False

    # Save to output folder
    base_name = os.path.basename(file_path).replace('.php', '.txt')
    output_path = os.path.join(OUTPUT_DIR, base_name)
    
    async with aiofiles.open(output_path, "w", encoding="utf-8") as f:
        await f.write(test_cases)

    print(f"✅ Test cases saved: {output_path}")
    return True


async def main():
    php_test_files = glob.glob(f"{INPUT_DIR}/**/*.php", recursive=True)

    if not php_test_files:
        print(f"⚠️ No PHP test files found in {INPUT_DIR}")
        return

    print(f"📁 Found {len(php_test_files)} test files")
    processed = 0
    skipped = 0

    for file_path in php_test_files:
        # --- SKIP LOGIC: Skip if output file already exists ---
        base_name = os.path.basename(file_path).replace('.php', '.txt')
        output_path = os.path.join(OUTPUT_DIR, base_name)
        
        if os.path.exists(output_path):
            print(f"⏭️ Skipping {file_path}: already processed")
            skipped += 1
            continue

        print(f"\n🔍 Processing: {file_path}")

        # Extract test case prefix from filename
        filename = os.path.basename(file_path)
        test_prefix = extract_initials(filename)
        print(f"   Test Prefix: {test_prefix}")

        # Read PHP test file
        try:
            async with aiofiles.open(file_path, "r", encoding="utf-8") as f:
                code = await f.read()
        except Exception as e:
            print(f"❌ Cannot read file: {e}")
            continue

        # Generate IEEE test cases
        try:
            success = await generate_ieee_test_cases(file_path, code, test_prefix)
            if success:
                processed += 1
        except asyncio.TimeoutError:
            print(f"⛔ API call timed out, skipping this file.")
            continue
        except Exception as e:
            print(f"❌ Error processing file: {e}")
            continue

        # Rate limiting
        print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s for rate limit...")
        await asyncio.sleep(RATE_LIMIT_SECONDS)

    print(f"\n🎉 Processing complete!")
    print(f"   ✅ Processed: {processed} files")
    print(f"   ⏭️ Skipped: {skipped} files")
    print(f"   📁 Output directory: {OUTPUT_DIR}")


# ------------------ ENTRY POINT ------------------
if __name__ == "__main__":
    if not API_KEY:
        print("FATAL: GOOGLE GEMINI API KEY is missing.")
        print("Please set UZAIR_GOOGLE_GEMINI_API_KEY_2 in your .env file")
    else:
        asyncio.run(main())
