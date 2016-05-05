<?php

namespace Terminus\Commands;

use Symfony\Component\Yaml\Yaml;
use Terminus\Commands\TerminusCommand;
use Terminus\Exceptions\TerminusException;
use Terminus\Utils;

/**
 * Manage Terminus plugins
 *
 * @command plugin
 */
class PluginSearchCommand extends TerminusCommand {

  /**
   * Object constructor
   *
   * @param array $options Elements as follow:
   * @return PluginCommand
   */
  public function __construct(array $options = []) {
    parent::__construct($options);
  }

  /**
   * Search for plugins in well-known or custom registries
   *
   * @param array $args A list of one or more partial
   *   or complete plugin names
   *
   * @subcommand search
   * @alias find
   */
  public function search($args = array()) {
    if (empty($args)) {
      $message = "Usage: terminus plugin search plugin-name-1";
      $message .= " [plugin-name-2] ...";
      $this->failure($message);
    }

    $plugins = $this->searchRegistries($args);
    if (empty($plugins)) {
      $message = "No plugins were found.";
      $this->log()->notice($message);
    } else {
      $rows = array();
      $labels = [
        'location'    => 'Location',
        'description' => 'Description',
      ];
      $start = microtime(true) * 10000;
      $message = "The following plugins were found:";
      $this->log()->notice($message);
      foreach ($plugins as $plugin => $description) {
        $rows[] = [
          'location'    => $plugin,
          'description' => $description,
        ];
      }
      // Output the plugin list in table format.
      $this->output()->outputRecordList($rows, $labels);
      $count = count($rows);
      $plural = '';
      if ($count > 1) {
        $plural = 's';
      }
      $end = microtime(true) * 10000;
      $elapsed = round($end - $start);
      $message = "Found {$count} plugin{$plural} in {$elapsed} sec.";
      $message .= "  Use 'terminus plugin install' to add plugins.";
      $this->log()->notice($message);
    }
  }

  /**
   * Manage registries
   *
   * @param array $args A subcommand followed by a list of one
   *   or more registries
   *
   * @subcommand registry
   * @alias reg
   */
  public function registry($args = array()) {
    $usage = "Usage: terminus plugin registry | reg add | list | remove";
    $usage .= " <URL to plugin Git registry 1>";
    $usage .= " [<URL to plugin Git registry 2>] ...";
    if (empty($args)) {
      $this->failure($usage);
    }
    $cmd = array_shift($args);
    $valid_cmds = array('add', 'list', 'remove');
    if (!in_array($cmd, $valid_cmds)) {
      $this->failure($usage);
    }
    switch ($cmd) {
      case 'add':
        if (empty($args)) {
          $this->failure($usage);
        }
        foreach ($args as $arg) {
          $this->addRegistry($arg);
        }
          break;
      case 'list':
        $registries = $this->listRegistries();
        if (empty($registries)) {
          $message = 'No plugin registries exist.';
          $this->log()->error($message);
        } else {
          $reg_yml = $this->getRegistriesPath();
          $message = "Plugin registries are stored in $reg_yml.";
          $this->log()->notice($message);
          $message = "The following plugin registries are available:";
          $this->log()->notice($message);
          foreach ($registries as $registry) {
            $this->log()->notice($registry);
          }
          $message = "The 'terminus plugin search' command will only search in these registries.";
          $this->log()->notice($message);
        }
          break;
      case 'remove':
        if (empty($args)) {
          $this->failure($usage);
        }
        foreach ($args as $arg) {
          $this->removeRegistry($arg);
        }
          break;
    }
  }

  /**
   * Get the plugin directory
   *
   * @param string $arg Plugin name
   * @return string Plugin directory
   */
  private function getPluginDir($arg = '') {
    $plugins_dir = getenv('TERMINUS_PLUGINS_DIR');
    $windows = Utils\isWindows();
    if (!$plugins_dir) {
      // Determine the correct $plugins_dir based on the operating system
      $home = getenv('HOME');
      if ($windows) {
        $system = '';
        if (getenv('MSYSTEM') !== null) {
          $system = strtoupper(substr(getenv('MSYSTEM'), 0, 4));
        }
        if ($system != 'MING') {
          $home = getenv('HOMEPATH');
        }
        $home = str_replace('\\', '\\\\', $home);
        $plugins_dir = $home . '\\\\terminus\\\\plugins\\\\';
      } else {
        $plugins_dir = $home . '/terminus/plugins/';
      }
    } else {
      // Make sure the proper trailing slash(es) exist
      if ($windows) {
        $slash = '\\\\';
        $chars = 2;
      } else {
        $slash = '/';
        $chars = 1;
      }
      if (substr("$plugins_dir", -$chars) != $slash) {
        $plugins_dir .= $slash;
      }
    }
    // Create the directory if it doesn't already exist
    if (!is_dir("$plugins_dir")) {
      mkdir("$plugins_dir", 0755, true);
    }
    return $plugins_dir . $arg;
  }

  /**
   * Get registries
   *
   * @return array Parsed Yaml from the registries.yml file
   */
  private function getRegistries() {
    $reg_yml = $this->getRegistriesPath();
    $header = $this->getRegistriesHeader();
    if (!file_exists($reg_yml)) {
      $registries = <<<YML
'https://github.com':
    - pantheon-systems
    - derimagia
    - pi-ron
    - sean-e-dietrich
    - uberhacker
YML;
      $handle = fopen($reg_yml, "w");
      fwrite($handle, "$header\n");
      fwrite($handle, $registries);
      fclose($handle);
    }
    $reg_data = @file_get_contents($reg_yml);
    if ($reg_data != $header) {
      return Yaml::parse($reg_data);
    }
    return array();
  }

  /**
   * Get registries.yml path
   *
   * @return string The full path to the registries.yml file
   */
  private function getRegistriesPath() {
    $plugin_dir = $this->getPluginDir();
    return $plugin_dir . 'registries.yml';
  }

  /**
   * Get registries.yml header
   *
   * @return string registries.yml header
   */
  private function getRegistriesHeader() {
    return <<<YML
# Terminus plugin registries
#
# List of well-known or custom plugin Git registries
---
YML;
  }

  /**
   * Add registry
   *
   * @param string $reg Registry URL
   */
  private function addRegistry($reg = '') {
    if (!$this->isValidUrl($reg)) {
      $message = "$reg is not a valid URL.";
      $this->failure($message);
    }
    $reg_exists = false;
    $registries = $this->listRegistries();
    foreach ($registries as $registry) {
      if ($registry == $reg) {
        $message = "Registry $reg already added.";
        $this->log()->error($message);
        $reg_exists = true;
        break;
      }
    }
    if (!$reg_exists) {
      $parts = parse_url($reg);
      if (isset($parts['path']) && ($parts['path'] != '/')) {
        $host = $parts['scheme'] . '://' . $parts['host'];
        $path = substr($parts['path'], 1);
        $registries = $this->getRegistries();
        $registries[$host][] = $path;
        $this->saveRegistries($registries);
      }
    }
  }

  /**
   * List registries
   *
   * @return array List of fully qualified domain registry URLs
   */
  private function listRegistries() {
    $reg_urls = array();
    $registries = $this->getRegistries();
    foreach ($registries as $host => $registry) {
      foreach ($registry as $path) {
        $reg_urls[] = $host . '/' . $path;
      }
    }
    return $reg_urls;
  }

  /**
   * Remove registry
   *
   * @param string $reg Registry URL
   */
  private function removeRegistry($reg = '') {
    $exists = false;
    $registries = $this->listRegistries();
    foreach ($registries as $registry) {
      if ($registry == $reg) {
        $exists = true;
        break;
      }
    }
    if (!$exists) {
      $message = "Registry $reg does not exist.";
      $this->log()->error($message);
    } else {
      $parts = parse_url($reg);
      $host = $parts['scheme'] . '://' . $parts['host'];
      $path = substr($parts['path'], 1);
      $registries = $this->getRegistries();
      foreach ($registries as $reg_host => $registry) {
        if ($reg_host == $host) {
          foreach ($registry as $key => $reg_url) {
            if ($reg_url == $path) {
              unset($registries[$host][$key]);
              $this->saveRegistries($registries, 'remove');
              break;
            }
          }
          break;
        }
      }
    }
  }

  /**
   * Save registries
   *
   * @param array $regs A list of plugin registries
   */
  private function saveRegistries($regs = array(), $op = 'add') {
    $reg_yml = $this->getRegistriesPath();
    $header = $this->getRegistriesHeader();
    $reg_data = "$header\n" . Yaml::dump($regs);
    try {
      $handle = fopen($reg_yml, "w");
      fwrite($handle, $reg_data);
      fclose($handle);
    } catch (Exception $e) {
      $messages = array();
      $messages[] = "Unable to $op plugin registry.";
      $messages[] = $e->getMessage();
      $message = implode("\n", $messages);
      $this->failure($message);
    }
    if ($op == 'add') {
      $oped = 'added';
    } else {
      $oped = 'removed';
    }
    $message = "Plugin registry was $oped successfully.";
    $this->log()->notice($message);
  }

  /**
   * Search registries
   *
   * @param string $args A list of partial or complete plugin names
   * @return array List of plugin names found
   */
  private function searchRegistries($args = array()) {
    $titles = array();
    $plugins = array();
    $registries = $this->listRegistries();
    foreach ($registries as $registry) {
      foreach ($args as $arg) {
        $url = $registry . '/' . $arg;
        if ($this->isValidUrl($url)) {
          if ($this->isValidPlugin($registry, $arg)) {
            $plugins[$arg] = $registry;
          }
        } else {
          $parts = @parse_url($registry);
          if (isset($parts['host'])) {
            $host = $parts['host'];
            switch ($host) {
              case 'bitbucket.com':
                // TODO: Add BitBucket parsing logic
                  break;
              case 'github.com':
                $reg_data = @file_get_contents($registry . '?tab=repositories');
                if (!empty($reg_data)) {
                  $path = $parts['path'];
                  $pattern = '|' . $path . '/(.*)".*codeRepository|U';
                  preg_match_all($pattern, $reg_data, $matches);
                  if (isset($matches[1])) {
                    foreach ($matches[1] as $match) {
                      if ($title = $this->isValidPlugin($registry, $match)) {
                        $titles["$registry/$match"] = $title;
                      }
                    }
                    foreach ($titles as $reg => $title) {
                      if ((stripos($reg, $arg) !== false) || (stripos($title, $arg) !== false)) {
                        $parts = explode(':', $title);
                        if (isset($parts[1])) {
                          $title = trim($parts[1]);
                        }
                        $plugins[$reg] = $title;
                      }
                    }
                  }
                }
                  break;
              default:
            }
          }
        }
      }
    }
    return $plugins;
  }

  /**
   * Check whether a plugin is valid
   *
   * @param string Registry URL
   * @param string Plugin name
   * @return string Plugin title, if found
   */
  private function isValidPlugin($registry, $plugin) {
    // Make sure the URL is valid
    $is_url = (filter_var($registry, FILTER_VALIDATE_URL) !== false);
    if (!$is_url) {
      return '';
    }
    // Make sure a subpath exists
    $parts = parse_url($registry);
    if (!isset($parts['path']) || ($parts['path'] == '/')) {
      return '';
    }
    // Search for a plugin title
    $plugin_data = @file_get_contents($registry . '/' . $plugin);
    if (!empty($plugin_data)) {
      preg_match('|<title>(.*)</title>|', $plugin_data, $match);
      if (isset($match[1])) {
        $title = $match[1];
        if (stripos($title, 'terminus') && stripos($title, 'plugin')) {
          return $title;
        }
        return '';
      }
      return '';
    }
    return '';
  }

  /**
   * Check whether a URL is valid
   *
   * @param string $url The URL to check
   * @return bool True if the URL returns a 200 status
   */
  private function isValidUrl($url = '') {
    if (!$url) {
      return false;
    }
    $headers = @get_headers($url);
    if (!isset($headers[0])) {
      return false;
    }
    return (strpos($headers[0], '200') !== false);
  }

}
