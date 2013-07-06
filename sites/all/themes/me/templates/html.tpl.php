<!DOCTYPE html>
<html lang="<?php print $language->language; ?>" dir="<?php print $language->dir; ?>"<?php print $rdf_namespaces;?>>
<head profile="<?php print $grddl_profile; ?>">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php print $head; ?>
  <title><?php print $head_title; ?></title>
  <?php print $styles; ?>
  <!-- HTML5 element support for IE6-8 -->
  <!--[if lt IE 9]>
    <script src="//html5shiv.googlecode.com/svn/trunk/html5.js"></script>
  <![endif]-->
  <?php print $scripts; ?>
</head>
<body class="<?php print $classes; ?>" <?php print $attributes;?>>
  <div id="sticky-non-footer">
    <div id="sticky-content">
      <div id="skip-link">
        <a href="#main-content" class="element-invisible element-focusable"><?php print t('Skip to main content'); ?></a>
      </div>
      <div id="header">
        <div class="container">
          <div class="row">
            <div class="span12 content text-center">
              <div>
                <h3><a class="brand" href="#">Gary Menezes</a></h3>
              </div>
              <div>
                <a class="header-subtext" href="mailto:gmenezes@seas.upenn.edu">gmenezes@seas.upenn.edu</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php print $page_top; ?>
      <?php print $page; ?>
      <?php print $page_bottom; ?>
</body>
</html>
