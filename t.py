import os
import subprocess
import sys
import asyncio
import aiofiles
from pathlib import Path
from dotenv import load_dotenv
from azure.ai.inference import ChatCompletionsClient
from azure.ai.inference.models import SystemMessage, UserMessage
from azure.core.credentials import AzureKeyCredential
from datetime import datetime

# ------------------ CONFIG ------------------
MODEL_NAME = "openai/gpt-4.1"
RATE_LIMIT_SECONDS = 6
MAX_RETRIES = 5
PEST_COMMAND = ["php", "-d", "memory_limit=2000M", "vendor/bin/pest"]
MAX_TOKENS_ALLOWED = 8000         # Hard limit before trimming
TRIMMED_TARGET = 7800             # Target tokens after trimming

# Load environment
load_dotenv()
GITHUB_TOKEN = os.getenv("UZAIR_OPENAI_API_KEY")
ENDPOINT = "https://models.github.ai/inference"

if not GITHUB_TOKEN:
    print("❌ GitHub token not found in environment variable 'UZAIR_OPENAI_API_KEY'.")
    sys.exit(1)

client = ChatCompletionsClient(
    endpoint=ENDPOINT,
    credential=AzureKeyCredential(GITHUB_TOKEN),
)

# ------------------ UTILITY FUNCTIONS ------------------
def estimate_tokens(text: str) -> int:
    """Rough token estimator."""
    return int(len(text) / 3.5)

def trim_prompt_to_limit(prompt: str) -> str:
    """Safely trim prompt to ~7800 tokens and append notice."""
    tokens = estimate_tokens(prompt)
    if tokens <= MAX_TOKENS_ALLOWED:
        return prompt  # no trimming needed

    print(f"⚠️ Prompt too long ({tokens} tokens). Trimming to {TRIMMED_TARGET} tokens...")

    target_chars = int(TRIMMED_TARGET * 3.5)
    lines = prompt.splitlines()
    trimmed_lines = []
    current_length = 0
    for line in lines:
        if current_length + len(line) + 1 > target_chars:
            break
        trimmed_lines.append(line)
        current_length += len(line) + 1
    trimmed = "\n".join(trimmed_lines)
    trimmed += "\n\n[PROMPT TOO LONG — TRIMMED, FIGURE OUT YOURSELF]\n"
    return trimmed

async def run_pest_test(test_file: str) -> tuple:
    """Run Pest test and return (success, stdout, stderr)"""
    cmd = PEST_COMMAND + [test_file]
    try:
        process = await asyncio.create_subprocess_exec(
            *cmd,
            stdout=asyncio.subprocess.PIPE,
            stderr=asyncio.subprocess.PIPE
        )
        stdout, stderr = await asyncio.wait_for(process.communicate(), timeout=300)
        stdout = stdout.decode('utf-8').strip()
        stderr = stderr.decode('utf-8').strip()
        success = process.returncode == 0
        return success, stdout, stderr
    except asyncio.TimeoutError:
        return False, "Test timed out after 5 minutes", ""
    except Exception as e:
        return False, f"Error: {str(e)}", ""

def extract_failure_details(stdout: str, stderr: str) -> str:
    """Extract failure information from stdout/stderr"""
    lines = stdout.split("\n")
    failure_lines = []
    for i, line in enumerate(lines):
        if any(x in line for x in ["FAIL", "ErrorException", "Error:", "Whoops"]):
            failure_lines = lines[max(0, i-2):min(len(lines), i+30)]
            break
    result = "\n".join(failure_lines) if failure_lines else stdout[:500]
    if stderr:
        result += f"\n\nSTDERR:\n{stderr[:500]}"
    return result

async def read_file(file_path: str) -> str:
    async with aiofiles.open(file_path, 'r', encoding='utf-8') as f:
        return await f.read()

async def write_file(file_path: str, content: str):
    async with aiofiles.open(file_path, 'w', encoding='utf-8') as f:
        await f.write(content)

async def fix_test_with_openai(test_file: str, failure_output: str, original_content: str, retry_count: int) -> str:
    """Use Azure/GitHub OpenAI to fix the test file"""
    prompt = f"""FIX THIS FAILING TEST. Return ONLY the complete fixed PHP code for pest style unit test.

TEST FILE: {test_file}
FAILURE OUTPUT:
{failure_output}

ORIGINAL TEST CODE:
{original_content}

INSTRUCTIONS:

- Fix ALL errors in the test
- DO NOT use Mockery or any mocking framework
- Create pure unit tests that increase coverage (lines, functions, methods, class)
- Use test models/doubles instead of mocks
- Ensure test passes with Pest
- Test edge cases thoroughly
- Fix any PHP syntax errors
- Return ONLY the complete fixed PHP code, no explanation, no markdown
"""

    initial_tokens = estimate_tokens(prompt)
    print(f"🔢 Estimated prompt tokens: {initial_tokens}")

    # Trim prompt if needed
    prompt = trim_prompt_to_limit(prompt)
    trimmed_tokens = estimate_tokens(prompt)
    print(f"✂️ Tokens AFTER trimming: {trimmed_tokens}")

    try:
        response = await asyncio.to_thread(
            lambda: client.complete(
                messages=[
                    SystemMessage(content="You are an SQA Expert in php/laravel and have indepth knowledge of pest testing framework in unit-testing"),
                    UserMessage(content=prompt)
                ],
                temperature=0,
                top_p=1,
                model=MODEL_NAME
            )
        )
        response_text = response.choices[0].message.content.strip()

        # Remove ```php or ``` wrapping if present
        if response_text.startswith("```php") and response_text.endswith("```"):
            code = response_text[len("```php"): -3].strip()
        elif response_text.startswith("```") and response_text.endswith("```"):
            code = response_text[3:-3].strip()
        else:
            code = response_text
        return code
    except Exception as e:
        print(f"❌ OpenAI error: {e}")
        return original_content

# ------------------ MAIN PROCESS ------------------
async def process_test_file(test_file: Path, correct_tc_dir: Path):
    """Process one test file"""
    output_file = correct_tc_dir / test_file.name

    # Skip if file already exists
    if output_file.exists():
        print(f"⏭️ Skipping {test_file.name} — already exists in output folder")
        return True

    print(f"\n{'='*60}\nProcessing: {test_file.name}\n{'='*60}")
    
    original_content = await read_file(str(test_file))
    current_content = original_content

    for retry in range(MAX_RETRIES):
        print(f"\nAttempt {retry + 1}/{MAX_RETRIES}")
        success, stdout, stderr = await run_pest_test(str(test_file))

        if success:
            print("✅ PASS")
            await write_file(str(output_file), current_content)
            print(f"✅ Saved to: {output_file}")
            return True
        else:
            print("❌ FAIL")
            failure_output = extract_failure_details(stdout, stderr)
            print(f"Failure output (first 500 chars):\n{failure_output[:500]}...")

            print("🔄 Getting fix from OpenAI...")
            fixed_code = await fix_test_with_openai(
                str(test_file.name),
                failure_output[:1500],
                current_content,
                retry
            )
            await write_file(str(test_file), fixed_code)
            current_content = fixed_code

            if retry < MAX_RETRIES - 1:
                print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s before next attempt...")
                await asyncio.sleep(RATE_LIMIT_SECONDS)

    print(f"❌ Failed after {MAX_RETRIES} attempts")
    return False

# ------------------ MAIN ENTRY ------------------
async def main():
    filtered_dir = Path("tests/filtered")
    correct_tc_dir = Path("tests/correct-tc")
    correct_tc_dir.mkdir(exist_ok=True)

    if not filtered_dir.exists():
        print(f"❌ Directory not found: {filtered_dir}")
        sys.exit(1)

    test_files = list(filtered_dir.glob("*.php"))
    if not test_files:
        print(f"❌ No PHP files found in {filtered_dir}")
        sys.exit(1)

    print(f"Found {len(test_files)} test files")

    results = []
    for i, test_file in enumerate(test_files):
        print(f"\n{'#'*60}\nFile {i+1}/{len(test_files)}: {test_file.name}\n{'#'*60}")
        success = await process_test_file(test_file, correct_tc_dir)
        results.append((test_file.name, success))
        if i < len(test_files) - 1:
            await asyncio.sleep(RATE_LIMIT_SECONDS)

    # Summary
    passed = sum(1 for _, s in results if s)
    print(f"\n{'='*60}\nSUMMARY\n{'='*60}")
    print(f"Total: {len(results)}, ✅ Passed: {passed}, ❌ Failed: {len(results)-passed}")

    summary = f"# Test Results\nGenerated: {datetime.now().isoformat()}\n\nTotal files: {len(results)}\nPassed: {passed}\nFailed: {len(results)-passed}\n\n## Details:\n"
    for name, s in results:
        summary += f"- {'✅' if s else '❌'} {name}\n"

    await write_file(str(correct_tc_dir / "SUMMARY.md"), summary)
    print(f"Summary saved to: {correct_tc_dir}/SUMMARY.md")

if __name__ == "__main__":
    try:
        subprocess.run(PEST_COMMAND + ["--version"], capture_output=True, check=True)
        print("✅ Pest found")
    except Exception:
        print("❌ Pest not found")
        sys.exit(1)

    asyncio.run(main())
