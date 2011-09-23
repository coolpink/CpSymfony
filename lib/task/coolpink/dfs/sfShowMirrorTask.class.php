<?php

/*
 * This file is part of the symfony package.
 * (c) 2004-2006 Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Displays the steps to update local databases with data from mirror
 *
 * @package    symfony
 * @subpackage task
 * @author     Blair McMillan <blair@coolpink.net>
 * @version    SVN: $Id: sfProjectDeployTask.class.php 23922 2009-11-14 14:58:38Z fabien $
 */
class sfShowMirrorTask extends sfBaseTask
{
  protected $tables         = null,
            $include_tables = null,
            $table_options  = array('all', 'campaigns', 'products');

  protected function configure()
  {
    $this->namespace        = 'dfs';
    $this->name             = 'show-mirror';
    $this->briefDescription = 'Update local database with data from mirror';

    $this->addOptions(array(
      new sfCommandOption('tables', null, sfCommandOption::PARAMETER_OPTIONAL, 'Tables to include (' . join('|', $this->table_options) . ')'),
    ));

    $this->detailedDescription = <<<EOF
Displays the steps to update local databases with data from mirror
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    if ($options['tables'])
    {
      $this->tables = trim($options['tables']);
    }
    if (!in_array($this->tables, $this->table_options))
    {
      $validator = new sfValidatorChoice(
        array('choices' => $this->table_options),
        array('required' => 'A valid table option is required.',
              'invalid'  => '"%value%" is not a valid table option.')
      );
      $this->tables = $this->askAndValidate('Tables to include (' . join('|', $this->table_options) . '):',
                                                $validator, array('attempts' => 3));
    }

    $this->logBlock("Step 1:\n".
      "    SSH onto mirror. Connection info can be found under /Tech/Rackspace/DFS\n", 'INFO');

    switch ($this->tables)
    {
      case 'all':
        $this->include_tables = 'module_campaigns module_categories module_products module_product_listing module_product_prices module_ranges module_range_colours module_range_files module_range_lozenges module_range_removable_covers module_range_scatter_options module_range_wood_options module_related_ranges module_sofas_colour_collections';
        break;
      case 'campaigns':
        $this->include_tables = 'module_campaigns module_categories';
        break;
      case 'products':
        $this->include_tables = 'module_products module_product_listing module_product_prices module_ranges module_range_colours module_range_files module_range_lozenges module_range_removable_covers module_range_scatter_options module_range_wood_options module_related_ranges module_sofas_colour_collections';
        break;
      default:
        $this->include_tables = null;
    }
    if ($this->include_tables)
    {
    $this->logBlock("Step 2:\n".
      "    mysqldump --user=root --databases dfs09 --tables {$this->include_tables} > db_dump_". date("Ymd") . ".sql\n", 'INFO');
    }
    else
    {
      throw new sfCommandException('Could not get a list of table to include. PHP code needs updating.');
    }

    $this->logBlock("Step 3:\n".
      "    sFTP onto mirror using scrollsa. Download db_dump_". date("Ymd") . ".sql\n", 'INFO');

    $this->logBlock("Step 4 (local):\n".
      "    Open http://localhost/phpmyadmin/\n".
      "    Select your DFS database.\n".
      "    Import the db_dump_". date("Ymd") . ".sql file that you saved earlier.\n", 'INFO');

    $this->logBlock("Step 4 (build):\n".
      "    Copy db_dump.sql onto oslo.\n".
      "    SSH onto oslo.\n".
      "    CD /path/to/db_dump_". date("Ymd") . ".sql\n".
      "    mysql -h riga --user=root --password=<password_here> dfs09 < db_dump_". date("Ymd") . ".sql", 'INFO');
  }
}