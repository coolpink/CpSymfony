<?php
/**
 * Created by PhpStorm.
 * User: blair
 * Date: 02-Aug-2010
 * Time: 15:41:36
 */

/**
 * sfValidatorSvn represents a validated SVN repository.
 *
 * @package    symfony
 * @subpackage validator
 * @author     Blair McMillan <blair@coolpink.net>
 */
class sfValidatorSvn extends sfValidatorBase
{
  public function doClean($value)
  {
    $svnInfo = shell_exec('svn info ' . escapeshellarg($value) .
                          ' --xml --username ' . escapeshellarg($this->getOption('user')) .
                          ' --password ' . escapeshellarg($this->getOption('password')) . ' 2>&1');

    // Load the XML result of SVN (ignoring errors temporarily)
    libxml_use_internal_errors(true);
    $svnInfo = simplexml_load_string($svnInfo);
    libxml_use_internal_errors(false);
    if ($svnInfo)
    {
      // Check that we get a valid result.
      if (sizeof($svnInfo->children()) < 1)
      {
        if ($this->getOption('exists'))
        {
          throw new sfValidatorError($this, 'path_not_full', array('value' => $value));
        }
      }
      else
      {
        if (!$this->getOption('exists'))
        {
          throw new sfValidatorError($this, 'path_not_empty', array('value' => $value));
        }
      }
    }
    else
    {
      if ($this->getOption('exists'))
      {
        throw new sfValidatorError($this, 'path_not_full', array('value' => $value));
      }
    }

    return $value;
  }

  public function configure($options = array(), $messages = array())
  {
    $this->addMessage('path_not_empty', 'Invalid response from SVN. Looks like "%value%" already exists?');
    $this->addMessage('path_not_full', 'Invalid response from SVN. Looks like "%value%" doesn\'t exist?');

    $this->addOption('user', 'devvo');
    $this->addOption('password', 'raNe3eHa');
    $this->addOption('exists', true);
  }
}
