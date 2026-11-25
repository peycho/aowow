// Actual slider source, DOM helpers and CSS in Chromium; no game data required.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createServer } from 'node:http';
import { execFileSync } from 'node:child_process';
import { browser } from './browser-cdp.mjs';
const files = ['static/js/jquery-3.7.0.min.js','static/js/basic.js','static/js/locale_enus.js',
    ...['utilities','dom_manipulation','slider'].map(n=>'setup/tools/filegen/templates/global.js/'+n+'.js')];
const script = file=>'<script>'+readFileSync(file,'utf8').replace(/<\/script/gi,'<\\/script')+'</script>';
const itemFixtures=JSON.parse(execFileSync('php',['tests/item-tooltip-levels.php','--fixtures'],{encoding:'utf8'}));
const html = `<!doctype html><meta name="viewport" content="width=device-width,initial-scale=1"><style>${readFileSync('static/css/aowow.css','utf8')}body{margin:20px}.fixture{width:300px;margin:30px 0}</style>
<script>var g_staticUrl='/static',g_host=location.origin,g_user={roles:0},g_serverTime=new Date();</script>${files.map(script).join('')}
<div class="fixture" id="normal"></div><div class="fixture" id="hidden" style="display:none"></div><div class="fixture" id="fixed"></div>
<script>window.moves=[];window.s=Slider.init(document.getElementById('normal'),{minValue:1,maxValue:80,onMove:(e,s,p)=>moves.push(p.value),title:'Level'});Slider.setValue(s,35);
window.h=Slider.init(document.getElementById('hidden'),{minValue:1,maxValue:80,title:'Hidden'});Slider.setValue(h,40);
window.f=Slider.init(document.getElementById('fixed'),{minValue:80,maxValue:80,title:'Fixed'});Slider.setValue(f,80);
window.itemCases=${JSON.stringify(itemFixtures).replace(/</g,'\\u003c')}.map(fixture=>{
const tip=document.createElement('div'),slot=document.createElement('div');slot.className='fixture';document.body.append(tip,slot);
tip.innerHTML=$WH.g_enhanceTooltip.call(tip,fixture.tooltip,true,false,slot);
return {tip,slot,fixture};});</script>`;
const server=createServer((req,res)=>{if(req.url!=='/'){res.writeHead(204);res.end();return;}res.setHeader('Content-Type','text/html');res.end(html);});
await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));
let page,checks=0;
function check(ok,message){++checks;assert.ok(ok,message);}
try {
    page=await browser();
    for(const width of [390,1440]) {
        await page.viewport(width);await page.goto('http://127.0.0.1:'+server.address().port);
        check(!page.errors.length,'Slider initializes without browser errors: '+page.errors.join(' | '));
        check(await page.evaluate('Slider.getValue(s)===35 && Slider.getInput(s).value==="35"'),'Initial handle and editable value agree');
        check(await page.evaluate(`(()=>{let i=Slider.getInput(s);i.value='999';i.dispatchEvent(new Event('change',{bubbles:true}));return Slider.getValue(s)===80&&i.value==='80'&&moves.at(-1)===80})()`),'Keyboard change clamps at the maximum and updates its consumer');
        check(await page.evaluate(`(()=>{let i=Slider.getInput(s);i.value='invalid';i.dispatchEvent(new Event('change',{bubbles:true}));return Slider.getValue(s)===80&&i.value==='80'})()`),'Invalid typed input retains the previous level');
        const r=await page.evaluate(`(()=>{const r=s.elements.track.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width}})()`);
        await page.call('Input.dispatchMouseEvent',{type:'mousePressed',x:r.x+3,y:r.y+5,button:'left',clickCount:1});
        await page.call('Input.dispatchMouseEvent',{type:'mouseMoved',x:r.x+r.width-2,y:r.y+5,buttons:1});
        await page.call('Input.dispatchMouseEvent',{type:'mouseReleased',x:r.x+r.width-2,y:r.y+5,button:'left',clickCount:1});
        check(await page.evaluate('Slider.getValue(s)===80&&moves.at(-1)===80'),'Track drag reaches the maximum and notifies consumers');
        await page.evaluate('Slider.setSize(s,200)');
        check(await page.evaluate('Slider.getValue(s)===80 && s.elements.container.offsetWidth===200'),'Resizing preserves the selected value');
        check(await page.evaluate(`document.getElementById('hidden').style.display='block';Slider.getValue(h)===40&&Slider.getInput(h).value==='40'`),'A slider initialized while hidden retains its selected level after reveal');
        check(await page.evaluate(`Slider.getValue(f)===80&&Number.isFinite(Slider.getPercent(f))&&!f.style.left.includes('NaN')`),'A fixed-level range remains finite');
        for(let i=0;i<itemFixtures.length;i++) {
            check(await page.evaluate(`(()=>{const {tip,slot,fixture}=itemCases[${i}];return !!tip.slider===fixture.slider&&!!slot.querySelector('input')===fixture.slider})()`),'Actual PHP item tooltips show a slider only for a usable level range');
            check(await page.evaluate(`(()=>{const {tip,fixture}=itemCases[${i}];return fixture.slider ? tip.slider._min===fixture.min&&tip.slider._max===fixture.max&&Slider.getValue(tip.slider)===fixture.current : !tip.querySelector('a[onclick*="g_staticTooltipLevelClick"]')})()`),'Item controls use valid bounds and fixed-level ratings have no inactive change-level links');
        }
        if(width===390) {
            await page.tap(r.x+60,r.y+5);
            check(await page.evaluate('Slider.getValue(s)>1&&Slider.getValue(s)<80'),'Mobile tap changes the level');
        }
    }
    console.log(`PASS: ${checks} November slider browser, keyboard, drag, resize and hidden-layout checks`);
} finally {await page?.close();server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
