<?php
/*
** Zabbix
** Copyright (C) 2001-2020 Zabbix SIA
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


/**
 * @var CView $this
 */

// Visibility box javascript is already added in main page. It should not be added in popup response.
define('CVISIBILITYBOX_JAVASCRIPT_INSERTED', 1);

$output = [
	'header' => $data['title'],
];

$options = $data['options'];
$field_values = $data['field_values'];

$operations_popup_form = (new CForm())
	->cleanItems()
	->setId('lldoperation_form')
	->addVar('no', $options['no'])
	->addItem((new CVar('templated', $options['templated']))->removeId())
	->addVar('action', 'popup.lldoperation')
	->addItem((new CInput('submit', 'submit'))->addStyle('display: none;'));

$operations_popup_form_list = (new CFormList())
	->addRow(
		(new CLabel(_('Object'), 'operationobject'))->setAsteriskMark(), // TODO VM: do I need asterix here?
		(new CComboBox('operationobject', $options['operationobject'], null, [
			OPERATION_OBJECT_ITEM_PROTOTYPE => _('Item prototype'),
			OPERATION_OBJECT_TRIGGER_PROTOTYPE => _('Trigger prototype'),
			OPERATION_OBJECT_GRAPH_PROTOTYPE => _('Graph prototype'),
			OPERATION_OBJECT_HOST_PROTOTYPE => _('Host prototype')
		]))
			->setAriaRequired() // TODO VM: do I need asterix here?
			->setId('operation_object')
	)
	->addRow((new CLabel(_('Condition'), 'operator')), [
		(new CComboBox('operator', $options['operator'], null, [
			CONDITION_OPERATOR_EQUAL  => _('equals'),
			CONDITION_OPERATOR_NOT_EQUAL  => _('does not equal'),
			CONDITION_OPERATOR_LIKE  => _('contains'),
			CONDITION_OPERATOR_NOT_LIKE  => _('does not contain'),
			CONDITION_OPERATOR_REGEXP => _('matches'),
			CONDITION_OPERATOR_NOT_REGEXP => _('does not match')
		]))
			->addStyle('margin-right:5px;'), // TODO VM: do ti by CSS
		(new CTextBox('value', $options['value'], false, DB::getFieldLength('lld_override_operation', 'value')))
			->setWidth(ZBX_TEXTAREA_MACRO_VALUE_WIDTH) // TODO VM: is it correct width?
			->setAttribute('placeholder', _('pattern')),
	])
	->addRow(
		(new CVisibilityBox('visible[opstatus_status]', 'opstatus_status', _('Original')))
			->setLabel(_('Create enabled'))
			->setChecked(array_key_exists('opstatus', $options)),
		// TODO VM: remove, if not decided othervise
//		(new CCheckBox('opstatus[status]', $field_values['opstatus']['status']))
//			->setChecked($field_values['opstatus']['status'] == 0) // TODO VM: use define
		(new CRadioButtonList('opstatus[status]', (int) $field_values['opstatus']['status']))
			->addValue(_('Yes'), 0) // TODO VM: use define
			->addValue(_('No'), 1) // TODO VM: use define
			->setModern(true)
	)
	->addRow(
		(new CVisibilityBox('visible[opdiscover_discover]', 'opdiscover_discover', _('Original')))
			->setLabel(_('Discover'))
			->setChecked(array_key_exists('opdiscover', $options)),
		(new CRadioButtonList('opdiscover[discover]', (int) $field_values['opdiscover']['discover']))
			->addValue(_('Yes'), 0) // TODO VM: use define
			->addValue(_('No'), 1) // TODO VM: use define
			->setModern(true)
	);

$update_interval = (new CTable())
	->setId('update_interval')
	->addRow([_('Delay'),
		(new CDiv((new CTextBox('opperiod[delay]', $field_values['opperiod']['delay']))->setWidth(ZBX_TEXTAREA_SMALL_WIDTH)))
	]);

$custom_intervals = (new CTable())
	->setId('lld_overrides_custom_intervals')
	->setHeader([
		new CColHeader(_('Type')),
		new CColHeader(_('Interval')),
		new CColHeader(_('Period')),
		(new CColHeader(_('Action')))->setWidth(50)
	])
	->addClass(ZBX_STYLE_TABLE_FORMS_SEPARATOR)
	->setAttribute('style', 'min-width: '.ZBX_TEXTAREA_STANDARD_WIDTH.'px;');

foreach ($field_values['opperiod']['delay_flex'] as $i => $delay_flex) {
	$type_input = (new CRadioButtonList('opperiod[delay_flex]['.$i.'][type]', (int) $delay_flex['type']))
		->addValue(_('Flexible'), ITEM_DELAY_FLEXIBLE)
		->addValue(_('Scheduling'), ITEM_DELAY_SCHEDULING)
		->setModern(true);

	if ($delay_flex['type'] == ITEM_DELAY_FLEXIBLE) {
		$delay_input = (new CTextBox('opperiod[delay_flex]['.$i.'][delay]', $delay_flex['delay']))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT);
		$period_input = (new CTextBox('opperiod[delay_flex]['.$i.'][period]', $delay_flex['period']))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL);
		$schedule_input = (new CTextBox('opperiod[delay_flex]['.$i.'][schedule]'))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT)
			->setAttribute('style', 'display: none;');
	}
	else {
		$delay_input = (new CTextBox('opperiod[delay_flex]['.$i.'][delay]'))
			->setAttribute('placeholder', ZBX_ITEM_FLEXIBLE_DELAY_DEFAULT)
			->setAttribute('style', 'display: none;');
		$period_input = (new CTextBox('opperiod[delay_flex]['.$i.'][period]'))
			->setAttribute('placeholder', ZBX_DEFAULT_INTERVAL)
			->setAttribute('style', 'display: none;');
		$schedule_input = (new CTextBox('opperiod[delay_flex]['.$i.'][schedule]', $delay_flex['schedule']))
			->setAttribute('placeholder', ZBX_ITEM_SCHEDULING_DEFAULT);
	}

	$button = (new CButton('opperiod[delay_flex]['.$i.'][remove]', _('Remove')))
		->addClass(ZBX_STYLE_BTN_LINK)
		->addClass('element-table-remove');

	$custom_intervals->addRow([$type_input, [$delay_input, $schedule_input], $period_input, $button], 'form_row');
}

$custom_intervals->addRow([(new CButton('interval_add', _('Add')))
	->addClass(ZBX_STYLE_BTN_LINK)
	->addClass('element-table-add')]);

$update_interval->addRow(
	(new CRow([
		(new CCol(_('Custom intervals')))->setAttribute('style', 'vertical-align: top;'),
		new CCol($custom_intervals)
	]))
);

$operations_popup_form_list
	->addRow(
		(new CVisibilityBox('visible[opperiod_delay]', 'opperiod_delay_div', _('Original')))
			->setLabel(_('Update interval'))
			->setChecked(array_key_exists('opperiod', $options)),
		(new CDiv($update_interval))->setId('opperiod_delay_div')
	)
	->addRow(
		(new CVisibilityBox('visible[ophistory_history]', 'ophistory_history_div', _('Original')))
			->setLabel(_('History storage period'))
			->setChecked(array_key_exists('ophistory', $options)),
		(new CDiv([
			(new CRadioButtonList('ophistory[history_mode]', (int) $field_values['ophistory']['history_mode']))
				->addValue(_('Do not keep history'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('ophistory[history]', $field_values['ophistory']['history']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('ophistory_history_div')
	)
	->addRow(
		(new CVisibilityBox('visible[optrends_trends]', 'optrends_trends_div', _('Original')))
			->setLabel(_('Trend storage period'))
			->setChecked(array_key_exists('optrends', $options)),
		(new CDiv([
			(new CRadioButtonList('optrends[trends_mode]', (int) $field_values['optrends']['trends_mode']))
				->addValue(_('Do not keep trends'), ITEM_STORAGE_OFF)
				->addValue(_('Storage period'), ITEM_STORAGE_CUSTOM)
				->setModern(true),
			(new CDiv())->addClass(ZBX_STYLE_FORM_INPUT_MARGIN),
			(new CTextBox('optrends[trends]', $field_values['optrends']['trends']))
				->setWidth(ZBX_TEXTAREA_TINY_WIDTH)
				->setAriaRequired()
		]))
			->addClass('wrap-multiple-controls')
			->setId('optrends_trends_div')
	);

$output['buttons'] = [
	[
		// TODO VM: is this check working?
		'title' => ($options['no'] > 0) ? _('Update') : _('Add'),
		'class' => '',
		'keepOpen' => true,
		'isSubmit' => true,
		'action' => 'return lldoverrides.operations.edit_form.validate(overlay);'
	]
];

$operations_popup_form->addItem($operations_popup_form_list);

// HTTP test step editing form.
$output['body'] = (new CDiv($operations_popup_form))->toString();
$output['script_inline'] = 'lldoverrides.operations.onOperationOverlayReadyCb('.$options['no'].');';
// Get js generated by CVisibilityBox fields.
$output['script_inline'] .= getPagePostJs(); // TODO VM: could be done nicer

if ($data['user']['debug_mode'] == GROUP_DEBUG_MODE_ENABLED) {
	CProfiler::getInstance()->stop();
	$output['debug'] = CProfiler::getInstance()->make()->toString();
}

echo json_encode($output);
