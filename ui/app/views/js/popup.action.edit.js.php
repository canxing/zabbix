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
?>

window.action_edit_popup = new class {

	init(data) {
		this.overlay = overlays_stack.getById('action-edit');
		this.dialogue = this.overlay.$dialogue[0];
		this.form = this.overlay.$dialogue.$body[0].querySelector('form');
		this.condition_operators = data.condition_operators;
		this.row_num = 0;

	//	this.footer = this.overlay.$dialogue.$footer[0];
	//	this.curl = new Curl('zabbix.php');
	//	this.curl.setArgument('action', 'action.list');
	//	this.curl.setArgument('eventsource', 0);

		document.getElementById('action-form').style.display = '';

		document.querySelector('.js-condition-create').addEventListener('click', () => {
			const overlay = this.openConditionPopup();
			const dialogue = overlay.$dialogue;

			dialogue.addEventListener('dialogue.submit', (e) => {
				clearMessages();

				addMessage(makeMessageBox('good', [], e.detail.title, true, false));
			});
		});

	/*	if (data.conditions){
			data.conditions.forEach(
			)*/
	//	}

		this.dialogue.addEventListener('dialogue.submit', (e) => {

			// todo: add row as one element, not 3 different ones.
			// todo: add multiselect title, not value

			this.row = document.createElement('tr');
			this.row.append(this.createLabelCell());
			this.row.append(this.createNameCell(e.detail.inputs));
			this.row.append(this.createRemoveCell());

			$('#conditions-table tr:last').before(this.row);

			processTypeOfCalculation();

		});
	}

	openConditionPopup() {
		const parameters = {
			type: <?= ZBX_POPUP_CONDITION_TYPE_ACTION ?>,
			source: 0
		};

		return PopUp('popup.condition.actions', parameters, {
			dialogueid: 'condition',
			dialogue_class: 'modal-popup-medium'
		});

	}


	// createHiddenInput(conditiontype, operator, value, value2) { // ????
	// todo: add hidden input to action edit form
	// }

	createLabelCell() {
		const cell = document.createElement('td');

		cell.append(num2letter(this.row_num));
		this.row_num ++;
		return cell;
	}

	createNameCell(input) {
		const cell = document.createElement('tr');
		const condition_cell = document.createElement('td');
		const value_cell = document.createElement('em');

		condition_cell.textContent = (
			this.condition_operators['condition_types'][input.conditiontype] + " " +
			this.condition_operators['condition_operators'][input.operator] + " "
		);
		value_cell.textContent = input.value;

		cell.append(condition_cell);
		cell.append(value_cell);

		return cell;
	}

	createRemoveCell() {
		const cell = document.createElement('td');
		const btn = document.createElement('button');
		btn.type = 'button';
		btn.classList.add('btn-link', 'element-table-remove');
		btn.textContent = <?= json_encode(_('Remove')) ?>;
		btn.addEventListener('click', () => btn.closest('tr').remove());

		cell.appendChild(btn);
		this.processTypeOfCalculation();
		return cell;
	}


	open(target, actionid, eventsource, recovery_phase, operation) {
		this.trigger_element = target;
		this.eventsource = eventsource;
		this.recovery_phase = recovery_phase;
		this.actionid = actionid;

		this.overlay = overlayDialogue({
			class: 'modal-popup modal-popup-medium',
			title: ('Operation details'),
			content: this.eventsource
		});

		const props = {
			recovery_phase,
			cmd: operation_details.OPERATION_TYPE_MESSAGE,
			scriptid: null
		};

		this.view = new OperationView(props);
		this.view.onupdate = () => this.overlay.centerDialog();
	}

	OperationView(props) {
		this.props = props;
		this.$obj = $($('#operation-popup-tmpl').html());
		this.$wrapper = this.$obj.find('>ul');
		this.operation_type = new OperationViewType(this.$obj.find('>ul>li[id^="operation-type"]'));
		this.$current_focus = this.operation_type.$select;

		this.operation_type.onchange = ({cmd, scriptid}) => {
			this.props.cmd = cmd;
			this.props.scriptid = scriptid;
			this.render();
			this.onupdate();
			this.operation_type.$select.focus();
		};

		this.operation_steps = new OperationViewSteps(this.$obj.find('>ul>li[id^="operation-step"]'));
		this.operation_message = new OperationViewMessage(this.$obj.find('>ul>li[id^="operation-message"]'));
		this.operation_command = new OperationViewCommand(this.$obj.find('>ul>li[id^="operation-command"]'));
		this.operation_attr = new OperationViewAttr(this.$obj.find('>ul>li[id^="operation-attr"]'));
		this.operation_condition = new OperationViewCondition(this.$obj.find('>ul>li[id^="operation-condition"]'));
	}

	submit() {
		const fields = getFormFields(this.form);

		const curl = new Curl('zabbix.php', false);
		curl.setArgument('action', 'action.create');

		this._post(curl.getUrl(), fields, (response) => {
			overlayDialogueDestroy(this.overlay.dialogueid);

			this.dialogue.dispatchEvent(new CustomEvent('dialogue.submit', {detail: response.success}));
		});
	}

	_post(url, data, success_callback) {

		fetch(url, {
			method: 'POST',
			headers: {'Content-Type': 'application/json'},
			body: JSON.stringify(data)
		})
			.then((response) => response.json())
			.then((response) => {
				if ('error' in response) {
					throw {error: response.error};
				}

				return response;
			})
			.then(success_callback)
			.catch((exception) => {
				for (const element of this.form.parentNode.children) {
					if (element.matches('.msg-good, .msg-bad, .msg-warning')) {
						element.parentNode.removeChild(element);
					}
				}

				let title, messages;

				if (typeof exception === 'object' && 'error' in exception) {
					title = exception.error.title;
					messages = exception.error.messages;
				}
				else {
					messages = [<?= json_encode(_('Unexpected server error.')) ?>];
				}

				const message_box = makeMessageBox('bad', messages, title)[0];

				this.form.parentNode.insertBefore(message_box, this.form);
			})
			.finally(() => {
				this.overlay.unsetLoading();
			});
	}

	processTypeOfCalculation() {

		var show_formula = (jQuery('#evaltype').val() == <?= CONDITION_EVAL_TYPE_EXPRESSION ?>),
			$labels = jQuery('#conditions-table .label');

		console.log($labels.length);
		jQuery('#evaltype').closest('li').toggle($labels.length > 1);
		jQuery('#conditionLabel').toggle(!show_formula);
		jQuery('#formula').toggle(show_formula);

		if ($labels.length > 1) {
			var conditions = [];

			$labels.each(function(index, label) {
				$label = jQuery(label);

				conditions.push({
					id: $label.data('formulaid'),
					type: $label.data('conditiontype')
				});
			});

			jQuery('#conditionLabel').html(getConditionFormula(conditions, +jQuery('#evaltype').val()));
		}
	}

}
