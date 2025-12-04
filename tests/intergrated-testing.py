import os
import glob
import aiofiles
import asyncio
from dotenv import load_dotenv
from google import genai
import string
import re

MODEL_NAME = "gemini-2.5-flash"
RATE_LIMIT_SECONDS = 6

load_dotenv()
API_KEY = os.getenv("HUSSNAIN_GOOGLE_GEMINI_API_KEY_2")

client = genai.Client(api_key=API_KEY) if API_KEY else None

def clean_quotes(text: str) -> str:
    """Replace smart quotes with straight quotes"""
    replacements = {
        '‘': "'",  # Left single smart quote
        '’': "'",  # Right single smart quote
        '“': '"',  # Left double smart quote
        '”': '"',  # Right double smart quote
    }
    for old, new in replacements.items():
        text = text.replace(old, new)
    return text

async def generate_test(prompt: str, output_path: str):
    if not client:
        print("❌ Gemini client not initialized (API Key missing).")
        return

    print(f"🧪 Generating integration test: {output_path}")

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

        # Clean smart quotes
        test_code = clean_quotes(test_code)
        
        # Remove markdown code blocks if present
        test_code = re.sub(r'^```php\s*', '', test_code, flags=re.MULTILINE)
        test_code = re.sub(r'^```\s*', '', test_code, flags=re.MULTILINE)
        test_code = re.sub(r'\s*```$', '', test_code, flags=re.MULTILINE)

        os.makedirs(os.path.dirname(output_path), exist_ok=True)

        async with aiofiles.open(output_path, "w", encoding="utf-8") as f:
            await f.write(test_code)

        print(f"✅ Saved: {output_path}")

    except Exception as e:
        print(f"❌ Error generating test: {e}")


def make_safe_filename(route_str: str) -> str:
    """
    Convert route string into a safe filename for Windows.
    Replace invalid characters with underscores and limit length.
    """
    valid_chars = f"-_.() {string.ascii_letters}{string.digits}"
    safe_name = ''.join(c if c in valid_chars else '_' for c in route_str)
    return safe_name[:50]


def extract_routes(route_code: str):
    """
    Extract all Route:: lines including multi-line closures.
    Supports get, post, put, patch, delete.
    """
    # Remove comments first
    route_code = re.sub(r"//.*?$|/\*.*?\*/", "", route_code, flags=re.DOTALL | re.MULTILINE)
    # Match Route::<method>( ... );
    pattern = r"Route::(?:get|post|put|patch|delete|any|match|resource|apiResource)\s*\((?:.|\n)*?\);"
    return re.findall(pattern, route_code, re.DOTALL)


async def main():
    route_files = glob.glob("../routes/*.php")
    if not route_files:
        print("⚠️ No route files found in ../routes/")
        return

    for file in route_files:
        basename = os.path.basename(file).replace(".php", "")
        print(f"\n📌 Processing file: {file}")

        try:
            async with aiofiles.open(file, "r", encoding="utf-8") as f:
                route_code = await f.read()
        except Exception as e:
            print(f"❌ Cannot read {file}: {e}")
            continue

        if not route_code.strip():
            print(f"⏭️ Skipping empty route file: {file}")
            continue

        routes = extract_routes(route_code)
        if not routes:
            print(f"⚠️ No routes detected in {file}")
            continue

        print(f"🔍 Found {len(routes)} routes in {file}")

        for i, single_route in enumerate(routes, 1):
            safe_name = make_safe_filename(single_route)
            output_path = os.path.join(
                "Integrated-Testing", f"{basename}_Route{i}_Test.php"
            )

            if os.path.exists(output_path):
                print(f"⏭️ Skipping: {output_path} already exists.")
                continue

            prompt = f"""
You are an expert Laravel tester specializing in Pest PHP integration testing.
Generate **integration-level** test cases for the following Laravel route.

CRITICAL REQUIREMENTS - READ CAREFULLY:
1. DO NOT redefine the route inside the test. Test the ACTUAL route that exists in Laravel.
2. DO NOT assume database tables exist (like 'access_logs') unless explicitly shown in route code.
3. DO NOT assume automatic logging happens. Only test logging if the route code explicitly shows it.
4. DO NOT assume User factories exist. Use generic user creation if authentication is needed.
5. DO NOT hardcode config values. Use the actual application state.
6. DO NOT use describe() blocks. Use ONLY test() or it() functions.
7. Test ONLY observable HTTP behavior - what you can see in responses.
8. If database assertions are needed, make them generic or conditional.
9. Use ONLY straight quotes (' and "), NEVER smart quotes (‘ ’ “ ”).
10. Output ONLY raw PHP code with <?php tag. No markdown, no explanations.

TESTING FOCUS:
- Route accessibility and HTTP status codes
- Request/response structure (JSON, redirects, views)
- Authentication/authorization requirements
- Database interactions ONLY if visible in route
- Error handling and validation
- HTTP method support (GET, POST, etc.)

USE THESE HELPERS:
- actingAs() for authentication
- get(), post(), put(), delete(), patch() for requests
- assertStatus(), assertJson(), assertRedirect(), assertViewIs()
- assertDatabaseHas(), assertDatabaseMissing() ONLY when appropriate
- assertHeader() for response headers

ROUTE TO TEST:
{clean_quotes(single_route)}
"""
            await generate_test(prompt, output_path)
            print(f"⏳ Waiting {RATE_LIMIT_SECONDS}s before next request...\n")
            await asyncio.sleep(RATE_LIMIT_SECONDS)

    print("\n🎉 All integration tests generated successfully!")


if __name__ == "__main__":
    if not API_KEY:
        print("❌ FATAL ERROR: HUSSNAIN_GOOGLE_GEMINI_API_KEY_2 is missing.")
    else:
        asyncio.run(main())