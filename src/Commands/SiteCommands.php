<?php

namespace Ymbra\DrupalRoboTasks\Commands;

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
 * Site tasks.
 */
class SiteCommands extends Tasks {

  const ROOT = __DIR__ . '/../../../../..';

  // Use Drush in non interactive mode.
  const DRUSH = self::ROOT . '/vendor/bin/drush --yes --root=' . self::ROOT . '/web';

  /**
   * Build a new Drupal installation from existing configuration.
   */
  public function siteInstall() {
    $this->siteSettings();
    $this->io()->newLine();
    $this->io()->title('Drupal installation');

    // Determines whether a new installation should be performed or an
    // installation should be made from an existing configuration.
    $existing_config = '';
    foreach (scandir(self::ROOT . '/config/sync') as $file) {
      if (!in_array($file, ['.', '..', '.htaccess'])) {
        $existing_config = '--existing-config';
        break;
      }
    }

    // Drupal installation.
    $site_install = $this->taskExec(self::DRUSH)
      ->arg('site:install')
      ->arg('--account-name=' . getenv('ACCOUNT_NAME'))
      ->arg('--account-pass=' . getenv('ACCOUNT_PASS'))
      ->arg('--account-mail=' . getenv('ACCOUNT_MAIL'))
      ->arg('--locale=' . getenv('LOCALE'))
      ->arg('--site-name=' . getenv('SITE_NAME'))
      ->arg('--site-mail=' . getenv('SITE_MAIL'))
      ->arg('kickstart')
      ->rawArg($existing_config)
      ->run();

    if ($site_install->wasSuccessful()) {
      $collection = $this->collectionBuilder();

      // Run additional Drush commands.
      if (isset($this->drupalConfig->drushPostInstall)) {
        foreach ($this->drupalConfig->drushPostInstall as $command) {
          $collection->addTask(
            $this->taskExec(self::DRUSH)
              ->rawArg($command)
          );
        }
      }

      // Finish and rebuild cache.
      $collection->run();
      $this->clearCache();
    }
  }

  /**
   * Synchronize your local Drupal installation.
   */
  public function siteUpdate() {
    $this->io()->newLine();
    $this->io()->title('Drupal synchronization');

    $collection = $this->collectionBuilder();

    // Update the database.
    $collection->addTask(
      $this->taskExec(self::DRUSH)
        ->arg('updatedb')
    );

    // Import existing configuration.
    $collection->addTask(
      $this->taskExec(self::DRUSH)
        ->arg('config:import')
    );

    $collection->run();

    // Update translations.
    $this->io()->newLine();
    $this->localeUpdate();

    // Rebuild cache.
    $this->clearCache();
  }

  /**
   * Check and update translations.
   */
  public function localeUpdate() {
    // Getting site information.
    $site = Yaml::parseFile(self::ROOT . '/config/sync/system.site.yml');

    if (!empty($site) && $site['default_langcode'] != 'en') {
      $collection = $this->collectionBuilder();

      $collection->addTask(
        $this->taskExec(self::DRUSH)
          ->arg('locale:check')
          ->arg('--quiet')
      );

      $collection->addTask(
        $this->taskExec(self::DRUSH)
          ->arg('locale:update')
      );

      // Imports custom .po files defined at the /translations folder.
      $translations = self::ROOT . '/custom_translations/';
      foreach (scandir($translations) as $translation_file) {
        if ((bool) preg_match('/\.po$/', $translation_file)) {
          [$language, $extension] = explode('.', $translation_file);
          $collection->addTask(
            $this->taskExec(self::DRUSH)
              ->arg('locale:import')
              ->arg($language)
              ->arg($translations . $translation_file)
              ->arg('--type=customized')
              ->arg('--override=all')
          );
        }
      }

      $collection->run();
    }
  }

  /**
   * Export translations for a module or theme.
   */
  public function localeExport($language, $module) {
    // Get path to module.
    $module_list = json_decode(
      shell_exec(self::DRUSH . ' pm:list --format=json --fields=path')
    );
    $path_to_module = $module_list->{$module}->path;
    $this->taskExec(self::DRUSH)
      ->arg('potx')
      ->arg('single')
      ->arg('--folder=' . $path_to_module)
      ->arg('--api=8')
      ->arg('--language=' . $language)
      ->run();

    // Create dir if it doesn't exist.
    $path = self::ROOT . '/web/' . $path_to_module . '/';
    $translations = $path . 'translations/';
    $this->taskExec('mkdir')
      ->arg('-p')
      ->arg($translations)
      ->run();
    $this->taskExec('mv')
      ->arg(self::ROOT . '/web/general.pot')
      ->arg($translations . $language . '.po')
      ->run();
    // Write to the info file if needed.
    $text = "\n'interface translation project': " . $module . "\n";
    $text .= "'interface translation server pattern': '" . $path_to_module . "/translations/%language.po'";
    $this->taskWriteToFile($path . $module . '.info.yml')
      ->append()
      ->appendUnlessMatches('/interface translation project/', $text)
      ->run();
  }

  /**
   * Prepare the environment for a later Drupal installation.
   */
  protected function siteSettings() {
    $this->io()->title('Build custom configurations');
    $base_path = self::ROOT . '/web/sites/default';
    $collection = $this->collectionBuilder();

    // Restores default Drupal settings.
    $collection->addTask(
      $this->taskFilesystemStack()
        ->copy("{$base_path}/default.settings.php", "{$base_path}/settings.php", TRUE)
    );

    // Add custom configurations.
    $collection->addTask(
      $this->taskWriteToFile("{$base_path}/settings.php")
        ->append(TRUE)
        ->line('')
        ->line('// Custom configurations.')
        ->line('include $app_root . \'/\' . $site_path . \'/settings.local.php\';')
        ->line('$settings[\'install_profile\'] = \'kickstart\';')
        ->line('$settings[\'config_sync_directory\'] = \'../config/sync\';')
    );

    $collection->run();
  }

  /**
   * Rebuild cache.
   */
  protected function clearCache() {
    $this->io()->newLine();
    $this->io()->title('Clearing cache');
    $collection = $this->collectionBuilder();

    // Rebuild cache.
    $collection->addTask(
      $this->taskExec(self::DRUSH)
        ->arg('cache-rebuild')
    );

    // Flush all derived images only if the "image" module is enabled.
    if (file_exists(self::ROOT . '/config/sync/image.settings.yml')) {
      $collection->addTask(
        $this->taskExec(self::DRUSH)
          ->arg('image-flush')
          ->arg('--all')
      );
    }

    $collection->run();
  }

}
