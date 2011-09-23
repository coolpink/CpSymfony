<?php
abstract class sfProgBaseTask extends sfBaseTask
{
  private
    $counter = 1,
    $indicators = array('-', '\\', '|', '/');

  /**
   * Output a progress bar
   *
   * @param integer $current The current iteration, must always be 0 on first call
   * @param integer $total Total number of iterations this progressbar will handle
   * @param integer $size Width of the progress bar in characters
   * @param integer $start The first number to be sent, usually will start at 0
   **/
  public function progressBar($current=0, $total=100, $size=NULL, $start=0)
  {
    if(is_null($size))
    {
      $size = (int) `tput cols`;
    }

    // Don't do anything if this isn't a commandline task or verbosity is off
    if (is_null($this->commandApplication) || !$this->commandApplication->isVerbose())
    {
      return;
    }

    $perc = ($current/$total)*100;

    // Show activity by cycling through the array of indicators on each iteration
    $this->counter += $this->counter === count($this->indicators) ? -3 : 1;
    $indicator = $this->indicators[$this->counter - 1];

    // if it's not first iteration, remove the previous bar by outputting a
    // backspace characters
    if($current > $start) echo str_repeat("\x08", $size);

    // generate progess bar
    $progress = floor($current / $total * ($size - 11));
    $soFar      = str_repeat('=', $progress);
    $remaining  = str_repeat(' ', $size - 11 - $progress);

    // prefix the bar with a (padded) percent progress and activity indicator
    // and wrap it in square brackets, with a greater-than as the current
    // position indicator
    printf(" %s %3u%% [%s>%s]",$indicator,$perc,$soFar,$remaining);

    // if it's the end, add a new line
    if($current == $total)
    {
      echo "\n";
    }
  }
}