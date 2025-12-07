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
INPUT_DIR = os.path.join(PROJECT_ROOT, "tests/Integration-Testing")
OUTPUT_DIR = os.path.join(PROJECT_ROOT, "tests/results")
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
    
    # If no capitals found, we might want to just uppercase the first 3 chars
    if not capitals:
        capitals = base[:3].upper()
    
    return capitals


async def generate_integration_test_cases(file_path: str, code: str, test_prefix: str, output_path: str) -> bool:
    """Generate Integration test cases using OpenAI API."""
    if not client:
        print("❌ OpenAI client not initialized.")
        return False

    # Note: Structure of ID is TC-{test_prefix}-001
    # test_prefix passed in will already contain "Adm-UT" or "Cust-PT" or just "AT"
    prompt = f"""
Analyze the following PHP Integration Test file and generate detailed integration-test documentation for each test case. Follow the IEEE-829-2008 (Software Test Documentation Standard) concepts, but use the merged integration-testing format defined below.

You must infer meaningful scenario names and the real business behavior validated by each test (not just the test function name). If the test contains mocks or stubs, the documentation should still describe the expected integrated behavior at the system level.

For EACH test() or it() function in the file, generate the following output block:

Integration-Testing:
Test ID
TC-{test_prefix}-001 (increment sequentially per test case)
Title
[Brief title describing what the test verifies]
Objective
[Purpose of this integration test, e.g., validate interaction between API and DB]
Preconditions

[Application running / required environment]

[Database seeded / API accessible]

[Any other required setup]
Test Data

[Inputs used in the test, e.g., request payload, credentials]

[Expected values used for validation]
Steps

[Step 1: action performed, e.g., send POST request]

[Step 2: next action, e.g., verify response]

[Step 3: any additional validation, DB checks, etc.]
Expected Result
[Expected outcome: HTTP status, JSON response, DB changes, side effects]
Actual Result
[Same as Expected Result if test passes]
Status
Pass / Fail
Severity
High / Medium / Low

========================================

IMPORTANT RULES:

1. Produce one block per test() or it() function.

2. Increment the Test Case ID sequentially (001, 002, 003 …).

3. The format must match exactly; do not add or remove headings.

4. Do not use Markdown code blocks or include PHP code in the output.

5. Expected Result must combine all assertions into a single clear outcome.

6. Assume Actual Result matches Expected Result since all tests pass.

7. Include meaningful Title and Objective describing the business scenario.

8. Include any Preconditions or environment setup necessary to execute the test.

9. Fill Test Data with input values and expected values relevant for integration verification.

10. Use Severity to indicate the importance of the test (e.g., High for login, Medium for optional APIs, Low for minor endpoints).

FILE TO ANALYZE:
{code}
    """

    # Estimate tokens before trimming
    initial_tokens = estimate_tokens(prompt)
    
    # Trim if needed
    prompt = trim_prompt_to_limit(prompt)

    print(f"🔧 Sending {os.path.basename(file_path)} to OpenAI...")

    try:
        response = await asyncio.to_thread(
            lambda: client.complete(
                messages=[
                    SystemMessage("You are a QA automation expert specializing in Integration Testing documentation."),
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

    # Save to output folder (path passed in)
    async with aiofiles.open(output_path, "w", encoding="utf-8") as f:
        await f.write(test_cases)

    print(f"✅ Test cases saved: {output_path}")
    return True


async def main():
    # Recursively find all PHP files
    php_test_files = glob.glob(f"{INPUT_DIR}/**/*.php", recursive=True)

    if not php_test_files:
        print(f"⚠️ No PHP test files found in {INPUT_DIR}")
        return

    print(f"📁 Found {len(php_test_files)} test files in {INPUT_DIR}")
    processed = 0
    skipped = 0

    for file_path in php_test_files:
        # Determine relative path and directory structure
        # e.g., tests/Integration-Testing/Admin/UserTest.php -> Admin/UserTest.php
        rel_path = os.path.relpath(file_path, INPUT_DIR)
        dir_name = os.path.dirname(rel_path) # "Admin" or "Customer" or ""
        filename = os.path.basename(file_path)

        # 1. Output Directory Logic -> Mirror Structure
        target_dir = os.path.join(OUTPUT_DIR, dir_name)
        os.makedirs(target_dir, exist_ok=True)
        
        # Output filename
        base_name = filename.replace('.php', '.txt')
        output_path = os.path.join(target_dir, base_name)
        
        # 2. Skip Logic -> check if output file exists in the MIRRORED location
        if os.path.exists(output_path):
            print(f"⏭️ Skipping {rel_path}: already processed")
            skipped += 1
            continue

        print(f"\n🔍 Processing: {file_path}")
        
        # 3. Prefix Logic
        initials = extract_initials(filename)
        
        # Normalize for checking "Admin" or "Customer"
        # dir_name might be "Admin" or "Admin\Subfolder" on windows
        normalized_dir = dir_name.replace('\\', '/')
        
        if "Admin" in normalized_dir:
            test_prefix = f"Adm-{initials}"
        elif "Customer" in normalized_dir:
            test_prefix = f"Cust-{initials}"
        else:
            # Root file or other folder -> No extra prefix, just initials
            test_prefix = initials

        print(f"   Structure: {dir_name if dir_name else '(Root)'}")
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

        # Generate Test Cases
        try:
            # We pass the calculated test_prefix and the specific output_path
            success = await generate_integration_test_cases(file_path, code, test_prefix, output_path)
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
        print("FATAL: NEWER_TOKEN is missing.")
        print("Please set NEWER_TOKEN in your .env file")
    else:
        asyncio.run(main())
