<?php
    namespace Aowow\Template;

    /** @var PageTemplate $this */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta http-equiv="pragma" content="no-cache" />
    <meta http-equiv="expires" content="-1" />
    <title>Maintenance</title>

    <style type="text/css">
        * { box-sizing: border-box; }
        body { margin: 0; text-align: center; font-family: Arial, sans-serif; background: #000; color: #d9f1ff; }
        .maintenance { max-width: 800px; margin: 0 auto; padding: 32px 20px 24px; }
        .maintenance-logo { display: block; width: 240px; max-width: 70%; height: auto; margin: 0 auto 24px; }
        .maintenance h1 { margin: 0 0 12px; font-size: clamp(24px, 4vw, 30px); line-height: 1.2; }
        .maintenance p { margin: 6px auto; max-width: 640px; color: #b7c3cf; font-size: 16px; line-height: 1.6; }
        .maintenance-art { display: block; width: 640px; max-width: 100%; height: auto; margin: 20px auto 0; }
    </style>
</head>
<body>
    <main class="maintenance">
        <img class="maintenance-logo" src="<?=$this->escHTML($this->gStaticUrl); ?>/images/logos/home.png" width="261" height="119" alt="World of Warcraft database" />
        <h1>Maintenance in progress</h1>
        <p>The database is temporarily unavailable while we perform maintenance.</p>
        <p>Please check back later. Thank you for your patience.</p>
        <img class="maintenance-art" src="<?=$this->escHTML($this->gStaticUrl); ?>/images/maintenance/archive-repair.webp" width="1536" height="1024" alt="" />
    </main>
</body>
</html>
