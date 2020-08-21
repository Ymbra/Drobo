<?php

namespace Ymbra\DrupalRoboTasks\Tasks;

/**
 * @file
 * This is project's console commands configuration for Robo task runner.
 *
 * @category Scripts
 * @package Scripts
 * @author Ymbra <hola@ymbra.com>
 * @license GPLv3+ https://www.gnu.org/licenses/gpl.html
 * @link https://robo.li/
 */

use Symfony\Component\Yaml\Yaml;
use Robo\Tasks;

/**
 * Database tasks.
 */
class DatabaseCommands extends Tasks {

  /**
   * Backup database.
   */
  public function dbBackup() {
    $date = date('YmdHi');

    // Create "backups" directory.
    if (!is_dir('backups')) {
      $this->taskExec('mkdir')
        ->arg(self::ROOT . '/backups')
        ->run();
    }

    // Generate backup.
    $this->taskExec(self::DRUSH)
      ->arg('sql-dump')
      ->arg('--result-file=' . self::ROOT . "/backups/backup_{$date}.sql")
      ->run();
  }

}
