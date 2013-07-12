<?php
$dir = 'technique_dictionary/';
$node_type = 'technique_post';
$uid = 1;

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

$techniques = getTechniquesFromDir($dir);

// Can have:
// - position to:position
// - submission to:submission
// - submission from:position

// Gotten all modified / consequently to be modified techniques.

mergeTechniques($techniques);

// Update 'from' fields.
function mergeTechniques($techniques) {
  $changes = true;
  while($changes) {
    $changes = false;
    foreach($techniques as $name => $t_from) {
      foreach($t_from->getTo() as $to) {
        // skip self references
        if($t_from->getName() === $to ||
           !isset($techniques[$to])) {
          continue;
        }
        $changes = $changes || $techniques[$to]->addFrom($t_from->getName());
      }
    }
  }
}

//print_r($techniques);


define('DRUPAL_ROOT', getcwd());
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

$taxonomy = array();
$result = db_query("SELECT tid, name FROM {taxonomy_term_data}");
if($result) {
  while($row = $result->fetchAssoc()) {
    $taxonomy[$row['name']] = $row['tid'];
  }
}

foreach($techniques as $t) {
  $node = NULL;
  $node_exists = db_query("SELECT nid FROM {node} WHERE UPPER(title) = UPPER(:title) AND type = :type",
    array(':title' => $t->getName(), ':type' => $node_type)
  );
  if($node_exists) {
    $row = $node_exists->fetchAssoc();
    $node = node_load($row['nid']);
  }else {
    $node = new stdClass(); // Create a new node object
    node_object_prepare($node);
  }
  if($node === NULL) {
    continue;
  }
  
  //print_r($node);
  $mt = new MarkdownTechnique($t, $taxonomy);
  $node->title = $mt->getTitle();
  $node->field_technique_tags[$node->language] = $mt->getTags();
  //$node->taxonomy = $t->getTags();
  $node->type = $node_type;
  $node->language = LANGUAGE_NONE;
  $node->uid = $uid;
  $node->body[$node->language][0]['value'] = $mt->getBody();
  $node->body[$node->language][0]['summary'] = $mt->getSummary();
  $node->body[$node->language][0]['format'] = 'markdown';
  // Make this change a new revision
  $node->revision = 1;
  $node->log = 'This node was programmatically updated at ' . date('c');
  if($node = node_submit($node)) {
    node_save($node);
  }
}

class Technique {
  // Raw text.
  private $raw = '';
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
  // List of notes as ListItem objects.
  private $notes = [];
  // List of directions as ListItem objects.
  private $directions = [];
  // List of escapes as ListItem objects
  private $escapes = [];
  /**
   * Does immediately necessary parsing.
   **/
  public function __construct($name, $raw) {
    $this->name = $name;
    $this->raw = $raw;
    
    $this->parseTo();
    $this->parseTags();
    $this->parseType();
    $this->parseFrom();
  }
  public function getName() {
    return $this->name;
  }
  public function getTo() {
    return $this->to;
  }
  public function getFrom() {
    return $this->from;
  }
  public function getTags() {
    return $this->tags;
  }
  public function getType() {
    return $this->type;
  }
  public function getNotes() {
    return $this->notes;
  }
  public function getDirections() {
    return $this->directions;
  }
  public function getEscapes() {
    return $this->escapes;
  }
  
  public function completeParse() {
    $this->parseNotes();
    $this->parseDirections();
    $this->parseEscapes();
  }
  public function parseNotes() {
    preg_match("/Notes:\s*(?P<select>[^\s].*[^\s])\s*Directions:/s", $this->raw, $m);
    $lines = preg_split("/\r?\n/", $m['select'], NULL, PREG_SPLIT_NO_EMPTY);
    foreach($lines as $l) {
      if(strpos($l, "- ") === 0) {
        $this->notes[] = new ListItem(substr($l,2), 0);
      } else {
        $this->notes[] = new ListItem($l, 1);
      }
    }
  }
  public function parseDirections() {
    preg_match("/Directions:\s*(?P<select>[^\s].*[^\s])\s*Escapes:/s", $this->raw, $m);
    $lines = preg_split("/\r?\n/", $m['select'], NULL, PREG_SPLIT_NO_EMPTY);
    foreach($lines as $l) {
      if(strpos($l, "- ") === 0) {
        $this->directions[] = new ListItem(substr($l,2), 0);
      } else {
        $this->directions[] = new ListItem($l, 1);
      }
    }
  }
  public function parseEscapes() {
    preg_match("/Escapes:\s*(?P<select>[^\s].*[^\s])\s*/s", $this->raw, $m);
    $lines = preg_split("/\r?\n/", $m['select'], NULL, PREG_SPLIT_NO_EMPTY);
    foreach($lines as $l) {
      if(strpos($l, "*") === 0) {
        $this->escapes[] = new ListItem(substr($l,1), 0);
      } else if(strpos($l, "-") === 0) {
        $this->escapes[] = new ListItem(substr($l,2), 1);
      } else {
        $this->escapes[] = new ListItem($l, 2);
      }
    }
  }
  public function addFrom($name) {
    if(in_array($name, $this->from)) {
      return false;
    }
    $this->from[] = $name;
    return true;
  }
  private function parseTo() { 
    preg_match_all("/to:\[(?P<select>[a-z\s\-]+)\]/i", $this->raw, $m);
    $this->to = $m['select'];
  }
  private function parseFrom() {
    preg_match_all("/From:(?P<select>[^\n]*)/i", $this->raw, $m);
    if($m['select']) {
      $this->from = preg_split("/\s*,\s*/", $m['select'][0]);
      $this->from = array_map("trim", $this->from);
    }
  }
  private function parseTags() {
    preg_match_all("/^Type:(?P<select>[^\n]*)/i", $this->raw, $m);
    $this->tags = preg_split("/\s*,\s*/", $m['select'][0]);
    $this->tags = array_map("trim", $this->tags);
    $replace = function($str) {
      return str_replace(' ', '-', $str);
    };
    $this->tags = array_map($replace, $this->tags);
  }
  private function parseType() {
    if(!$this->tags) {
      $this->parseTags();
    }
    foreach($this->tags as $tag) {
      if($type = TechniqueType::reverseLookup($tag)) {
        $this->type = $type;
        return true;
      }
    }
    return false;
  }
}

class MarkdownTechnique {
  private $title = '';
  private $tags = [];
  private $header = '';
  private $notes = '';
  private $directions = '';
  private $escapes = '';
  // input: technique object
  public function __construct($technique, $taxonomy) {
    $technique->completeParse();
    $this->title = $this->generateTitle($technique);
    $this->tags = $this->generateTags($technique, $taxonomy);
    $this->header = $this->generateHeader($technique);
    $this->notes = $this->generateNotes($technique);
    $this->directions = $this->generateDirections($technique);
    $this->escapes = $this->generateEscapes($technique);
  }
  public function getTitle() {
    return $this->title;
  }
  public function getTags() {
    return $this->tags;
  }
  public function getSummary() {
    return $this->header;
  }
  public function getBody() {
    return $this->header . "\n\n" . $this->notes . "\n\n" . $this->directions . "\n\n" . $this->escapes;
  }
  private function generateTitle($technique) {
    return ucwords(strtolower($technique->getName()));
  }
  private function generateTags($technique, $taxonomy) {
    //TODO: Right now it ignores tags that don't already exist
    $new_tags = array();
    foreach($technique->getTags() as $tag) {
      if(isset($taxonomy[$tag])) {
        $new_tags[] = ['tid' => $taxonomy[$tag]];
      }
    }
    return $new_tags;
  }
  private function generateHeader($technique) {
    $h = ["Type: " . join(', ', array_map(function($i) {
      return "[[path:".$i."|".$i."]]"; }, $technique->getTags()))];
    $h[] = "";
    $h[] = "Transition From: " . join(', ', array_map(function($i) {
      return "[[".$i."|".$i."]]"; }, $technique->getFrom()));
    $h[] = "";
    $h[] = "Transition To: " . join(', ', array_map(function($i) {
      return "[[".$i."|".$i."]]"; }, $technique->getTo()));
    return join("\n", $h);
  }
  private function replaceLinks($str) {
    $patterns = [
      "/ref:\[([^\]]+)\]/",
      "/to:\[([^\]]+)\]/",
      "/. to:\[([^\]]+)\]/"
    ];
    $replacements = [
      "[[$1|$1]]",
      "transition to [[$1|$1]]",
      "Transition to [[$1|$1]]"
    ];
    return preg_replace($patterns, $replacements, $str);
  }
  private function generateNotes($technique) {
    $h = ["#### Notes:"];
    $h[] = "";
    foreach($technique->getNotes() as $item) {
      $text = $this->replaceLinks($item->text);
      $h[] = ($item->inset ? '' : '- ') . str_repeat('  ', $item->inset) . $text;
    }
    return join("\n\n", $h);
  }
  private function generateDirections($technique) {
    $h = ["#### Directions:"];
    $h[] = "";
    foreach($technique->getDirections() as $item) {
      $text = $this->replaceLinks($item->text);
      $h[] = ($item->inset ? '' : '1. ') . str_repeat('  ', $item->inset) . $text;
    }
    return join("\n\n", $h);
  }
  private function generateEscapes($technique) {
    return '';
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
class ListItem {
  // Text.
  public $text = '';
  // Degree of indentation.
  public $inset = 0;
  public function __construct($text, $inset) {
    $this->text = $text;
    $this->inset = $inset;
  }
}
?>