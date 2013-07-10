<?php
$dir = 'technique_dictionary/';
$post_type = 'technique_post';
$uid = 1;

$techniques = getTechniquesFromDir($dir);

// Gotten all modified / consequently to be modified techniques.

define('DRUPAL_ROOT', getcwd());
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

foreach($techniques as $technique) {
  $node = NULL;
  $node_exists = db_query("SELECT nid FROM {node} WHERE title = :title AND type = :type",
    array(':title' => $title, ':type' => $post_type)
  );
  if($node_exists) {
    $row = $node_exists->fetchAssoc();
    $node = node_load($row['nid']);
    print("HERE!");
  }else {
    $node = new stdClass(); // Create a new node object
    node_object_prepare($node);
  }
  if($node === NULL) {
    continue;
  }
  $technique->fullParse();
  $node->title = $technique->getName();
  //LANGUAGE_NONE?
  $node->uid = $uid;
  $node->body[$node->language][0]['value'] = $technique->getMarkdown();
  $node->body[$node->language][0]['format'] = 'markdown';
  if($node = node_submit($node)) {
      node_save($node);
  }
}

function getNameFromFile($filename) {
  return str_replace('_', ' ', pathinfo($filename)['filename']);
}

function getTechniquesFromDir($dir) {
  $return = array();
  foreach (glob($dir . '*.txt') as $filename) {
    $name = getNameFromFile($filename);
    $raw = file_get_contents($filename);
    $return[$name] = new Technique($name, $raw);
  }
  return $return;
}

class Technique {
  // Raw text.
  private $raw = '';
>>>>>>> Begins work on post-generation script.
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
  /**
   * Does immediately necessary parsing.
   **/
  public function __construct($name, $raw) {
    $this->name = $name;
    $this->raw = $raw;
    
    preg_match_all("/to:\[(?P<to>[a-z\s\-]+)\]/i", $this->raw, $m);
    $this->to = $m['to'];
  }
  public function getName() {
    return $this->name;
  }
  public function getTo() {
    return $this->to;
  }
  public function getMarkdown() {
    return $this->raw;
  }
  public function fullParse() {
    $this->parseTags();
    $this->parseType();
  }
  private function parseTags() {
    preg_match_all("/^Type:(?P<tags>[^\n]*)/i", $this->raw, $m);
    $this->tags = preg_split("/\s*,\s*/", $m['tags'][0]);
    $this->tags = array_map("trim", $this->tags);
  }
  private function parseType() {
    foreach($this->tags as $tag) {
      if($type = TechniqueType::reverseLookup($tag)) {
        $this->type = $type;
        return true;
      }
    }
    return false;
  }
}

class TechniqueType {
  const POSITION = 'position';
  const SUBMISSION = 'submission';
  static function reverseLookup($str) {
    switch(strtolower($str)) {
      case TechniqueType::POSITION:
        return TechniqueType::POSITION;
      case TechniqueType::SUBMISSION:
        return TechniqueType::SUBMISSION;
    }
    return false;
  }
}
class Direction {
  // Text.
  public $text = '';
  // Whether this is a substep.
  public $isSubstep = False;
}
?>