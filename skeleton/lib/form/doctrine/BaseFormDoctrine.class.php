<?php

/**
 * Project form base class.
 *
 * @package    awesomeadmin
 * @subpackage form
 * @author     Your name here
 * @version    SVN: $Id: sfDoctrineFormBaseTemplate.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
abstract class BaseFormDoctrine extends sfFormDoctrine
{
  public function setup()
  {
      unset($this["updated_at"], $this["created_at"], $this["path_id"], $this["version"]);
  }
  public function saveEmbeddedForms($con = null, $forms = null)
  {
    if (null === $con)
    {
      $con = $this->getConnection();
    }

    if (null === $forms)
    {
      $forms = $this->embeddedForms;
    }

    foreach ($forms as $key=>$form)
    {
      if ($form instanceof sfFormObject)
      {
        unset($form[self::$CSRFFieldName]);
        $form->bindAndSave($this->taintedValues[$key], $this->taintedFiles, $con);
        $form->saveEmbeddedForms($con);
      }
      else
      {
        $this->saveEmbeddedForms($con, $form->getEmbeddedForms());
      }
    }

  }
  public function bind(array $taintedValues = null, array $taintedFiles = null)
  {
    if($this->getObject()->getId()>0){
      $taintedValues['id']=$this->getObject()->getId();
      $this->isNew = false;
    }
    parent::bind($taintedValues, $taintedFiles);
  }
}
