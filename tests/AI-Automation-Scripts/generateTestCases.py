import os
import glob
import aiofiles
import asyncio
from dotenv import load_dotenv
from google import genai 

MODEL_NAME = "gemini-2.5-flash"
RATE_LIMIT_SECONDS = 6   # 10 requests per minute

load_dotenv()
API_KEY = os.getenv("UZAIR_GOOGLE_GEMINI_API_KEY_2")

client = genai.Client(api_key=API_KEY) if API_KEY else None

async def generate_test(prompt: str, output_path: str):
    if not client:
        print(f"Error: Gemini client not initialized (API Key missing).")
        return

    print(f"Generating test for {output_path} using {MODEL_NAME}...")

    try:
        response = await asyncio.to_thread(
            lambda: client.models.generate_content(
                model=MODEL_NAME,
                contents=prompt,
            )
        )

        test_code = response.text.strip()
        if not test_code:
            raise ValueError("Model returned empty content.")

        os.makedirs(os.path.dirname(output_path), exist_ok=True)

        async with aiofiles.open(output_path, "w", encoding="utf-8") as f:
            await f.write(test_code)

        print(f"✅ Generated: {output_path}")

    except Exception as e:
        print(f"❌ Error generating {output_path}: {e}")


async def main():
    php_files = glob.glob("../../app/**/*.php", recursive=True)
    if not php_files:
        print("⚠️ No PHP files found in ../app/")
        return

    for file in php_files:
        basename = os.path.basename(file).replace(".php", "")
        output_path = os.path.join("../Unit-Testing", f"{basename}-Test.php")

        # Skip if test already exists
        if os.path.exists(output_path):
            print(f"⏭️ Skipping {file}: Test already exists at {output_path}")
            continue

        print(f"🔍 Found file: {file}")

        try:
            async with aiofiles.open(file, "r", encoding="utf-8") as f:
                code = await f.read()
        except Exception as e:
            print(f"❌ Cannot read {file}: {e}")
            continue

        if not code.strip():
            print(f"⏭️ Skipping empty file: {file}")
            continue

        prompt = f"""
You are an expert Laravel/PHP developer and tester specializing in white-box unit testing. Your task is to generate comprehensive Pest PHP unit tests for the functions and methods in the provided file.

Requirements:
- The tests MUST use the Pest PHP syntax (e.g., `test('...')`).
- Focus ONLY on unit-level white-box testing of the class/functions/methods. Do NOT generate tests for HTTP endpoints, routes, middleware, authorization, or full database/framework integration.
- Cover all public, protected, and private methods/functions (using reflection or appropriate mock strategies where necessary).
- Include comprehensive branch, condition, and logic coverage.
- Include success, failure, and edge case tests for input parameters and internal state changes.
- Use mocks (e.g., Mockery) and stubs extensively to isolate the class under test from its dependencies.
- Return ONLY the clean, runnable PHP Pest test code block (no markdown code fences, no explanations, no extra text, and no imports or opening/closing PHP tags unless necessary for Pest).

FILE CONTENT:
{code}
"""

        # Run generation
        await generate_test(prompt, output_path)

        # 🔥 RATE LIMIT: 10 RPM (wait 6 seconds)
        print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s for rate-limiting...")
        await asyncio.sleep(RATE_LIMIT_SECONDS)

    print("\nAll test generation completed.")


if __name__ == "__main__":
    if not API_KEY:
        print("FATAL ERROR: GOOGLE_GEMINI_API_KEY is not set.")
    else:
        asyncio.run(main())
