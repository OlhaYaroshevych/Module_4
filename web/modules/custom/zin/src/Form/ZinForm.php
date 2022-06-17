<?php

namespace Drupal\zin\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Creates a page with an accounting sheet with a complex validation mechanism.
 */
class ZinForm extends FormBase {

  /**
   * Table heading.
   *
   * @var array
   */
  protected array $heading = [
    'Year',
    'Jan',
    'Feb',
    'Mar',
    'Q1',
    'Apr',
    'May',
    'Jun',
    'Q2',
    'Jul',
    'Aug',
    'Sep',
    'Q3',
    'Oct',
    'Nov',
    'Dec',
    'Q4',
    'YTD',
  ];

  /**
   * Disabled cells for storage of summative values.
   *
   * @var array
   */
  protected array $cellsWithSum = [
    'Q1',
    'Q2',
    'Q3',
    'Q4',
    'YTD',
  ];

  /**
   * Initial number of tables.
   *
   * @var int
   */
  protected $tablesAmount = 1;

  /**
   * Initial number of rows.
   *
   * @var int
   */
  protected $rowsAmount = 1;

  /**
   * {@inheritDoc}
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritDoc}
   */
  public function getFormId(): string {
    return 'zin_form';
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container): ZinForm {
    $instance = parent::create($container);
    $instance->setMessenger($container->get('messenger'));
    return $instance;
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['#prefix'] = '<div id="zin-table">';
    $form['#suffix'] = '</div>';
    $form['#attached']['library'][] = 'zin/zin-css';
    // Adding controll buttons.
    $form['add_table'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add table'),
      '#submit' => [
        '::addTable',
      ],
      '#ajax' => [
        'wrapper' => 'zin-table',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding new table..'),
        ],
      ],
    ];
    $form['add_row'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add year'),
      '#submit' => [
        '::addRow',
      ],
      '#ajax' => [
        'wrapper' => 'zin-table',
        'progress' => [
          'type' => 'throbber',
          'message' => $this->t('Adding one more year..'),
        ],
      ],
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#ajax' => [
        'wrapper' => 'zin-table',
        'progress' => [
          'callback' => '::submitAjax',
          'type' => 'throbber',
          'message' => $this->t('Submitting the data..'),
        ],
      ],
    ];
    // Calling function needed for the correct table output.
    $this->buildTable($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Function for building rows.
   */
  public function buildRow(string $table_id, array &$form, FormStateInterface $form_state) {
    for ($i = $this->rowsAmount; $i > 0; $i--) {
      $rowID = 'row_' . $i;
      // Building three types of cells.
      for ($z = 0; $z < count($this->heading); $z++) {
        // Building cells with year numbers.
        if ($this->heading[$z] === 'Year') {
          $form[$table_id][$rowID][$this->heading[$z]] = [
            '#type' => 'number',
            '#value' => date('Y') + 1 - $i,
            '#disabled' => TRUE,
            '#prefix' => '<div id="year-cell">',
            '#suffix' => '</div>',
          ];
        }
        elseif (in_array($this->heading[$z], $this->cellsWithSum, TRUE)) {
          // Building empty cells for storage of summative values.
          $form[$table_id][$rowID][$this->heading[$z]] = [
            '#type' => 'number',
            '#value' => $form_state->getValue([
              $table_id,
              $rowID,
              $this->heading[$z],
            ], ''),
            '#disabled' => TRUE,
            '#prefix' => '<div id="sum-cell">',
            '#suffix' => '</div>',
          ];
        }
        else {
          // Building cells for data input.
          $form[$table_id][$rowID][$this->heading[$z]] = [
            '#type' => 'number',
          ];
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Building needed number of rows.
   */
  public function addRow(array &$form, FormStateInterface $form_state): array {
    $this->rowsAmount++;
    $form_state->setRebuild();
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Function for building a table.
   */
  public function buildTable(&$form, FormStateInterface $form_state) {
    for ($i = 0; $i < $this->tablesAmount; $i++) {
      $table_id = 'table_' . $i;
      $form[$table_id] = [
        '#type' => 'table',
        '#header' => $this->heading,
        '#tree' => 'TRUE',
      ];
      // Calling the function that creates table rows.
      $this->buildRow($table_id, $form, $form_state);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Building needed number of tables.
   */
  public function addTable(array &$form, FormStateInterface $form_state): array {
    $this->tablesAmount++;
    $form_state->setRebuild();
    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * Getting values from the zin form.
   */
  public function getFormData(FormStateInterface $form_state): array {
    $values = $form_state->getValues();
    $data = [];
    for ($i = 0; $i < $this->tablesAmount; $i++) {
      $table_id = 'table_' . $i;
      foreach ($values as $key => $element) {
        if ($key === $table_id) {
          $data[$key] = $element;
        }
      }
    }
    foreach ($data as $table_id => $table) {
      foreach ($table as $rowID => $row) {
        foreach ($row as $key => $value) {
          if (in_array($key, $this->cellsWithSum, TRUE) || $key === 'Year') {
            unset($row[$key]);
          }
        }
        $table[$rowID] = $row;
      }
      $data[$table_id] = $table;
    }
    return $data;
  }

  /**
   * {@inheritdoc}
   *
   * Arrays identity check.
   * Deleting all non-empty values in order to detect arrays identity.
   */
  public function arraysIdentityCheck(array $arr_one, array $arr_two): bool {
    $arr_one = array_filter($arr_one, function ($k) {
      return $k === '';
    }, ARRAY_FILTER_USE_BOTH);
    $arr_two = array_filter($arr_two, function ($k) {
      return $k === '';
    }, ARRAY_FILTER_USE_BOTH);
    return $arr_one !== $arr_two;
  }

  /**
   * {@inheritdoc}
   *
   * Form validation mechanism.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      $form_state->clearErrors();
    }
    $data = $this->getFormData($form_state);
    foreach ($data as $table_id => $table) {
      foreach ($table as $rowID => $row) {
        foreach ($row as $key => $value) {
          // For each table, stored all rows in one array.
          $tables[$table_id][] = $value;
          $index = 0;
          $additional[$table_id] = $tables[$table_id];
          // Deleting empty values from the very begining.
          while (empty($additional[$table_id][$index]) && $index < count($tables[$table_id]) && $additional[$table_id][$index] !== '0') {
            unset($additional[$table_id][$index]);
            $index++;
          }
          $tablesNumber[$table_id] = array_values($additional[$table_id]);
          for ($i = 0; $i < count($additional[$table_id]); $i++) {
            if (empty($tablesNumber[$table_id][$i]) && $tablesNumber[$table_id][$i] !== '0') {
              unset($tablesNumber[$table_id][$i]);
            }
          }
        }
        // Months gap check (the period given should not have gaps between months).
        foreach ($tablesNumber as $item) {
          if (array_values($item) !== $item) {
            $form_state->setErrorByName($table_id, $this->t('The row should not contain spaces between months'));
          }
        }
        // Tables identity check (the periods given in tables have to be equal).
        if ($table_id !== 'table_0' && $this->arraysIdentityCheck($row, $data['table_0'][$rowID])) {
          $form_state->setError($form[$table_id][$rowID], $this->t('Invalid. The periods given do not coincide.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   *
   * Updating the page.
   */
  public function submitAjax(array $form, FormStateInterface $form_state): array {
    return $form;
  }

  /**
   * {@inheritDoc}
   *
   * Form submission mechanism.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getErrors()) {
      $this->messenger()->addError('Invalid data input.', MessengerInterface::TYPE_ERROR);
      $form_state->clearErrors();
    }
    else {
      $data = $this->getFormData($form_state);
      foreach ($data as $table_id => $table) {
        foreach ($table as $rowID => $row) {
          // Setting up initial values of summative cells.
          $q1 = 0;
          $q2 = 0;
          $q3 = 0;
          $q4 = 0;
          // Checking if the cells needed are empty and getting average values.
          if (!empty($row['Jan']) || !empty($row['Feb']) || !empty($row['Mar'])) {
            $q1 = round((floatval($row['Jan']) + floatval($row['Feb']) + floatval($row['Mar']) + 1) / 3, 2);
          }
          if (!empty($row['Apr']) || !empty($row['May']) || !empty($row['Jun'])) {
            $q2 = round((floatval($row['Apr']) + floatval($row['May']) + floatval($row['Jun']) + 1) / 3, 2);
          }
          if (!empty($row['Jul']) || !empty($row['Aug']) || !empty($row['Sep'])) {
            $q3 = round((floatval($row['Jul']) + floatval($row['Aug']) + floatval($row['Sep']) + 1) / 3, 2);
          }
          if (!empty($row['Oct']) || !empty($row['Nov']) || !empty($row['Dec'])) {
            $q4 = round((floatval($row['Oct']) + floatval($row['Nov']) + floatval($row['Dec']) + 1) / 3, 2);
          }
          if ($q1 !== 0 || $q2 !== 0 || $q3 !== 0 || $q4 !== 0) {
            $ytd = round(($q1 + $q2 + $q3 + $q4 + 1) / 4, 2);
          }
          else {
            $this->messenger()->addError('Invalid. Empty rows and year gaps are not allowed.');
            return FALSE;
          }
          // Data output into summative cells.
          $form_state->setValue([$table_id, $rowID, 'Q1'], $q1);
          $form_state->setValue([$table_id, $rowID, 'Q2'], $q2);
          $form_state->setValue([$table_id, $rowID, 'Q3'], $q3);
          $form_state->setValue([$table_id, $rowID, 'Q4'], $q4);
          $form_state->setValue([$table_id, $rowID, 'YTD'], $ytd);
        }
      }
      $this->messenger->addStatus('Data is valid.');
      $form_state->setRebuild();
    }
  }

}
