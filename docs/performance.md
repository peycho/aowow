# Performance measurement

Use a populated development or staging site for lab sampling. The regression
suites use independent synthetic fixtures and do not establish deployed speed
or field Core Web Vitals.

## Sample a configured site

The manual sampler requires the same Node 24 and Chromium environment as the
[browser tests](../tests/README.md). It makes anonymous GET requests and clears
only its browser cache. It does not clear application caches, edit settings,
submit forms or alter database contents.

```sh
node tests/seo-performance-lab.mjs \
  https://database.example.com/ reports/performance/before.json
```

Provide routes after the output filename to select the sample:

```sh
AOWOW_PERF_RUNS=3 AOWOW_PERF_WIDTHS=390,1440 \
  node tests/seo-performance-lab.mjs https://database.example.com/ \
  reports/performance/guide.json '?guide=lunar-festival'
```

Without explicit routes the sampler covers the home page, item, spell, quest,
paginated item browse page and Lunar Festival guide. Choose records and guides
that exist in the target installation. Application subdirectory base URLs are
supported; sampled routes must stay inside that base.

The [Docker development CLI](docker-development.md) also contains Node and
Chromium. To retain output on the host, bind an ignored evidence directory to
the CLI container and write the JSON there. Direct Compose commands must use
`.env.dev` and both Compose files, as documented in the development guide.

By default each route has three cold/warm browser-cache pairs at 390 and 1440
CSS pixels. Optional `AOWOW_PERF_THROTTLE=1` applies 4× CPU slowdown, 150 ms
network latency, 200,000 bytes/s download and 93,750 bytes/s upload at both
widths. Record the profile and compare identical settings; this is a controlled
lab profile, not a model of every mobile device.

The JSON records browser version, sample time, viewport, response/paint/load
timings, LCP candidates, initial-window layout-shift sessions, resource sizes,
cache behavior, browser exceptions and DevTools CPU/layout metrics. Observers
remain active for 750 ms after load. Unsupported observer types are recorded;
an empty long-task list alone is not evidence of fast interaction.

Report medians together with all raw samples. Keep site URLs and operator
reports under ignored `reports/`. Compare bytes and requests alongside timings:
a faster single run is not proof that a change helped. Keep cold and warm
results separate, and distinguish browser cache from server/application cache.

## Images and embedded maps

Initial approved article images use native lazy loading and asynchronous decode.
Known local image dimensions reserve their space; one authored dimension derives
the other from the original ratio, while two authored dimensions retain priority.
Only the existing approved static/upload paths are inspected. No remote image
lookup is performed. JavaScript enhancement retains the initial dimensions and
loading policy; later editor changes do not inherit the previous image size.

Article maps retain native dimensions and pin coordinates. Maps in or within
600 pixels of the viewport initialize normally; maps farther away reserve their
dimensions and float before construction. They initialize on approach, and
printing flushes pending maps. Browsers without IntersectionObserver retain
immediate initialization. Screenshot thumbnails similarly reserve the known
displayed ratio and load lazily.

Header artwork and CSS logos retain their normal loading behavior. Follow
[image loading guidance](https://web.dev/articles/browser-level-image-lazy-loading)
when introducing new hero imagery; do not defer an LCP image. Diagnose LCP using
its [loading and rendering phases](https://web.dev/articles/optimize-lcp).

## Release and field acceptance

Deploy the PHP image renderer and source templates with a rebuilt global bundle:

```sh
php aowow --build=globaljs
```

Deliver the generated `static/js/global.js` and invalidate affected application
page caches and cached assets through the normal deployment procedure. No SQL
migration or sound rebuild is required. For a local generated bundle use
`make dev-cli ARGS='--build=globaljs'`. Development Apache changes require
`make dev-build` and recreation of the development web container.

After deployment, repeat the same lab samples and check visible images, map
pins, zoom/toggle controls, gallery sizing, article editing and printing. Review
mobile and desktop Search Console/CrUX data separately. The existing mobile
field baseline is a comparison point, not a result of these code changes. Lab
LCP and initial-window CLS do not substitute for field p75 LCP/INP/CLS or prove
ranking improvements.
