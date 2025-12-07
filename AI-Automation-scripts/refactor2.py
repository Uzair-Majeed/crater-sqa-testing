import os
import glob
import aiofiles
import asyncio
import subprocess
from dotenv import load_dotenv
from google import genai

# ------------------ CONFIG ------------------
MODEL_NAME = "gemini-2.5-flash"
RATE_LIMIT_SECONDS = 6        # ~10 requests per minute
OUTPUT_DIR = "../tests/sample"
OUTPUT_DIR_2 = "../tests/newfolder"

# Ensure output folders exist
os.makedirs(OUTPUT_DIR, exist_ok=True)
os.makedirs(OUTPUT_DIR_2, exist_ok=True)

# Load API key
load_dotenv()
API_KEY = os.getenv("UZAIR_GOOGLE_GEMINI_API_KEY_2")

# Initialize Gemini client
client = genai.Client(api_key=API_KEY) if API_KEY else None

# ------------------ FUNCTIONS ------------------
async def run_pest(file_path: str) -> str:
    """Run Pest on a given PHP test file and return stdout."""
    try:
        result = subprocess.run(
            ["php", "-d", "memory_limit=2000M", "vendor/bin/pest", file_path],
            capture_output=True,
            text=True,
            timeout=60
        )
        print(f"🛠️ Pest run completed for {file_path} (exit {result.returncode})")
        return result.stdout
    except subprocess.TimeoutExpired:
        print(f"⏭️ Pest exceeded 60 seconds, skipping.")
        return "Pest run timed out."
    except Exception as e:
        return f"ERROR running Pest: {e}"


async def generate_fixed_test(file_path: str, code: str, pest_output: str) -> bool:
    """Generate fixed Pest tests using Gemini 2.5 Flash."""
    if not client:
        print("❌ Gemini client not initialized.")
        return False

    prompt = f"""
You are an expert Laravel/PHP developer and Pest testing specialist. 
You are given a PHP Pest unit test file and its debug output. Your task is to **fix all failing tests and errors** while preserving passing tests and existing test logic.

Application Context:
- Laravel project with controllers, services, and resources.
- Pest PHP tests, often using Mockery for mocking dependencies.
- Common errors: Mockery exceptions, config/facade access issues, JsonResource or collection null errors, syntax/runtime errors.

Constraints:
1. Do not remove tests unless clearly broken.
2. Preserve original test names, descriptions, and structure.
3. Fix syntax errors, Mockery/facade usage issues, and logical test problems.
4. Ensure proper Pest syntax, mocking/stubbing, and test isolation.
5. Do not modify production code.
6. Include proper assertions, branch coverage, and edge cases where necessary.
7. Replace broken mocks/stubs correctly.
8. Ensure all tests pass after rewriting.
9. Avoid unnecessary libraries or calls with no effect.
10. Return ONLY the complete PHP file content with fixes; no explanations or extra text.

FILE CONTENT:
{code}

PEST DEBUG OUTPUT:
{pest_output}
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

    test_code = response.text.strip()
    if not test_code:
        print("⚠️ Model returned empty content.")
        return False

    # Save to first output folder
    fixed_path = os.path.join(OUTPUT_DIR, os.path.basename(file_path))
    os.makedirs(os.path.dirname(fixed_path), exist_ok=True)
    async with aiofiles.open(fixed_path, "w", encoding="utf-8") as f:
        await f.write(test_code)

    # Save to second output folder
    fixed_path2 = os.path.join(OUTPUT_DIR_2, os.path.basename(file_path))
    os.makedirs(os.path.dirname(fixed_path2), exist_ok=True)
    async with aiofiles.open(fixed_path2, "w", encoding="utf-8") as f:
        await f.write(test_code)

    print(f"✅ Fixed file saved: {fixed_path}")
    return True


async def main():
    php_test_files = glob.glob("tests/Unit/**/*.php", recursive=True)

    if not php_test_files:
        print("⚠️ No PHP test files found.")
        return

    for file_path in php_test_files:

        # --- SKIP LOGIC: Skip if basename exists in OUTPUT_DIR ---
        basename = os.path.basename(file_path)
        sample_path = os.path.join(OUTPUT_DIR, basename)
        if os.path.exists(sample_path):
            print(f"⏭️ Skipping {file_path}: already processed in {OUTPUT_DIR}")
            continue

        print(f"\n🔍 Processing: {file_path}")

        # Read PHP test file
        try:
            async with aiofiles.open(file_path, "r", encoding="utf-8") as f:
                code = await f.read()
        except Exception as e:
            print(f"❌ Cannot read file: {e}")
            continue

        pest_output = await run_pest(file_path)

        try:
            await generate_fixed_test(file_path, code, pest_output)
        except asyncio.TimeoutError:
            print(f"⛔ GPT FIX TIMED OUT, skipping this file.")
            continue

        print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s for rate limit...")
        await asyncio.sleep(RATE_LIMIT_SECONDS)

    print("\n🎉 All test files processed.")


# ------------------ ENTRY POINT ------------------
if __name__ == "__main__":
    if not API_KEY:
        print("FATAL: GOOGLE GEMINI API KEY is missing.")
    else:
        asyncio.run(main())
