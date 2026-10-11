# Third-party notices

These notices identify component-level evidence. **No blanket license is granted
for this repository.** License texts in `licenses/` apply only to the identified
components and are not a license for inherited design, fonts, artwork or all
AoWoW source. Existing source headers remain authoritative evidence.
See the [provenance report](docs/licensing-provenance.md) for unresolved coverage
and the distinction between a notice and established redistribution permission.

## Bundled source

| Component | Attribution and declared terms | Evidence and accompanying text |
| --- | --- | --- |
| DBCReader | Copyright 2009–2010 Mix; AGPL version 3 or any later version | [Source header](setup/tools/dbcreader.class.php); [AGPL 3 text](licenses/AGPL-3.0.txt) |
| File uploader 2.0 beta | Original version copyright 2010 Andrew Valums; header declares GPL 2 or later and LGPL 2 or later | [Source header](static/js/fileuploader.js); [GPL 2 text](licenses/GPL-2.0.txt), [LGPL 2 text](licenses/LGPL-2.0.txt). Exact upstream dual-license interpretation remains unverified. |
| jQuery 3.7.0 | OpenJS Foundation and other contributors; MIT | [Bundled header](static/js/jquery-3.7.0.min.js); [release license](licenses/jquery-MIT.txt) |
| jQuery Color Animations | Copyright 2007 John Resig; header declares MIT and GPL without a GPL version | [Source header](setup/tools/filegen/templates/global.js/animations.js). Exact historic license text/version remains unresolved. |

Generic GNU license texts retain their original terms; component headers that
permit later versions continue to do so. Their inclusion does not select a
project-wide license or settle compatibility between bundled components.

## Composer packages

These versions and identifiers are recorded in the committed
[lockfile](composer.lock). Composer installs each package's own notices with its
source; preserve those notices when deploying dependencies. Multiple identifiers
below reproduce metadata, not an independently selected licensing route.

| Package | Locked version | Declared identifiers | Copied notice |
| --- | --- | --- | --- |
| `dibi/dibi` | 5.1.1 | BSD-3-Clause, GPL-2.0-only, GPL-3.0-only | [Notice](licenses/dibi-license.md) |
| `phpmailer/phpmailer` | 7.1.1 | LGPL-2.1-only | [Notice](licenses/phpmailer-LICENSE.txt) |
| `jfcherng/php-diff` (development) | 6.16.3 | BSD-3-Clause | [Notice](licenses/php-diff-LICENSE.txt) |
| `jfcherng/php-color-output` (development) | 3.0.0 | MIT | [Notice](licenses/php-color-output-LICENSE.txt) |
| `jfcherng/php-mb-string` (development) | 2.0.1 | MIT | [Notice](licenses/php-mb-string-LICENSE.txt) |
| `jfcherng/php-sequence-matcher` (development) | 4.0.3 | BSD-3-Clause | [Notice](licenses/php-sequence-matcher-LICENSE.txt) |

[GPL 3](licenses/GPL-3.0.txt) and [LGPL 2.1](licenses/LGPL-2.1.txt) reference texts
are included for the recorded dependency terms. Package-specific copyright and
BSD/MIT grants are included alongside verified installed package notices in
`licenses/`; see the [provenance report](docs/licensing-provenance.md).
The generated Composer autoloader retains the MIT notice for Nils Adermann and
Jordi Boggiano in [its copied license](licenses/composer-autoloader-LICENSE.txt).
Dibi explicitly offers BSD or GPL alternatives in [its license](licenses/dibi-license.md).
PHPMailer copyright attribution remains in its source headers; preserve those
as well as its [license](licenses/phpmailer-LICENSE.txt).
Update this inventory when reviewing lockfile changes.

## Inherited assets and project attribution

The [README credits](README.md#thanks) recognize Mix, LordJZ, kliver, Sarjuuk and
the original database website. Attribution does not establish permission to
relicense inherited code or design.

World of Warcraft assets originating from Blizzard Entertainment remain the
property of Blizzard Entertainment and applicable rights holders. Other
third-party assets belong to their respective owners.
World of Warcraft, Warcraft and Blizzard Entertainment are trademarks or
registered trademarks of Blizzard Entertainment, Inc. See
[Blizzard's copyright notices](https://www.blizzard.com/en-us/legal/9c9cb70b-d1ed-4e17-998a-16c6df46be7b/copyright-notices).
This fork claims no ownership of third-party artwork; the software component
licenses above do not relicense it.
Individual asset sources and redistribution terms remain subject to review.

The bundled `static/ZYHei.ttf` identifies itself as Zhongyi Heiti (中易黑体),
with the font names `ZYHei` and `ZYHei_FRIZQT`. It is used for Simplified Chinese
tooltip text in [the locale stylesheet](static/css/locale_zhcn.css).
Its embedded copyright notice credits Beijing ZhongYi Electronics Co.,
1995–2003, with all rights reserved. No accompanying redistribution grant has
been established for this font.

The maintenance illustration has a documented generation history in
[its artwork record](docs/artwork/maintenance.md). That history does not establish
a blanket license for other assets or for game-related intellectual property.
Extracted client assets and operator uploads are separate inputs, not covered by
these component notices.
