<?php
// {{{ICINGA_LICENSE_HEADER}}}
/**
 * This file is part of Icinga Web 2.
 *
 * Icinga Web 2 - Head for multiple monitoring backends.
 * Copyright (C) 2013 Icinga Development Team
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @copyright  2013 Icinga Development Team <info@icinga.org>
 * @license    http://www.gnu.org/licenses/gpl-2.0.txt GPL, version 2
 * @author     Icinga Development Team <info@icinga.org>
 *
 */
// {{{ICINGA_LICENSE_HEADER}}}

use \Icinga\Web\Form;
use Icinga\Module\Monitoring\Controller;
use \Icinga\Web\Widget\Tabextension\OutputFormat;
use \Icinga\Module\Monitoring\Backend;
use \Icinga\Data\SimpleQuery;
use \Icinga\Web\Widget\Chart\InlinePie;
use \Icinga\Module\Monitoring\Form\Command\MultiCommandFlagForm;
use \Icinga\Module\Monitoring\DataView\HostStatus    as HostStatusView;
use \Icinga\Module\Monitoring\DataView\ServiceStatus as ServiceStatusView;
use \Icinga\Module\Monitoring\DataView\Comment       as CommentView;

/**
 * Displays aggregations collections of multiple objects.
 */
class Monitoring_MultiController extends Controller
{
    public function hostAction()
    {
        $multiFilter = $this->getAllParamsAsArray();
        $errors  = array();

        // Fetch Hosts
        $hostQuery = HostStatusView::fromRequest(
            $this->_request,
            array(
                'host_name',
                'host_in_downtime',
                'host_unhandled_service_count',
                'host_passive_checks_enabled',
                'host_obsessing',
                'host_state',
                'host_notifications_enabled',
                'host_event_handler_enabled',
                'host_flap_detection_enabled',
                'host_active_checks_enabled',

                // columns intended for filter-request
                'host_problem',
                'host_handled'
            )
        )->getQuery();
        $this->applyQueryFilter($hostQuery, $multiFilter);
        $hosts = $hostQuery->fetchAll();

        // Fetch comments
        $commentQuery = $this->applyQueryFilter(
            CommentView::fromParams(array('backend' => $this->_request->getParam('backend')))->getQuery(),
            $multiFilter,
            'comment_host'
        );
        $comments = array_keys($this->getUniqueValues($commentQuery->fetchAll(), 'comment_internal_id'));

        // Populate view
        $this->view->objects   = $this->view->hosts = $hosts;
        $this->view->problems  = $this->getProblems($hosts);
        $this->view->comments  = isset($comments) ? $comments : $this->getComments($hosts);
        $this->view->hostnames = $this->getProperties($hosts, 'host_name');
        $this->view->downtimes = $this->getDowntimes($hosts);
        $this->view->errors    = $errors;
        $this->view->states    = $this->countStates($hosts, 'host', 'host_name');
        $this->view->pie       = $this->createPie($this->view->states, $this->view->getHelper('MonitoringState')->getHostStateColors());

        // need the query content to list all hosts
        $this->view->query = $this->_request->getQuery();

        // Handle configuration changes
        $this->handleConfigurationForm(array(
            'host_passive_checks_enabled' => 'Passive Checks',
            'host_active_checks_enabled'  => 'Active Checks',
            'host_notifications_enabled'  => 'Notifications',
            'host_event_handler_enabled'  => 'Event Handler',
            'host_flap_detection_enabled' => 'Flap Detection',
            'host_obsessing'              => 'Obsessing'
        ));
    }


    public function serviceAction()
    {
        $multiFilter = $this->getAllParamsAsArray();
        $errors = array();
        $backendQuery = ServiceStatusView::fromRequest(
            $this->_request,
            array(
                'host_name',
                'host_state',
                'service_description',
                'service_handled',
                'service_state',
                'service_in_downtime',
                'service_passive_checks_enabled',
                'service_notifications_enabled',
                'service_event_handler_enabled',
                'service_flap_detection_enabled',
                'service_active_checks_enabled',
                'service_obsessing',

                 // also accept all filter-requests from ListView
                'service_problem',
                'service_severity',
                'service_last_check',
                'service_state_type',
                'host_severity',
                'host_address',
                'host_last_check'
            )
        )->getQuery();

        $this->applyQueryFilter($backendQuery, $multiFilter);
        $services = $backendQuery->fetchAll();

        // Comments
        $commentQuery = $this->applyQueryFilter(
            CommentView::fromParams(array('backend' => $this->_request->getParam('backend')))->getQuery(),
            $multiFilter,
            'comment_host',
            'comment_service'
        );
        $comments = array_keys($this->getUniqueValues($commentQuery->fetchAll(), 'comment_internal_id'));

        // populate the view
        $this->view->objects        = $this->view->services = $services;
        $this->view->problems       = $this->getProblems($services);
        $this->view->comments       = isset($comments) ? $comments : $this->getComments($services);
        $this->view->hostnames      = $this->getProperties($services, 'host_name');
        $this->view->servicenames   = $this->getProperties($services, 'service_description');
        $this->view->downtimes      = $this->getDowntimes($services);
        $this->view->service_states = $this->countStates($services, 'service');
        $this->view->host_states    = $this->countStates($services, 'host',  'host_name');
        $this->view->service_pie    = $this->createPie($this->view->service_states, $this->view->getHelper('MonitoringState')->getServiceStateColors());
        $this->view->host_pie       = $this->createPie($this->view->host_states, $this->view->getHelper('MonitoringState')->getHostStateColors());
        $this->view->errors         = $errors;

        // need the query content to list all hosts
        $this->view->query = $this->_request->getQuery();

        $this->handleConfigurationForm(array(
            'service_passive_checks_enabled' => 'Passive Checks',
            'service_active_checks_enabled'  => 'Active Checks',
            'service_notifications_enabled'  => 'Notifications',
            'service_event_handler_enabled'  => 'Event Handler',
            'service_flap_detection_enabled' => 'Flap Detection',
            'service_obsessing'              => 'Obsessing',
        ));
    }


    /**
     * Apply the query-filter received
     *
     * @param $backendQuery  SimpleQuery  The query to apply the filter to
     * @param $filter        array      Containing the queries of the current request, converted into an
     *                                      array-structure.
     * @param $hostColumn    string     The name of the host-column in the SimpleQuery, defaults to 'host_name'
     * @param $serviceColumn string     The name of the service-column in the SimpleQuery, defaults to 'service-description'
     *
     * @return SimpleQuery    The given SimpleQuery
     */
    private function applyQueryFilter(
        SimpleQuery $backendQuery,
        array $filter,
        $hostColumn = 'host_name',
        $serviceColumn = 'service_description'
    ) {
        // fetch specified hosts
        foreach ($filter as $index => $expr) {
            // Every query entry must define at least the host.
            if (!array_key_exists('host', $expr)) {
                $errors[] = 'Query ' . $index . ' misses property host.';
                continue;
            }
            // apply filter expressions from query
            $backendQuery->orWhere($hostColumn, $expr['host']);
            if (array_key_exists('service', $expr)) {
                $backendQuery->andWhere($serviceColumn, $expr['service']);
            }
        }
        return $backendQuery;
    }

    /**
     * Create an array with all unique values as keys.
     *
     * @param array $values     The array containing the objects
     * @param       $key        The key to access
     *
     * @return array
     */
    private function getUniqueValues($values, $key)
    {
        $unique = array();
        foreach ($values as $value)
        {
			if (is_array($value)) {
				$unique[$value[$key]] = $value[$key];
			} else {
            	$unique[$value->$key] = $value->$key;
			}
        }
        return $unique;
    }

    /**
     * Get the numbers of problems of the given objects
     *
     * @param $objects  The objects containing the problems
     *
     * @return int  The problem count
     */
    private function getProblems($objects)
    {
        $problems = 0;
        foreach ($objects as $object) {
            if (property_exists($object, 'host_unhandled_service_count')) {
                $problems += $object->host_unhandled_service_count;
            } else if (
                property_exists($object, 'service_handled') &&
                !$object->service_handled &&
                $object->service_state > 0
            ) {
                $problems++;
            }
        }
        return $problems;
    }

    private function countStates($objects, $type = 'host', $unique = null)
    {
        $known  = array();
        if ($type === 'host') {
            $states = array_fill_keys($this->view->getHelper('MonitoringState')->getHostStateNames(), 0);
        } else {
            $states = array_fill_keys($this->view->getHelper('MonitoringState')->getServiceStateNames(), 0);
        }
        foreach ($objects as $object) {
            if (isset($unique)) {
                if (array_key_exists($object->$unique, $known)) {
                    continue;
                }
                $known[$object->$unique] = true;
            }
            $states[$this->view->monitoringState($object, $type)]++;
        }
        return $states;
    }

    private function createPie($states, $colors)
    {
        $chart = new InlinePie(array_values($states), $colors);
        $chart->setLabels(array_keys($states))->setHeight(100)->setWidth(100);
        return $chart;
    }


    private function getComments($objects)
    {
        $unique = array();
        foreach ($objects as $object) {
            $unique = array_merge($unique, $this->getUniqueValues($object->comments, 'comment_internal_id'));
        }
        return array_keys($unique);
    }

    private function getProperties($objects, $property)
    {
        $objectnames = array();
        foreach ($objects as $object) {
            $objectnames[] = $object->$property;
        }
        return $objectnames;
    }

    private function getDowntimes($objects)
    {
        $downtimes = array();
        foreach ($objects as $object)
        {
            if (
                (property_exists($object, 'host_in_downtime') && $object->host_in_downtime) ||
                (property_exists($object, 'service_in_downtime') && $object->service_in_downtime)
            ) {
                $downtimes[] = true;
            }
        }
        return $downtimes;
    }


    /**
     * Handle the form to edit configuration flags.
     *
     * @param $flags array  The used flags.
     */
    private function handleConfigurationForm(array $flags)
    {
        $this->view->form = $form = new MultiCommandFlagForm($flags);
        $this->view->formElements = $form->buildCheckboxes();
        $form->setRequest($this->_request);
        if ($form->isSubmittedAndValid()) {
            // TODO: Handle commands
            $changed = $form->getChangedValues();
        }
        if ($this->_request->isPost() === false) {
            $this->view->form->initFromItems($this->view->objects);
        }
    }

    /**
     * "Flips" the structure of the objects created by _getAllParams
     *
     * Regularly, _getAllParams would return queries like <b>host[0]=value1&service[0]=value2</b> as
     * two entirely separate arrays. Instead, we want it as one single array, containing one single object
     * for each index, containing all of its members as keys.
     *
     * @return array    An array of all query parameters (See example above)
     *                  <b>
     *                      array( <br />
     *                          0 => array(host => value1, service => value2), <br />
     *                          ... <br />
     *                      )
     *                  </b>
     */
    private function getAllParamsAsArray()
    {
        $details = $this->_getAllParams();
        $queries = array();

        foreach ($details as $property => $values) {
            if (!is_array($values)) {
                continue;
            }
            foreach ($values as $index => $value) {
                if (!array_key_exists($index, $queries)) {
                    $queries[$index] = array();
                }
                $queries[$index][$property] = $value;
            }
        }
        return $queries;
    }
}
