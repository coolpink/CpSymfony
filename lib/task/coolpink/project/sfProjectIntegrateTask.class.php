<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 * 
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Integrate a project into phpUnderControl and the build server
 *
 * @package    symfony
 * @subpackage task
 * @author     Blair McMillan <blair@coolpink.net>
 * @version    SVN: $Id: sfProjectDeployTask.class.php 23922 2009-11-14 14:58:38Z fabien $
 */
class sfProjectIntegrateTask extends sfProgBaseTask
{
  protected
    $hostname           = array('oslo', 'dev-two'),
    $interact           = true,
    $progressBar        = true,
    $projectName        = '',
    $projectPath        = '',
    $svnPath            = '',
    $svnUser            = 'devvo',
    $svnPassword        = 'raNe3eHa',
    $wwwroot            = '/var/www/build',
    $apacheConfig       = '/etc/apache2/sites-enabled',
    $cruisecontrolPath  = '/opt/cruisecontrol',
    $ignore             = 'lib/vendor/symfony/,test/,tests/,plugins/,cache/,lib/model/doctrine/base/,lib/form/doctrine/base/,lib/filter/doctrine/base/';

  /**
   * @var string VirtualHost details for apache
   */
  private $virtualHost = '<VirtualHost *:80>
	ServerName %project%.oslo.dev
	ServerAlias %project%.oslo.dev
	DocumentRoot %docroot%/%project%/
</VirtualHost>';

  /**
   * @see sfTask
   */
  protected function configure()
  {
    $this->addArguments(array(
      new sfCommandArgument('project', sfCommandArgument::REQUIRED, 'Project name (a-zA-Z0-9_.- characters only)'),
    ));

    $this->addOptions(array(
      new sfCommandOption('svn-path', null, sfCommandOption::PARAMETER_OPTIONAL, 'Full path to SVN repository'),
      new sfCommandOption('ignore', null, sfCommandOption::PARAMETER_OPTIONAL, 'Comma seperated list of folders to ignore'),
      new sfCommandOption('no-interact', null, sfCommandOption::PARAMETER_NONE, 'Execute task without prompts'),
      new sfCommandOption('no-progress', null, sfCommandOption::PARAMETER_NONE, 'Execute svn checkout without progress bar'),
    ));

    $this->namespace        = 'project';
    $this->name             = 'integrate';
    $this->briefDescription = 'Integrate a project into phpUnderControl';

    $this->detailedDescription = <<<EOF
The [project:integrate|INFO] task integrates a project into phpUnderControl and adds it to the build server:

  [./dev-tools project:integrate projectname|INFO]

You can specify what SVN repository to use by specifying the [svn-path|COMMENT] option:

  [./dev-tools project:integrate --svn-path=http://example/svn/projectname/trunk projectname|INFO]

As the task restarts some services it prompts you whether you want to continue or not.
You can prevent this by specifying the [no-interact|COMMENT] option in combination with the [svn-path|COMMENT] option:

  [./dev-tools project:integrate --no-interact --svn-path=http://example/svn/projectname/trunk projectname|INFO]

You can specify not to show a progress bar of the SVN checkout by specifying the [no-progress|COMMENT] option:

  [./dev-tools project:integrate --no-progress projectname|INFO]

You can specify what folders to ignore by using the [ignore|COMMENT] option, the defaults are:

  [./dev-tools project:integrate --ignore=lib/vendor/symfony/,test/,tests/,plugins/,cache/,lib/model/doctrine/base/,lib/form/doctrine/base/,lib/filter/doctrine/base/ --svn-path=http://example/svn/projectname/trunk projectname|INFO]
EOF;
  }

  /**
   * @see sfTask
   */
  protected function execute($arguments = array(), $options = array())
  {
    $this->logSection('task', sprintf('Validating project "%s" settings..', $arguments['project']), null, 'INFO');
    $this->validateHostname();
    $this->validateInput($arguments, $options);

    if ($this->interact)
    {
      $this->logSection('notice', 'Running this task will restart cruisecontrol and apache.', null, 'INFO');
      $this->logSection('notice', 'Double check with your workmates that this is ok.', null, 'INFO');
      if (!$this->askConfirmation('Are you sure you want to continue? [y/N]', 'QUESTION', false))
      {
          $this->logSection('aborted', 'Task aborted.', null, 'INFO');
          exit(1);
      }
    }

    $this->logSection('task', 'Adding project to cruisecontrol...', null, 'INFO');
    $this->addToCruisecontrolConfig();
    $this->createDirectoryStructure('cc');
    $this->getFilesystem()->replaceTokens(
      sfFinder::type('file')->name('build.xml')->in($this->projectPath),
      '%', '%',
      $this->generateTokenReplacements());

    $this->checkoutFromSvn('cc');

    $this->logSection('task', 'Setting project permissions...', null, 'INFO');
    $this->setPermissions();

    $this->logSection('task', 'Restarting cruisecontrol server...', null, 'INFO');
    $this->restartCruisecontrol();

    $this->logSection('task', 'Adding project to build server...', null, 'INFO');
    $this->addVirtualHost();
    $this->createDirectoryStructure('apache');
    
    $this->checkoutFromSvn('apache');

    $this->logSection('task', 'Restarting apache web server...', null, 'INFO');
    $this->restartApache();

    $this->logSection('task', 'Task completed.', null, 'INFO');
  }

  /**
   * Adds the project to cruisecontrol config
   *
   * @throws sfCommandException if problem opening/writing config.xml or if project already exists
   */
  private function addToCruisecontrolConfig()
  {
    if (!file_exists($this->cruisecontrolPath . "/config.xml") || !is_writable($this->cruisecontrolPath . "/config.xml"))
    {
      throw new sfCommandException(sprintf('Could open/write to cruisecontrol config file "%s/config.xml".',
        $this->cruisecontrolPath));
    }

    $doc = new DOMDocument();
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $doc->load($this->cruisecontrolPath . '/config.xml');

    // Check that the current config file is valid
    $cruisecontrol = $doc->getElementsByTagName('cruisecontrol');
    if ($cruisecontrol->length != 1)
    {
      throw new sfCommandException(sprintf('Cruisecontrol config file appears to be malformed "%s/config.xml".',
        $this->cruisecontrolPath));
    }
    $cruisecontrol = $cruisecontrol->item(0);

    // Check that the project doesn't already exist in config.xml
    $projects = $doc->getElementsByTagName('project');
    foreach ($projects as $project)
    {
      if ($project->getAttribute('name') == $this->projectName)
      {
        throw new sfCommandException(sprintf('A project named "%s" already exists in cruisecontrol.',
          $this->projectName));
      }
    }
    unset($project);

    $proj = $doc->createElement('project');
    $proj = $cruisecontrol->appendChild($proj);
    $proj->setAttribute('name', $this->projectName);
    $proj->setAttribute('buildafterfailed', 'false');
      $plugin = $doc->createElement('plugin');
      $plugin = $proj->appendChild($plugin);
      $plugin->setAttribute('name', 'svnbootstrapper');
      $plugin->setAttribute('classname', 'net.sourceforge.cruisecontrol.bootstrappers.SVNBootstrapper');

      $plugin = $doc->createElement('plugin');
      $plugin = $proj->appendChild($plugin);
      $plugin->setAttribute('name', 'svn');
      $plugin->setAttribute('classname', 'net.sourceforge.cruisecontrol.sourcecontrols.SVN');

      $listeners = $doc->createElement('listeners');
      $listeners = $proj->appendChild($listeners);
        $currentbuildstatuslistener = $doc->createElement('currentbuildstatuslistener');
        $currentbuildstatuslistener = $listeners->appendChild($currentbuildstatuslistener);
        $currentbuildstatuslistener->setAttribute('file', 'logs/${project.name}/status.txt');

      $modificationset = $doc->createElement('modificationset');
      $modificationset = $proj->appendChild($modificationset);
      $modificationset->setAttribute('quietperiod', '${svn.quiet.period}');
        $svn = $doc->createElement('svn');
        $svn = $modificationset->appendChild($svn);
        $svn->setAttribute('localWorkingCopy', 'projects/${project.name}/source/');

      $bootstrappers = $doc->createElement('bootstrappers');
      $bootstrappers = $proj->appendChild($bootstrappers);
        $svnbootstrapper = $doc->createElement('svnbootstrapper');
        $svnbootstrapper = $bootstrappers->appendChild($svnbootstrapper);
        $svnbootstrapper->setAttribute('localWorkingCopy', 'projects/${project.name}/source/');

      $schedule = $doc->createElement('schedule');
      $schedule = $proj->appendChild($schedule);
      $schedule->setAttribute('interval', '${build.quiet.period}');
        $ant = $doc->createElement('ant');
        $ant = $schedule->appendChild($ant);
        $ant->setAttribute('anthome', '${anthome}');
        $ant->setAttribute('buildfile', 'projects/${project.name}/build.xml');

      $_log = $doc->createElement('log');
      $_log = $proj->appendChild($_log);
      $_log->setAttribute('dir', 'logs/${project.name}');
        $merge = $doc->createElement('merge');
        $merge = $_log->appendChild($merge);
        $merge->setAttribute('dir', 'projects/${project.name}/build/logs/');

      $publishers = $doc->createElement('publishers');
      $publishers = $proj->appendChild($publishers);
        $artifactspublisher = $doc->createElement('artifactspublisher');
        $artifactspublisher = $publishers->appendChild($artifactspublisher);
        $artifactspublisher->setAttribute('dir', 'projects/${project.name}/build/api');
        $artifactspublisher->setAttribute('dest', 'artifacts/${project.name}');
        $artifactspublisher->setAttribute('subdirectory', 'api');

        $execute = $doc->createElement('execute');
        $execute = $publishers->appendChild($execute);
        $execute->setAttribute('command', 'phpuc graph '.
          'logs/${project.name} artifacts/${project.name}');

        $onsuccess = $doc->createElement('onsuccess');
        $onsuccess = $publishers->appendChild($onsuccess);

          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo svn up /var/www/build/${project.name}/');
    
          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo chmod +x /var/www/build/${project.name}/symfony');

          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo /var/www/build/${project.name}/symfony project:permissions');

          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo /var/www/build/${project.name}/symfony cc');

          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo /var/www/build/${project.name}/symfony doctrine:build'.
            ' --all --and-load --no-confirmation');

          $execute = $doc->createElement('execute');
          $execute = $onsuccess->appendChild($execute);
          $execute->setAttribute('command', 'sudo /var/www/build/${project.name}/symfony plugin:publish-assets');

    // Save our config.xml file
    if (!$doc->save("/opt/cruisecontrol/config.xml"))
    {
      throw new sfCommandException(sprintf('Could not save cruisecontrol config file "%s/config.xml".',
        $this->cruisecontrolPath));
    }
  }

  /**
   * Adds a new virtual host to apache.
   *
   * The directory is owned by root and we want to keep it that way.
   * This means we need a fairly complicated process to write a file.
   *
   * @throws sfCommandException on error
   */
  private function addVirtualHost()
  {
    $file = $this->apacheConfig . DIRECTORY_SEPARATOR . $this->projectName;
    // Create the file as root
    exec("sudo touch {$file}", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error creating VirtualHost config "%s". Response was "%s".',
        $file, array_pop($response)));
    }
    // Allow the web server to write to the file
    exec("sudo chown cruisecontrol:www-data {$file}", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error setting ownership of "%s" to www-data. Response was "%s".',
        $file, array_pop($response)));
    }
    // Replace our tokens
    $content = preg_replace(array('/%project%/', '/%docroot%/'), array($this->projectName, $this->wwwroot), $this->virtualHost);
    // Write the config file
    if (!file_put_contents($file, $content))
    {
      throw new sfCommandException(sprintf('Error writing VirtualHost config "%s".',
        $file));
    }
    // Change the ownership back to root
    exec("sudo chown root:root {$file}", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error setting ownership of "%s" back to root. Response was "%s".',
        $file, array_pop($response)));
    }
    unset($file);
  }

  /**
   * Checks out the SVN repository showing an optional progress bar
   *
   * @param  string $to the name of the location we are checking out to
   * @throws sfCommandException if an SVN error occurs
   */
  private function checkoutFromSvn($to = '')
  {
    // Set the full location path
    switch ($to)
    {
      case 'cc':
        $location = $this->projectPath . '/source';
        break;

      case 'apache':
        $location = $this->wwwroot . DIRECTORY_SEPARATOR . $this->projectName;
        break;

      default:
        throw new sfCommandException('Trying to check out from SVN to an invalid location. Task PHP code needs updating.');
    }

    if ($this->progressBar)
    {
      $this->logSection('svn', 'Gathering list of files to checkout', null, 'INFO');
      $numFiles = array();
      exec("svn list {$this->svnPath} -R --username {$this->svnUser} --password {$this->svnPassword}", $numFiles);
      if (empty($numFiles)) {
        throw new sfCommandException(sprintf('Could not list the files in SVN "%s".', $this->svnPath));
      }
      $numFiles = sizeof($numFiles);
    }

    $this->logSection('svn', 'Performing SVN checkout...', null, 'INFO');
    $handle = popen("svn co {$this->svnPath} {$location} --ignore-externals --username {$this->svnUser} --password {$this->svnPassword}", 'r');
    if ($handle) {
      $buffer = array();
      $size = (int) `tput cols`;
      $size = $size > 0 ? $size : 80;
      while(!feof($handle)) {
        $buffer[] = fgets($handle);
        if ($this->progressBar)
        {
          $this->progressBar(sizeof($buffer), $numFiles, $size, 1);
        }
      }
      $ret_val = pclose($handle);
    }
    unset($handle);
    if ($ret_val || sizeof($buffer) < 4) {
      throw new sfCommandException(sprintf('Could not check out SVN "%s" to "%s". %s', $this->svnPath, $this->projectPath, array_pop($buffer)));
    }
  }

  /**
   * Creates the directory structure based on the location, populating with data when required.
   *
   * @param string $location the location to populate
   */
  private function createDirectoryStructure($location = '')
  {
    switch ($location)
    {
      case 'cc':
          if (!$this->getFilesystem()->mirror(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . 'skeleton',
            $this->cruisecontrolPath . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $this->projectName,
            sfFinder::type('any')
          ))
          {
            throw new sfCommandException(sprintf('Could not create project directory on cruisecontrol server "%s".',
              $this->cruisecontrolPath . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $this->projectName));
          }
        break;

      case 'apache':
          if (!$this->getFilesystem()->mkdirs($this->wwwroot . DIRECTORY_SEPARATOR . $this->projectName))
          {
            throw new sfCommandException(sprintf('Could not create project directory on build server "%s".',
              $this->wwwroot . DIRECTORY_SEPARATOR . $this->projectName));
          }
        break;

      default:
        throw new sfCommandException('Trying to create directory structure for an invalid location. Task PHP code needs updating.');
    }
  }

  /**
   * Generates the token replacements
   *
   * @return array the token/value pairs
   */
  private function generateTokenReplacements()
  {
    $tokens = array();

    $tokens['projectName'] = $this->projectName;
    $tokens['docIgnorePaths'] = $this->ignore;
    $folders = explode(',', $this->ignore);
    $tokens['cpdIgnorePaths'] = implode(' --exclude ', $folders);
    foreach ($folders as &$folder)
    {
      $folder = '*/' . $folder . '*';
    }
    $tokens['unitIgnorePaths'] = implode(',', $folders);

    return $tokens;
  }

  /**
   * Restarts apache
   *
   * @throws sfCommandException if an error occurs
   */
  private function restartApache()
  {
    exec("sudo /etc/init.d/apache2 restart", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error restarting apache. Response was "%s".', array_pop($response)));
    }
  }

  /**
   * Restarts cruisecontrol
   *
   * @throws sfCommandException if an error occurs
   */
  private function restartCruisecontrol()
  {
    exec("sudo /etc/init.d/cruisecontrol restart", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error restarting cruisecontrol. Response was "%s".', array_pop($response)));
    }
  }

  /**
   * Sets ownership and permissions on cruisecontrol project
   *
   * @throws sfCommandException if error setting ownership or permissions
   */
  private function setPermissions()
  {
    exec("sudo chown -R cruisecontrol:www-data {$this->projectPath}", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error setting ownership of "%s". Response was "%s".',
        $this->projectPath, array_pop($response)));
    }
    exec("sudo chown -R 0775 {$this->projectPath}", $response = array(), $return_var = 0);
    if ($return_var)
    {
      throw new sfCommandException(sprintf('Error setting permissions to "%s". Response was "%s".',
        $this->projectPath, array_pop($response)));
    }
  }

  /**
   * Check that the hostname is valid
   *
   * @throws sfCommandException if hostname does not match config
   * @param array $arguments
   * @param array $options
   */
  private function validateHostname()
  {
    $hostname = exec('hostname');
    if (!in_array($hostname, $this->hostname))
    {
      throw new sfCommandException(sprintf('dev-tools cannot be used on host "%s". Please use on "%s".', $hostname, $this->hostname[0]));
    }
  }

  /**
   * Validate and assign arguments and options
   *
   * @throws sfCommandException if input is invalid
   * @param array $arguments
   * @param array $options
   */
  private function validateInput($arguments = array(), $options = array())
  {
    // Validate the project name
    $this->projectName = preg_replace('/[^\w\-\.]/', '', $arguments['project']);
    if (empty($this->projectName))
    {
      throw new InvalidArgumentException(sprintf('The project name "%s" is invalid. It can only contain alphanumeric, '.
        'hyphens, underscores and fullstops only.', $arguments['project']));
    }
    $this->projectPath = $this->cruisecontrolPath . DIRECTORY_SEPARATOR . 'projects' . DIRECTORY_SEPARATOR . $this->projectName;
    if (file_exists($this->projectPath) ||
      file_exists($this->wwwroot . DIRECTORY_SEPARATOR . $this->projectName ||
      file_exists($this->apacheConfig . DIRECTORY_SEPARATOR . $this->projectName)))
    {
      throw new InvalidArgumentException(sprintf('A project named "%s" already exists. Please choose another project name.',
        $this->projectName));
    }

    if ($options['no-interact'])
    {
      $this->interact = false;
    }

    if ($options['no-progress'])
    {
      $this->progressBar = false;
    }

    if ($options['ignore'])
    {
      $this->ignore = $options['ignore'];
    }

    // Validate the SVN path
    if ($options['svn-path'])
    {
      $this->svnPath = $options['svn-path'];
    }
    else
    {
      $this->svnPath = $this->ask('Type full SVN repository path or [enter] to abort.');
    }
    if (empty($this->svnPath))
    {
      $this->logSection('aborted', 'You need to type the full SVN path or use the --svn-path option.', null, 'INFO');
      exit(1);
    }

    $this->logSection('task', sprintf('Checking SVN repository path "%s"', $this->svnPath), null, 'INFO');
    $svnInfo = shell_exec('svn info ' . escapeshellarg($this->svnPath) .
                          ' --xml --username ' . escapeshellarg($this->svnUser) .
                          ' --password ' . escapeshellarg($this->svnPassword) . ' 2>&1');

    // Load the XML result of SVN (ignoring errors temporarily)
    libxml_use_internal_errors(true);
    $svnInfo = simplexml_load_string($svnInfo);
    libxml_use_internal_errors(false);
    if ($svnInfo) {
      // Check that we get a valid result.
      if (sizeof($svnInfo->children()) < 1) {
        throw new sfCommandException(sprintf('Invalid SVN repository "%s"', $this->svnPath));
      }
    } else {
      throw new sfCommandException(sprintf('Invalid response from SVN. Perhaps the path "%s" is incorrect?',
        $this->svnPath));
    }
  }
}
