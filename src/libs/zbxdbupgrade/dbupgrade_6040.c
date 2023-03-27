/*
** Zabbix
** Copyright (C) 2001-2023 Zabbix SIA
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

#include "dbupgrade.h"
#include "zbxdbhigh.h"
#include "log.h"

/*
 * 6.4 maintenance database patches
 */

#ifndef HAVE_SQLITE3

static int	DBpatch_6040000(void)
{
	return SUCCEED;
}

static int	DBpatch_6040999(void)
{
	if (SUCCEED == zbx_db_check_compatibility_colum_type("history", "value", ZBX_TYPE_FLOAT) &&
			SUCCEED == zbx_db_check_compatibility_colum_type("trends", "value_min", ZBX_TYPE_FLOAT) &&
			SUCCEED == zbx_db_check_compatibility_colum_type("trends", "value_avg", ZBX_TYPE_FLOAT) &&
			SUCCEED == zbx_db_check_compatibility_colum_type("trends", "value_max", ZBX_TYPE_FLOAT))
	{
		return SUCCEED;
	}
	else
	{
		zabbix_log(LOG_LEVEL_CRIT, "The old numeric type is no longer supported. Please upgrade to numeric"
				"values of extended range.");
	}

	return FAIL;
}

#endif

DBPATCH_START(6040)

/* version, duplicates flag, mandatory flag */

DBPATCH_ADD(6040000, 0, 1)
DBPATCH_ADD(6040999, 0, 1)

DBPATCH_END()
