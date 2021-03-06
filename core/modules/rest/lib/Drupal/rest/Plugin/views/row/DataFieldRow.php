<?php

/**
 * @file
 * Contains \Drupal\rest\Plugin\views\row\DataFieldRow.
 */

namespace Drupal\rest\Plugin\views\row;

use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views\Annotation\ViewsRow;
use Drupal\Core\Annotation\Translation;

/**
 * Plugin which displays fields as raw data.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "data_field",
 *   title = @Translation("Fields"),
 *   help = @Translation("Use fields as row data."),
 *   display_types = {"data"}
 * )
 */
class DataFieldRow extends RowPluginBase {

  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::$usesFields.
   */
  protected $usesFields = TRUE;

  /**
   * Stores an array of prepared field aliases from options.
   *
   * @var array
   */
  protected $replacementAliases = array();

  /**
   * Stores an array of options to determine if the raw field output is used.
   *
   * @var array
   */
  protected $rawOutputOptions = array();

  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::init().
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    if (!empty($this->options['field_options'])) {
      $options = (array) $this->options['field_options'];
      // Prepare a trimmed version of replacement aliases.
      $aliases = static::extractFromOptionsArray('alias', $options);
      $this->replacementAliases = array_filter(array_map('trim', $aliases));
      // Prepare an array of raw output field options.
      $this->rawOutputOptions = static::extractFromOptionsArray('raw_output', $options);
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::buildOptionsForm().
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['field_options'] = array('default' => array());

    return $options;
  }


  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::buildOptionsForm().
   */
  public function buildOptionsForm(&$form, &$form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['field_options'] = array(
      '#type' => 'table',
      '#header' => array(t('Field'), t('Alias'), t('Raw output')),
      '#empty' => t('You have no fields. Add some to your view.'),
      '#tree' => TRUE,
    );

    $options = $this->options['field_options'];

    if ($fields = $this->view->display_handler->getOption('fields')) {
      foreach ($fields as $id => $field) {
        $form['field_options'][$id]['field'] = array(
          '#markup' => $id,
        );
        $form['field_options'][$id]['alias'] = array(
          '#title' => t('Alias for @id', array('@id' => $id)),
          '#title_display' => 'invisible',
          '#type' => 'textfield',
          '#default_value' => isset($options[$id]['alias']) ? $options[$id]['alias'] : '',
          '#element_validate' => array(array($this, 'validateAliasName')),
        );
        $form['field_options'][$id]['raw_output'] = array(
          '#title' => t('Raw output for @id', array('@id' => $id)),
          '#title_display' => 'invisible',
          '#type' => 'checkbox',
          '#default_value' => isset($options[$id]['raw_output']) ? $options[$id]['raw_output'] : '',
        );
      }
    }
  }

  /**
   * Form element validation handler for \Drupal\rest\Plugin\views\row\DataFieldRow::buildOptionsForm().
   */
  public function validateAliasName($element, &$form_state) {
    if (preg_match('@[^A-Za-z0-9_-]+@', $element['#value'])) {
      form_error($element, t('The machine-readable name must contain only letters, numbers, dashes and underscores.'));
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::validateOptionsForm().
   */
  public function validateOptionsForm(&$form, &$form_state) {
    // Collect an array of aliases to validate.
    $aliases = static::extractFromOptionsArray('alias', $form_state['values']['row_options']['field_options']);

    // If array filter returns empty, no values have been entered. Unique keys
    // should only be validated if we have some.
    if (($filtered = array_filter($aliases)) && (array_unique($filtered) !== $filtered)) {
      form_set_error('aliases', t('All field aliases must be unique'));
    }
  }

  /**
   * Overrides \Drupal\views\Plugin\views\row\RowPluginBase::render().
   */
  public function render($row) {
    $output = array();

    foreach ($this->view->field as $id => $field) {
      // If this is not unknown and the raw output option has been set, just get
      // the raw value.
      if (($field->field_alias != 'unknown') && !empty($this->rawOutputOptions[$id])) {
        $value = $field->sanitizeValue($field->getValue($row), 'xss_admin');
      }
      // Otherwise, pass this through the field render() method.
      else {
        $value = $field->render($row);
      }

      $output[$this->getFieldKeyAlias($id)] = $value;
    }

    return $output;
  }

  /**
   * Return an alias for a field ID, as set in the options form.
   *
   * @param string $id
   *   The field id to lookup an alias for.
   *
   * @return string
   *   The matches user entered alias, or the original ID if nothing is found.
   */
  public function getFieldKeyAlias($id) {
    if (isset($this->replacementAliases[$id])) {
      return $this->replacementAliases[$id];
    }

    return $id;
  }

  /**
   * Extracts a set of option values from a nested options array.
   *
   * @param string $key
   *   The key to extract from each array item.
   * @param array $options
   *   The options array to return values from.
   *
   * @return array
   *   A regular one dimensional array of values.
   */
  protected static function extractFromOptionsArray($key, $options) {
    return array_map(function($item) use ($key) {
      return isset($item[$key]) ? $item[$key] : NULL;
    }, $options);
  }

}
