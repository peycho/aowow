// Exercise actual site CSS, responsive enhancement, jQuery and Menu in Chromium.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import http from 'node:http';
import { execFileSync } from 'node:child_process';
import { browser } from './browser-cdp.mjs';
const inlineScript = source => '<script>{const source=document.createElement("script");source.textContent=' + JSON.stringify(source).replace(/</g, '\\u003c') + ';document.head.appendChild(source);}</script>';
const script = file => inlineScript(fs.readFileSync(file, 'utf8'));
const panels = JSON.parse(execFileSync('php', ['tests/fixtures/responsive-panels.php'], { encoding: 'utf8' }));
const css = ['basic', 'global', 'aowow', 'home', 'talentcalc', 'talent', 'petcalc', 'Book', 'responsive'].map(name => '<style>' + fs.readFileSync('static/css/' + name + '.css', 'utf8') + '</style>').join('');
const fixture = `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${css}
<script>window.g_staticUrl='';window.g_host='';window.g_user={debug:false,roles:0};window.g_serverTime=new Date('2026-10-22T00:00:00Z');</script>
${['static/js/jquery-3.7.0.min.js', 'static/js/basic.js', 'setup/tools/filegen/templates/global.js/0_user.js', 'static/js/locale_enus.js', 'setup/tools/filegen/templates/global.js/utilities.js', 'setup/tools/filegen/templates/global.js/rectangle.js', 'setup/tools/filegen/templates/global.js/positioning.js', 'setup/tools/filegen/templates/global.js/menu.js', 'static/js/responsive.js'].map(script).join('')}
</head><body><div id="layers"></div><div class="layout nosidebar"><div class="layout-inner"><header class="header"><div id="header-logo"><a class="header-logo" href="?home"></a></div><a class="header-image"><img width="468" height="60" alt="Banner"></a></header><div class="wrapper"><div class="toplinks"><a href="?account">Log in / Register</a></div><div id="toptabs" class="toptabs"><dl><dt><a href="?items">Database</a></dt><dt><a href="?guides">Guides</a></dt></dl></div>
<div class="topbar"><div class="topbar-search"><form><a href="#search"></a><input name="search"></form></div><div id="topbar-buttons"></div></div>
<main class="main-contents"><table class="infobox"><tr><th>Quick facts</th></tr><tr><td>Public facts</td></tr></table><div class="text"><h1>A long readable entity title</h1><p>Visible article text with <a href="?item=1">a database link</a>.</p><img width="650" height="300" alt="Artwork"><table class="grid"><tr><td style="white-space:nowrap">${'Wide article table content '.repeat(5)}</td></tr></table><div id="mapper-generic" role="group" aria-label="Authored map"><div class="mapper" style="width:778px;height:519px"><a id="map-pin" style="position:absolute;left:700px;top:50px" href="?npc=1">Map pin</a></div></div></div>
<div id="tc-classes" class="choose"><div id="tc-classes-outer"><div id="tc-classes-inner"><p>Choose a class:</p><div class="iconmedium"><a href="#class" style="display:block;width:38px;height:38px">Class</a></div></div></div></div><div id="tc-itself" class="choose"><div class="talentcalc-main"></div></div><div class="listview"><div class="listview-band-top"><span class="listview-note">Results</span><span class="listview-nav"><a href="#prev">Previous</a><a href="#next">Next</a></span></div><table class="listview-mode-default"><thead><tr><th>Name</th><th>Level</th></tr></thead><tbody><tr><td>Entity</td><td>80</td></tr></tbody></table></div></main></div></div></div>
<script>$(function(){function addMenu(){Menu.addButtons($('#topbar-buttons'),[[1,'Browse','?items',[[2,'Items','?items'],[3,'Quests','?quests']]]]);}if(location.search==='?items')setTimeout(addMenu,350);else addMenu();});</script></body></html>`;
const homeNewsBackground = 'data:image/jpeg;base64,' + fs.readFileSync('static/images/enus/mainpage-bg-news.jpg').toString('base64');
const homeFixture = fixture.slice(0, fixture.indexOf('<body>')) + `<body class="home"><div class="home-wrapper"><div class="home-logo"></div><div class="home-search"><form><input name="search"><a href="#search" aria-label="Search"></a></form></div><div class="home-menu"><span class="menu-buttons"><a href="?items">Database</a><a href="?guides">Guides</a></span></div><div class="home-featuredbox home-featuredbox-extended" style="background-image:url(${homeNewsBackground})"><div class="home-featuredbox-links"><a href="?items" title="Featured items" style="left:300px;top:18px;width:150px;height:160px"></a></div><div class="home-featuredbox-inner text"><p>Readable news text with <a href="?quest=1">a quest link</a>. ${'News paragraph. '.repeat(20)}</p></div></div></div><div class="toplinks"><a href="?account">Log in</a></div></body></html>`;
function panelFixture(language, kind) {
    const locale = panels[language];
    const localeData = { [locale.id]: { id: locale.id, name: locale.locale, description: language } };
    const localeScript = inlineScript(fs.readFileSync('setup/tools/filegen/templates/global.js/locale.js', 'utf8').replace('/*setup:locales*/ null', JSON.stringify(localeData)));
    const scripts = ['dom_manipulation', 'tabs', 'listview', 'markup'].map(name => script('setup/tools/filegen/templates/global.js/' + name + '.js')).join('') + script('static/js/Book.js') + (kind === 'article' ? script('setup/tools/filegen/templates/global.js/listview_templates.js') : '');
    const head = fixture.slice(0, fixture.indexOf('</head>')).replace(script('static/js/locale_enus.js'), script('static/js/locale_' + panels[language].locale + '.js'));
    const article = `<div class="text"><div class="random-enchantments" style="margin-right:25px"><ul><li>...of Agility<br>+19 Agility</li></ul></div><div class="random-enchantments" style="margin-right:25px"><ul><li>...of Power<br>+22 Attack Power</li></ul></div><div class="clear"></div><div id="article-lists"></div><div class="clear"></div><div id="article-book"></div><table class="listview-mode-default" style="width:640px"><tr><td id="quest-name-cell" style="width:130px"></td><td>Other quest columns</td></tr></table></div><script>Markup.printHtml('[iconlist][b]First crafting material with a long description[/b][/iconlist][iconlist][b]Second crafting material with a long description[/b][/iconlist]', 'article-lists');new Book({parent:'article-book',pages:['First readable book page','Second readable book page']});Listview.templates.quest.columns[0].compute.call(Listview.templates.quest,{id:54,name:'Report to Goldshire',wflags:32},document.getElementById('quest-name-cell'));</script>`;
    return head + localeScript + scripts + '<script>g_user.roles=' + (kind === 'member' ? 'U_GROUP_VIDEO' : '0') + ';g_user.name="Fixture";</script></head><body><div class="layout nosidebar"><div class="layout-inner"><div class="wrapper"><main class="main-contents">' + (kind === 'article' ? article : locale[kind]) + '</main></div></div></div></body></html>';
}
const server = http.createServer((req, res) => {
    if (new URL(req.url, 'http://fixture').searchParams.has('data')) { res.writeHead(200, { 'Content-Type': 'application/javascript' }); res.end('/* Synthetic dataset. */'); return; }
    if (req.url.startsWith('/images/') || req.url.startsWith('/static/')) { res.writeHead(204); res.end(); return; }
    const languages = { en: 'Database', fr: 'Base de données', de: 'Datenbank', zh: '数据库', es: 'Base de datos', ru: 'База данных' };
    const language = new URL(req.url, 'http://fixture').searchParams.get('language') || 'en';
    const locale = Object.hasOwn(languages, language) ? language : 'en';
    const kind = new URL(req.url, 'http://fixture').searchParams.get('kind') || 'guest';
    const body = (req.url.startsWith('/panels') ? panelFixture(locale, kind) : req.url.startsWith('/home') ? homeFixture : fixture).replace('<html lang="en">', '<html lang="' + locale + '">').replace('>Database</a>', '>' + languages[locale] + '</a>');
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8', ...(req.url.includes('scripts=off') ? { 'Content-Security-Policy': "script-src 'none'" } : {}) }); res.end(body);
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const origin = 'http://127.0.0.1:' + server.address().port;
let page, checks = 0;
function check(ok, message) { ++checks; assert.ok(ok, message); }
try {
    page = await browser();
    for (const width of [360, 390, 768, 1440]) {
        await page.viewport(width); await page.goto(origin);
        const layout = await page.evaluate(`(()=>{const main=document.querySelector('main'),facts=document.querySelector('.infobox'),heading=document.querySelector('h1'),table=document.querySelector('.listview-mode-default'),map=document.querySelector('.mapper');return {width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,inner:innerWidth,layout:getComputedStyle(document.querySelector('.layout')).minWidth,wrap:table.parentElement.className,tableWidth:table.getBoundingClientRect().width,mapWidth:map.getBoundingClientRect().width,factsInText:facts.parentElement.classList.contains('text'),headingFirst:heading.getBoundingClientRect().top<facts.getBoundingClientRect().top,search:document.querySelector('.topbar-search input').getBoundingClientRect().toJSON(),target:document.querySelector('.toptabs a').getBoundingClientRect().height,focus:document.querySelector('.responsive-scroll')?.tabIndex,regions:document.querySelectorAll('[data-responsive-scroll][aria-label][tabindex="0"]').length};})()`);
        check(layout.scroll <= width + 1 && layout.width >= width - 20 && layout.inner === width, `no page overflow at ${width}: ${JSON.stringify(layout)}`);
        check(layout.mapWidth === 784, 'map retains native coordinate width plus border');
        if (width < 980) {
            check(await page.evaluate(`(()=>{const e=document.querySelector('#tc-classes-inner p'),r=e.getBoundingClientRect();return r.height>0&&r.right<=innerWidth&&getComputedStyle(document.querySelector('#tc-itself')).display==='none';})()`), 'class picker reflows and the empty calculator preview is hidden');
            check(layout.wrap === 'responsive-scroll' && layout.tableWidth >= 640, 'wide result table has its own scroller');
            check(layout.target >= 36 && layout.search.height >= 40, 'navigation and search have usable touch dimensions');
            check(layout.search.right <= width && layout.regions >= 2 && layout.focus === 0, 'search fits and scroll regions are keyboard accessible');
            check(layout.factsInText === (width <= 600) && (width > 600 || layout.headingFirst), 'phone facts follow the heading');
            check(await page.evaluate(`(()=>{const e=document.querySelector('.responsive-scroll');e.scrollLeft=100;return e.scrollLeft>0;})()`), 'table scroll actually moves');
            check(await page.evaluate(`(()=>{const e=document.querySelector('#mapper-generic');e.scrollLeft=400;return e.scrollLeft>0&&document.querySelector('#map-pin').offsetLeft===700;})()`), 'map scroll preserves pin coordinates');
        } else {
            check(layout.layout === '980px' && !layout.wrap.includes('responsive-scroll') && !layout.factsInText, 'desktop layout and DOM stay intact');
        }
        check(page.errors.length === 0, 'no browser exceptions: ' + page.errors.join(' | '));
    }
    for (const width of [360, 390, 440, 768, 979]) {
        await page.viewport(width); await page.goto(origin + '/home');
        check(await page.evaluate(`document.documentElement.scrollWidth<=${width}&&innerWidth===${width}`), 'homepage does not overflow at ' + width);
        check(await page.evaluate(`(()=>{const top=document.querySelector('.toplinks').getBoundingClientRect(),logo=document.querySelector('.home-logo').getBoundingClientRect(),input=document.querySelector('.home-search input').getBoundingClientRect();return top.bottom<=logo.top&&input.right<=innerWidth&&input.height>=44;})()`), 'homepage account links do not overlap the logo and search stays usable');
        check(await page.evaluate(`(()=>{const a=document.querySelector('.home-featuredbox-links a'),b=document.querySelector('.home-featuredbox-inner p');return a.textContent===''&&getComputedStyle(a,'::after').content.includes('Featured items')&&a.getBoundingClientRect().height>=36&&b.getBoundingClientRect().right<=innerWidth;})()`), 'authored banner links remain labeled and news text reflows');
        // The news frame is painted inside the JPEG, not at the div's edges.
        // Check both standard and extended boxes against its visible side borders.
        for (const extended of [false, true]) {
            await page.evaluate(`document.querySelector('.home-featuredbox').classList.toggle('home-featuredbox-extended', ${extended})`);
            check(await page.evaluate(`(()=>{const box=document.querySelector('.home-featuredbox').getBoundingClientRect(),text=document.querySelector('.home-featuredbox-inner p').getBoundingClientRect(),scale=box.width/415;return text.left>=box.left+18*scale+1&&text.right<=box.left+395*scale-1;})()`), 'homepage text stays inside the painted news frame: ' + width + '/' + (extended ? 'extended' : 'standard'));
        }
    }
    await page.goto(origin);
    // Resize an already enhanced page back to desktop and then back to a phone.
    await page.viewport(390); await page.sleep(150); await page.viewport(1440, false); await page.sleep(150);
    check(await page.evaluate(`!document.querySelector('.responsive-scroll')&&document.querySelector('.infobox').parentElement.classList.contains('main-contents')`), 'resize restores original desktop parents');
    check(await page.evaluate(`document.querySelector('#mapper-generic').getAttribute('aria-label')==='Authored map'&&document.querySelector('#mapper-generic').getAttribute('role')==='group'`), 'existing accessible map names and roles survive enhancement and resize');
    await page.viewport(390); await page.sleep(150);
    await page.evaluate(`document.querySelector('.listview').insertAdjacentHTML('beforeend','<table class="listview-mode-default" id="lazy-table"><tr><td>Later list</td></tr></table>')`); await page.sleep(150);
    check(await page.evaluate(`document.querySelector('#lazy-table').parentElement.className==='responsive-scroll'`), 'lazy result table is bounded after insertion');
    await page.evaluate('scrollTo(0,0)');
    const target = await page.evaluate(`(()=>{const a=document.querySelector('#topbar-buttons a');const r=a.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2};})()`);
    await page.tap(target.x, target.y);
    check(await page.evaluate(`location.pathname==='/'&&[...document.querySelectorAll('.menu')].some(e=>e.getBoundingClientRect().height>0)`), 'first real touch opens a submenu without following its link: '+page.errors.join(' | '));
    await page.tap(target.x, target.y);
    await page.waitFor(`location.search==='?items'&&document.readyState==='complete'&&!!document.querySelector('#topbar-buttons a')`, { description: 'the second tap destination to finish initializing its menu button' });
    check(await page.evaluate(`location.search==='?items'`), 'second touch follows the existing destination');
    await page.evaluate(`Menu.show(Array.from({length:100},(_,i)=>[i+1,'Long menu item '+i,'?item='+i]),document.querySelector('#topbar-buttons a'));`);
    check(await page.evaluate(`(()=>{const e=[...document.querySelectorAll('.menu')].find(e=>e.getBoundingClientRect().height>0),r=e.getBoundingClientRect();return r.left>=0&&r.right<=390&&r.height<=884;})()`), 'long menus stay inside the phone viewport');
    await page.evaluate(`document.body.insertAdjacentHTML('beforeend','<div class="lightbox-outer"><div class="lightbox-inner" style="width:500px;height:1100px;left:-250px;top:-550px"><div class="dialog"><table><tr><th><label for="modal-field">Label</label></th><td><input type="text" id="modal-field" size="40"></td></tr></table></div></div></div>')`);
    check(await page.evaluate(`(()=>{const e=document.querySelector('.lightbox-inner'),r=e.getBoundingClientRect(),input=document.querySelector('#modal-field').getBoundingClientRect();return r.left>=0&&r.right<=390&&r.top>=0&&r.bottom<=900&&input.right<=r.right&&input.height>=36;})()`), 'wide/tall dialogs and their fields fit without clipped controls');
    await page.goto(origin + '/home?scripts=off');
    check(await page.evaluate(`document.documentElement.scrollWidth<=390&&!!document.querySelector('.home-featuredbox-inner a')`), 'script-blocked homepage news and links reflow');
    await page.goto(origin + '/?scripts=off');
    check(await page.evaluate(`document.documentElement.scrollWidth<=390&&!!document.querySelector('h1')&&!!document.querySelector('.text a')`), 'script-blocked text and links remain readable without page overflow');
    check(await page.evaluate(`document.querySelector('#mapper-generic').scrollWidth>document.querySelector('#mapper-generic').clientWidth`), 'script-blocked map is still scrollable');
    for (const [language, label] of Object.entries({ en: 'Scrollable content', fr: 'Contenu défilant', de: 'Verschiebbarer Inhalt', zh: '可滚动内容', es: 'Contenido desplazable', ru: 'Прокручиваемое содержимое' })) {
        await page.viewport(360); await page.goto(origin + '/?language=' + language);
        check(await page.evaluate(`document.documentElement.scrollWidth<=360&&innerWidth===360`), 'localized navigation fits: ' + language);
        check(await page.evaluate(`document.querySelector('.responsive-scroll').getAttribute('aria-label')===${JSON.stringify(label)}`), 'scroll regions use the document language: ' + language);
    }
    // Real PHP contribution and account bricks: absence of page overflow alone
    // cannot detect a readable form squeezed into a desktop sidebar reservation.
    for (const language of Object.keys(panels)) {
        for (const width of [360, 390, 470, 600, 768, 979, 1440]) {
            await page.viewport(width);
            for (const kind of ['guest', 'member']) {
                await page.goto(origin + '/panels?language=' + language + '&kind=' + kind);
                check(page.errors.length === 0, `contribution scripts run: ${language}/${kind}/${width}: ${page.errors.join(' | ')}`);
                const dimensions = await page.evaluate(`(()=>{const t=document.querySelector('#tabs-contribute-generic'),panel=t.nextElementSibling,main=t.parentElement;return {tabs:t.getBoundingClientRect().width,panel:panel.getBoundingClientRect().width,available:main.clientWidth-parseFloat(getComputedStyle(main).paddingLeft)-parseFloat(getComputedStyle(main).paddingRight)};})()`);
                if (width < 980) {
                    check(Math.abs(dimensions.panel-dimensions.available)<1 && Math.abs(dimensions.tabs-dimensions.available)<1, `contribution uses available width: ${language}/${kind}/${width}: ${JSON.stringify(dimensions)}`);
                } else {
                    check(Math.abs(dimensions.panel-(dimensions.available-310))<1 && Math.abs(dimensions.tabs-dimensions.available/2)<1, 'desktop contribution dimensions remain unchanged');
                }
                const tabs = await page.evaluate(`document.querySelectorAll('#tabs-contribute-generic a').length`);
                for (let index = 0; index < tabs; ++index) {
                    await page.evaluate(`document.querySelectorAll('#tabs-contribute-generic a')[${index}].scrollIntoView({block:'center'})`);
                    const target = await page.evaluate(`(()=>{const a=document.querySelectorAll('#tabs-contribute-generic a')[${index}],r=a.getBoundingClientRect();return {x:r.x+r.width/2,y:r.y+r.height/2,height:r.height,inside:r.left>=0&&r.right<=innerWidth};})()`);
                    if (width < 980) {
                        check(target.height >= 36 && target.inside, 'translated contribution tab fits and has a usable touch target');
                        await page.tap(target.x, target.y);
                    } else {
                        await page.evaluate(`document.querySelectorAll('#tabs-contribute-generic a')[${index}].click()`);
                    }
                    check(await page.evaluate(`(()=>{const tab=document.querySelectorAll('#tabs-contribute-generic a')[${index}],panel=document.querySelector('#tab-'+tab.hash.slice(1)),field=panel.querySelector('textarea,input:not([type=hidden])'),r=field.getBoundingClientRect(),p=panel.getBoundingClientRect();return getComputedStyle(panel).display!=='none'&&r.width>=(${width}<980?Math.min(240,p.width*.9):1)&&r.left>=p.left&&r.right<=p.right+1&&field.disabled===${kind === 'guest'};})()`), `tab opens a readable ${kind} field: ${language}/${width}/${index}`);
                }
                check(await page.evaluate(`document.documentElement.scrollWidth<=innerWidth`), 'contribution tabs/forms do not overflow the page');
            }
            for (const kind of ['signin', 'signup']) {
                await page.goto(origin + '/panels?language=' + language + '&kind=' + kind);
                check(page.errors.length === 0, `account scripts run: ${language}/${kind}/${width}: ${page.errors.join(' | ')}`);
                check(await page.evaluate(`(()=>{const box=document.querySelector('.inputbox').getBoundingClientRect();return [...document.querySelectorAll('.inputbox input:not([type=hidden])${width < 980 ? ',.inputbox a' : ''}')].every(e=>{const r=e.getBoundingClientRect();return r.width>0&&r.left>=box.left&&r.right<=box.right&&r.top>=box.top&&r.bottom<=box.bottom;})&&box.left>=0&&box.right<=innerWidth;})()`), `account fields and mobile recovery links remain inside their box: ${language}/${kind}/${width}`);
            }
            await page.goto(origin + '/panels?language=' + language + '&kind=article');
            check(page.errors.length === 0, `real article widgets run: ${language}/${width}: ${page.errors.join(' | ')}`);
            if (width < 980) {
                check(await page.evaluate(`(()=>{const cell=document.querySelector('#quest-name-cell'),link=cell.querySelector('a').getBoundingClientRect(),note=cell.querySelector('.small').getBoundingClientRect(),wrapper=cell.firstElementChild.getBoundingClientRect();return note.top>=link.bottom&&wrapper.bottom>=note.bottom;})()`), 'real quest status annotation fits below its name without overlap');
            }
            if (width <= 600) {
                check(await page.evaluate(`['.random-enchantments','.iconlist-col'].every(selector=>{const [a,b]=[...document.querySelectorAll(selector)].map(e=>e.getBoundingClientRect()),parent=document.querySelector('.main-contents>.text').getBoundingClientRect();return a.width>=parent.width*.95&&b.width>=parent.width*.95&&b.top>=a.bottom;})`), 'phone article/enchantment columns use readable, stacked widths');
            }
            check(await page.evaluate(`(()=>{const e=document.querySelector('.book'),r=e.getBoundingClientRect();return r.left>=0&&r.right<=innerWidth&&document.documentElement.scrollWidth<=innerWidth;})()`), 'readable book fits without page overflow');
            await page.evaluate(`document.querySelector('.book .next a').click()`);
            check(await page.evaluate(`[...document.querySelectorAll('.book .page')].some(e=>getComputedStyle(e).display!=='none'&&e.textContent.includes('Second readable book page'))`), 'bounded book retains working page navigation');
        }
    }
    console.log(`PASS: ${checks} responsive viewport, layout, table, map, resize, lazy content and real touch checks`);
} finally { await page?.close(); await new Promise(resolve => server.close(resolve)); }
