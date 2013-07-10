<?php
$techniques_dir = 'technique_dictionary';
$positions_dir = $techniques_dir . '/positions/';
$submissions_dir = $techniques_dir . '/submissions/';

function getNameFromFile($filename) {
  return str_replace('_', ' ', pathinfo($filename)['filename']);
}

$raw_positions = array();
foreach (glob($positions_dir . '*.txt') as $filename) {
  $raw_positions[getNameFromFile($filename)] = file_get_contents($filename);
}
print_r($raw_positions);

define('DRUPAL_ROOT', getcwd());
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

class Technique {
  // Name.
  private $name = '';
  // TechniqueType
  private $type;
  // List of taxonomy terms.
  private $tags = [];
  // List of technique names from which to attempt this technique.
  private $from = [];
  // List of techniques to transition into from this technique.
  private $to = [];
  // List of terminology notes.
  private $notes = [];
  // List of Direction objects.
  private $directions = [];
  // List of Escape objects
  private $escapes = [];
  public function __construct($name, $type, $tags, $from, $to, $notes, $directions, $escapes) {
    $this->name = $name;
    $this->type = $type;
    $this->tags = $tags;
    $this->from = $from;
    $this->to = $to;
    $this->notes = $notes;
    $this->directions = $directions;
    $this->escapes = $escapes;
  }
  public function getMarkdown() {
   return '';
  }
}
class TechniqueType {
  const POSITION = 0;
  const SUBMISSION = 1;
}
class Direction {
  // Text.
  public $text = '';
  // Whether this is a substep.
  public $isSubstep = False;
}
?>