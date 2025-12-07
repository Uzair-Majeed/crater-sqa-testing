import os
import glob
import aiofiles
import asyncio
import re
from dotenv import load_dotenv
from azure.ai.inference import ChatCompletionsClient
from azure.ai.inference.models import SystemMessage, UserMessage
from azure.core.credentials import AzureKeyCredential

# ------------------ CONFIG ------------------
MODEL_NAME = "openai/gpt-4.1"
RATE_LIMIT_SECONDS = 6        # ~10 requests per minute
# Adjust paths to be relative to project root (1 level up)
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
INPUT_DIR = os.path.join(PROJECT_ROOT, "tests/Unit-Testing")
OUTPUT_DIR = os.path.join(PROJECT_ROOT, "tests/results-openai")
MAX_TOKENS_ALLOWED = 8000     # Hard limit before trimming
TRIMMED_TARGET = 7800         # Target tokens after trimming

# Ensure output folder exists
os.makedirs(OUTPUT_DIR, exist_ok=True)

# Load API key from .env in project root
dotenv_path = os.path.join(PROJECT_ROOT, '.env')
load_dotenv(dotenv_path)
API_KEY = os.getenv("TOKEN_2")
ENDPOINT = "https://models.github.ai/inference"

# Initialize OpenAI client
client = ChatCompletionsClient(
    endpoint=ENDPOINT,
    credential=AzureKeyCredential(API_KEY),
) if API_KEY else None


# ------------------ TOKEN COUNTER ------------------
def estimate_tokens(text: str) -> int:
    """Rough token estimator."""
    return int(len(text) / 3.5)


def trim_prompt_to_limit(prompt: str) -> str:
    """Trim prompt to ~7800 tokens and append notice."""
    tokens = estimate_tokens(prompt)
    if tokens <= MAX_TOKENS_ALLOWED:
        return prompt  # no trimming needed

    print(f"⚠️ Prompt too long ({tokens} tokens). Trimming to {TRIMMED_TARGET} tokens...")

    # Approximate character cutoff to reach target tokens
    target_chars = int(TRIMMED_TARGET * 3.5)

    trimmed = prompt[:target_chars].rstrip()

    trimmed += "\n\n[PROMPT TOO LONG — TRIMMED, ANALYZE WHAT YOU CAN]\n"

    return trimmed


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
    """Generate IEEE-format test cases using OpenAI API."""
    if not client:
        print("❌ OpenAI client not initialized.")
        return False

    prompt = f"""
Analyze the following PHP Pest test file and generate IEEE 829-2008 standard test case documentation.

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

Note: All test cases pass actually, so the Actual Result should match Expected Result.make something up that fits.
FILE TO ANALYZE:
{code}

Generate the IEEE test case documentation now:
"""

    # Estimate tokens before trimming
    initial_tokens = estimate_tokens(prompt)
    print(f"🔢 Estimated prompt tokens: {initial_tokens}")

    # Trim if needed
    prompt = trim_prompt_to_limit(prompt)

    trimmed_tokens = estimate_tokens(prompt)
    print(f"✂️ Tokens AFTER trimming: {trimmed_tokens}")

    print(f"🔧 Sending {os.path.basename(file_path)} to OpenAI...")

    try:
        response = await asyncio.to_thread(
            lambda: client.complete(
                messages=[
                    SystemMessage("You are a software testing expert specializing in IEEE 829-2008 test case documentation."),
                    UserMessage(prompt)
                ],
                model=MODEL_NAME
            )
        )
    except Exception as e:
        print(f"❌ OpenAI API Error: {e}")
        return False

    try:
        test_cases = response.choices[0].message.content.strip()
    except:
        print("⚠️ Empty response from API.")
        return False

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

        if not code.strip():
            print(f"⏭️ Empty file, skipping.")
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
        print("FATAL: UZAIR_OPEN_AI_API_KEY_5 is missing.")
        print("Please set UZAIR_OPEN_AI_API_KEY_5 in your .env file")
    else:
        asyncio.run(main())
