<?php

/**
 * @file admin.inc
 * Provides administrative callbacks and tasks.
 */

/**
 * Icon Bundle Overview Form
 */
function icon_bundle_overview_form($form, $form_state) {
  $module_path = drupal_get_path('module', 'icon');
  $form['#attached'] = array(
    'css' => array(
      $module_path . '/css/icon.admin.css',
      $module_path . '/css/iconapi-embedded.css',
      array(
        'type' => 'file',
        'data' => $module_path . '/css/iconapi-ie7.css',
        'browser' => array(
          '!IE' => FALSE,
          'IE' => 'IE 7',
        ),
      )
    ),
  );
  $form['bundles'] = array(
    '#tree' => TRUE,
  );
  foreach (icon_bundles() as $bundle) {
    $form['bundles'][$bundle['name']]['#bundle'] = $bundle;
    $form['bundles'][$bundle['name']]['status'] = array(
      '#type' => 'checkbox',
      '#default_value' => $bundle['status'],
    );
  }
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save configuration'),
  );
  return $form;
}

function icon_bundle_overview_form_submit($form, $form_state) {
  $bundles = icon_bundles();
  $saved_bundles = $form_state['values']['bundles'];
  $clear_caches = FALSE;
  foreach ($bundles as $name => $bundle) {
    if (!isset($saved_bundles[$name]['status'])) {
      continue;
    }
    $status = $saved_bundles[$name]['status'];
    if ($status !== $bundle['status']) {
      if ($status) {
        icon_bundle_enable($bundle);
      }
      else {
        icon_bundle_disable($bundle);
      }
    }
  }
}

function theme_icon_bundle_overview_form($variables) {
  $output = '';
  $form = $variables['form'];
  $header = array(
    'enable' => t('Enabled'),
    'bundle' => t('Bundle'),
    'count' => t('Count'),
    'provider' => t('Provider'),
    'operations' => t('Operations'),
  );
  $destination = drupal_get_destination();
  $rows = array();
  foreach (element_children($form['bundles']) as $key) {
    $row = array();
    $bundle = $form['bundles'][$key]['#bundle'];
    $row['status'] = array(
      'data' => drupal_render($form['bundles'][$key]['status']),
      'class' => 'compact center',
    );
    $row['bundle'] = $bundle['title'] . (!empty($bundle['version']) ? ' - ' . $bundle['version'] : '');
    $row['count'] = array(
      'data' => count($bundle['icons']),
      'class' => 'compact center',
    );
    $row['provider']['class'] = 'compact nowrap';
    if ($provider = icon_provider_load($bundle['provider'])) {
      $row['provider']['data'] = !empty($provider['url']) ? l($provider['title'], $provider['url']) : $provider['title'];
    }
    else {
      $row['provider']['data'] = $bundle['provider'];
    }
    // Build operations for each bundle.
    $row['operations'] = array('class' => 'compact nowrap');
    $operations = array();
    if ($bundle['status']) {
      $operations['view'] = array(
        'title' => '<i class="iconapi-view"></i>' . t('View'),
        'href' => ICON_ADMIN_PATH . '/bundle/' . $bundle['name'],
        'html' => TRUE,
      );
      $operations['configure'] = array(
        'title' => '<i class="iconapi-configure"></i>' . t('Configure'),
        'href' => ICON_ADMIN_PATH . '/bundle/' . $bundle['name'] . '/configure',
        'html' => TRUE,
      );
    }
    if (!empty($bundle['import'])) {
      $operations['delete'] = array(
        'title' => '<i class="iconapi-delete"></i>' . t('Delete'),
        'href' => ICON_ADMIN_PATH . '/bundle/' . $bundle['name'] . '/delete',
        'html' => TRUE,
      );
    }
    elseif (!empty($bundle['overridden'])) {
      $operations['reset'] = array(
        'title' => '<i class="iconapi-throbber"></i>' . t('Reset'),
        'href' => ICON_ADMIN_PATH . '/bundle/' . $bundle['name'] . '/reset',
        'html' => TRUE,
      );
    }
    if (!empty($operations)) {
      $row['operations']['data'] = array(
        '#theme' => 'links',
        '#links' => $operations,
        '#attributes' => array('class' => array('links', 'inline')),
      );
    }
    $rows[] = $row;
  }
  $output .= theme('table', array(
    'header' => $header,
    'rows' => $rows,
    'empty' => t('No bundles currently installed.'),
  ));
  $output .= drupal_render_children($form);
  return $output;
}

function icon_bundle_configure_form($form, $form_state, $bundle) {
  drupal_set_title(t('Configure %bundle icons', array('%bundle' => $bundle['title'])), PASS_THROUGH);
  $form_state['bundle'] = $bundle;
  $form['status'] = array(
    '#type' => 'checkbox',
    '#title' => t('Enabled'),
    '#default_value' => $bundle['status'],
  );
  $form['settings'] = array(
    '#type' => 'fieldset',
    '#title' => t('Bundle settings'),
    '#tree' => TRUE,
  );
  foreach (icon_extension_implements('icon_bundle_configure') as $extension => $type) {
    icon_extension_invoke($type, $extension, 'icon_bundle_configure', $form['settings'], $form_state, $form);
  }
  $settings = element_children($form['settings']);
  if (empty($settings)) {
    $form['settings']['#access'] = FALSE;
  }
  $form['actions'] = array('#type' => 'actions');
  $form['actions']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save bundle'),
  );
  if (!empty($bundle['import'])) {
    $form['actions']['delete'] = array(
      '#type' => 'submit',
      '#value' => t('Delete bundle'),
    );
  }
  elseif (!empty($bundle['overridden'])) {
    $form['actions']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset bundle'),
    );
  }
  return $form;
}

function icon_bundle_configure_form_submit($form, &$form_state) {
  $destination = drupal_get_destination();
  $bundle = $form_state['bundle'];
  $button = $form_state['triggering_element']['#value'];
  if ($button === t('Reset bundle')) {
    $form_state['redirect'] = array(ICON_ADMIN_PATH . '/bundle/' . $bundle['name'] . '/reset', array('query' => $destination));
    return;
  }
  elseif ($button === t('Delete bundle')) {
    $form_state['redirect'] = array(ICON_ADMIN_PATH . '/bundle/' . $bundle['name'] . '/delete', array('query' => $destination));
    return;
  }
  $form_state['redirect'] = array(ICON_ADMIN_PATH);
  form_state_values_clean($form_state);
  $bundle = icon_array_merge_recursive($bundle, $form_state['values']);
  if (icon_bundle_save($bundle)) {
    drupal_set_message(t('Configuration for icon bundle %bundle has been saved.', array('%bundle' => $bundle['title'])));
  }
}

function icon_bundle_delete_form($form, $form_state, $bundle) {
  return confirm_form($form, t('Delete %bundle icon bundle?', array('%bundle' => $bundle['title'])), ICON_ADMIN_PATH, NULL, t('Delete'));
}

function icon_bundle_delete_form_submit($form, &$form_state) {
  // Redirect to the overview form after submit.
  if (isset($_GET['destination'])) {
    unset($_GET['destination']);
  }
  $form_state['redirect'] = array(ICON_ADMIN_PATH);
  if (!empty($form_state['values']['confirm'])) {
    icon_bundle_delete($form_state['build_info']['args'][0]);
  }
}

function icon_bundle_reset_form($form, $form_state, $bundle) {
  if (empty($bundle['overridden'])) {
    drupal_set_message(t('The icon bundle %bundle is not currently overridden in the database.', array('%bundle' => $bundle['title'])), 'error');
    drupal_goto(ICON_ADMIN_PATH);
  }
  return confirm_form($form, t('Reset %bundle icon bundle?', array('%bundle' => $bundle['title'])), ICON_ADMIN_PATH, NULL, t('Reset'));
}

function icon_bundle_reset_form_submit($form, &$form_state) {
  // Redirect to the overview form after submit.
  $destination = ICON_ADMIN_PATH;
  if (isset($_GET['destination'])) {
    $destination = array('destination' => $_GET['destination']);
  }
  $form_state['redirect'] = array($destination);
  if (!empty($form_state['values']['confirm'])) {
    icon_bundle_delete($form_state['build_info']['args'][0]);
  }
}

function icon_bundle_list($bundle) {
  $build = array();
  foreach ($bundle['icons'] as $icon => $value) {
    $build[] = array(
      '#theme' => 'icon',
      '#bundle' => $bundle['name'],
      '#icon' => $icon,
    );
  }
  return $build;
}