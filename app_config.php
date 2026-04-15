<?php

	//application details
		$apps[$x]['name'] = "Call Flow Diagram";
		$apps[$x]['uuid'] = "d7c4f2a1-8b3e-4f9d-bc12-5a6e7890abcd";
		$apps[$x]['category'] = "Report";
		$apps[$x]['subcategory'] = "";
		$apps[$x]['version'] = "1.0";
		$apps[$x]['license'] = "Mozilla Public License 1.1";
		$apps[$x]['url'] = "http://www.fusionpbx.com";
		$apps[$x]['description']['en-us'] = "Visual call flow diagram that traces routing paths from a selected inbound destination.";

	//permission details
		$y=0;
		$apps[$x]['permissions'][$y]['name'] = "call_flow_diagram_view";
		$apps[$x]['permissions'][$y]['menu']['uuid'] = "e3f1a2b4-9c5d-4e7f-8012-3456789abcde";
		$apps[$x]['permissions'][$y]['groups'][] = "superadmin";
		$apps[$x]['permissions'][$y]['groups'][] = "admin";
		$apps[$x]['permissions'][$y]['groups'][] = "user";

?>
