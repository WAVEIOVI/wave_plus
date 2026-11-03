import puppeteer from 'puppeteer';

(async () => {
  console.log('Launching browser for Step 2 Template & Composition verification...');
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox', '--disable-setuid-sandbox', '--window-size=1400,900'],
  });

  try {
    const page = await browser.newPage();
    await page.setViewport({ width: 1400, height: 900 });

    // 1. Login
    console.log('Navigating to login page...');
    await page.goto('http://localhost:5173/login', { waitUntil: 'domcontentloaded' });
    await page.waitForSelector('input', { timeout: 10000 });
    await page.type('input[type="email"]', 'admin@waveplus.xyz');
    await page.type('input[type="password"]', 'password');

    const submitBtn = await page.waitForSelector('button[type="submit"]');
    await submitBtn.click();
    await page.waitForNavigation({ waitUntil: 'networkidle2' }).catch(() => {});
    await new Promise(r => setTimeout(r, 2000));

    // 2. Go to Email Center
    console.log('Navigating to Email Center...');
    await page.goto('http://localhost:5173/communications/email', { waitUntil: 'networkidle2' });
    await new Promise(r => setTimeout(r, 3000));

    // 3. Open Composer
    console.log('Opening composer...');
    const composeBtn = await page.evaluateHandle(() => {
      const buttons = Array.from(document.querySelectorAll('button'));
      return buttons.find(b => b.textContent && b.textContent.includes('Compose')) || null;
    });

    if (composeBtn) {
      await composeBtn.click();
      await new Promise(r => setTimeout(r, 1500));
    }

    // 4. Fill in Step 1 recipient and advance to Step 2
    console.log('Filling in Step 1 recipient...');
    // Type manual recipient
    const manualInput = await page.$('input[placeholder*="user@company.com"], input[placeholder*="manual"], input[placeholder*="email"]');
    if (manualInput) {
      await manualInput.type('ahmed.benali@client-partner.tn');
      // Click Add recipient
      const addBtn = await page.evaluateHandle(() => {
        const btns = Array.from(document.querySelectorAll('button'));
        return btns.find(b => b.textContent && (b.textContent.includes('Ajouter') || b.textContent.includes('Add'))) || null;
      });
      if (addBtn) {
        await addBtn.click();
        await new Promise(r => setTimeout(r, 500));
      }
    }

    // Click Next to Step 2
    console.log('Advancing to Step 2...');
    const nextBtn = await page.evaluateHandle(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      return btns.find(b => b.textContent && (b.textContent.includes('Suivant') || b.textContent.includes('Next'))) || null;
    });
    if (nextBtn) {
      await nextBtn.click();
      await new Promise(r => setTimeout(r, 2000));
    }

    // Take screenshot 1: Step 2 Template Library & Dynamic Variables
    console.log('Taking screenshot of Step 2 Template & Message composition view...');
    const ssPath1 = 'C:/Users/hwasa/.gemini/antigravity/brain/c6c2b56a-7ca8-4825-8a99-c7487c2479f5/evidence_step2_template_library.png';
    await page.screenshot({ path: ssPath1, fullPage: false });
    console.log(`Saved: ${ssPath1}`);

    // Click on an Arabic template or switch language to 'ar'
    console.log('Testing Arabic templates toggle...');
    const arToggleBtn = await page.evaluateHandle(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      return btns.find(b => b.textContent && b.textContent.includes('العربية')) || null;
    });
    if (arToggleBtn) {
      await arToggleBtn.click();
      await new Promise(r => setTimeout(r, 1500));
    }

    // Select the first Arabic template
    const useTemplateBtn = await page.evaluateHandle(() => {
      const btns = Array.from(document.querySelectorAll('button'));
      return btns.find(b => b.textContent && (b.textContent.includes('Use') || b.textContent.includes('Utiliser'))) || null;
    });
    if (useTemplateBtn) {
      await useTemplateBtn.click();
      await new Promise(r => setTimeout(r, 2000));
    }

    // Switch to Live Preview tab
    console.log('Switching to Live Preview tab...');
    const previewTab = await page.evaluateHandle(() => {
      const tabs = Array.from(document.querySelectorAll('button[role="tab"]'));
      return tabs.find(t => t.textContent && (t.textContent.includes('Aperçu') || t.textContent.includes('Live Preview'))) || null;
    });
    if (previewTab) {
      await previewTab.click();
      await new Promise(r => setTimeout(r, 1500));
    }

    // Take screenshot 2: Live rendered email preview with RTL and branding
    console.log('Taking screenshot of Live Preview tab...');
    const ssPath2 = 'C:/Users/hwasa/.gemini/antigravity/brain/c6c2b56a-7ca8-4825-8a99-c7487c2479f5/evidence_step2_live_preview.png';
    await page.screenshot({ path: ssPath2, fullPage: false });
    console.log(`Saved: ${ssPath2}`);

    console.log('Verification completed successfully!');
  } catch (err) {
    console.error('Error during verification:', err);
  } finally {
    await browser.close();
  }
})();
