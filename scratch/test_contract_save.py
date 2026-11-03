from playwright.sync_api import sync_playwright

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True)
    page = browser.new_page()
    page.on("console", lambda msg: print(f"CONSOLE: {msg.text}"))
    page.on("pageerror", lambda err: print(f"PAGE ERROR: {err.message}"))
    page.goto('http://localhost:5173/crm-sales/contracts/edit/new')
    page.wait_for_load_state('networkidle')
    
    # Let's try to trigger a save
    # Note: we need to fill the required fields to pass frontend validation
    # Client is a combobox maybe? 
    # Let's just click save and see if we get the "Client is required" snackbar
    # If it crashes before that, we'll see the pageerror.
    
    print("Clicking save...")
    try:
        page.locator("button:has-text('Save')").click(timeout=3000)
    except:
        print("Save button not found!")
    
    page.wait_for_timeout(2000)
    browser.close()
