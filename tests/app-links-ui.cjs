// Local isolated preview only: TEST_BASE_URL=http://localhost:8093 node tests/app-links-ui.cjs
const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const base = process.env.TEST_BASE_URL || 'http://localhost:8088';
const artifacts = path.join(__dirname, '../artifacts');
fs.mkdirSync(artifacts, {recursive:true});

(async () => {
    const browser = await chromium.launch({headless:true, channel:process.env.TEST_BROWSER_CHANNEL || 'msedge'});
    try {
        for (const blocked of ['/app-links.php', '/app-links-tests.php', '/templates/smart-app.php', '/docs/n8n-telegram.md']) {
            const response = await fetch(base + blocked);
            assert.ok([403,404].includes(response.status), 'Private source blocked: ' + blocked);
        }
        assert.equal((await fetch(base + '/gmail', {method:'POST'})).status, 405);
        for (const invalid of ['/gmail?mid=1a1bb7de99c66f42', '/gmail?mid[]=a@b', '/whatsapp?phone=bad', '/telegram?username=proxy', '/maps?q=']) {
            assert.equal((await fetch(base + invalid)).status, 400, invalid);
        }
        const catalog = await browser.newPage({viewport:{width:1280,height:1000}});
        await catalog.goto(base + '/apps');
        assert.equal(await catalog.locator('form').count(),4);
        await catalog.screenshot({path:path.join(artifacts,'apps-desktop.png'),fullPage:true});
        await catalog.setViewportSize({width:390,height:844});
        assert.ok(await catalog.evaluate(() => document.documentElement.scrollWidth <= innerWidth), 'Catalog has no mobile overflow');
        await catalog.locator('#mid').fill('<receipt+2026@example.org>');
        await catalog.getByRole('button',{name:'Gmail-Link anzeigen'}).click();
        await catalog.waitForURL('**/gmail?**');
        assert.equal(await catalog.locator('#app-share-url').inputValue(), base + '/gmail?mid=receipt%2B2026%40example.org');
        await catalog.close();

        for (const [device, ua] of [
            ['ios', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) Telegram'],
            ['android', 'Mozilla/5.0 (Linux; Android 15; wv) AppleWebKit/537.36 Telegram'],
            ['desktop', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/142.0'],
        ]) {
            const context = await browser.newContext({userAgent:ua,viewport:{width:390,height:844}});
            const page = await context.newPage();
            const errors = [];
            page.on('pageerror', error => errors.push(error.message));
            page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
            const response = await page.goto(base + '/gmail?mid=%3Creceipt%2B2026%40example.org%3E&account=me%40gmail.com&url=https://evil.example');
            assert.equal(response.status(),200);
            assert.equal(response.headers()['cache-control'],'no-store');
            assert.equal(response.headers()['referrer-policy'],'no-referrer');
            assert.ok(response.headers()['content-security-policy'].includes("script-src 'self'"));
            assert.equal(response.headers()['set-cookie'],undefined);
            assert.equal(await page.locator('#gmail-search').inputValue(),'rfc822msgid:receipt+2026@example.org');
            const primary = await page.locator('#open-app').getAttribute('href');
            if (device === 'ios') assert.equal(primary,'googlegmail:///');
            if (device === 'android') assert.ok(primary.startsWith('intent:#Intent;action=android.intent.action.MAIN;'));
            if (device === 'desktop') assert.ok(primary.startsWith('https://mail.google.com/mail/'));
            assert.equal(await page.locator('#gmail-experimental').isVisible(),device === 'ios');
            assert.equal(await page.locator('#gmail-experimental').getAttribute('href'),'googlegmail:///search?q=rfc822msgid%3Areceipt%2B2026%40example.org');
            assert.ok(!(await page.locator('#app-share-url').inputValue()).includes('evil'));
            // No launch clicks: href verification cannot simulate a physical phone's app handling.
            await page.evaluate(() => Object.defineProperty(navigator,'clipboard',{value:{writeText:async value=>{window.copied=value;}}, configurable:true}));
            await page.getByRole('button',{name:'Suche kopieren'}).click();
            await page.getByRole('status').filter({hasText:'Kopiert.'}).waitFor();
            assert.equal(await page.evaluate(() => window.copied),'rfc822msgid:receipt+2026@example.org');
            await page.evaluate(() => Object.defineProperty(navigator,'clipboard',{value:undefined, configurable:true}));
            await page.getByRole('button',{name:'Suche kopieren'}).click();
            await page.getByRole('status').filter({hasText:'manuell kopieren'}).waitFor();
            assert.equal(await page.evaluate(() => document.activeElement.id),'gmail-search');
            assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), device + ' has no overflow');
            await page.screenshot({path:path.join(artifacts,'gmail-' + device + '.png'),fullPage:true});
            for (const [route, native] of [['/whatsapp?phone=491701234567&text=Gr%C3%BC%C3%9Fe%20%26%20mehr','whatsapp://send?'], ['/telegram?username=durov','tg://resolve?'], ['/maps?q=Berlin',device === 'ios' ? 'comgooglemaps://' : 'geo:']]) {
                await page.goto(base + route);
                const href = await page.locator('#open-app').getAttribute('href');
                assert.ok(href.startsWith(device === 'desktop' ? 'https://' : native),route + ': ' + href);
            }
            assert.deepEqual(errors,[],errors.join('\n'));
            await context.close();
        }
        const nojs = await browser.newContext({javaScriptEnabled:false,userAgent:'Mozilla/5.0 (iPhone)'});
        const page = await nojs.newPage();
        await page.goto(base + '/gmail?mid=receipt%40example.org');
        assert.equal(await page.locator('#open-app').getAttribute('href'),'googlegmail:///');
        assert.equal(await page.locator('#gmail-search').inputValue(),'rfc822msgid:receipt@example.org');
        assert.equal(await page.locator('noscript').isVisible(),true);
        await nojs.close();
        const amazon = await fetch(base + '/api/shorten', {method:'POST', body:new URLSearchParams({url:'https://www.amazon.de/dp/B09V36WL6H?tag=example-21&x=a%2Fb#section'})});
        assert.equal(amazon.status,200);
        const amazonLink = await amazon.json();
        assert.equal(amazonLink.provider,'amazon');
        assert.ok(amazonLink.short_url.includes('tag%3Dexample-21%26x%3Da%252Fb%23section'));
        const amazonMobile = await fetch(base + '/amazon?url=' + encodeURIComponent(amazonLink.canonical_url), {headers:{'User-Agent':'Mozilla/5.0 (iPhone) Telegram'}});
        assert.equal(amazonMobile.status,200);
        assert.ok((await amazonMobile.text()).includes('Open Amazon'));
        console.log('App-link HTTP/UI checks passed: source privacy, validation, native hrefs, exact web search, clipboard/fallback, mobile layouts, no-JS and existing Amazon.');
    } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode=1; });
