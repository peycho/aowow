import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

const source = readFileSync('static/js/turnstile.js', 'utf8');
new vm.Script(source);
const contact = readFileSync('setup/tools/filegen/templates/global.js/contacttool.js', 'utf8');
if (process.argv.includes('--browser')) {
    const script = s => `<script>(0,eval)(new TextDecoder().decode(Uint8Array.from(atob('${Buffer.from(s).toString('base64')}'),c=>c.charCodeAt(0))));</script>`;
    const actions = ['registration','login','password_recovery','username_recovery','resend','feedback'];
    const forms = actions.map(action => `<form id="${action}"><div data-turnstile-action="${action}"></div></form>`).join('');
    const setup = `var g_turnstile={actions:${JSON.stringify(actions)},siteKey:'SITE_FIXTURE',error:'Verification failed; retry.'};
        var g_feedbackEnabled=true,g_user={id:7},LANG={},requests=[],loadedScripts=[],dialogs=0;
        var $=function(){return {ready:function(){}}};
        var $WH={cO:Object.assign,urlencode:encodeURIComponent};
        var Dialog=function(){this.show=function(){dialogs++}};Dialog.templates={};
        var Ajax=function(url,opt){requests.push({url:url,opt:opt})};
        var append=document.head.appendChild.bind(document.head);
        document.head.appendChild=function(node){if(node.tagName==='SCRIPT' && node.src.startsWith('https://challenges.cloudflare.com/')){loadedScripts.push(node);return node}return append(node)};`;
    // Keep dialog hash navigation local to the fixture; Chrome --dump-dom waits on real navigations.
    const contactFixture = 'window.ContactTool=(function(location){'+contact+';return ContactTool;})({hash:"",href:"https://example.test/",replace:function(hash){this.hash=hash;}});';
    process.stdout.write('<!doctype html><meta charset="UTF-8"><title>Turnstile regression</title><pre id="result">RUNNING</pre>'+forms+
        script(setup)+script(source)+script(contactFixture)+script(readFileSync('tests/security-turnstile-browser.js','utf8')));
    process.exit(0);
}

// Execute actual feedback submission code with a verifier/widget spy, never a remote request.
let requests = [], resets = [], mounts = [], removed = [];
let required = true, token = '';
const context = vm.createContext({g_user:{id:7},LANG:{},location:{hash:'',href:'https://example.test/',replace(){}},document:{},
    navigator:{userAgent:'fixture',appName:'fixture'},$:()=>({ready(){}}),$WH:{cO:Object.assign,urlencode:encodeURIComponent},
    AowowTurnstile:{enabled:()=>required,token:()=>token,reset:form=>resets.push(form),mount:(form,action)=>mounts.push(action),remove:form=>removed.push(form)},
    Ajax:function(url,opt){requests.push({url,opt});},g_feedbackEnabled:true});
vm.runInContext(contact, context);
const form={elements:[{disabled:false}]};
const data=mode=>({mode,reason:1,description:'Fixture',currenturl:'https://example.test/',comment:{id:1},post:{id:1},screenshot:{id:1},profile:{source:1},video:{id:1},guide:{id:1}});
context.ContactTool.onShow.call({data:{mode:0}},form);
assert.deepEqual(mounts,['feedback']);
context.ContactTool.onShow.call({data:{mode:1}},form);
assert.equal(mounts.length,1);
assert.equal(context.ContactTool.onSubmit(data(0),null,form),false);
assert.equal(requests.length,0);
assert.equal(form.elements[0].disabled,false);
token='token+with/special=characters';
context.ContactTool.onSubmit(data(0),null,form);
assert.equal(requests.length,1);
assert.ok(requests[0].opt.params.includes('cf-turnstile-response='+encodeURIComponent(token)));
requests[0].opt.onComplete({},{});
assert.deepEqual(resets,[form]);
assert.equal(form.elements[0].disabled,false);
token='';
for(const mode of [1,2,3,4,5,6]) {
    context.ContactTool.onSubmit(data(mode),null,form);
    assert.ok(!requests.at(-1).opt.params.includes('cf-turnstile-response'));
    requests.at(-1).opt.onComplete({},{});
}
assert.equal(resets.length,1);
required=false; context.ContactTool.onSubmit(data(0),null,form);
assert.ok(!requests.at(-1).opt.params.includes('cf-turnstile-response'));
context.ContactTool.onHide(form);
assert.deepEqual(removed,[form]);
context.g_feedbackEnabled=false;const count=requests.length;
context.ContactTool.onSubmit(data(0),null,form);assert.equal(requests.length,count);
console.log('PASS: Turnstile feedback token encoding/retry/reset/content-report compatibility checks');
