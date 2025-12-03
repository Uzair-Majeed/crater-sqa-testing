import os
import glob
import aiofiles
import asyncio
import subprocess
import time
from dotenv import load_dotenv
from azure.ai.inference import ChatCompletionsClient
from azure.ai.inference.models import SystemMessage, UserMessage
from azure.core.credentials import AzureKeyCredential

# ------------------ CONFIG ------------------
MODEL_NAME = "openai/gpt-4.1"
RATE_LIMIT_SECONDS = 6
OUTPUT_DIR = "tests/sample"
OUTPUT_DIR_2 = "tests/newfolder"
MAX_ITERATION_TIME = 90           # 1.5 minutes
MAX_TOKENS_ALLOWED = 8000         # Hard limit before trimming
TRIMMED_TARGET = 7800             # Target tokens after trimming

# Ensure output folder exists
os.makedirs(OUTPUT_DIR, exist_ok=True)

load_dotenv()
API_KEY = os.getenv("UZAIR_OPEN_AI_API_KEY_5")
ENDPOINT = "https://models.github.ai/inference"

client = ChatCompletionsClient(
    endpoint=ENDPOINT,
    credential=AzureKeyCredential(API_KEY),
) if API_KEY else None


# ------------------ TOKEN COUNTER ------------------
def estimate_tokens(text: str) -> int:
    """Rough token estimator."""
    return int(len(text) / 3.5)


# ------------------ TRIMMER ------------------
def trim_prompt_to_limit(prompt: str) -> str:
    """Trim prompt to ~7800 tokens and append notice."""
    tokens = estimate_tokens(prompt)
    if tokens <= MAX_TOKENS_ALLOWED:
        return prompt  # no trimming needed

    print(f"⚠️ Prompt too long ({tokens} tokens). Trimming to {TRIMMED_TARGET} tokens...")

    # Approximate character cutoff to reach target tokens
    target_chars = int(TRIMMED_TARGET * 3.5)

    trimmed = prompt[:target_chars].rstrip()

    trimmed += "\n\n[PROMPT TOO LONG — TRIMMED, FIGURE OUT YOURSELF]\n"

    return trimmed


# ------------------ FUNCTIONS ------------------
async def run_pest(file_path: str) -> str:
    """Run Pest on file."""
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
    if not client:
        print("❌ Client not initialized.")
        return False

    # Build prompt
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

    # Estimate tokens before trimming
    initial_tokens = estimate_tokens(prompt)
    print(f"🔢 Estimated prompt tokens: {initial_tokens}")

    # Trim if needed
    prompt = trim_prompt_to_limit(prompt)

    trimmed_tokens = estimate_tokens(prompt)
    print(f"✂️ Tokens AFTER trimming: {trimmed_tokens}")

    print(f"🔧 Sending {file_path} to OpenAI...")

    try:
        response = await asyncio.to_thread(
            lambda: client.complete(
                messages=[
                    SystemMessage("You are an expert Laravel/PHP developer and Pest testing specialist."),
                    UserMessage(prompt)
                ],
                model=MODEL_NAME
            )
        )
    except Exception as e:
        print(f"❌ OpenAI Error: {e}")
        return False

    try:
        fixed_code = response.choices[0].message.content.strip()
    except:
        print("⚠️ Empty response.")
        return False

    if not fixed_code:
        print("⚠️ Model returned empty code.")
        return False

    # Save output file
    fixed_path = os.path.join(OUTPUT_DIR, os.path.basename(file_path))
    async with aiofiles.open(fixed_path, "w", encoding="utf-8") as f:
        await f.write(fixed_code)

    
    fixed_path_2 = os.path.join(OUTPUT_DIR_2, os.path.basename(file_path))
    async with aiofiles.open(fixed_path_2, "w", encoding="utf-8") as f:
        await f.write(fixed_code)     

    print(f"✅ Fixed file saved: {fixed_path}")
    return True


# ------------------ MAIN ------------------
async def main():
    php_test_files = glob.glob("tests/Unit/**/*.php", recursive=True)

    if not php_test_files:
        print("⚠️ No PHP test files found.")
        return

    for file_path in php_test_files:


        # Skip if already processed
        sample_path = os.path.join(OUTPUT_DIR, os.path.basename(file_path))
        if os.path.exists(sample_path):
            print(f"⏭️ Already processed: {sample_path}")
            continue

        print(f"\n🔍 Processing: {file_path}")

        # Read source test file
        try:
            async with aiofiles.open(file_path, "r", encoding="utf-8") as f:
                code = await f.read()
        except Exception as e:
            print(f"❌ Cannot read file: {e}")
            continue

        if not code.strip():
            print(f"⏭️ Empty file, skipping.")
            continue

        pest_output = await run_pest(file_path)

        try:
            await asyncio.wait_for(
            generate_fixed_test(file_path, code, pest_output),
            timeout=MAX_ITERATION_TIME
            )
        except asyncio.TimeoutError:
            print(f"⛔ GPT FIX TIMED OUT after {MAX_ITERATION_TIME} minutes. Skipping this file.")
            continue


        print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s for rate limit...")
        await asyncio.sleep(RATE_LIMIT_SECONDS)

    print("\n🎉 All test files processed.")


# ------------------ ENTRY ------------------
if __name__ == "__main__":
    if not API_KEY:
        print("FATAL: UZAIR_OPEN_AI_API_KEY_3 missing.")
    else:
        asyncio.run(main())
