<?php
/*
	FusionPBX
	Version: MPL 1.1

	The contents of this file are subject to the Mozilla Public License Version
	1.1 (the "License"); you may not use this file except in compliance with
	the License. You may obtain a copy of the License at
	http://www.mozilla.org/MPL/

	Software distributed under the License is distributed on an "AS IS" basis,
	WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
	for the specific language governing rights and limitations under the
	License.
*/

//run only on the first domain processed (global permission, not per-domain)
if ($domains_processed == 1) {

	//permission definitions: name → groups that should have it
	$permission_defaults = [
		'call_flow_diagram_view' => ['superadmin', 'admin', 'user'],
	];

	foreach ($permission_defaults as $permission_name => $groups) {
		foreach ($groups as $group_name) {
			//check if permission already exists for this group
			$sql  = "select count(*) from v_group_permissions ";
			$sql .= "where permission_name = :permission_name ";
			$sql .= "and group_name = :group_name ";
			$sql .= "and (domain_uuid is null) ";
			$count = $database->select($sql, [
				'permission_name' => $permission_name,
				'group_name'      => $group_name,
			], 'column');

			if (empty($count)) {
				//get group uuid
				$sql_g = "select group_uuid from v_groups where group_name = :group_name and (domain_uuid is null) limit 1";
				$group_uuid = $database->select($sql_g, ['group_name' => $group_name], 'column');

				if (!empty($group_uuid)) {
					$x = 0;
					$array['group_permissions'][$x]['group_permission_uuid'] = uuid();
					$array['group_permissions'][$x]['domain_uuid']           = null;
					$array['group_permissions'][$x]['group_uuid']            = $group_uuid;
					$array['group_permissions'][$x]['group_name']            = $group_name;
					$array['group_permissions'][$x]['permission_name']       = $permission_name;
					$array['group_permissions'][$x]['permission_protected']  = 'false';
					$array['group_permissions'][$x]['permission_assigned']   = 'true';

					//grant temporary permissions to save
					$p = permissions::new();
					$p->add('group_permission_add', 'temp');
					$p->add('group_permission_edit', 'temp');

					$database->save($array, false);
					unset($array);

					$p->delete('group_permission_add', 'temp');
					$p->delete('group_permission_edit', 'temp');
				}
			}
		}
	}
	unset($permission_defaults, $permission_name, $groups, $group_name, $count, $group_uuid, $sql, $sql_g, $p);
}

?>
