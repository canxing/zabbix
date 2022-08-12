<?php
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


/**
 * @var CView $this
 */
?>

<script type="text/x-jquery-tmpl" id="filter-tag-row-tmpl">
	<?= CTagFilterFieldHelper::getTemplate() ?>
</script>

<script>
	const view = {
	//	eventsource: null,

		init({eventsource}) {
			//this.eventsource = eventsource;
			document.addEventListener('click', (e) => {

				if (e.target.classList.contains('js-action-create')) {
					this.openActionPopup({eventsource: eventsource});
				}
				else if (e.target.classList.contains('js-action-edit')) {
					this.openActionPopup({eventsource: eventsource, actionid: e.target.attributes.actionid.nodeValue});

				}
			});
		},

		openActionPopup(parameters = {}) {
			return PopUp('popup.action.edit', parameters, {
				dialogueid: 'action-edit',
				dialogue_class: 'modal-popup-large'
			});
		}
	};
</script>
