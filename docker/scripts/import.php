<?php

declare(strict_types=1);

// The same guard protects manual imports and local MySQL first initialization.
require __DIR__.'/environment.php';
try {
    if (in_array('--environment', $argv, true)) {
        $db = dockerDatabase('AOWOW');
    } else {
        define('AOWOW_REVISION', 1);
        require '/opt/aowow/config/config.php';
        $db = $AoWoWconf['aowow'];
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $link = new mysqli($db['host'], $db['user'], $db['pass'], $db['db'], (int)($db['port'] ?? 3306));
    $link->set_charset('utf8mb4');
    if ($link->query('SHOW FULL TABLES')->num_rows !== 0) throw new RuntimeException('Import refused: application database is not empty.');
    // SQL DDL is not transactional. A persistent marker blocks replay even if
    // failure happens before the first shipped table has been created.
    $link->query('CREATE TABLE `_aowow_docker_import_pending` (`started` TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
    foreach (['01-db_structure.sql', '02-db_initial_data.sql', '03-db_initial_articles.sql'] as $file) {
        $sql = file_get_contents('/opt/aowow/setup/sql/'.$file);
        $link->multi_query($sql);
        do {
            if ($result = $link->store_result()) $result->free();
            if (!$link->more_results()) break;
            $link->next_result();
        } while (true);
        echo 'Imported '.$file."\n";
    }
    $link->query('DROP TABLE `_aowow_docker_import_pending`');
} catch (Throwable) {
    fwrite(STDERR, "Import failed or refused. The database must be empty; inspect database state before recovery. Existing or partially imported databases are never replayed automatically.\n");
    exit(1);
}
