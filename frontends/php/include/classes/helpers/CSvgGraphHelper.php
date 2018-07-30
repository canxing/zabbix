<?php
/*
** Zabbix
** Copyright (C) 2001-2018 Zabbix SIA
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


class CSvgGraphHelper {

	/**
	 * Calculate graph data and draw SVG graph based on given graph configuration.
	 *
	 * @param array $options					Options for graph.
	 * @param array $options[data_sets]			Graph data set options.
	 * @param array $options[problems]			Graph problems options.
	 * @param array $options[overrides]			Graph problems options.
	 * @param array $options[data_source]		Data source of graph.
	 * @param array $options[time_period]		Graph time period used.
	 * @param bool  $options[dashboard_time]	True if dashboard time is used.
	 * @param array $options[left_y_axis]		Options for graph left Y axis.
	 * @param array $options[right_y_axis]		Options for graph right Y axis.
	 * @param array $options[x_axis]			Options for graph X axis.
	 * @param array $options[legend]			Options for graph legend.
	 *
	 * @return array
	 */
	public static function get(array $options = [], $width, $height) {
		$metrics = [];
		$errors = [];
		$problems = [];

		// Find which metrics will be shown in graph and calculate time periods and display options.
		self::getMetrics($metrics, $options['data_sets']);
		// Apply overrides for previously selected $metrics.
		self::applyOverrides($metrics, $options['overrides']);
		// Apply time periods for each $metric, based on graph/dashboard time as well as metric level timeshifts.
		self::getTimePeriods($metrics, $options['time_period']);
		// Find what data source (history or trends) will be used for each metric.
		self::getGraphDataSource($metrics, $errors, $options['data_source']);
		// Load Data for each metric.
		self::getMetricsData($metrics, $errors, $width);

		// Get problems to display in graph.
		if (array_key_exists('problems', $options)) {
			$options['problems']['itemids_only'] = (array_key_exists('graph_item_problems_only', $options['problems'])
					&& $options['problems']['graph_item_problems_only'] == SVG_GRAPH_SELECTED_ITEM_PROBLEMS)
				? zbx_objectValues($metrics, 'itemid')
				: null;

			$problems = self::getProblems($options['problems'], $options['time_period']);
		}

		// Clear unneeded data.
		unset($options['data_sets'], $options['overrides'], $options['problems']);

		// Draw SVG graph.
		$graph = (new CSvgGraph($width, $height, $options))
			->setTimePeriod($options['time_period']['time_from'], $options['time_period']['time_to'])
			->setYAxisLeft(array_key_exists('left_y_axis', $options) ? $options['left_y_axis'] : false)
			->setYAxisRight(array_key_exists('right_y_axis', $options) ? $options['right_y_axis'] : false)
			->setXAxis(array_key_exists('x_axis', $options) ? $options['x_axis'] : false)
			->setLegendType($options['legend'])
			->addProblems($problems)
			->addMetrics($metrics)
			->draw();

		return [
			'svg' => $graph,
			'data' => [
				'dims' => [
					'x' => $graph->getCanvasX(),
					'y' => $graph->getCanvasY(),
					'w' => $graph->getCanvasWidth(),
					'h' => $graph->getCanvasHeight()
				],
				'spp' => (int) ($options['time_period']['time_to'] - $options['time_period']['time_from']) / $graph->getCanvasWidth()
			],
			'errors' => $errors
		];
	}

	protected static function getMetricsData(array &$metrics = [], array &$errors = [], $width) {
		// To reduce number of requests, group metrics by time range.
		$same_timerange_metrics = [];
		foreach ($metrics as $metric_num => &$metric) {
			$metric['points'] = [];

			$key = $metric['time_period']['time_from'].$metric['time_period']['time_to'];
			if (!array_key_exists($key, $same_timerange_metrics)) {
				$same_timerange_metrics[$key]['time'] = [
					'from' => $metric['time_period']['time_from'],
					'to' => $metric['time_period']['time_to']
				];
			}

			$same_timerange_metrics[$key]['items'][$metric_num] = [
				'itemid' => $metric['itemid'],
				'value_type' => $metric['value_type'],
				'source' => ($metric['source'] == SVG_GRAPH_DATA_SOURCE_HISTORY) ? 'history' : 'trends'
			];
		}
		unset($metric);

		// Request data.
		foreach ($same_timerange_metrics as $tr_group) {
			$results = Manager::History()->getGraphAggregation($tr_group['items'], $tr_group['time']['from'],
				$tr_group['time']['to'], $width
			);

			if ($results) {
				foreach ($tr_group['items'] as $metric_num => $m) {
					$metric = &$metrics[$metric_num];

					// Collect and sort data points.
					if (array_key_exists($m['itemid'], $results)) {
						$points = [];
						foreach ($results[$m['itemid']]['data'] as $point) {
							$points[] = ['clock' => $point['clock'], 'value' => $point['avg']];
						}
						usort($points, [__CLASS__, 'sortByClock']);
						$metric['points'] = $points;

						unset($metric['history'], $metric['source'], $metric['trends']);
					}
				}
				unset($metric);
			}
		}

		return $metrics;
	}

	protected static function getGraphDataSource(array &$metrics = [], array &$errors = [], $data_source) {
		$simple_interval_parser = new CSimpleIntervalParser();
		$config = select_config();

		foreach ($metrics as &$metric) {
			/**
			 * If data source is not specified, calculate it automatically. Otherwise, set given $data_source to each
			 * $metric.
			 */
			if ($data_source == SVG_GRAPH_DATA_SOURCE_AUTO) {
				$to_resolve = [];

				/**
				 * First, if global configuration setting "Override item history period" is enabled, override globally
				 * specified "Data storage period" value to each metric's custom history storage duration, converting it
				 * to seconds. If "Override item history period" is disabled, item level field 'history' will be used
				 * later but now we are just storing the field name 'history' in array $to_resolve.
				 *
				 * Do the same with trends.
				 */
				if ($config['hk_history_global']) {
					$metric['history'] = timeUnitToSeconds($config['hk_history']);
				}
				else {
					$to_resolve[] = 'history';
				}

				if ($config['hk_trends_global']) {
					$metric['trends'] = timeUnitToSeconds($config['hk_trends']);
				}
				else {
					$to_resolve[] = 'trends';
				}

				/**
				 * If no global history and trend override enabled, resolve 'history' and/or 'trends' values for given
				 * $metric and convert its values to seconds.
				 */
				if ($to_resolve) {
					$metric = CMacrosResolverHelper::resolveTimeUnitMacros([$metric], $to_resolve)[0];

					if (!$config['hk_history_global']) {
						if ($simple_interval_parser->parse($metric['history']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'history',
								_('invalid history storage period')
							);
						}
						$metric['history'] = timeUnitToSeconds($metric['history']);
					}

					if (!$config['hk_trends_global']) {
						if ($simple_interval_parser->parse($metric['trends']) != CParser::PARSE_SUCCESS) {
							$errors[] = _s('Incorrect value for field "%1$s": %2$s.', 'trends',
								_('invalid trend storage period')
							);
						}
						$metric['trends'] = timeUnitToSeconds($metric['trends']);
					}
				}

				/**
				 * History as a data source is used in 2 cases:
				 * 1) if trends are disabled (set to 0) either for particular $metric item or globally;
				 * 2) if period for requested data is newer than the period of keeping history for particular $metric
				 *	  item.
				 *
				 * Use trends otherwise.
				 */
				$metric['source'] = ($metric['trends'] == 0
						|| (time() - $metric['history']) <= $metric['time_period']['time_from'])
					? SVG_GRAPH_DATA_SOURCE_HISTORY
					: SVG_GRAPH_DATA_SOURCE_TRENDS;
			}
			else {
				$metric['source'] = $data_source;
			}
		}
	}

	protected static function getProblems(array $problem_options = [], array $time_period) {
		/**
		 * There can be 2 problem groups in graph.
		 *  - problems that begun before the graph start time and ended later than graph start time or never;
		 *  - problems that has started between graph start time and end time.
		 *
		 * This is solved making 2 separate requests:
		 * - First is made with raw SQL, requesting all eventids in calculated time period. This query also involves
		 *   filtering by severity and triggerid (based on $problem_options[itemids_only] and
		 *   $problem_options[problem_hosts]). This is done to make response smaller.
		 * - Seconds request is made using problem.get API method. This involves checks for permissions as well as
		 *   search by problem name and selected problem tags.
		 */

		$sql_parts = [
			dbConditionInt('p.severity', $problem_options['severities']),
			'p.source = '.EVENT_SOURCE_TRIGGERS,
			'p.object = '.EVENT_OBJECT_TRIGGER
		];

		if (array_key_exists('problem_hosts', $problem_options) || array_key_exists('itemids_only', $problem_options)) {
			$options = [
				'output' => [],
				'selectTriggers' => ['triggerid'],
				'preservekeys' => true
			];

			if (array_key_exists('problem_hosts', $problem_options)) {
				$options += [
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'search' => [
						'name' => self::processPattern($problem_options['problem_hosts'])
					]
				];
			}

			if (array_key_exists('itemids_only', $problem_options)) {
				$options += [
					'itemids' => $problem_options['itemids_only']
				];
			}

			$triggerids = [];
			$problem_hosts = API::Host()->get($options);
			foreach ($problem_hosts as $problem_host) {
				$triggerids = zbx_array_merge($triggerids, zbx_objectValues($problem_host['triggers'], 'triggerid'));
			}
			$sql_parts[] = dbConditionInt('p.objectid', $triggerids);
		}

		// Raw SQL written as temporary solution because API doesn't allow make such request.
		$query =
		'SELECT problems.eventid FROM (
			(
				SELECT eventid
				FROM problem p
				WHERE '.implode(' AND ', $sql_parts).' AND
					('.$time_period['time_from'].' > clock AND (r_clock > '.$time_period['time_from'].' OR r_clock = 0))
			) UNION ALL (
				SELECT eventid
				FROM problem p
				WHERE '.implode(' AND ', $sql_parts).' AND
					(clock BETWEEN '.$time_period['time_from'].' AND '.$time_period['time_to'].')
			)
		) problems';

		$eventids = [];
		$config = select_config();
		$problem_data = DBselect($query, $config['search_limit']);
		while ($problem = DBfetch($problem_data)) {
			$eventids[] = $problem['eventid'];
		}

		// Prepare API request to select problems.
		$options = [
			'output' => ['objectid', 'name', 'severity', 'clock', 'r_clock'],
			'selectAcknowledges' => ['action'],
			'eventids' => $eventids,
			'recent' => true,
			'preservekeys' => true
		];
		if (array_key_exists('problem_name', $problem_options)) {
			$options['search']['name'] = $problem_options['problem_name'];
		}
		if (array_key_exists('evaltype', $problem_options)) {
			$options['evaltype'] = $problem_options['evaltype'];
		}
		if (array_key_exists('tags', $problem_options)) {
			$options['tags'] = $problem_options['tags'];
		}

		return API::Problem()->get($options);
	}

	protected static function getMetrics(array &$metrics = [], array $data_sets = []) {
		$data_set_num = 0;
		$metrics = [];

		if (!$data_sets) {
			return;
		}

		do {
			$data_set = $data_sets[$data_set_num];
			$data_set_num++;

			if ((!array_key_exists('hosts', $data_set) || $data_set['hosts'] === '')
					|| (!array_key_exists('items', $data_set) || $data_set['items'] === '')) {
				continue;
			}

			// Find hosts.
			$matching_hosts = API::Host()->get([
				'output' => [],
				'searchWildcardsEnabled' => true,
				'searchByAny' => true,
				'search' => [
					'name' => self::processPattern($data_set['hosts'])
				],
				'sortfield' => 'name',
				'sortorder' => ZBX_SORT_UP,
				'preservekeys' => true
			]);

			if ($matching_hosts) {
				$matching_items = API::Item()->get([
					'output' => ['itemid', 'name', 'history', 'trends', 'units', 'value_type', 'valuemapid'],
					'hostids' => array_keys($matching_hosts),
					'selectHosts' => ['hostid', 'name'],
					'searchWildcardsEnabled' => true,
					'searchByAny' => true,
					'search' => [
						'name' => self::processPattern($data_set['items'])
					],
					'filter' => [
						'value_type' => [ITEM_VALUE_TYPE_UINT64, ITEM_VALUE_TYPE_FLOAT]
					],
					'sortfield' => 'name',
					'sortorder' => ZBX_SORT_UP,
					'limit' => SVG_GRAPH_MAX_NUMBER_OF_METRICS - count($metrics),
					'preservekeys' => true
				]);

				if (!$matching_items) {
					continue;
				}

				unset($data_set['hosts'], $data_set['items']);

				// Add display options and append to $metrics list.
				if (!array_key_exists('color', $data_set) && $data_set['color'] === '') {
					// TODO miks: no workflow specified. Consult Andzs and fix it.
					exit('No color specified');
				}
				if (substr($data_set['color'], 0, 1) !== '#') {
					$data_set['color'] = '#'.$data_set['color'];
				}

				$data_set['timeshift'] = ($data_set['timeshift'] !== '')
					? (int) timeUnitToSeconds($data_set['timeshift'], true)
					: 0;

				$colors = (count($matching_items) > 1)
					? getColorVariations($data_set['color'], count($matching_items))
					: [$data_set['color']];

				$i = 0;
				foreach ($matching_items as $item) {
					$data_set['color'] = $colors[$i];
					$metrics[] = $item + ['options' => $data_set];
					$i++;
				}
			}
		}
		while (SVG_GRAPH_MAX_NUMBER_OF_METRICS > count($metrics) && array_key_exists($data_set_num, $data_sets));

		CArrayHelper::sort($metrics, ['name']);

		return $metrics;
	}

	protected static function applyOverrides(array &$metrics = [], array $overrides = []) {
		foreach ($overrides as $override) {
			// Convert timeshift to seconds.
			if (array_key_exists('timeshift', $override)) {
				$override['timeshift'] = ($override['timeshift'] !== '')
					? (int) timeUnitToSeconds($override['timeshift'], true)
					: 0;
			}

			// TODO miks: still not clear how valid override looks like. Fix this if needed.
			if ((!array_key_exists('hosts', $override) || $override['hosts'] === '')
					|| (!array_key_exists('items', $override) || $override['items'] === '')) {
				continue;
			}

			$hosts_patterns = self::processPattern($override['hosts']);
			$items_patterns = self::processPattern($override['items']);

			unset($override['hosts'], $override['items']);

			$metrics_matched = [];
			foreach ($metrics as $metric_num => $metric) {
				// If '*' used, apply options to all metrics.
				$host_matches = ($hosts_patterns === null);
				$item_matches = ($items_patterns === null);

				/**
				 * Find if host and item names matches one of given patterns.
				 *
				 * It currently checks if at least one of host pattern and at least one of item pattern matches,
				 * without checking relation between matching host and item.
				 */
				$host_pattern_num = 0;
				while (!$host_matches && array_key_exists($host_pattern_num, $hosts_patterns)) {
					$re = '/^'.str_replace('\*', '.*', preg_quote($hosts_patterns[$host_pattern_num], '/')).'$/i';
					$host_matches = (strpos($hosts_patterns[$host_pattern_num], '*') === false)
						? ($metric['hosts'][0]['name'] === $hosts_patterns[$host_pattern_num])
						: preg_match($re, $metric['hosts'][0]['name']);

					$host_pattern_num++;
				}

				$item_pattern_num = 0;
				while (!$item_matches && array_key_exists($item_pattern_num, $items_patterns)) {
					$re = '/^'.str_replace('\*', '.*', preg_quote($items_patterns[$item_pattern_num], '/')).'$/i';
					$item_matches = (strpos($items_patterns[$item_pattern_num], '*') === false)
						? ($metric['name'] === $items_patterns[$item_pattern_num])
						: preg_match($re, $metric['name']);

					$item_pattern_num++;
				}

				/**
				 * We need to know total amount of matched metrics to calculate variations of colors. That's why we
				 * first collect matching metrics and than override existing metric options.
				 */
				if ($host_matches && $item_matches) {
					$metrics_matched[] = $metric_num;
				}
			}

			// Apply override options to matching metrics.
			if ($metrics_matched) {
				if (array_key_exists('color', $override) && $override['color'] !== '') {
					$override['color'] = (substr($override['color'], 0, 1) === '#') ? $override['color'] : '#'.$override['color'];

					$colors = (count($metrics_matched) > 1)
						? getColorVariations($override['color'], count($metrics_matched))
						: [$override['color']];
				}
				else {
					$colors = null;
				}

				foreach ($metrics_matched as $i => $metric_num) {
					$metric = &$metrics[$metric_num];
					$metric['options'] = $override + $metric['options'] + ($colors ? ['color' => $colors[$i]] : []);
				}
				unset($metric);
			}
		}
	}

	protected static function getTimePeriods(array &$metrics = [], array $options) {
		foreach ($metrics as &$metric) {
			$metric['time_period'] = $options;

			if ($metric['options']['timeshift'] != 0) {
				$metric['time_period']['time_from'] = bcadd($metric['time_period']['time_from'], $metric['options']['timeshift'], 0);
				$metric['time_period']['time_to'] = bcadd($metric['time_period']['time_to'], $metric['options']['timeshift'], 0);
			}
		}
		unset($metric);
	}

	/**
	 * Make array of patterns from given comma separated patterns string.
	 *
	 * @param string   $patterns		String containing comma separated patterns.
	 *
	 * @return array   Returns array of patterns or NULL if '*' used, thus all database records are valid.
	 */
	protected static function processPattern($patterns) {
		$patterns = explode(',', $patterns);
		$patterns = array_keys(array_flip($patterns));

		foreach ($patterns as &$pattern) {
			$pattern = trim($pattern);
			if ($pattern === '*') {
				$patterns = null;
				break;
			}
		}
		unset($pattern);

		return $patterns;
	}

	protected static function sortByClock($a, $b) {
		$a = $a['clock'];
		$b = $b['clock'];

		if ($a == $b) {
			return 0;
		}

		return ($a < $b) ? -1 : 1;
	}
}
