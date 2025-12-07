# AI-Driven Testing Automation Scripts

This folder contains a suite of Python scripts designed to automate the generation, execution, and reporting of unit and integration tests for the Crater application.

## Prerequisites

1.  **Python 3.x**: Ensure Python is installed.
2.  **Dependencies**: Install required packages (e.g., `pip install azure-ai-inference azure-core python-dotenv reportlab aiofiles google-generativeai`).
3.  **Environment Variables**: The scripts rely on an `.env` file in the **project root** containing necessary API keys (e.g., `TOKEN_2`, `NEW_TOKEN`, `UZAIR_GOOGLE_GEMINI_API_KEY_2`).

---

## 1. Test Generation

### `generate_unit_tests.py`
*   **Purpose**: Generates PHP unit tests (Pest format) for `app/` files using Azure AI/OpenAI models.
*   **Input**: `tests/Unit-Testing` directory (scans PHP files).
*   **Output**: `tests/results-openai/*.txt`
*   **Usage**:
    ```bash
    python AI-Automation-scripts/generate_unit_tests.py
    ```

### `generate_integration_tests.py`
*   **Purpose**: Generates IEEE-829 compatible integration test cases from existing PHP integration tests.
*   **Input**: `tests/Integration-Testing/**/*.php`
*   **Output**: `tests/results/**/*.txt` (Mirroring input structure)
*   **Key Features**:
    *   Automatically assigns prefixes (`Adm-BT`, `Cust-PT`, etc.) based on folder structure.
    *   Mirrors the `Admin/` and `Customer/` directory structure in the output.
*   **Usage**:
    ```bash
    python AI-Automation-scripts/generate_integration_tests.py
    ```

### `generateTestCases.py`
*   **Purpose**: Unit test generator using Google Gemini AI.
*   **Input**: `app/**/*.php`
*   **Output**: `tests/Unit-Testing/*-Test.php`
*   **Usage**:
    ```bash
    python AI-Automation-scripts/generateTestCases.py
    ```

---


### `refactor.py,refactor2.py`
*   **Purpose**: Refactoring Unit test generator using Google Gemini AI.
*   **Input**: `tests/Unit-Testing/*-Test.php`
*   **Output**: `tests/anyfolder/*-Test.php`
*   **Usage**:
    ```bash
    python AI-Automation-scripts/refactor.py
    ```

---

## 2. Result Merging

### `merge_integration_test_results.py`
*   **Purpose**: Merges all generated integration test text files into a single master document.
*   **Input**: `tests/results/**/*.txt`
*   **Output**: `combined_integration_test_results.txt` (in Project Root)
*   **Usage**:
    ```bash
    python AI-Automation-scripts/merge_integration_test_results.py
    ```

### `merge_unit_test_results.py`
*   **Purpose**: Merges all generated unit test text files.
*   **Input**: `tests/results-openai/*.txt`
*   **Output**: `combined_unit_test_results.txt` (in Project Root)
*   **Usage**:
    ```bash
    python AI-Automation-scripts/merge_unit_test_results.py
    ```

---

## 3. Reporting (PDF)

### `unit_test_report_to_pdf.py`
*   **Purpose**: Converts the combined Unit Test text results into a professional PDF report.
*   **Input**: `combined_unit_test_results.txt`
*   **Output**: `SQE_Final_Project_Report.pdf`
*   **Features**: Includes an "Index of Test Files" with clickable links.
*   **Usage**:
    ```bash
    python AI-Automation-scripts/unit_test_report_to_pdf.py
    ```

### `integration_test_report_to_pdf.py`
*   **Purpose**: Converts the combined Integration Test text results into a professional PDF report.
*   **Input**: `combined_integration_test_results.txt`
*   **Output**: `SQE_Integration_Project_Report.pdf`
*   **Features**: Tailored for Integration Test keys (`Test ID`, `Steps`) and includes an Index.
*   **Usage**:
    ```bash
    python AI-Automation-scripts/integration_test_report_to_pdf.py
    ```

---

## Utility Scripts

*   **`filterFiles.py`**: Helper to filter/move specific unit test files from source to destination.
*   **`findFile.py`**: Helper to locate a file by basename within the `app` directory.

---
**Note**: All scripts are configured to run from the project root or within the `AI-Automation-scripts` folder, resolving paths relative to the project root.
