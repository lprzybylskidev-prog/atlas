import { chromium } from 'playwright';
import { readFile, writeFile } from 'node:fs/promises';

const [, , inputPath, outputPath] = process.argv;

if (!inputPath || !outputPath) {
    console.error('Usage: node tools/reports/render-pdf.mjs <input-html> <output-pdf>');
    process.exit(2);
}

const html = await readFile(inputPath, 'utf8');
const browser = await chromium.launch({
    headless: true,
});

try {
    const page = await browser.newPage();

    await page.setContent(html, {
        waitUntil: 'networkidle',
    });
    await page.emulateMedia({ media: 'print' });
    await page.pdf({
        path: outputPath,
        format: 'A4',
        printBackground: true,
        displayHeaderFooter: true,
        margin: {
            top: '16mm',
            right: '14mm',
            bottom: '18mm',
            left: '14mm',
        },
        footerTemplate:
            '<div style="width:100%;font-size:8px;color:#52525b;padding:0 14mm;text-align:right;">Page <span class="pageNumber"></span> of <span class="totalPages"></span></div>',
        headerTemplate: '<div></div>',
    });
} finally {
    await browser.close();
}

await writeFile(`${outputPath}.ok`, 'ok');
