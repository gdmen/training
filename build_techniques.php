<?php
// TODO: add tags to post body.

$dir = 'technique_dictionary/';
$content_type = 'technique_post';
$taxonomy_machine_name = 'technique_tags';
$uid = '1';

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

propogateReferences($techniques);

// Update 'from' fields.
function propogateReferences($techniques) {
  $changes = true;
  while($changes) {
    $changes = false;
    foreach($techniques as $name => $t_source) {
      foreach($t_source->getTo() as $other) {
        // skip self references and unchanged references
        if($t_source->getName() === $other || !isset($techniques[$other])) {
          print "Missing: " . $other . "\n";
          continue;
        }
        $changes |= $techniques[$other]->addFrom($t_source->getName());
      }
      foreach($t_source->getFrom() as $other) {
        // skip self references and unchanged references
        if($t_source->getName() === $other || !isset($techniques[$other])) {
          print "Missing: " . $other . "\n";
          continue;
        }
        $changes |= $techniques[$other]->addTo($t_source->getName());
      }
    }
  }
}

define('DRUPAL_ROOT', getcwd());
$_SERVER['REMOTE_ADDR'] = "localhost"; // Necessary if running from command line
require_once DRUPAL_ROOT . '/includes/bootstrap.inc';
drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);

function createTag($tag, $parent=0) {
  $term = array(
    'vid' => 2,
    'name' => $tag,
    'parent' => $parent,
  );
  $term = (object) $term;
  taxonomy_term_save($term);
  return $term->tid;
}

function updateField($log_name, &$old, $new, &$log) {
  if ($old === $new) {
    return false;
  }
  $log .= " : " . $log_name;
  $old = $new;
  return true;
}
foreach($techniques as $t) {
  $node = NULL;
  $node_exists = db_query("SELECT nid FROM {node} WHERE UPPER(title) = UPPER(:title) AND type = :type",
    array(':title' => $t->getName(), ':type' => $content_type)
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
  
  $mt = new MarkdownTechnique($t);
  
  $changed = False;
  $log = '';
  $changed |= updateField('Title', $node->title, $mt->getTitle(), $log);
  $changed |= updateField('Tags', $node->field_technique_tags[$node->language], $mt->getTags(), $log);
  $changed |= updateField('Type', $node->type, $content_type, $log);
  $changed |= updateField('Language', $node->language, LANGUAGE_NONE, $log);
  $changed |= updateField('uid', $node->uid, $uid, $log);
  $changed |= updateField('Body', $node->body[$node->language][0]['value'], $mt->getBody(), $log);
  $changed |= updateField('Summary', $node->body[$node->language][0]['summary'], $mt->getSummary(), $log);
  $changed |= updateField('Format', $node->body[$node->language][0]['format'], 'markdown', $log);

  // Only write node if it was changed.
  if($changed) {
    print "Updated: " . $mt->getTitle() . "\n";
    // At least one field was changed.
    $node->revision = 1;
    $node->log = "Revised" . $log;
    if($node = node_submit($node)) {
      node_save($node);
    }
  }
}

class Technique {
  // Raw text.
  private $raw = '';
  // Name.
  private $name = '';
  // Taxonomy Category
  private $category = '';
  // Taxonomy Subcategory
  private $subcategory = '';
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
  public function getCategory() {
    return $this->category;
  }
  public function getSubcategory() {
    return $this->subcategory;
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
    $this->parseCategories();
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
  public function addTo($name) {
    if(in_array($name, $this->to)) {
      return false;
    }
    $this->to[] = $name;
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
  private function parseCategories() {
    preg_match_all("/Category:(?P<select>[^\n]*)/i", $this->raw, $m);
    if($m['select']) {
      $this->category = trim($m['select'][0]);
    }
    $m = array();
    preg_match_all("/Subcategory:(?P<select>[^\n]*)/i", $this->raw, $m);
    if($m['select']) {
      $this->subcategory = trim($m['select'][0]);
    }
  }
}

class MarkdownTechnique {
  private $title = '';
  private $tags = [];
  private $header = '';
  private $notes = '';
  private $directions = '';
  private $escapes = '';
  // Taxonomy (locally fetched)
  private $taxonomy = [];
  // input: technique object
  public function __construct($t) {
    $t->completeParse();
    $this->taxonomy = $this->fetchTaxonomy();
    $this->title = $this->generateTitle($t);
    $this->tags = $this->generateTags($t);
    $this->header = $this->generateHeader($t);
    $this->notes = $this->generateNotes($t);
    $this->directions = $this->generateDirections($t);
    $this->escapes = $this->generateEscapes($t);
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
  private function fetchTaxonomy() {
    $ret = array();
    $result = db_query("SELECT T.tid, T.name FROM {taxonomy_term_data} as T, {taxonomy_vocabulary} as V WHERE T.vid = V.vid AND V.machine_name = :machine_name", array(':machine_name' => 'technique_tags'));
    if($result) {
      while($row = $result->fetchAssoc()) {
        $ret[$row['name']] = $row['tid'];
      }
    }
    return $ret;
  }
  private function generateTitle($t) {
    return ucwords(strtolower($t->getName()));
  }
  private function generateTags($t) {
    $generated = array();
    if($t->getCategory() != '') {
      if(!isset($this->taxonomy[$t->getCategory()])) {
        print("Adding top level category: " . $t->getCategory() . "\n");
        $this->taxonomy[$t->getCategory()] = createTag($t->getCategory());
      }
      $generated[] = ['tid' => $this->taxonomy[$t->getCategory()]];
      if($t->getSubcategory() != '') {
        if(!isset($this->taxonomy[$t->getSubcategory()])) {
          print("Adding subcategory: " . $t->getSubcategory() . "\n");
          $this->taxonomy[$t->getSubcategory()] = createTag($t->getSubcategory(), $this->taxonomy[$t->getCategory()]);
        }
        $generated[] = ['tid' => $this->taxonomy[$t->getSubcategory()]];
      }
    }
    return $generated;
  }
  private function generateHeader($t) {
    //$h = ["Type: " . join(', ', array_map(function($i) {
    //  return "[[path:".$i."|".$i."]]"; }, $t->getTags()))];
    $h = [];
    $h[] = "Transition From: " . join(', ', array_map(function($i) {
      return "[[".$i."|".$i."]]"; }, $t->getFrom()));
    $h[] = "";
    $h[] = "Transition To: " . join(', ', array_map(function($i) {
      return "[[".$i."|".$i."]]"; }, $t->getTo()));
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
  private function generateNotes($t) {
    $h = ["#### Notes:"];
    $h[] = "";
    foreach($t->getNotes() as $item) {
      $text = $this->replaceLinks($item->text);
      $h[] = ($item->inset ? '' : '- ') . str_repeat('  ', $item->inset) . $text;
    }
    return join("\n\n", $h);
  }
  private function generateDirections($t) {
    $h = ["#### Directions:"];
    $h[] = "";
    foreach($t->getDirections() as $item) {
      $text = $this->replaceLinks($item->text);
      $h[] = ($item->inset ? '' : '1. ') . str_repeat('  ', $item->inset) . $text;
    }
    return join("\n\n", $h);
  }
  private function generateEscapes($t) {
    return '';
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