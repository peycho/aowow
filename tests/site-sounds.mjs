import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';
let checks=0;
const check=(ok,label)=>{checks++;assert.ok(ok,label);};
const source=path=>readFileSync(path,'utf8');
for (const locale of ['enus','frfr','dede','zhcn','eses','ruru']) for (const enabled of [false,true]) {
    const context=vm.createContext({g_staticUrl:'/static'});
    vm.runInContext(source(`static/js/locale_${locale}.js`),context);
    vm.runInContext(source('static/js/external-links.js'),context);
    const before=JSON.stringify(context.mn_database);
    context.g_applySoundsMenu(context.mn_database,enabled);
    check(context.mn_database.some(entry=>entry[0]===19)===enabled,`${locale}: Sounds and playlist follow flag`);
    check(JSON.stringify(context.mn_database.filter(entry=>entry[0]!==19))===JSON.stringify(JSON.parse(before).filter(entry=>entry[0]!==19)),`${locale}: other navigation preserved`);
    if (enabled) check(JSON.stringify(context.mn_database)===before,`${locale}: enabled menu unchanged`);
    const after=JSON.stringify(context.mn_database);
    context.g_applySoundsMenu(context.mn_database,enabled);
    check(JSON.stringify(context.mn_database)===after,`${locale}: repeated filtering stable`);
}
for (const enabled of [false,true]) {
    let reads=0,created=[];
    const element=tag=>({tag,id:'',style:{},children:[],canPlayType:()=>true,replaceChild(next,old){next.parentNode=this;this.children[this.children.indexOf(old)]=next;}});
    const context=vm.createContext({g_soundsEnabled:enabled,g_staticUrl:'/static',window:{JSON},RedButton:{setText(){}},LANG:{previous:'Previous',next:'Next',add:'Add'},
        $:()=>({click(){},removeClass(){}}),$WH:{is_array:Array.isArray,ce(tag,props){const el=Object.assign(element(tag),props);created.push(el);return el;},
            ae(parent,child){parent.children.push(child);child.parentNode=parent;},aE(){},st(){},ee(){},ct:text=>({text}),
            g_createButton:()=>element('button'),localStorage:{isSupported:()=>true,get(){reads++;return JSON.stringify([{id:42,title:'Saved track',type:'audio/mpeg',url:'/static/wowsounds/42'}]);},set(){}}}});
    vm.runInContext(source('setup/tools/filegen/templates/global.js/audio.js'),context);
    check(context.g_audioplaylist.isEnabled()===enabled,'Playlist initialization follows feature flag');
    check(reads===(enabled?1:0),'Disabled feature does not read saved playlists');
    context.g_audiocontrols.__windowloaded=true;
    vm.runInContext(`(new AudioControls).init([{title:'Fixture',type:'audio/mpeg',url:'/static/wowsounds/42'}],$WH.ce('div'));`,context);
    check(created.filter(el=>el.tag==='audio').length===(enabled?1:0),'Only enabled controls create native audio');
    check(created.filter(el=>el.tag==='source').length===(enabled?1:0),'Only enabled controls attach media sources');
    if (enabled) check(created.find(el=>el.tag==='source').src==='/static/wowsounds/42','Enabled playback preserves generated URL');
}
const context=vm.createContext({g_soundsEnabled:false,document:{},LANG:{},g_staticUrl:'/static',location:{hostname:'example.test'},$:()=>({ready(){}})});
vm.runInContext(source('setup/tools/filegen/templates/global.js/markup.js'),context);
// Disabled rendering must exit before touching globals, URLs or transport; no g_sounds is supplied.
for (const attributes of [{unnamed:42},{src:'/static/wowsounds/42',type:'audio/mpeg'}])
    check(context.Markup.tags.sound.toHtml(attributes)==='', 'Disabled sound markup creates no player or source');
context.g_soundsEnabled=true;
context.g_sounds={42:{name:'Fixture',files:[{url:'/static/wowsounds/42',type:'audio/mpeg'}]}};
context.g_staticUrl='/static';context.g_locale=0;context.LANG={};
context.Markup._getDatabaseDomainInfo=()=>[''];
context.Markup._addGlobalAttributes=()=>'';
check(context.Markup.tags.sound.toHtml({unnamed:42}).includes('/static/wowsounds/42'),'Enabled sound markup retains player URL');
console.log(`PASS: ${checks} localized sound menu/playlist/audio/markup checks`);
