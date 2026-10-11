# Licensing provenance review

Reviewed **October 11, 2026**, against fork base commit
`960616048f0e099800188dcc266a06681b5566d5` and the committed Composer lockfile.
This is an evidence inventory, not a blanket licensing decision or a legal
compatibility determination. Component notices do not establish a blanket
project license.

## Method and evidence

The review enumerated tracked source/assets, inspected explicit license and
copyright headers, read Composer's locked package metadata and installed package
notices, and decoded the TrueType font's name table. Dependencies were installed
from the lockfile with Composer plugins/scripts disabled. Extracted client files,
configuration and operator uploads are outside this inventory.

The [third-party notices](../THIRD_PARTY_NOTICES.md) list verified declarations.
A declared license is evidence about that component; it does not establish that
every inherited file has the same license. Credits, public hosting and a
noncommercial intention are not substitutes for a redistribution grant.

| Area | Verified evidence | Remaining uncertainty |
| --- | --- | --- |
| DBCReader | Header names Mix and AGPL 3-or-later; unmodified reference license text is included | Effects on a combined distribution need a separate compatibility assessment |
| Uploader | Andrew Valums attribution and GPL/LGPL 2-or-later declaration; reference texts included | Header's exact dual-license interpretation and corresponding historical release grant |
| jQuery 3.7.0 | Versioned bundled header and official release MIT license | No blanket inference for other inherited scripts |
| Color Animations | John Resig attribution and MIT/GPL declaration | Original complete grant, GPL version and exact inherited release |
| Composer dependencies | Versions/identifiers in lockfile and copyright/license notices in installed packages | Metadata alone does not select between alternative terms or establish combined-project compatibility |
| AoWoW PHP/client code and design | README credits and existing repository history | Rights and license coverage for files without explicit grants, original client/design and contributor changes |
| Font | Name table identifies Zhongyi Heiti (中易黑体), ZYHei/ZYHei_FRIZQT, version 3.00, Beijing ZhongYi Electronics, 1995–2003, all rights reserved; Simplified Chinese tooltip stylesheet loads it | No accompanying redistribution grant found |
| Logos and static/game imagery | Ownership notices attribute World of Warcraft assets originating from Blizzard Entertainment to Blizzard and applicable rights holders; other third-party assets belong to their respective owners | Individual asset sources and redistribution terms are not established for every asset |
| Maintenance illustration | Existing generation/encoding record | Project-wide licensing and game-related intellectual-property rights are not established by generation history |
| Client extractions and community uploads | Separate generated/operator inputs excluded by ignore rules | Operators need appropriate rights for their own inputs and uploads; this review grants none |

## Accompanying texts

The [asset notices](../THIRD_PARTY_NOTICES.md#inherited-assets-and-project-attribution)
attribute World of Warcraft assets originating from Blizzard Entertainment using
[Blizzard's copyright notices](https://www.blizzard.com/en-us/legal/9c9cb70b-d1ed-4e17-998a-16c6df46be7b/copyright-notices).
The font attribution comes from its embedded TrueType name table, including
the Chinese family name and copyright statement. Attribution does not establish
redistribution permission or change the component's terms.

The jQuery MIT text is copied unchanged from its
[3.7.0 release](https://raw.githubusercontent.com/jquery/jquery/3.7.0/LICENSE.txt).
The GNU reference texts are copied unchanged from the
[SPDX license-text collection](https://github.com/spdx/license-list-data/tree/main/text):
`AGPL-3.0-only`, `GPL-2.0-only`, `GPL-3.0-only`, `LGPL-2.0-only` and `LGPL-2.1-only`.
Their filenames omit the SPDX suffix for readability; source grants allowing
later versions are preserved in component headers and the notices.

Verified installed package notices are retained with package-specific names in
`licenses/`. They accompany the inventory rather than applying to the entire
repository. Recheck versions and notices when dependencies change.

## Possible Chinese font replacements

These Chinese sans-serif (Heiti) fonts are candidates for a future replacement
of `static/ZYHei.ttf`. They are reference options; no replacement font is bundled
or selected by this report.

| Candidate | Licensing information to consider |
| --- | --- |
| Source Han Sans (思源黑体) | Adobe's [official release](https://github.com/adobe-fonts/source-han-sans) uses the [SIL Open Font License 1.1](https://github.com/adobe-fonts/source-han-sans/blob/release/LICENSE.txt), allowing commercial use and distribution with software under its conditions. Preserve the copyright/license notices and observe reserved-font-name rules when modifying it. |
| Alibaba PuHuiTi (阿里巴巴普惠体) | Another candidate for free personal and commercial use. Consult the [official font distribution](https://www.alibabafonts.com/) for the chosen version's legal statement. Commercial-use, embedding, redistribution and modification terms have not been verified in this inventory; do not assume an OFL or other open-source license. |

Before adopting either font, record the exact version and accompanying terms,
check Simplified Chinese glyph coverage, and compare tooltip wrapping, alignment
and download size with the existing font.

## Follow-up before a blanket license or asset distribution

Obtain original component/source and contributor permission evidence for uncovered
AoWoW files, resolve the uploader/animation declarations, and establish provenance
for the font, logos and inherited imagery. Determine applicable combined-code
obligations before choosing a root license. If asset permissions cannot be
established, review replacement or removal before redistributing those assets.

Record each resolution with its source, covered files, exact terms and relevant
copyright holder. Keep uncertain entries explicit. Do not interpret this
inventory or passing tests as approval to redistribute all assets.
