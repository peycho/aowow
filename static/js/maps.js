function ma_Init(instanceMaps, mapLocales) {
    instanceMaps = instanceMaps || { dungeons: {}, raids: {} };
    ma_AddOptions($WH.ge('maps-ek'), [1, 3, 4, 8, 10, 11, 12, 28, 33, 36, 38, 40, 41, 44, 45, 46, 47, 51, 85, 130, 139, 267, 1497, 1519, 1537, 3430, 3433, 3487, 4080, 4298]);
    ma_AddOptions($WH.ge('maps-kalimdor'), [14, 15, 16, 17, 141, 148, 215, 331, 357, 361, 400, 405, 406, 440, 490, 493, 618, 1377, 1637, 1638, 1657, 3524, 3525, 3557]);
    ma_AddOptions($WH.ge('maps-outland'), [3483, 3518, 3519, 3520, 3521, 3522, 3523, 3703]);
    ma_AddOptions($WH.ge('maps-northrend'), [65, 66, 67, 210, 394, 495, 2817, 3537, 3711, 4197, 4395, 4742]);
    ma_AddOptions($WH.ge('maps-battlegrounds'), [2597, 3277, 4384, 3358, 3820, 4710]);
    ma_AddOptions($WH.ge('maps-raids'), Object.keys(instanceMaps.raids), instanceMaps.raids);
    ma_AddOptions($WH.ge('maps-dungeons'), Object.keys(instanceMaps.dungeons), instanceMaps.dungeons);

    myMapper = new Mapper({
        parent: 'mapper-generic',
        editable: true,
        zoom: 1,
        mapLocales: mapLocales || {},
        onPinUpdate: ma_UpdateLink,
        onMapUpdate: ma_UpdateLink
    });

    var _ = location.href.indexOf('maps=');
    if (_ != -1) {
        _ = location.href.substr(_ + 5);
        if (myMapper.setLink(_)) {
            $WH.ge('mapper').style.display = '';
        }
    }
}

function ma_AddOptions(s, a, names) {
    names = names || g_zones;
    a.sort(function (a, b) { return ma_Sort(a, b, names); });

    $WH.array_apply(a, function (x) {
        var o = $WH.ce('option');
        o.value = x
        $WH.ae(o, $WH.ct(names[typeof x == 'string' ? parseInt(x) : x]));
        $WH.ae(s, o);
    });
}

function ma_Sort(a, b, names) {
    if (typeof a == 'string') {
        a = parseInt(a);
    }

    if (typeof b == 'string') {
        b = parseInt(b);
    }

    return $WH.strcmp((names || g_zones)[a], (names || g_zones)[b]);
}

function ma_ChooseZone(s) {
    if (s.value && s.value != '0') {
        if (myMapper.getZone() == 0) {
            $WH.ge('mapper').style.display = '';
        }

        myMapper.setZone(s.value);
    }

    s.selectedIndex = 0;
}

function ma_UpdateLink(_) {
    var
        b = '?maps',
        l = _.getLink();

    if (l) {
        b += '=' + l;
    }

    $WH.ge('link-to-this-map').href = b;
};
