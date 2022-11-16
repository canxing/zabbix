<?php declare(strict_types = 0);
/*
** Zabbix
** Copyright (C) 2001-2022 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/


class CControllerActionList extends CController {

	protected function init(): void {
		$this->disableSIDValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'eventsource' =>	'required|db actions.eventsource|in '.implode(',', [
									EVENT_SOURCE_TRIGGERS, EVENT_SOURCE_DISCOVERY, EVENT_SOURCE_AUTOREGISTRATION,
									EVENT_SOURCE_INTERNAL, EVENT_SOURCE_SERVICE
								]),
			'filter_set' =>		'in 1',
			'filter_rst' =>		'in 1',
			'filter_name' =>	'string',
			'filter_status' =>	'in '.implode(',', [-1, ACTION_STATUS_ENABLED, ACTION_STATUS_DISABLED]),
			'sort' =>			'in '.implode(',', ['name', 'status']),
			'sortorder' =>		'in '.implode(',', [ZBX_SORT_UP, ZBX_SORT_DOWN])
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		switch ($this->getInput('eventsource')) {
			case EVENT_SOURCE_TRIGGERS:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_TRIGGER_ACTIONS);

			case EVENT_SOURCE_DISCOVERY:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_DISCOVERY_ACTIONS);

			case EVENT_SOURCE_AUTOREGISTRATION:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_AUTOREGISTRATION_ACTIONS);

			case EVENT_SOURCE_INTERNAL:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_INTERNAL_ACTIONS);

			case EVENT_SOURCE_SERVICE:
				return $this->checkAccess(CRoleHelper::UI_CONFIGURATION_SERVICE_ACTIONS);
		}

		return false;
	}

	protected function doAction(): void {
		$eventsource = $this->getInput('eventsource', EVENT_SOURCE_TRIGGERS);
		$sort_field = $this->getInput('sort', CProfile::get('web.action.list.sort', 'name'));
		$sort_order = $this->getInput('sortorder', CProfile::get('web.action.list.sortorder', ZBX_SORT_UP));

		CProfile::update('web.action.list.sort', $sort_field, PROFILE_TYPE_STR);
		CProfile::update('web.action.list.sortorder', $sort_order, PROFILE_TYPE_STR);

		if ($this->hasInput('filter_set')) {
			CProfile::update('web.action.list.filter_name', $this->getInput('filter_name', ''), PROFILE_TYPE_STR);
			CProfile::update('web.action.list.filter_status', $this->getInput('filter_status', -1), PROFILE_TYPE_INT);
		}
		elseif ($this->hasInput('filter_rst')) {
			CProfile::delete('web.action.list.filter_name');
			CProfile::delete('web.action.list.filter_status');
		}

		$filter = [
			'name' => CProfile::get('web.action.list.filter_name', ''),
			'status' => CProfile::get('web.action.list.filter_status', -1)
		];

		$data = [
			'eventsource' => $eventsource,
			'sort' => $sort_field,
			'sortorder' => $sort_order,
			'filter' => $filter,
			'profileIdx' => 'web.action.list.filter',
			'active_tab' => CProfile::get('web.action.list.filter.active', 1)
		];

		$limit = CSettingsHelper::get(CSettingsHelper::SEARCH_LIMIT) + 1;
		$data['actions'] = API::Action()->get([
			'output' => API_OUTPUT_EXTEND,
			'search' => [
				'name' => $filter['name'] === '' ? null : $filter['name']
			],
			'filter' => [
				'eventsource' => $data['eventsource'],
				'status' => $filter['status'] == -1 ? null : $filter['status']
			],
			'selectFilter' => ['formula', 'conditions', 'evaltype'],
			'selectOperations' => API_OUTPUT_EXTEND,
			'editable' => true,
			'sortfield' => $sort_field,
			'limit' => $limit
		]);

		$data['actionConditionStringValues'] = actionConditionValueToString($data['actions']);
		$data['operation_descriptions'] = getActionOperationData($data['actions'],  ACTION_OPERATION);

		order_result($data['actions'], $sort_field, $sort_order);

		foreach ($data['actions'] as &$action) {
			order_result($action['filter']['conditions'], 'conditiontype');
		}
		unset ($action);

		// pager
		if (hasRequest('page')) {
			$page_num = getRequest('page');
		}
		elseif (isRequestMethod('get') && !hasRequest('cancel')) {
			$page_num = 1;
		}
		else {
			$page_num = $this->getInput('page', 1);
		}

		$data['paging'] = CPagerHelper::paginate($page_num, $data['actions'], $sort_order, (new CUrl('zabbix.php'))
			->setArgument('action', 'action.list')
			->setArgument('eventsource', $eventsource)
		);

		$response = new CControllerResponseData($data);
		$response->setTitle(_('Configuration of actions'));
		$this->setResponse($response);
	}
}
