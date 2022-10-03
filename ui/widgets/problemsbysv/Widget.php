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


namespace Widgets\ProblemsBySv;

use Zabbix\Core\CWidget;

class Widget extends CWidget {

	public const SHOW_GROUPS = 0;
	public const SHOW_TOTALS = 1;

	public function hasPadding(array $values, int $view_mode): bool {
		return $view_mode == ZBX_WIDGET_VIEW_MODE_NORMAL
			&& $values['show_type'] != self::SHOW_TOTALS;
	}
}
