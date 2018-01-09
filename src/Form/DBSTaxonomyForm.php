<?php

/**
 * @file
 * Contains \Drupal\dbs\Form\DBSTaxonomyForm.
 */

namespace Drupal\dbs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal;
use Drupal\taxonomy\Entity\Term;

class DBSTaxonomyForm extends FormBase {

    /*
     * getFormId()
     */
    public function getFormId() {
        return 'db_search_taxonomy_form';
    }

    /*
     * Initial form build.
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
        $form = [];

        $form['#method'] = 'get';

        $header = $this->_header_build();

        $form['vocabulary'] = [
            '#type' => 'select',
            '#title' => t('Vocabulary'),
            '#options' => $this->_get_vocab(),
            '#default_value'=> isset($_GET['vocabulary']) ? $_GET['vocabulary'] : '',
        ];

        //if vocabulary is selected list all terms from it.
        if(!empty($_GET['vocabulary'])) {
            $form['taxonomy'] = [
                '#type' => 'select',
                '#title' => 'Taxonomy',
                '#options' => $this->_get_taxonomy($_GET['vocabulary']),
                '#default_value' => isset($_GET['taxonomy']) ? $_GET['taxonomy'] : 'any',
            ];
        }

        $results = $this->_get_results($_GET['vocabulary'], $_GET['taxonomy']);

        $form['actions']['submit'] = [
            '#type' => 'submit',
            '#value' => 'Submit'
        ];

        $form['actions']['clear'] = [
            '#title' => $this->t('Reset'),
            '#type' => 'link',
            '#url' => Url::fromRoute('dbs.taxonomy'),
        ];

        if(!empty($_GET)){
            $form['results'] = [
                '#type' => 'tableselect',
                '#header' => $header,
                '#options' => $this->_parse_results($results),
                '#empty' => t('No Data'),
            ];
        }

        //when taxonomy is selected build new header.
        if(!empty($_GET['taxonomy']) && ($_GET['taxonomy'] != 'any')) {

            $results_node = $this->_get_results_node($_GET['taxonomy']);

            $header = [
                'id' => t('Id'),
                'title' => t('Title'),
                'status' => t('Published'),
                'created' => t('Created'),
                'edit' => t('Action'),
            ];

            $form['results'] = [
                '#type' => 'tableselect',
                '#header' => $header,
                '#options' => $this->_parse_results_node($results_node),
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
     * Get all vocabulary.
     */
    function _get_vocab() {
        $SQL = "SELECT ttd.vid FROM taxonomy_term_data ttd GROUP BY ttd.vid";

        $result = \Drupal::database()->query($SQL)->fetchAll();

        $authors = [];

        foreach ($result as $author) {
            $authors[$author->vid] = $author->vid;
        }

        return $authors;
    }

    /*
     * Get all terms of selected vocabulary
     */
    function _get_taxonomy($vid = '') {

        if(!empty($vid)) {
            $SQL = "SELECT ttfd.vid, ttfd.name, ttfd.tid FROM taxonomy_term_field_data ttfd WHERE ttfd.vid = :vid";
        } else {
            $SQL = "SELECT ttfd.vid, ttfd.name, ttfd.tid FROM taxonomy_term_field_data ttfd WHERE ttfd.vid != NULL";
        }

        $result = \Drupal::database()->query($SQL, [':vid' => $vid])->fetchAll();

        $name = [];

        foreach ($result as $tax_name) {
            $name[$tax_name->tid] = $tax_name->name;
        }

        return $name;
    }

    /*
     * Parse results for taxonomy terms.
     */
    function _parse_results($results = []) {

        $result = [];

        foreach ($results as $res) {
            $result[$res->tid] = [
                'tid' => $res->tid,
                'title' => Drupal::l($res->name, Url::fromUri('internal:/taxonomy/term/' . $res->tid)),
                'parent' => $res->parent != 0 ? $this->_get_taxonomy_name_by_id($res->parent) : 'None',
                'edit' => Drupal::l('Edit', Url::fromUri('internal:/taxonomy/term/' . $res->tid . '/edit')),
            ];
        }


        return $result;
    }

    /*
     * Get results of selected vocabulary and term.
     */
    function _get_results($vocabulary, $taxonomy) {

        $query = \Drupal::database()->select('taxonomy_term_hierarchy', 'tth');

        $query->fields('tth', ['parent']);

        $query->INNERJOIN('taxonomy_term_field_data', 'ttfd', 'ttfd.tid = tth.tid');

        $query->fields('ttfd', ['tid', 'vid', 'name']);

        //content vocabulary check.
        if(!empty($vocabulary)) {
            $query->condition('ttfd.vid', $vocabulary);
        }
        //content taxonomy check.
        if(!empty($taxonomy) && ($taxonomy != 'any')) {
            $query->condition('ttfd.tid', $taxonomy);
        } else {
          $query->condition('ttfd.tid', NULL, 'is not');
        }

//        table sort select extender.
        $query = $query->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($this->_header_build());

        $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);

        return $query->execute();
    }

    /*
     * Default header build.
     */
    function _header_build() {
        $header = [
            'tid' => t('Tid'),
            'title' => t('Title'),
            'parent' => t('Parent'),
            'edit' => t('Action'),
        ];
        return $header;
    }

    /*
     * Get nid where selected taxonomy is referenced.
     */
    function _get_tid_node($tid) {
        $SQL = "SELECT ti.nid FROM taxonomy_index ti WHERE ti.tid = :tid";

        $result = \Drupal::database()->query($SQL, [':tid' => $tid])->fetchAll();

        $nid = [];

        foreach ($result as $term) {
            $nid[$term->nid] = $term->nid;
        }

        if(!empty($nid)) {
            return $nid;
        }
    }

    /*
     * Get nodes  where selected taxonomy is referenced.
     */
    function _get_results_node($taxonomy) {
        $query = \Drupal::database()->select('node_field_data', 'nfd');

        $query->fields('nfd', ['nid', 'uid', 'type', 'title', 'status', 'created']);

        $query->JOIN('taxonomy_index', 'ti');

        //content taxonomy check.
        if(!empty($taxonomy)) {
            $query->condition('nfd.nid', $this->_get_tid_node($taxonomy));
        }

        // Table sort select extender.
        $query = $query->extend('Drupal\Core\Database\Query\TableSortExtender')->orderByHeader($this->_header_build());

        $query = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(10);

        return $query->execute();
    }

    /*
     * Parse node results.
     */
    function _parse_results_node($results = []) {
        if(!empty($results)) {
            $result = [];

            foreach ($results as $res) {
                $result[$res->nid] = [
                    'id' => $res->nid,
                    'title' => Drupal::l($res->title, Url::fromUri('internal:/node/' . $res->nid)),
                    'status' => $res->status == 1 ? 'yes' : 'no',
                    'created' => date('m/d/Y | H:i:s', $res->created),
                    'edit' => Drupal::l('Edit', Url::fromUri('internal:/node/' . $res->nid . '/edit')),
                ];
            }
            return $result;
        }
    }

    /*
     * Get term name by id.
     */
    function _get_taxonomy_name_by_id($tid) {
        $term = Term::load($tid);
        $name = $term->getName();
        return $name;
    }
}

