<?php

session_start();

// --- ZUGRIFFSSCHUTZ ---
$password = "MMBbS2026"; 
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['login_pass']) && $_POST['login_pass'] === $password) { $_SESSION['logged_in'] = true; } 
    else { die('<div style="text-align:center; margin-top:100px; font-family:sans-serif;"><h2>🔑 Admin Login</h2><form method="POST"><input type="password" name="login_pass" style="padding:10px;"><br><br><button type="submit" style="padding:10px 20px; background:#34495e; color:white; border:none; border-radius:4px; cursor:pointer;">Anmelden</button></form></div>'); }
}

$jsonFile = 'wizard_data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : ['classes' => [], 'templates' => []];
function saveData($d, $f) { file_put_contents($f, json_encode($d, JSON_PRETTY_PRINT)); }

$activeId = $_GET['edit_id'] ?? null;
$activeType = $_GET['type'] ?? 'classes';

// --- EINZEL-BACKUP DOWNLOAD (EXPORT) ---
if (isset($_GET['download_single']) && $activeId) {
    if (isset($data[$activeType][$activeId])) {
        $export = $data[$activeType][$activeId];
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="Backup_'.$activeType.'_'.$activeId.'_'.date('Y-m-d').'.json"');
        echo json_encode($export, JSON_PRETTY_PRINT);
        exit;
    }
}

// --- BACKUP EINLESEN (IMPORT) ---
if (isset($_POST['upload_backup'])) {
    if ($_FILES['backup_file']['error'] == 0) {
        $uploadedData = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);
        if ($uploadedData) {
            // Namen bestimmen: Entweder Input-Feld oder aus Datei
            $rawName = !empty($_POST['new_name']) ? trim($_POST['new_name']) : ($uploadedData['name'] ?? 'Import');
            $newId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawName));
            
            // Daten aktualisieren (Name in den Daten überschreiben falls geändert)
            $uploadedData['name'] = $rawName;
            
            $targetType = $_POST['upload_type'] ?? 'classes';
            $data[$targetType][$newId] = $uploadedData;
            saveData($data, $jsonFile);
            header("Location: admin.php?edit_id=$newId&type=$targetType"); exit;
        }
    }
}

// --- AKTIONEN ---
// --- AKTIONEN ---
if (isset($_GET['delete_entity'])) {
    unset($data[$_GET['del_type']][$_GET['delete_entity']]);
    saveData($data, $jsonFile);
    header("Location: admin.php"); exit;
}

if (isset($_POST['add_entity'])) {
    $rawName = trim($_POST['entity_name']);
    $id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawName));
    $type = isset($_POST['is_template']) ? 'templates' : 'classes';
    if ($id) { $data[$type][$id] = ['name' => $rawName, 'subjects' => []]; saveData($data, $jsonFile); }
    header("Location: admin.php?edit_id=$id&type=$type"); exit;
}

// DIESE FUNKTION FEHLTE:
if (isset($_POST['apply_template'])) {
    $tplId = $_POST['template_src_id'];
    if (isset($data['templates'][$tplId])) {
        $data[$activeType][$activeId]['subjects'] = $data['templates'][$tplId]['subjects'];
        saveData($data, $jsonFile);
    }
    header("Location: admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_POST['save_subject'])) {
    $oldKey = $_POST['old_sub_key'] ?? '';
    $newKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['sub_key']));
    
    $existingPlanning = [];
    // Prüfen ob altes Fach existiert um Planung zu retten
    if (!empty($oldKey) && isset($data[$activeType][$activeId]['subjects'][$oldKey])) {
        $existingPlanning = $data[$activeType][$activeId]['subjects'][$oldKey]['planning'] ?? [];
        if ($oldKey !== $newKey) {
            unset($data[$activeType][$activeId]['subjects'][$oldKey]);
        }
    } 
    // Falls kein oldKey da ist, aber das newKey schon existiert (normales Update ohne Key-Änderung)
    elseif (isset($data[$activeType][$activeId]['subjects'][$newKey])) {
        $existingPlanning = $data[$activeType][$activeId]['subjects'][$newKey]['planning'] ?? [];
    }

    $data[$activeType][$activeId]['subjects'][$newKey] = [
        'name' => $_POST['sub_name'],
        'total_hours' => (int)$_POST['sub_hours'],
        'category' => $_POST['sub_cat'],
        'planning' => $existingPlanning
    ];
    
    saveData($data, $jsonFile);
    header("Location: admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_GET['del_subject'])) {
    unset($data[$activeType][$activeId]['subjects'][$_GET['del_subject']]);
    saveData($data, $jsonFile);
    header("Location: admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_POST['save_ls'])) {
    $sub = $_POST['subject'];
    $lsData = [
        'ls_nr' => $_POST['ls_nr'], 'title' => $_POST['title'],
        'hours' => (int)$_POST['hours'], 'start' => (int)$_POST['start'],
        'end' => (int)$_POST['end'], 'color' => $_POST['color']
    ];
    if (isset($_POST['ls_index']) && $_POST['ls_index'] !== "") {
        $data[$activeType][$activeId]['subjects'][$sub]['planning'][$_POST['ls_index']] = $lsData;
    } else {
        $data[$activeType][$activeId]['subjects'][$sub]['planning'][] = $lsData;
    }
    saveData($data, $jsonFile);
    header("Location: admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_GET['del_ls'])) {
    array_splice($data[$activeType][$activeId]['subjects'][$_GET['sub']]['planning'], $_GET['del_ls'], 1);
    saveData($data, $jsonFile);
    header("Location: admin.php?edit_id=$activeId&type=$activeType"); exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>MMBbS Admin - Dashboard</title>
    <style>
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; display: flex; margin: 0; min-height: 100vh; color: #374151; }
        .sidebar { width: 300px; background: #fff; border-right: 1px solid #d1d5db; padding: 24px; display: flex; flex-direction: column; box-sizing: border-box; height: 100vh; position: sticky; top: 0; }
        .main { flex: 1; padding: 40px; overflow-y: auto; }
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; border: 1px solid #e5e7eb; }
        h3 { margin: 0 0 20px 0; font-size: 0.85rem; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 15px; margin-bottom: 15px; align-items: end; }
        .col-wide { grid-column: span 2; }
        label { display: block; font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #f9fafb; }
        .btn { display: block; width: 100%; padding: 10px 16px; border-radius: 6px; font-weight: 600; cursor: pointer; border: none; font-size: 13px; text-align: center; box-sizing: border-box; transition: all 0.2s; text-decoration: none; margin-bottom: 8px; }
        .btn-primary { background: #1f2937; color: white; }
        .btn-blue { background: #3b82f6; color: white; }
        .btn-green { background: #10b981; color: white; }
        .btn-danger { background: #fff; color: #ef4444; border: 1px solid #fee2e2; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .ls-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .ls-table th { text-align: left; padding: 12px; background: #f9fafb; font-size: 12px; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
        .ls-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .sidebar-bottom { margin-top: auto; padding-top: 20px; border-top: 1px solid #f3f4f6; }
        .badge { background:#f3f4f6; border:1px solid #e5e7eb; padding:4px 10px; border-radius:15px; font-size:12px; display:inline-flex; align-items:center; gap:8px; margin: 2px; }
		
    </style>
</head>
<body>

<div class="sidebar">
    <img src="logo.png" style="margin: 0 0 32px 0; max-width: 100%;">
    <h2 style="font-size: 1.2rem; color: #111827; margin: 0 0 32px 0;">Planungs-Wizard</h2>
    
    <div style="margin-bottom: 30px;">
        <h3 style="color: #14508c">Neu anlegen</h3>
        <form method="POST">
            <input type="text" name="entity_name" placeholder="Name (z.B. FISI24A)" required>
            <label style="display:flex; align-items:center; gap:8px; margin: 10px 0; font-size:13px; cursor:pointer;">
                <input type="checkbox" name="is_template" style="width:auto"> Als Vorlage speichern
            </label>
            <button type="submit" name="add_entity" class="btn btn-primary">Erstellen</button>
        </form>
    </div>

    <div style="margin-bottom: 30px;">
        <h3 style="color: #14508c">Auswahl</h3>
        <div style="display: flex; gap: 5px;">
            <select onchange="location.href=this.value" style="background: #f0f7ff; border-color: #bfdbfe;">
                <option value="">-- wählen --</option>
                <optgroup label="Klassen">
                    <?php foreach($data['classes'] as $id => $c) echo "<option value='?edit_id=$id&type=classes' ".($activeId==$id && $activeType=='classes'?'selected':'').">".htmlspecialchars($c['name'])."</option>"; ?>
                </optgroup>
                <optgroup label="Vorlagen">
                    <?php foreach($data['templates'] as $id => $t) echo "<option value='?edit_id=$id&type=templates' ".($activeId==$id && $activeType=='templates'?'selected':'').">📂 ".htmlspecialchars($t['name'])."</option>"; ?>
                </optgroup>
            </select>
            <?php if($activeId): ?>
                <a href="?delete_entity=<?= $activeId ?>&del_type=<?= $activeType ?>" class="btn btn-danger" style="width:42px; padding:10px 0; margin-bottom:0" onclick="return confirm('Wirklich alles löschen?')">🗑️</a>
            <?php endif; ?>
        </div>
    </div>

    <div style="margin-bottom: 30px;">
        <h3 style="color: #14508c">Backup & Import</h3>
        <form method="POST" enctype="multipart/form-data" style="margin-bottom: 15px; border-bottom: 1px solid #f3f4f6; padding-bottom: 15px;">
            <label>Datei auswählen:</label>
            <input type="file" name="backup_file" required style="font-size:11px; margin-bottom:8px;">
            
            <label>Neuer Name (optional):</label>
            <input type="text" name="new_name" placeholder="Leer lassen = Original" style="font-size:12px; margin-bottom:8px;">
            
            <select name="upload_type" style="padding:5px; font-size:12px; margin-bottom:8px;">
                <option value="classes">Als Klasse importieren</option>
                <option value="templates">Als Vorlage importieren</option>
            </select>
            <button type="submit" name="upload_backup" class="btn btn-secondary">📥 Import</button>
        </form>

        <?php if($activeId): ?>
            <label>Aktuelle Auswahl sichern:</label>
            <a href="?download_single=1&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" class="btn btn-secondary" style="background:#4b5563">📤 Einzel-Backup (.json)</a>
        <?php endif; ?>
    </div>

    <div class="sidebar-bottom">
        <a href="index.php" class="btn btn-blue">➔ Zum Viewer</a>
        <a href="?logout=1" class="btn btn-danger">Abmelden</a>
    </div>
</div>

<div class="main">
    <?php if($activeId && isset($data[$activeType][$activeId])): $current = $data[$activeType][$activeId]; ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h1 style="margin:0; font-size:1.8rem;"><?= ($activeType=='templates'?'Vorlage: ':'Klasse: ') . htmlspecialchars($current['name']) ?></h1>
            
            <?php if($activeType == 'classes'): ?>
            <form method="POST" style="display:flex; gap:8px; align-items:center; background:#fff; padding:10px; border-radius:8px; border:1px solid #d1d5db;">
                <label style="margin:0; white-space:nowrap">Aus Vorlage laden:</label>
                <select name="template_src_id" style="width:150px; margin:0">
                    <?php foreach($data['templates'] as $tid => $t) echo "<option value='$tid'>{$t['name']}</option>"; ?>
                </select>
                <button type="submit" name="apply_template" class="btn btn-warning" style="margin:0; width:auto;" onclick="return confirm('Fächer werden überschrieben!')">Anwenden</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
    <h3>Fächer & Lernfelder bearbeiten</h3>
    <form method="POST" id="sub_form">
        <input type="hidden" name="old_sub_key" id="old_sub_key" value="">
        
        <div class="form-grid">
            <div style="max-width: 100px;">
                <label>Kürzel</label>
                <input type="text" name="sub_key" id="sub_key" placeholder="LF1" required>
            </div>
            <div class="col-wide">
                <label>Name des Fachs</label>
                <input type="text" name="sub_name" id="sub_name" required>
            </div>
            <div style="max-width: 80px;">
                <label>Soll-h</label>
                <input type="number" name="sub_hours" id="sub_hours" required>
            </div>
            <div>
                <label>Bereich</label>
                <select name="sub_cat" id="sub_cat">
                    <option value="bezogen">Berufsbezogen</option>
                    <option value="uebergreifend">Übergreifend</option>
                </select>
            </div>
            <div style="display:flex; gap:8px">
                <button type="submit" name="save_subject" class="btn btn-green" style="margin-bottom:0">Speichern</button>
                <button type="button" onclick="resetSubForm()" class="btn btn-danger" style="margin-bottom:0">Reset</button>
            </div>
        </div>
    </form>

    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-top:20px; border-top: 1px solid #f3f4f6; padding-top: 15px;">
        <?php foreach($current['subjects'] as $sk => $s): ?>
            <div class="badge" style="padding: 8px 12px; border-radius: 8px;">
                <span style="cursor:pointer; flex:1" onclick='editSub("<?= $sk ?>", <?= json_encode($s) ?>)'>
                    <strong><?= htmlspecialchars($sk) ?></strong>: <?= htmlspecialchars($s['name']) ?> (<?= $s['total_hours'] ?>h)
                </span>
                <a href="?del_subject=<?= $sk ?>&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" 
                   style="color:#ef4444; text-decoration:none; margin-left:10px; font-weight:bold" 
                   onclick="return confirm('Fach wirklich löschen?')">×</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>

        <div class="card">
            <h3>Lernsituation bearbeiten</h3>
            <form method="POST" id="ls_form">
                <input type="hidden" name="ls_index" id="ls_index" value="">
                <div class="form-grid">
                    <div><label>Fach</label><select name="subject" id="ls_sub"><?php foreach($current['subjects'] as $k => $s) echo "<option value='$k'>$k - {$s['name']}</option>"; ?></select></div>
                    <div class="col-wide"><label>Titel</label><input type="text" name="title" id="ls_title" required></div>
                    <div><label>LS-Nr.</label><input type="text" name="ls_nr" id="ls_nr" placeholder="1.1"></div>
                </div>
                <div class="form-grid">
					<div><label>Stunden</label><input type="number" name="hours" id="ls_hours" required></div>
					<div><label>Start (W)</label><input type="number" name="start" id="ls_start" min="1" max="13"></div>
					<div><label>Ende (W)</label><input type="number" name="end" id="ls_end" min="1" max="13"></div>
					
					<div style="min-width: 140px;">
						<label>Farbe (HEX)</label>
						<div style="display: flex; gap: 4px;">
							<input type="color" id="ls_color_picker" value="#d1e7dd" 
								   style="width: 45px; height: 41px; padding: 2px; cursor: pointer;" 
								   oninput="document.getElementById('ls_color').value = this.value">
							
							<input type="text" name="color" id="ls_color" value="#d1e7dd" 
								   placeholder="#000000" 
								   style="font-family: monospace; width: 90px; flex: none;" 
								   oninput="document.getElementById('ls_color_picker').value = this.value">
						</div>
					</div>

					<div style="display:flex; gap:8px">
						<button type="submit" name="save_ls" class="btn btn-blue" style="margin-bottom:0">Speichern</button>
						<button type="button" onclick="resetLSForm()" class="btn btn-danger" style="margin-bottom:0">Reset</button>
					</div>
				</div>
            </form>

            <?php
				// 1. Die Fächer (Subjects) sortieren
				// Nach deiner Logik: LF-Nummern zuerst, dann alphabetisch
				uksort($current['subjects'], function($a, $b) {
					// Falls beide mit LF beginnen, numerisch sortieren
					if (str_starts_with($a, 'LF') && str_starts_with($b, 'LF')) {
						return (int)substr($a, 2) <=> (int)substr($b, 2);
					}
					// Falls nur einer LF ist, kommt dieser zuerst
					if (str_starts_with($a, 'LF')) return -1;
					if (str_starts_with($b, 'LF')) return 1;
					
					// Ansonsten alphabetisch (z.B. GP, Deutsch, etc.)
					return strnatcasecmp($a, $b);
				});

				// 2. Innerhalb jedes Fachs die Lernsituationen nach 'ls_nr' sortieren
				foreach ($current['subjects'] as $sk => &$sub) {
					if (isset($sub['planning']) && is_array($sub['planning'])) {
						usort($sub['planning'], function($a, $b) {
							// Natürliche Sortierung (behandelt 1.1, 1.2, 1.10 korrekt)
							return strnatcmp($a['ls_nr'], $b['ls_nr']);
						});
					}
				}
				unset($sub); // Referenz löschen
				?>

				<table class="ls-table">
					<thead>
						<tr>
							<th>Fach</th>
							<th>Nr.</th>
							<th>Thema / Titel</th>
							<th>Dauer</th>
							<th>Wochen</th>
							<th>Aktion</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach($current['subjects'] as $sk => $sub): 
							foreach(($sub['planning']??[]) as $idx => $ls): ?>
							<tr>
								<td style="color:#6b7280; font-weight:bold;"><?= htmlspecialchars($sk) ?></td>
								<td><?= htmlspecialchars($ls['ls_nr']) ?></td>
								<td><?= htmlspecialchars($ls['title']) ?></td>
								<td><?= $ls['hours'] ?>h</td>
								<td>KW <?= $ls['start'] ?> - <?= $ls['end'] ?></td>
								<td style="display:flex; gap:5px">
									<button class="btn btn-blue" style="width:auto; padding:5px 10px; margin:0" onclick='editLS(<?= json_encode($ls) ?>, "<?= $sk ?>", <?= $idx ?>)'>✎</button>
									<a href="?del_ls=<?= $idx ?>&sub=<?= $sk ?>&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" class="btn btn-danger" style="width:auto; padding:5px 10px; margin:0" onclick="return confirm('LS löschen?')">×</a>
								</td>
							</tr>
						<?php endforeach; endforeach; ?>
					</tbody>
				</table>
        </div>

        <script>
        function editLS(ls, subKey, index) {
            document.getElementById('ls_index').value = index;
            document.getElementById('ls_sub').value = subKey;
            document.getElementById('ls_title').value = ls.title;
            document.getElementById('ls_nr').value = ls.ls_nr;
            document.getElementById('ls_hours').value = ls.hours;
            document.getElementById('ls_start').value = ls.start;
            document.getElementById('ls_end').value = ls.end;
            document.getElementById('ls_color').value = ls.color;
            window.scrollTo({top: 150, behavior: 'smooth'});
        }
        function resetLSForm() {
            document.getElementById('ls_index').value = "";
            document.getElementById('ls_form').reset();
        }
			function editSub(key, data) {
    document.getElementById('old_sub_key').value = key;
    document.getElementById('sub_key').value = key;
    document.getElementById('sub_name').value = data.name;
    document.getElementById('sub_hours').value = data.total_hours;
    document.getElementById('sub_cat').value = data.category;
    
    // Optisches Feedback: Zum Formular scrollen
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function resetSubForm() {
    document.getElementById('old_sub_key').value = "";
    document.getElementById('sub_form').reset();
}
        </script>

    <?php else: ?>
        <div style="text-align:center; margin-top:100px; color:#9ba3af">
            <div style="font-size: 4rem; margin-bottom:20px;">🗓️</div>
            <h2>Willkommen im Admin-Panel</h2>
            <p>Wählen Sie links eine Klasse aus oder nutzen Sie den Import.</p>
        </div>
    <?php endif; ?>
	

	
</div>
</body>
</html>