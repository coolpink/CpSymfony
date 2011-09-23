<?php
/**
 * Coolpink symfony installer script.
 *
 * Run the following command to generate a new project
 *
 *    php lib/vendor/symfony-1.4.6/data/bin/symfony generate:project my_project_name --installer=lib/vendor/symfony-1.4.6/lib/task/coolpink/generator/coolpink_setup.php
 */

if(!$this instanceof sfGenerateProjectTask)
{
  $this->logBlock(array('', 'This script cannot be run outside of generating a new project!', ''), 'ERROR');
  exit(1);
}

/**
 * Configuration values
 */
$svnUser = 'devvo';
$svnPass = 'raNe3eHa';
$plugins = <<<EXTERNALS
plugins/cpAdminGeneratorPlugin https://office.coolpink.net/svn/symfony/plugins/cpAdminGeneratorPlugin
plugins/cpCmsPlugin https://office.coolpink.net/svn/symfony/plugins/cpCmsPlugin
plugins/cpFormsPlugin https://office.coolpink.net/svn/symfony/plugins/cpFormsPlugin
plugins/cpMediaBrowserPlugin https://office.coolpink.net/svn/symfony/plugins/cpMediaBrowserPlugin
plugins/sfDoctrineGuardPlugin https://office.coolpink.net/svn/symfony/plugins/sfDoctrineGuardPlugin
plugins/sfFormExtraPlugin https://office.coolpink.net/svn/symfony/plugins/sfFormExtraPlugin
plugins/sfImageTransformPlugin https://office.coolpink.net/svn/symfony/plugins/sfImageTransformPlugin
plugins/sfThumbnailPlugin https://office.coolpink.net/svn/symfony/plugins/sfThumbnailPlugin
EXTERNALS;

/**
 * Include the validators directly since I can't get the auto load working correctly with the paths
 */
require_once(realpath(dirname(__FILE__) . DIRECTORY_SEPARATOR . '../validators') . DIRECTORY_SEPARATOR . 'sfValidatorSvn.class.php');

/**
 * Convenience task! Configure the author
 */
$this->logSection('configure', 'Setting project author to: "Coolpink <dev@coolpink.net>"');
$this->runTask('configure:author', '"Coolpink <dev@coolpink.net>"');

/**
 * Generate the app
 */
//$this->runTask('generate:app', 'frontend');
//$this->runTask('generate:app', 'backend');

$this->logSection('svn', 'Validating SVN...');
$svnRepo = $this->askAndValidate('What is the full SVN repository path that you are using?',
  new sfValidatorSvn(array('user' => $svnUser, 'password' => $svnPass, 'exists' => true)));
$this->logSection('svn', 'SVN repository valid.');

$this->logSection('svn', 'Ignoring "cache" folder.');
$this->getFilesystem()->execute('svn propset svn:ignore "cache/*" .');

$this->logSection('svn', 'Ignoring "log" folder.');
$this->getFilesystem()->execute('svn propset svn:ignore "log/*" .');

$this->logSection('svn', 'Ignoring "Plugin" folders.');
$this->getFilesystem()->execute('svn propset svn:ignore "web/*Plugin" .');

$this->logSection('svn', 'Adding Plugin folders.');
$this->getFilesystem()->execute("svn propset svn:externals '{$plugins}' .");


/*
commit and then update


# In /svn/symfony/ there's a zip file called skeleton.zip. Extract this over the top of your project.

# type "php symfony project:permissions"
 *
# type "php symfony doctrine:build --all --and-load"

# type "php symfony plugin:publish-assets"
  *
 * php symfony log:rotate frontend prod --period=7 --history=10
  */