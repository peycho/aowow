<?php

namespace Aowow;

if (!defined('AOWOW_REVISION'))
    die('illegal access');

if (!CLI)
    die('not in cli mode');


CLISetup::registerSetup("build", new class extends SetupScript
{
    use TrTemplateFile;

    protected array $info = array(
        'robots' => [[], CLISetup::ARGV_PARAM, 'Fills robots.txt with site variables.']
    );

    protected array $fileTemplateSrc  = ['robots.txt.in'];
    protected array $fileTemplateDest = ['robots.txt'];     // aowow root

    public function __construct()
    {
        // Containers keep the code root immutable and alias this generated file.
        if ($path = getenv('AOWOW_ROBOTS_FILE'))
            $this->fileTemplateDest = [$path];
    }
});

?>
