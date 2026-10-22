import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import vm from 'node:vm';

let checks = 0;
for (const locale of ['enus', 'dede', 'eses', 'frfr', 'ruru', 'zhcn']) {
    const context = vm.createContext({g_staticUrl: '/static'});
    vm.runInContext(readFileSync(`static/js/locale_${locale}.js`, 'utf8'), context);
    vm.runInContext(readFileSync('static/js/filters.js', 'utf8'), context);
    const petMenu = context.mn_spells.find(row => row[0] === -7)[3];
    for (const [id, icon] of [[411,'Ability_Hunter_CombatExperience'],[410,'Ability_Druid_Swipe'],[409,'Ability_Hunter_Pet_Bear']]) {
        assert.equal(petMenu.find(row => row[0] === id)[4].tinyIcon, icon, `${locale}: pet talent category matches its type`); ++checks;
    }
    for (const [id,name] of [[80,'ownsmount'],[81,'ownspet']]) {
        const filter = context.fi_filters.profiles.find(filter => filter.id === id);
        assert.equal(filter.name,name); assert.equal(filter.type,'str-small');
        assert.ok(context.LANG.fiprofiles[name]); checks += 3;
    }
    const filters = context.fi_filters.spells;
    const ids = filters.filter(filter => filter.type).map(filter => filter.id);
    assert.equal(new Set(ids).size, ids.length, 'Criteria IDs remain unique'); ++checks;
    for (const [id, type] of [[104, 'flags'], [105, 'flags'], [202, 'spell_flagsCu']]) {
        const filter = filters.find(filter => filter.id === id);
        assert.equal(filter.type, type); assert.equal(filter.staffonly, true);
        assert.ok(context.LANG.fispells[filter.name]); checks += 3;
    }
    for (const filter of filters.filter(filter => filter.type?.startsWith('spell_flags'))) {
        const options = context.LANG.fidropdowns[filter.type];
        assert.ok(options, `${locale}: ${filter.type} has a selection table`);
        assert.equal(options.length, 32);
        assert.deepEqual(Array.from(options, option => option[0]), Array.from({length: 32}, (_, i) => i + 1));
        assert.ok(options.every(option => typeof option[1] === 'string' && option[1].length));
        checks += 4;
    }
    for (const role of [0, 8, 2, 16, 32]) {
        context.g_user = {roles: role};
        context.U_GROUP_EMPLOYEE = 50;
        context.$WH = {
            ce: name => ({nodeName: name, childNodes: []}),
            ct: text => ({textContent: text, childNodes: []}),
            ae: (parent, child) => parent.childNodes.push(child)
        };
        const div = {childNodes: []};
        context.fi_initCriterion({firstChild: div}, 'cr[]', 'spells');
        const options = div.childNodes[0].childNodes.flatMap(node => node.childNodes);
        for (const id of [104, 105, 202]) {
            assert.equal(options.some(option => option.value === id), Boolean(role & 50), 'Actual criterion UI respects employee roles');
            ++checks;
        }
    }
}
console.log(`PASS: ${checks} upstream spell-filter JavaScript locale/criterion checks`);
