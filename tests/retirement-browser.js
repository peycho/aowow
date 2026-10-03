// Use actual generated assets and page widgets, with game/character data supplied by the fixture.
$(async function () {
    let checks = 0;
    const check = (ok, message) => {++checks; if (!ok) throw new Error(message);};
    try {
        document.getElementById('widget').src = 'widget';
        for (const [name, source] of Object.entries(retirementLocales)) {
            (0, eval)(source);
            Locale.set(Locale.getAll().find(locale => locale.name === name).id);
            checkRetirementMarkup(check);
        }
        (0, eval)(retirementLocales.enus); Locale.set(0);
        check(typeof ModelViewer === 'undefined' && typeof swfobject === 'undefined', 'No viewer globals');

        const item = {id:1, name:'7Fixture Sword', quality:2, icon:'inv_misc_questionmark', slot:13, classs:2,
            subclass:7, level:10, reqlevel:1, jsonequip:{id:1, name:'5Fixture Sword', classs:2, subclass:7, slotbak:13, level:10, str:2}};
        g_items.add(1, {name_enus:'Fixture Sword', quality:2, icon:item.icon, jsonequip:item.jsonequip});
        $WowheadPower.loadScales(3, 0); $WowheadPower.loadScales(6, 0);
        $WowheadPower.registerItem(1, 0, {name_enus:'Fixture Sword', quality:2, icon:item.icon,
            tooltip_enus:'<table><tr><td>Local tooltip fixture</td></tr></table>', map:{}, spells:{}});
        $WowheadPower.init();
        const lv = new Listview({id:'retirement-items', parent:'list', template:'item', data:[item]});
        check(lv.mode === Listview.MODE_CHECKBOX, 'Equipment list retains checkboxes');
        check(document.querySelector('#list input[value="'+LANG.button_compare+'"]'), 'Item list retains Compare control');
        check(!document.getElementById('list').textContent.includes('View in 3D'), 'Item list has no viewer control');

        new Listview({id:'retirement-models', parent:'models', template:'model', data:[{npcId:1, displayId:1, skin:1, level:1}]});
        check(document.querySelector('#models a[href="?npc=1"]'), 'Legacy model list uses a local entity link');
        check(!document.querySelector('#models img'), 'Legacy model list does not fetch thumbnails');
        new Listview({id:'retirement-generic', parent:'models', template:'genericmodel', genericlinktype:'item',
            data:[{id:1, name:'@Fixture Sword', displayid:1}]});
        check(document.querySelector('#models a[href="?item=1"]'), 'Generic model rows retain local item navigation');
        new Listview({id:'retirement-gallery', parent:'models', template:'gallery',
            data:[{id:1, npcId:1, displayName:'Fixture Pet', completed:true, _included:()=>true}]});
        check(document.querySelector('#lv-retirement-gallery a[href="?npc=1"]'), 'Completion gallery retains local pet navigation');
        check(document.querySelector('#lv-retirement-gallery img[src$="tick.png"]'), 'Completion gallery retains status icon');
        check(!document.querySelector('#models img[src*="modelviewer"]'), 'No gallery/model thumbnails');
        new Summary({id:'comparison', template:'compare', groups:[[[1]]], editable:0, searchable:0, weightable:0, draggable:0});
        await new Promise(resolve => setTimeout(resolve, 300));
        check(document.querySelector('#comparison a[href*="item=1"]') && g_summaries.comparison.groups[0][0][0] === 1,
            'Comparison renders equipment: '+errors.join('; '));

        const calc = new TalentCalc(); calc.initialize('talent', {});
        // Pre-register a minimal real tree response so URL imports stay entirely local.
        calc.registerClass(1, [{n:'Arms', icon:'inv_misc_questionmark', t:[]}, {n:'Fury', icon:'inv_misc_questionmark', t:[]}, {n:'Protection', icon:'inv_misc_questionmark', t:[]}]);
        calc.setClass(1);
        const build = calc.getWhBuild();
        window.prompt = () => 'https://www.wowhead.com/talent='+build;
        calc.promptWhBuild();
        check(calc.getWhBuild() === build, 'Historical talent URL imports without remote traffic');
        g_pet_talents = [{f:[1], n:'Ferocity', icon:'inv_misc_questionmark', t:[]}];
        const pet = new TalentCalc(); pet.initialize('petcalc', {mode:TalentCalc.MODE_PET, classId:1});
        check(pet.getTalentTrees()[0].n === 'Ferocity', 'Pet calculator selects family without viewer');

        // Intercept only the local profile data transport; initialize and render the actual profiler.
        const ajax = $WH.g_ajaxIshRequest;
        $WH.g_ajaxIshRequest = () => {};
        g_statistics = {classs:{1:[[1,1,1,1],[1,1,1,1],[1,1],[1,1],1,1,1,1,1,{},{}]},
            race:{1:[1,1,1,1,1,{},{}]}, combo:{1:{80:Array(12).fill(1)}}, level:{80:1}, skills:{}};
        const profile = new Profiler(); profile.initialize('profile', {id:1});
        profile.registerProfile({id:1, name:'Fixture', region:['',''], battlegroup:['',''], realm:['',''],
            level:80, classs:1, race:1, gender:0, faction:0, source:0, user:0, username:'', published:1,
            talents:{}, pets:[], skills:{}, reputation:{}, achievements:{}, statistics:{}, activity:{},
            titles:{}, quests:{}, spells:{}, glyphs:{}, inventory:[], items:[], nomodel:1});
        $WH.g_ajaxIshRequest = ajax;
        check(document.getElementById('profile').textContent.includes('Fixture'), 'Profile renders without a viewer');

        const link = document.getElementById('tooltip-link');
        link.dispatchEvent(new MouseEvent('mouseover', {bubbles:true, clientX:20, clientY:20}));
        await new Promise(resolve => setTimeout(resolve, 200));
        check(document.body.textContent.includes('Local tooltip fixture'), 'Local tooltip shows on hover');
        await new Promise(resolve => setTimeout(resolve, 100));
        check(link.textContent === 'Fixture Sword' && link.classList.contains('icontinyl'), 'Link renaming and icons work');
        const crop = new Cropper({parent:'crop', oWidth:100, oHeight:100, rWidth:100, rHeight:100,
            minCrop:20, url:g_staticUrl+'/images/ui/misc/selection-h.gif'});
        crop.selectAll();
        check(crop.getCoords() === '0.000,0.000,1.000,1.000', 'Real cropper selection renders at the requested coordinates');
        for (const border of document.querySelectorAll('.selection .hborder, .selection .hborder2, .selection .vborder, .selection .vborder2')) {
            const url = getComputedStyle(border).backgroundImage.match(/url\("?([^"\)]+)/)[1];
            check(new URL(url).pathname.startsWith(g_staticUrl+'/images/ui/misc/selection-'), 'Crop border resolves locally under this installation');
            const img = new Image();
            await new Promise((resolve, reject) => {img.onload = resolve; img.onerror = reject; img.src = url;});
            check(img.naturalWidth > 0, 'Local crop border image loads');
        }
        await new Promise(resolve => setTimeout(resolve, 400));
        check(document.getElementById('widget').contentDocument.getElementById('result').textContent === 'PASS', 'Standalone widget embedding retains tooltips, renaming and icons');
        check(!document.querySelector('object, embed, .profiler-model, .talentcalc-model'), 'No Flash or model UI');
        check(!performance.getEntriesByType('resource').some(entry => /(?:wowhead\.com|zamimg\.com|zam\.com)/i.test(entry.name)), 'No application asset requests to Wowhead/ZAM');
        check(errors.length === 0, 'No JavaScript errors or blocked resources: '+errors.join('; '));
        document.getElementById('result').textContent = `PASS: ${checks} retirement browser checks`;
    }
    catch (error) { document.getElementById('result').textContent = 'FAIL: '+error.stack+'\n'+errors.join('\n'); }
});
