import os
import aiofiles
from dotenv import load_dotenv
from openai import OpenAI

load_dotenv()
api_key = os.getenv("GOOGLE_GEMINI_API_KEY")
client = OpenAI(api_key=api_key)

async def generate_test(prompt: str, output_path: str):
    try:
        response = client.chat.completions.create(
            model="gemini-2.0",
            messages=[{"role": "user", "content": prompt}],
            temperature=0
        )
        test_code = response.choices[0].message['content']

        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        async with aiofiles.open(output_path, "w") as f:
            await f.write(test_code)
        print(f"✅ Generated: {output_path}")
    except Exception as e:
        print(f"❌ Error generating {output_path}: {e}")
