
import re
import os
from reportlab.lib import colors
from reportlab.lib.pagesizes import A4
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.platypus import SimpleDocTemplate, Table, TableStyle, Paragraph, Spacer, PageBreak
from reportlab.lib.units import inch

# Configuration
PROJECT_ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '..'))
INPUT_FILE = os.path.join(PROJECT_ROOT, "combined_integration_test_results.txt")
OUTPUT_FILE = os.path.join(PROJECT_ROOT, "SQE_Integration_Project_Report.pdf")

def parse_test_results(file_path):
    """
    Parses the integration text file into a structured dictionary.
    Returns: List of dictionaries, each representing a file with its test cases.
    """
    if not os.path.exists(file_path):
        print(f"Error: {file_path} not found.")
        return []

    with open(file_path, 'r', encoding='utf-8') as f:
        content = f.read()

    # Split by File Separator
    # Look for: ==================================================\nFILE: ...
    # This matches the merge script output which uses 50 '='
    file_chunks = re.split(r'={50}\nFILE: ', content)
    
    parsed_data = []

    # Skip first chunk if empty (before first file)
    if not file_chunks[0].strip():
        file_chunks.pop(0)

    for chunk in file_chunks:
        lines = chunk.splitlines()
        if not lines:
            continue
            
        filename = lines[0].strip()
        # The rest is the content
        
        # Split by Test Separator
        # Integration tests use 40 '=' in the generation script
        test_chunks = re.split(r'={40}', chunk)
        
        test_cases = []
        
        for test_chunk in test_chunks:
            # Basic check to see if this chunk looks like a test case
            if "Test ID" not in test_chunk and "Integration-Testing:" not in test_chunk:
                continue
            
            # Parse individual test case
            tc_data = {}
            current_key = None
            buffer = []

            # Define keys used in Integration Tests
            keys = [
                "Test ID", "Title", "Objective", "Preconditions", 
                "Test Data", "Steps", "Expected Result", 
                "Actual Result", "Status", "Severity"
            ]
            
            for line in test_chunk.splitlines():
                line = line.strip()
                if not line:
                    continue
                
                # Check if line starts with a key (exact match or close enough?)
                # The generation script outputs keys on their own lines usually or "Key\nValue"
                # But let's check for "Key" exact match first as they are headers in the text file
                
                found_key = False
                for key in keys:
                    # The text file format from the generator usually has lines like:
                    # Test ID
                    # TC-AT-001
                    # So the line itself IS the key.
                    if line == key:
                        # Save previous buffer
                        if current_key:
                            tc_data[current_key] = "\n".join(buffer).strip()
                        
                        # Start new key
                        current_key = key
                        buffer = []
                        found_key = True
                        break
                
                if not found_key and current_key:
                    buffer.append(line)
            
            # Save last buffer
            if current_key:
                tc_data[current_key] = "\n".join(buffer).strip()
            
            if tc_data:
                test_cases.append(tc_data)
        
        if test_cases:
            parsed_data.append({
                "filename": filename,
                "test_cases": test_cases
            })
            
    return parsed_data

def create_pdf(parsed_data, output_filename):
    doc = SimpleDocTemplate(output_filename, pagesize=A4, rightMargin=40, leftMargin=40, topMargin=40, bottomMargin=40)
    story = []
    
    styles = getSampleStyleSheet()
    title_style = styles["Title"]
    subtitle_style = styles["Normal"]
    subtitle_style.alignment = 1 # Center
    subtitle_style.fontSize = 12
    
    heading_style = styles["Heading2"]
    heading_style.textColor = colors.black
    
    normal_style = styles["Normal"]
    normal_style.fontSize = 10
    
    # Custom style for table content
    table_cell_style = ParagraphStyle('TableCell', parent=styles['Normal'], fontSize=9, leading=11)
    
    # --- Title Page ---
    story.append(Spacer(1, 2*inch))
    story.append(Paragraph("SQE Final Project", title_style))
    story.append(Spacer(1, 0.5*inch))
    story.append(Paragraph("<b>Project Title:</b> Comprehensive Quality Engineering for the Open-Source Crater Application", subtitle_style))
    story.append(Spacer(1, 0.2*inch))
    story.append(Paragraph("<b>Testers:</b> Uzair Majeed 23i-3063, Hussnain Haider 23i-0695, Faez Ahmed 23i-0598", subtitle_style))
    story.append(Spacer(1, 0.2*inch))
    story.append(Paragraph("<b>Section:</b> SE-B", subtitle_style))
    story.append(Spacer(1, 1*inch))
    story.append(Paragraph("<b>Integration Testing Report (IEEE 829-2008 Standard)</b>", subtitle_style))
    story.append(PageBreak())

    # --- Index/Table of Contents ---
    story.append(Paragraph("Index of Test Files", heading_style))
    story.append(Spacer(1, 0.2*inch))
    
    # Create an Index table or list
    index_data = []
    for i, file_data in enumerate(parsed_data):
        filename = file_data['filename']
        # Create a safe anchor name key
        anchor_name = f"FILE_{i}"
        file_data['anchor_name'] = anchor_name # Store for later use
        
        # Link to the anchor
        link_text = f'<a href="#{anchor_name}" color="blue">{filename}</a>'
        index_data.append([Paragraph(f"{i+1}.", table_cell_style), Paragraph(link_text, table_cell_style)])
    
    # Draw Index Table
    if index_data:
        t_index = Table(index_data, colWidths=[0.5*inch, 5.5*inch])
        t_index.setStyle(TableStyle([
            ('VALIGN', (0, 0), (-1, -1), 'TOP'),
            ('GRID', (0, 0), (-1, -1), 0.25, colors.lightgrey),
            ('PADDING', (0, 0), (-1, -1), 4),
        ]))
        story.append(t_index)
    
    story.append(PageBreak())

    # --- Content ---
    for file_data in parsed_data:
        filename = file_data['filename']
        anchor_name = file_data.get('anchor_name', '')
        
        # Heading with Anchor
        heading_text = f'<a name="{anchor_name}"/>File: {filename}'
        story.append(Paragraph(heading_text, heading_style))
        story.append(Spacer(1, 0.1*inch))
        
        for tc in file_data['test_cases']:
            # Construct Table Data
            # Format: Key | Value
            data = []
            
            # Order to display (Keys match the parsing logic)
            display_order = [
                ("Test ID", tc.get("Test ID", "N/A")),
                ("Title", tc.get("Title", "N/A")),
                ("Objective", tc.get("Objective", "N/A")),
                ("Preconditions", tc.get("Preconditions", "N/A")),
                ("Steps", tc.get("Steps", "N/A")),  # "Steps" in integration, not "Test Steps"
                ("Test Data", tc.get("Test Data", "N/A")),
                ("Expected Result", tc.get("Expected Result", "N/A")),
                ("Actual Result", tc.get("Actual Result", "N/A")),
                ("Status", tc.get("Status", "N/A")),
                ("Severity", tc.get("Severity", "N/A"))
            ]
            
            for key, value in display_order:
                # Use Paragraphs to allow text wrapping
                p_key = Paragraph(f"<b>{key}</b>", table_cell_style)
                # Replace newlines with <br/> for Paragraph
                formatted_value = value.replace("\n", "<br/>")
                p_value = Paragraph(formatted_value, table_cell_style)
                data.append([p_key, p_value])

            # Table Style
            t = Table(data, colWidths=[1.5*inch, 4.5*inch])
            t.setStyle(TableStyle([
                ('BACKGROUND', (0, 0), (0, -1), colors.lightgrey), # Header column background
                ('TEXTCOLOR', (0, 0), (-1, -1), colors.black),
                ('ALIGN', (0, 0), (-1, -1), 'LEFT'),
                ('VALIGN', (0, 0), (-1, -1), 'TOP'),
                ('FONTNAME', (0, 0), (0, -1), 'Helvetica-Bold'),
                ('FONTSIZE', (0, 0), (-1, -1), 9),
                ('GRID', (0, 0), (-1, -1), 0.5, colors.grey),
                ('PADDING', (0, 0), (-1, -1), 6),
            ]))
            
            story.append(t)
            story.append(Spacer(1, 0.2*inch))
            
        story.append(PageBreak())

    print(f"Building PDF: {output_filename}...")
    try:
        doc.build(story)
        print("PDF generation complete.")
    except Exception as e:
        print(f"Error building PDF: {e}")

if __name__ == "__main__":
    print("Parsing test results...")
    data = parse_test_results(INPUT_FILE)
    print(f"Parsed {len(data)} files.")
    if data:
        create_pdf(data, OUTPUT_FILE)
    else:
        print("No data found to generate PDF.")
