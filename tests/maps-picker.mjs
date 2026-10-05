// Execute the actual picker and rendered template initializer with synthetic DOM boundaries.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

let input = '';
for await (const chunk of process.stdin) input += chunk;
const fixtures = JSON.parse(input);
const read = path => readFileSync(new URL('../' + path, import.meta.url), 'utf8');
const picker = read('static/js/maps.js');
const mapper = read('setup/tools/filegen/templates/global.js/mapper.js');
const strcmp = read('static/js/basic.js').match(/\$WH\.strcmp = function\(a, b\) \{[\s\S]*?\n\};/)[0];
let checks = 0;
function equal(actual, expected, label) { ++checks; assert.deepEqual(actual, expected, label); }
for (const fixture of fixtures) {
    const elements = {};
    const element = tag => ({ tag, children: [], style: {}, value: '', selectedIndex: 0 });
    const context = {
        pickerMarker: 0,
        g_staticUrl: '/static',
        location: { href: 'https://example.test/?maps=2557.1:102203' },
        $WH: {
            ge: id => elements[id] ||= element('select'),
            ce: tag => element(tag),
            ct: text => ({ tag: '#text', text: String(text) }),
            ae: (parent, child) => parent.children.push(child),
            array_apply: (values, callback) => values.forEach(callback)
        }
    };
    const locale = fixture.locale === 'empty' ? 'enus' : fixture.locale;
    context.Locale = { getName: () => locale };
    runInNewContext(strcmp, context, { timeout: 1000 });
    const zones = read(`static/js/locale_${locale}.js`).match(/var g_zones = \{[\s\S]*?\n\};/);
    assert.ok(zones, 'Actual locale zone-name table exists');
    runInNewContext(zones[0], context, { timeout: 1000 });
    const originalNames = JSON.stringify(context.g_zones);
    runInNewContext(mapper, context, { timeout: 1000 });
    const realMapper = context.Mapper;
    // Keep the real link parser/serializer; replace only its DOM-heavy constructor and pin updates.
    function MapperFixture(options) { this.options = options; this.zone = 0; this.level = 0; this.pins = []; }
    MapperFixture.sizes = realMapper.sizes;
    MapperFixture.multiLevelZones = { 2557: ['2557-0', '2557-1'] };
    Object.assign(MapperFixture.prototype, {
        setLink: realMapper.prototype.setLink,
        getLink: realMapper.prototype.getLink,
        getZone: realMapper.prototype.getZone,
        setZone(zone, level = 0) { this.zone = zone; this.level = level; return true; },
        setCoords(coords, level = this.level) { this.pins = coords.map(([x, y]) => ({ x, y, floor: level, free: false })); }
    });
    context.Mapper = MapperFixture;
    runInNewContext(picker, context, { timeout: 1000 });
    runInNewContext(fixture.script, context, { timeout: 1000 });
    equal(context.pickerMarker, 0, 'Localized names stay data');
    equal(JSON.stringify(context.g_zones), originalNames, 'Instance labels do not alter global zone-name rendering');
    for (const group of ['dungeons', 'raids']) {
        const options = elements['maps-' + group].children;
        const expected = Object.entries(fixture.maps[group]).sort(([, a], [, b]) => a.localeCompare(b));
        equal(options.map(option => [String(option.value), option.children[0].text]), expected, `${locale}: available ${group} sorted by localized name`);
        for (const option of options) {
            equal(option.tag, 'option', 'Native option element');
            equal(option.children.map(child => child.tag), ['#text'], 'Labels use text nodes even with HTML-like names');
            const select = { value: String(option.value), selectedIndex: 2 };
            context.ma_ChooseZone(select);
            equal(context.myMapper.getZone(), select.value, 'Choosing an available instance selects its zone');
            equal(select.selectedIndex, 0, 'Picker resets after a choice');
            const imageLocale = fixture.mapLocales[select.value];
            const prefix = `static/images/wow/maps/${imageLocale}/original/${select.value}`;
            const image = fixture.images.find(path => path === prefix + '.jpg' || path.startsWith(prefix + '-'));
            assert.ok(image, 'Resolved image locale contains the selected map'); ++checks;
            const display = { zone: select.value, level: 0, floorPins: {}, span: { style: {} }, mapLocales: context.myMapper.options.mapLocales };
            realMapper.prototype.setMap.call(display, image.split('/').at(-1).slice(0, -4), 0);
            equal(display.span.style.background, 'url(/' + image + ')', 'Real Mapper requests the resolved localized/English image');
        }
    }
    equal(context.myMapper.options.editable, true, 'Existing editable map mode preserved');
    equal(context.myMapper.options.zoom, 1, 'Existing map zoom preserved');
    const defaultDisplay = { zone: 12, level: 0, floorPins: {}, span: { style: {} } };
    realMapper.prototype.setMap.call(defaultDisplay, '12', 0);
    equal(defaultDisplay.span.style.background, `url(/static/images/wow/maps/${locale}/original/12.jpg)`, 'Mappers without a locale override retain their active-locale path');
    for (const [group, count] of [['ek', 30], ['kalimdor', 24], ['outland', 8], ['northrend', 12], ['battlegrounds', 6]]) {
        equal(elements['maps-' + group].children.length, count, 'Existing non-instance picker options preserved');
        assert.ok(elements['maps-' + group].children.every(option => option.children[0].text !== 'undefined')); ++checks;
    }
    // Re-run initialization in a new DOM to verify unchanged floor/pin deep links and clear behavior.
    for (const node of Object.values(elements)) node.children = [];
    runInNewContext(fixture.script, context, { timeout: 1000 });
    equal(context.myMapper.getLink(), '2557.1:102203', 'Existing deep link keeps zone, floor and pins');
    equal(elements.mapper.style.display, '', 'Deep link opens the map');
    context.ma_UpdateLink(context.myMapper);
    equal(elements['link-to-this-map'].href, '?maps=2557.1:102203', 'Map link format preserved');
    context.myMapper.setCoords([]);
    context.ma_UpdateLink(context.myMapper);
    equal(elements['link-to-this-map'].href, '?maps=2557.1', 'Clearing pins preserves the selected zone and floor');
    for (const node of Object.values(elements)) node.children = [];
    context.ma_Init();
    equal(elements['maps-dungeons'].children.length, 0, 'No hardcoded dungeon fallback');
    equal(elements['maps-raids'].children.length, 0, 'No hardcoded raid fallback');
}
process.stdout.write(`PASS: ${checks} map picker DOM/locale/link checks\n`);
