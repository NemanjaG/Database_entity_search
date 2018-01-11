<?php

/**
 * @file
 * Contains \Drupal\dbs\Form\DBSBlockForm.
 */

namespace Drupal\dbs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal;
use Drupal\block_content\Entity\BlockContent;

class DBSBlockForm extends FormBase {

    /*
     * getFormId()
     */
    public function getFormId() {
        return 'db_search_block_form';
    }

    /*
     * Initial form build.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form = [];

        $header = $this->_header_build();

        $results = $this->_get_results($_GET['block_type']);

        $form['#method'] = 'get';

        $form['block_type'] = [
            '#type' => 'select',
            '#title' => t('Type'),
            '#options' => $this->_get_block_type(),
            '#default_value'=> isset($_GET['block_type']) ? $_GET['block_type'] : '',
        ];

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => 'Submit'
        ];

        $form['actions']['clear'] = [
            '#title' => $this->t('Reset'),
            '#type' => 'link',
            '#url' => Url::fromRoute('dbs.block'),
        ];

        if(!empty($_GET)){
            $form['results'] = [
                '#type' => 'tableselect',
                '#header' => $header,
                '#options' => $this->_parse_results($results),
                '#empty' => t('No Data'),
            ];
        }

        return $form;
    }

    /*
     * Form submit.
     */
    public function submitForm(array &$form, FormStateInterface $form_state) {
        $form_state->setRebuild(TRUE);
    }

    /*
     * Get all Block types.
     */
    function _get_block_type() {
        $SQL = "SELECT bcfd.type FROM block_content_field_data bcfd GROUP BY bcfd.type";

        $result = \Drupal::database()->query($SQL)->fetchAll();

        $type = [];

        foreach ($result as $types) {
            $type[$types->type] = $types->type;
        }

        return $type;
    }

    /*
     * Initial header build.
     */
    function _header_build() {
        $header = [
            'id' => t('Id'),
            'title' => t('Title'),
            'type' => t('Type'),
            'edit' => t('Action'),
        ];
        return $header;
    }

    /*
     * Parse results for block types.
     */
    function _parse_results($results = []) {

        $result = [];

        foreach ($results as $res) {

            $result[$res->id] = [
                'id' => $res->id,
                'title' => Drupal::l($res->info, Url::fromUri('internal:/taxonomy/term/' . $res->tid)),
                'type' => $res->type,
                'edit' => Drupal::l('Edit', Url::fromUri('internal:/taxonomy/term/' . $res->tid . '/edit')),
            ];

        }

        return $result;
    }

    /*
     * Get results of selected block type.
     */
    function _get_results($block_type) {

        $query = \Drupal::database()->select('block_content_field_data', 'bcfd');

        $query->fields('bcfd', ['id', 'type', 'info']);

        //content block type check.
        if(!empty($block_type)) {
            $query->condition('bcfd.type', $block_type);
        }

        //table sort select extender.
        $query = $query->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($this->_header_build());

        $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);

        return $query->execute();
    }
}
