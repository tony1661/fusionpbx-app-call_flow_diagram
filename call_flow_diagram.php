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

	The Original Code is FusionPBX
	Contributor(s): FusionPBX Team
*/

//includes files
	require_once dirname(__DIR__, 2) . "/resources/require.php";
	require_once "resources/check_auth.php";

//check permissions — accept registered permission OR admin/superadmin group membership
	if (!permission_exists('call_flow_diagram_view') && !if_group("superadmin") && !if_group("admin") && !if_group("user")) {
		echo "access denied";
		exit;
	}

//add multi-lingual support
	$language = new text;
	$text = $language->get();

//load class
	require_once __DIR__ . "/resources/classes/call_flow_diagram.php";

//initialize the diagram builder
	$diagram = new call_flow_diagram([
		'domain_uuid' => $domain_uuid,
		'domain_name' => $_SESSION['domain_name'] ?? '',
		'database'    => $database,
	]);

//get available starting points
	$starting_points = $diagram->get_starting_points();

//handle AJAX data request
	if (!empty($_GET['ajax']) && !empty($_GET['type']) && !empty($_GET['uuid'])) {
		header('Content-Type: application/json');
		$type = preg_replace('/[^a-z_]/', '', $_GET['type']);
		$uuid = $_GET['uuid'];
		if (!is_uuid($uuid)) {
			echo json_encode(['error' => 'Invalid UUID']);
			exit;
		}
		$data = $diagram->build($type, $uuid);
		echo json_encode($data);
		exit;
	}

//selected type/uuid from GET
	$selected_type = $_GET['type'] ?? '';
	$selected_uuid = $_GET['uuid'] ?? '';

//validate
	if (!empty($selected_type)) {
		$selected_type = preg_replace('/[^a-z_]/', '', $selected_type);
	}
	if (!empty($selected_uuid) && !is_uuid($selected_uuid)) {
		$selected_uuid = '';
	}

//pre-load diagram data if both type and uuid are set
	$diagram_json = 'null';
	if (!empty($selected_type) && !empty($selected_uuid)) {
		$flow_data    = $diagram->build($selected_type, $selected_uuid);
		$diagram_json = json_encode($flow_data);
	}

//page title
	$document['title'] = $text['title-call_flow_diagram'] ?? 'Call Flow Diagram';
	require_once "resources/header.php";
?>

<!-- vis-network from CDN -->
<link  href="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/dist/dist/vis-network.min.css" rel="stylesheet" type="text/css" />
<script src="https://cdn.jsdelivr.net/npm/vis-network@9.1.9/dist/vis-network.min.js"></script>

<style>
	#diagram-container {
		width: 100%;
		height: 600px;
		border: 1px solid var(--container-border-color, #ccc);
		border-radius: 4px;
		background: var(--input-background-color, #fff);
		position: relative;
	}
	#diagram-placeholder {
		display: flex;
		align-items: center;
		justify-content: center;
		height: 100%;
		color: var(--text-muted-color, #888);
		font-size: 14px;
	}
	.legend-grid {
		display: flex;
		flex-wrap: wrap;
		gap: 8px 18px;
		margin: 10px 0 0 0;
	}
	.legend-item {
		display: flex;
		align-items: center;
		gap: 6px;
		font-size: 12px;
	}
	.legend-dot {
		width: 14px;
		height: 14px;
		border-radius: 3px;
		flex-shrink: 0;
		border: 1px solid rgba(0,0,0,0.15);
	}
	.diagram-toolbar {
		display: flex;
		gap: 8px;
		margin-bottom: 8px;
		align-items: center;
	}
	#diagram-loading {
		display: none;
		position: absolute;
		inset: 0;
		background: rgba(255,255,255,0.7);
		align-items: center;
		justify-content: center;
		font-size: 16px;
		color: #555;
		z-index: 10;
	}

</style>

<?php
echo modal::create([
	'id'      => 'modal-png-export',
	'type'    => 'general',
	'title'   => $text['label-png_background'] ?? 'Export background',
	'actions' =>
		button::create(['type'=>'button','label'=>'White',       'icon'=>'square',       'id'=>'btn-png-white',        'collapse'=>'never','onclick'=>"modal_close(); doDownloadPng(true);"]).
		button::create(['type'=>'button','label'=>'Transparent', 'icon'=>'border-all',   'id'=>'btn-png-transparent',  'collapse'=>'never','onclick'=>"modal_close(); doDownloadPng(false);"]),
]);
?>

<div class="action_bar" id="action_bar">
	<div class="heading"><b><?php echo $text['title-call_flow_diagram'] ?? 'Call Flow Diagram'; ?></b></div>
	<div class="actions">
		<?php
		echo button::create(['type'=>'button','label'=>$text['label-fit_view']??'Fit View',       'icon'=>'compress-arrows-alt','id'=>'btn-fit','collapse'=>'hide-xs','style'=>'display: none;','onclick'=>'fitDiagram()']);
		echo button::create(['type'=>'button','label'=>$text['label-download_png']??'Download PNG','icon'=>'download',           'id'=>'btn-png','collapse'=>'hide-xs','style'=>'display: none;','onclick'=>'downloadPng()']);
		?>
	</div>
	<div style="clear:both;"></div>
</div>

<?php echo isset($text['description-call_flow_diagram']) ? '<p>'.$text['description-call_flow_diagram'].'</p><br>' : ''; ?>

<!-- ── Picker form ─────────────────────────────────────────────────── -->
<div class="card" style="margin-bottom: 16px;">
	<form id="picker-form" method="get" action="" style="padding: 12px 16px;">
		<div style="display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end;">

			<div>
				<label class="lbl" for="sel-type" style="display:block; margin-bottom:4px;">
					<?php echo $text['label-starting_type'] ?? 'Starting Type'; ?>
				</label>
				<select id="sel-type" name="type" class="formfld" style="min-width:170px;"
					onchange="populateDestinations(this.value)">
					<option value="">-- select type --</option>
					<?php
					$types = [
						'inbound'        => $text['label-inbound_routes']    ?? 'Inbound Routes',
						'ivr'            => $text['label-ivr_menus']         ?? 'IVR Menus',
						'ring_group'     => $text['label-ring_groups']        ?? 'Ring Groups',
						'call_flow'      => $text['label-call_flows']         ?? 'Call Flows',
						'time_condition' => $text['label-time_conditions']    ?? 'Time Conditions',
						'extension'      => $text['label-extensions']         ?? 'Extensions',
						'contact_center' => $text['label-contact_centers']    ?? 'Contact Centers',
					];
					foreach ($types as $tkey => $tlabel):
						$sel = ($selected_type === $tkey) ? ' selected' : '';
					?>
					<option value="<?php echo escape($tkey); ?>"<?php echo $sel; ?>><?php echo escape($tlabel); ?></option>
					<?php endforeach; ?>
				</select>
			</div>

			<div>
				<label class="lbl" for="sel-uuid" style="display:block; margin-bottom:4px;">
					<?php echo $text['label-starting_destination'] ?? 'Destination'; ?>
				</label>
				<select id="sel-uuid" name="uuid" class="formfld" style="min-width:280px;">
					<option value="">-- select destination --</option>
					<?php
					// Pre-populate if type is already selected
					if (!empty($selected_type) && !empty($starting_points[$selected_type])):
						foreach ($starting_points[$selected_type] as $sp):
							$sel2 = ($selected_uuid === $sp['uuid']) ? ' selected' : '';
					?>
					<option value="<?php echo escape($sp['uuid']); ?>"<?php echo $sel2; ?>><?php echo escape($sp['label']); ?></option>
					<?php
						endforeach;
					endif;
					?>
				</select>
			</div>

			<div>
				<?php echo button::create(['type'=>'submit','label'=>$text['button-generate']??'Generate Diagram','icon'=>'project-diagram']); ?>
			</div>
		</div>
	</form>
</div>

<!-- ── Diagram area ────────────────────────────────────────────────── -->
<div class="card">
	<!-- Legend -->
	<div style="padding: 10px 16px 6px;">
		<div class="legend-grid">
			<?php
			$legend = [
				['type' => 'inbound',        'bg' => '#BBDEFB', 'border' => '#1565C0', 'label' => 'Inbound Route'],
				['type' => 'ivr',            'bg' => '#FFE0B2', 'border' => '#BF360C', 'label' => 'IVR Menu'],
				['type' => 'ring_group',     'bg' => '#C8E6C9', 'border' => '#1B5E20', 'label' => 'Ring Group'],
				['type' => 'extension',      'bg' => '#B2EBF2', 'border' => '#006064', 'label' => 'Extension'],
				['type' => 'call_flow',      'bg' => '#B3E5FC', 'border' => '#01579B', 'label' => 'Call Flow'],
				['type' => 'time_condition', 'bg' => '#FFF9C4', 'border' => '#F57F17', 'label' => 'Time Condition'],
				['type' => 'contact_center', 'bg' => '#DCEDC8', 'border' => '#33691E', 'label' => 'Contact Center'],
				['type' => 'voicemail',      'bg' => '#E1BEE7', 'border' => '#4A148C', 'label' => 'Voicemail'],
				['type' => 'hangup',         'bg' => '#FFCDD2', 'border' => '#B71C1C', 'label' => 'Hangup'],
				['type' => 'external',       'bg' => '#E0E0E0', 'border' => '#424242', 'label' => 'External'],
			];
			foreach ($legend as $leg):
			?>
			<div class="legend-item">
				<div class="legend-dot" style="background:<?php echo $leg['bg']; ?>;border-color:<?php echo $leg['border']; ?>;"></div>
				<span><?php echo escape($leg['label']); ?></span>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div style="padding: 0 16px 8px;">
		<div id="diagram-container">
			<div id="diagram-loading"><i class="fas fa-circle-notch fa-spin"></i>&nbsp; Building diagram…</div>
			<div id="diagram-placeholder"><?php echo $text['message-select_destination'] ?? 'Select a starting destination above and click Generate Diagram.'; ?></div>
		</div>
	</div>
</div>

<script>
// ── Starting points data (for dynamic population of destination select) ──
var startingPoints = <?php echo json_encode($starting_points); ?>;

// ── Node style map ──
var nodeStyles = {
	inbound:        { color: { background: '#BBDEFB', border: '#1565C0' }, font: { color: '#0D47A1' } },
	ivr:            { color: { background: '#FFE0B2', border: '#BF360C' }, font: { color: '#BF360C' } },
	ring_group:     { color: { background: '#C8E6C9', border: '#1B5E20' }, font: { color: '#1B5E20' } },
	extension:      { color: { background: '#B2EBF2', border: '#006064' }, font: { color: '#006064' } },
	call_flow:      { color: { background: '#B3E5FC', border: '#01579B' }, font: { color: '#01579B' } },
	time_condition: { color: { background: '#FFF9C4', border: '#F57F17' }, font: { color: '#E65100' } },
	contact_center: { color: { background: '#DCEDC8', border: '#33691E' }, font: { color: '#1B5E20' } },
	voicemail:      { color: { background: '#E1BEE7', border: '#6A1B9A' }, font: { color: '#4A148C' } },
	hangup:         { color: { background: '#FFCDD2', border: '#B71C1C' }, font: { color: '#B71C1C' } },
	external:       { color: { background: '#F5F5F5', border: '#616161' }, font: { color: '#424242' } },
};

var network = null;

// ── Populate destination dropdown when type changes ──
function populateDestinations(type) {
	var sel = document.getElementById('sel-uuid');
	sel.innerHTML = '<option value="">-- select destination --</option>';
	if (!type || !startingPoints[type]) return;
	startingPoints[type].forEach(function(item) {
		var opt = document.createElement('option');
		opt.value = item.uuid;
		opt.textContent = item.label;
		sel.appendChild(opt);
	});
}

// ── Build diagram from JSON data ──
function renderDiagram(data) {
	var placeholder  = document.getElementById('diagram-placeholder');
	var loadingEl    = document.getElementById('diagram-loading');
	var container    = document.getElementById('diagram-container');
	placeholder.style.display = 'none';
	document.getElementById('btn-fit').style.display = 'none';
	document.getElementById('btn-png').style.display = 'none';

	if (!data || !data.nodes || data.nodes.length === 0) {
		placeholder.textContent = <?php echo json_encode($text['message-no_data'] ?? 'No routing data found.'); ?>;
		placeholder.style.display = 'flex';
		return;
	}

	// Build styled node and edge arrays (kept as plain arrays for reuse in phase 2)
	var styledNodes = data.nodes.map(function(n) {
		var style = nodeStyles[n.type] || nodeStyles['external'];
		var props = Object.assign({}, n, style, {
			shape: 'box',
			margin: { top: 8, bottom: 8, left: 10, right: 10 },
			widthConstraint: { maximum: 160 },
			font: Object.assign({ size: 13, face: 'Arial', multi: false }, style.font),
			borderWidth: 2,
			shadow: { enabled: true, size: 4, x: 2, y: 2, color: 'rgba(0,0,0,0.15)' },
		});
		return props;
	});

	var styledEdges = data.edges.map(function(e) {
		return Object.assign({}, e, {
			arrows: { to: { enabled: true, scaleFactor: 0.6, type: 'arrow' } },
			font:   { size: 11, align: 'middle', color: '#444', strokeWidth: 2, strokeColor: '#fff' },
			color:  { color: '#555', highlight: '#1565C0', opacity: 0.85 },
			width:  1.5,
			smooth: { type: 'cubicBezier', forceDirection: 'vertical', roundness: 0.6 },
		});
	});

	// Phase 1: hierarchical layout using node `level` properties for correct row alignment.
	// Interaction is disabled during this phase — it's purely for computing positions.
	loadingEl.style.display = 'flex';
	if (network) { network.destroy(); network = null; }

	network = new vis.Network(container,
		{ nodes: new vis.DataSet(styledNodes), edges: new vis.DataSet(styledEdges) },
		{
			layout: {
				hierarchical: {
					enabled:              true,
					direction:            'UD',
					sortMethod:           'directed',
					levelSeparation:      140,
					nodeSpacing:          30,
					treeSpacing:          50,
					blockShifting:        true,
					edgeMinimization:     true,
					parentCentralization: true,
				}
			},
			physics: {
				enabled: true,
				solver:  'hierarchicalRepulsion',
				hierarchicalRepulsion: { nodeDistance: 80, avoidOverlap: 1, damping: 0.12 },
				stabilization: { enabled: true, iterations: 300 },
			},
			interaction: { dragNodes: false, zoomView: false, dragView: false },
		}
	);

	network.once('stabilized', function() {
		// Capture positions computed by the hierarchical layout
		var positions = network.getPositions();
		network.destroy();
		network = null;

		// Phase 2: free layout — no hierarchical constraints, so nodes can be dragged
		// in any direction. Positions from phase 1 are baked in as starting coordinates.
		var freeNodes = styledNodes.map(function(n) {
			var pos = positions[n.id] || { x: 0, y: 0 };
			return Object.assign({}, n, { x: pos.x, y: pos.y });
		});

		network = new vis.Network(container,
			{ nodes: new vis.DataSet(freeNodes), edges: new vis.DataSet(styledEdges) },
			{
				layout:    { hierarchical: { enabled: false } },
				physics:   { enabled: false },
				interaction: { dragNodes: true, zoomView: true, dragView: true, tooltipDelay: 100 },
			}
		);

		var nodeMap = {};
		freeNodes.forEach(function(n) { nodeMap[n.id] = n; });

		network.on('doubleClick', function(params) {
			if (params.nodes.length === 0) return;
			var nodeId = params.nodes[0];
			var node   = nodeMap[nodeId];
			var url    = (node && node.edit_url) || nodeEditUrl(nodeId);
			if (url) window.open(url, '_blank');
		});

		loadingEl.style.display = 'none';
		network.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
		document.getElementById('btn-fit').style.display = '';
		document.getElementById('btn-png').style.display = '';
	});
}

// ── Resolve an edit URL from a node ID ──
function nodeEditUrl(nodeId) {
	var uuid = '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}';
	var m;
	if ((m = nodeId.match(new RegExp('^inbound_(' + uuid + ')$', 'i'))))
		return '/app/destinations/destination_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^ivr_(' + uuid + ')$', 'i'))))
		return '/app/ivr_menus/ivr_menu_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^rg_(' + uuid + ')$', 'i'))))
		return '/app/ring_groups/ring_group_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^cf_(' + uuid + ')$', 'i'))))
		return '/app/call_flows/call_flow_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^tc_(' + uuid + ')$', 'i'))))
		return '/app/time_conditions/time_condition_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^ext_(' + uuid + ')$', 'i'))))
		return '/app/extensions/extension_edit.php?id=' + m[1];
	if ((m = nodeId.match(new RegExp('^cc_(' + uuid + ')$', 'i'))))
		return '/app/call_centers/call_center_queue_edit.php?id=' + m[1];
	return null;
}

function fitDiagram() {
	if (network) network.fit({ animation: { duration: 400, easingFunction: 'easeInOutQuad' } });
}

function downloadPng() {
	if (!network) return;
	modal_open('modal-png-export', 'btn-png');
}

function doDownloadPng(withBackground) {
	var src = document.querySelector('#diagram-container canvas');
	if (!src) return;

	var canvas = document.createElement('canvas');
	canvas.width  = src.width;
	canvas.height = src.height;
	var ctx = canvas.getContext('2d');

	if (withBackground) {
		ctx.fillStyle = '#ffffff';
		ctx.fillRect(0, 0, canvas.width, canvas.height);
	}
	ctx.drawImage(src, 0, 0);

	var link = document.createElement('a');
	link.download = 'call_flow_diagram.png';
	link.href = canvas.toDataURL('image/png');
	link.click();
}

<?php if (!empty($diagram_json) && $diagram_json !== 'null'): ?>
// ── Render pre-loaded diagram ──
document.addEventListener('DOMContentLoaded', function() {
	renderDiagram(<?php echo $diagram_json; ?>);
});
<?php endif; ?>

</script>

<?php require_once "resources/footer.php"; ?>
