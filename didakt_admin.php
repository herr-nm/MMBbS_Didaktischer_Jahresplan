<?php
session_start();

// --- ZUGRIFFSSCHUTZ ---
$password = "MMBbS2026"; 
if (isset($_GET['logout'])) { session_destroy(); header("Location: didakt_admin.php"); exit; }
if (!isset($_SESSION['logged_in'])) {
    if (isset($_POST['login_pass']) && $_POST['login_pass'] === $password) { $_SESSION['logged_in'] = true; } 
    else { die('<div style="text-align:center; margin-top:100px; font-family:sans-serif;"><h2>🔑 Admin Login</h2><form method="POST"><input type="password" name="login_pass" style="padding:10px;"><br><br><button type="submit" style="padding:10px 20px; background:#34495e; color:white; border:none; border-radius:4px; cursor:pointer;">Anmelden</button></form></div>'); }
}

// --- SQLITE VERBINDUNG ---
try {
    $db = new PDO('sqlite:didakt_data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Datenbankfehler. Bitte die Datei didakt_data.db sicherstellen.");
}

$activeId = $_GET['edit_id'] ?? null;
$activeType = $_GET['type'] ?? 'classes';

// --- EINZEL-BACKUP EXPORT (JSON) ---
if (isset($_GET['download_single']) && $activeId) {
    $stmt = $db->prepare("SELECT * FROM entities WHERE id = ?");
    $stmt->execute([$activeId]);
    $entity = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entity) {
        $stmtSub = $db->prepare("SELECT * FROM subjects WHERE entity_id = ?");
        $stmtSub->execute([$activeId]);
        $subs = $stmtSub->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($subs as &$s) {
            $stmtLS = $db->prepare("SELECT * FROM planning WHERE subject_id = ?");
            $stmtLS->execute([$s['id']]);
            $s['planning'] = $lsRows = $stmtLS->fetchAll(PDO::FETCH_ASSOC);
        }
        $entity['subjects_data'] = $subs;
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="Backup_'.$activeType.'_'.$activeId.'_'.date('Y-m-d').'.json"');
        echo json_encode($entity, JSON_PRETTY_PRINT);
        exit;
    }
}

// --- BACKUP IMPORT ---
if (isset($_POST['upload_backup'])) {
    if ($_FILES['backup_file']['error'] == 0) {
        $json = json_decode(file_get_contents($_FILES['backup_file']['tmp_name']), true);
        if ($json) {
            $rawName = !empty($_POST['new_name']) ? trim($_POST['new_name']) : ($json['name'] ?? 'Import');
            $newId = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawName));
            $targetType = $_POST['upload_type'] ?? 'classes';
            
            $db->prepare("INSERT OR REPLACE INTO entities (id, name, type) VALUES (?, ?, ?)")->execute([$newId, $rawName, $targetType]);
            
            if (isset($json['subjects_data'])) {
                foreach($json['subjects_data'] as $s) {
                    $db->prepare("INSERT INTO subjects (entity_id, sub_key, name, total_hours, category) VALUES (?, ?, ?, ?, ?)")
                       ->execute([$newId, $s['sub_key'], $s['name'], $s['total_hours'], $s['category']]);
                    $subId = $db->lastInsertId();
                    if (isset($s['planning'])) {
                        foreach($s['planning'] as $ls) {
                            $db->prepare("INSERT INTO planning (subject_id, ls_nr, title, hours, start, end, color, url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                               ->execute([$subId, $ls['ls_nr'], $ls['title'], $ls['hours'], $ls['start'], $ls['end'], $ls['color'], $ls['url']]);
                        }
                    }
                }
            }
            header("Location: didakt_admin.php?edit_id=$newId&type=$targetType"); exit;
        }
    }
}

// --- AKTIONEN ---
if (isset($_GET['delete_entity'])) {
    $db->prepare("DELETE FROM entities WHERE id = ?")->execute([$_GET['delete_entity']]);
    header("Location: didakt_admin.php"); exit;
}

if (isset($_POST['add_entity'])) {
    $rawName = trim($_POST['entity_name']);
    $id = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $rawName));
    $type = isset($_POST['is_template']) ? 'templates' : 'classes';
    if ($id) $db->prepare("INSERT OR IGNORE INTO entities (id, name, type) VALUES (?, ?, ?)")->execute([$id, $rawName, $type]);
    header("Location: didakt_admin.php?edit_id=$id&type=$type"); exit;
}

if (isset($_POST['apply_template'])) {
    $tplId = $_POST['template_src_id'];
    $db->prepare("DELETE FROM subjects WHERE entity_id = ?")->execute([$activeId]);
    $stmt = $db->prepare("SELECT * FROM subjects WHERE entity_id = ?");
    $stmt->execute([$tplId]);
    while($s = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $db->prepare("INSERT INTO subjects (entity_id, sub_key, name, total_hours, category) VALUES (?, ?, ?, ?, ?)")
           ->execute([$activeId, $s['sub_key'], $s['name'], $s['total_hours'], $s['category']]);
        $newSubId = $db->lastInsertId();
        $stmtLS = $db->prepare("SELECT * FROM planning WHERE subject_id = ?");
        $stmtLS->execute([$s['id']]);
        while($ls = $stmtLS->fetch(PDO::FETCH_ASSOC)) {
            $db->prepare("INSERT INTO planning (subject_id, ls_nr, title, hours, start, end, color, url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
               ->execute([$newSubId, $ls['ls_nr'], $ls['title'], $ls['hours'], $ls['start'], $ls['end'], $ls['color'], $ls['url']]);
        }
    }
    header("Location: didakt_admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_POST['save_subject'])) {
    $oldKey = $_POST['old_sub_key'] ?? '';
    $newKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $_POST['sub_key']));
    if (!empty($oldKey)) {
        $db->prepare("UPDATE subjects SET sub_key = ?, name = ?, total_hours = ?, category = ? WHERE entity_id = ? AND sub_key = ?")
           ->execute([$newKey, $_POST['sub_name'], (int)$_POST['sub_hours'], $_POST['sub_cat'], $activeId, $oldKey]);
    } else {
        $db->prepare("INSERT INTO subjects (entity_id, sub_key, name, total_hours, category) VALUES (?, ?, ?, ?, ?)")
           ->execute([$activeId, $newKey, $_POST['sub_name'], (int)$_POST['sub_hours'], $_POST['sub_cat']]);
    }
    header("Location: didakt_admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_GET['del_subject'])) {
    $db->prepare("DELETE FROM subjects WHERE entity_id = ? AND sub_key = ?")->execute([$activeId, $_GET['del_subject']]);
    header("Location: didakt_admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_POST['save_ls'])) {
    $stmt = $db->prepare("SELECT id FROM subjects WHERE entity_id = ? AND sub_key = ?");
    $stmt->execute([$activeId, $_POST['subject']]);
    $sub_id = $stmt->fetchColumn();
    if (isset($_POST['ls_index']) && $_POST['ls_index'] !== "") {
        $db->prepare("UPDATE planning SET ls_nr = ?, title = ?, hours = ?, start = ?, end = ?, color = ?, url = ? WHERE id = ?")
           ->execute([$_POST['ls_nr'], $_POST['title'], (int)$_POST['hours'], (int)$_POST['start'], (int)$_POST['end'], $_POST['color'], $_POST['url'], $_POST['ls_index']]);
    } else {
        $db->prepare("INSERT INTO planning (subject_id, ls_nr, title, hours, start, end, color, url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
           ->execute([$sub_id, $_POST['ls_nr'], $_POST['title'], (int)$_POST['hours'], (int)$_POST['start'], (int)$_POST['end'], $_POST['color'], $_POST['url']]);
    }
    header("Location: didakt_admin.php?edit_id=$activeId&type=$activeType"); exit;
}

if (isset($_GET['del_ls_sql_id'])) {
    $db->prepare("DELETE FROM planning WHERE id = ?")->execute([$_GET['del_ls_sql_id']]);
    header("Location: didakt_admin.php?edit_id=$activeId&type=$activeType"); exit;
}

// --- DATEN LADEN ---
$dropdownClasses = $db->query("SELECT * FROM entities WHERE type='classes' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$dropdownTemplates = $db->query("SELECT * FROM entities WHERE type='templates' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$current = null;
$currentSubjects = [];
if ($activeId) {
    $stmt = $db->prepare("SELECT * FROM entities WHERE id = ?");
    $stmt->execute([$activeId]);
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    // SORTIERUNG: Erst LF (numerisch), dann Fächer (alphabetisch)
    $stmt = $db->prepare("SELECT * FROM subjects WHERE entity_id = ?");
    $stmt->execute([$activeId]);
    $currentSubjects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    usort($currentSubjects, function($a, $b) {
        $isLF_a = (stripos($a['sub_key'], 'LF') === 0);
        $isLF_b = (stripos($b['sub_key'], 'LF') === 0);
        if ($isLF_a && !$isLF_b) return -1;
        if (!$isLF_a && $isLF_b) return 1;
        if ($isLF_a && $isLF_b) {
            return (int)substr($a['sub_key'], 2) <=> (int)substr($b['sub_key'], 2);
        }
        return strcasecmp($a['sub_key'], $b['sub_key']);
    });
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Admin - Didaktik MMBbS</title>
    <style>
        :root { --primary-blue: #14508c; --sidebar-bg: #ffffff; --main-bg: #f0f2f5; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--main-bg); display: flex; margin: 0; min-height: 100vh; color: #374151; }
        
        .sidebar { width: 320px; background: var(--sidebar-bg); border-right: 1px solid #d1d5db; padding: 24px; position: fixed; height: 100vh; overflow-y: auto; box-sizing: border-box; display: flex; flex-direction: column; }
        .main { margin-left: 320px; flex: 1; padding: 40px; }
        
        .card { background: white; padding: 24px; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 24px; border: 1px solid #e5e7eb; }
        h3 { margin: 0 0 15px 0; font-size: 0.85rem; color: var(--primary-blue); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid #f3f4f6; padding-bottom: 10px; font-weight: 700; }
        
        label { display: block; font-size: 12px; font-weight: 600; color: #4b5563; margin-bottom: 4px; }
        input, select { width: 100%; padding: 10px; border: 1px solid #d1d5db; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #f9fafb; margin-bottom: 10px; }
        
        .btn { display: block; width: 100%; padding: 10px 16px; border-radius: 8px; cursor: pointer; border: none; font-family: inherit; font-size: 14px; font-weight: 600; text-align: center; text-decoration: none; transition: all 0.2s; box-sizing: border-box; flex-shrink: 0; }
        .btn-primary { background: #1f2937; color: white; }
        .btn-blue { background: var(--primary-blue); color: white; }
        .btn-green { background: #10b981; color: white; width: auto; display: inline-block; }
        .btn-danger { background: #fff; color: #a6a6a7; border: 1px solid #fee2e2; }
        .btn-danger:hover { background: #dc2626; color: white; border-color: #dc2626; }
        .btn-secondary { background: #4b5563; color: white; }
        
        .custom-select-container { position: relative; margin-bottom: 15px; }
        .select-trigger { padding: 10px; background: #f0f7ff; border: 1px solid #bfdbfe; border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; font-size: 14px; color: var(--primary-blue); font-weight: 600; }
        .select-dropdown { position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #d1d5db; border-radius: 0 0 8px 8px; display: none; z-index: 1000; max-height: 250px; overflow-y: auto; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .select-dropdown.show { display: block; }
        .search-box { position: sticky; top: 0; background: #f9fafb; padding: 8px; border-bottom: 1px solid #eee; }
        .option-item { padding: 10px; cursor: pointer; font-size: 14px; border-bottom: 1px solid #f3f4f6; }
        .option-item:hover { background: #eff6ff; color: var(--primary-blue); }

        .badge { background:#f3f4f6; border:1px solid #e5e7eb; padding:6px 12px; border-radius:20px; font-size:12px; display:inline-flex; align-items:center; gap:8px; margin: 3px; }
        .ls-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .ls-table th { text-align: left; padding: 12px; background: #f9fafb; font-size: 12px; border-bottom: 1px solid #e5e7eb; color: #64748b; }
        .ls-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; font-size: 14px; }
        .sidebar-bottom { margin-top: auto; padding-top: 20px; border-top: 1px solid #f3f4f6; display: flex; flex-direction: column; gap: 10px; }
    </style>
</head>
<body>

<div class="sidebar">
    <img src="logo.png" style="max-width: 100%; margin-bottom: 25px;" onerror="this.style.display='none'">
    
    <h3>Neu anlegen</h3>
    <form method="POST" style="margin-bottom: 20px;">
        <input type="text" name="entity_name" placeholder="Name (z.B. FISI24A)" required>
        <label style="display:flex; align-items:center; gap:8px; margin-bottom:10px; cursor:pointer;">
            <input type="checkbox" name="is_template" style="width:auto; margin:0;"> Als Vorlage speichern
        </label>
        <button type="submit" name="add_entity" class="btn btn-primary">Erstellen</button>
    </form>

    <h3>Auswahl</h3>
    <div class="custom-select-container">
        <label>Klassen</label>
        <div class="select-trigger" onclick="toggleDropdown('classMenu')">
            <span><?= ($activeId && $activeType=='classes') ? htmlspecialchars($current['name']) : '-- wählen --' ?></span><small>▼</small>
        </div>
        <div class="select-dropdown" id="classMenu">
            <div class="search-box"><input type="text" placeholder="Suchen..." onkeyup="filterList(this, 'cO')" onclick="event.stopPropagation()"></div>
            <?php foreach($dropdownClasses as $c): ?>
                <div class="option-item cO" onclick="location.href='?edit_id=<?= $c['id'] ?>&type=classes'"><?= htmlspecialchars($c['name']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="custom-select-container">
        <label>Vorlagen</label>
        <div class="select-trigger" onclick="toggleDropdown('tplMenu')">
            <span><?= ($activeId && $activeType=='templates') ? "📂 ".htmlspecialchars($current['name']) : '-- wählen --' ?></span><small>▼</small>
        </div>
        <div class="select-dropdown" id="tplMenu">
            <div class="search-box"><input type="text" placeholder="Suchen..." onkeyup="filterList(this, 'tO')" onclick="event.stopPropagation()"></div>
            <?php foreach($dropdownTemplates as $t): ?>
                <div class="option-item tO" onclick="location.href='?edit_id=<?= $t['id'] ?>&type=templates'">📂 <?= htmlspecialchars($t['name']) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if($activeId): ?>
        <a href="?delete_entity=<?= $activeId ?>" class="btn btn-danger" style="margin-bottom:20px;" onclick="return confirm('Wirklich löschen?')">🗑️ Auswahl löschen</a>
    <?php endif; ?>

    <h3>Backup & Import</h3>
    <form method="POST" enctype="multipart/form-data" style="margin-bottom:15px;">
        <input type="file" name="backup_file" required style="font-size:11px;">
        <input type="text" name="new_name" placeholder="Neuer Name (optional)" style="font-size:12px;">
        <select name="upload_type" style="font-size:12px;">
            <option value="classes">Als Klasse importieren</option>
            <option value="templates">Als Vorlage importieren</option>
        </select>
        <button type="submit" name="upload_backup" class="btn btn-secondary">Import</button>
    </form>
    <?php if($activeId): ?>
        <a href="?download_single=1&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" class="btn btn-secondary">Einzel-Backup (.json)</a>
    <?php endif; ?>

    <div class="sidebar-bottom">
        <a href="didakt_index.php" class="btn btn-blue">➔ Zum Viewer</a>
        <a href="?logout=1" class="btn btn-danger">Abmelden</a>
    </div>
</div>

<div class="main">
    <?php if($current): ?>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
            <h1 style="margin:0;"><?= ($activeType=='templates'?'Vorlage: ':'Klasse: ') . htmlspecialchars($current['name']) ?></h1>
            <?php if($activeType == 'classes'): ?>
            <form method="POST" style="display:flex; gap:10px; align-items:center; background:#fff; padding:10px; border-radius:10px; border:1px solid #d1d5db;">
                <label style="margin:0; white-space:nowrap">Aus Vorlage laden:</label>
                <select name="template_src_id" style="margin:0; width:150px;">
                    <?php foreach($dropdownTemplates as $t) echo "<option value='{$t['id']}'>{$t['name']}</option>"; ?>
                </select>
                <button type="submit" name="apply_template" class="btn btn-primary" style="width:auto;" onclick="return confirm('Fächer werden überschrieben!')">Anwenden</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3>Fächer & Lernfelder bearbeiten</h3>
            <form method="POST" id="sub_form">
                <input type="hidden" name="old_sub_key" id="old_sub_key">
                <div style="display:grid; grid-template-columns: 100px 1fr 100px 180px 220px; gap:12px; align-items:end;">
                    <div><label>Kürzel</label><input type="text" name="sub_key" id="sub_key" required></div>
                    <div><label>Name des Fachs</label><input type="text" name="sub_name" id="sub_name" required></div>
                    <div><label>Soll-h</label><input type="number" name="sub_hours" id="sub_hours" required></div>
                    <div><label>Bereich</label><select name="sub_cat" id="sub_cat"><option value="bezogen">Berufsbezogen</option><option value="uebergreifend">Übergreifend</option></select></div>
                    <div style="display:flex; gap:8px; margin-bottom:10px;">
                        <button type="submit" name="save_subject" class="btn btn-green">Speichern</button>
                        <button type="button" onclick="resetSub()" class="btn btn-danger" style="width:auto; display:inline-block;">Reset</button>
                    </div>
                </div>
            </form>
            <div style="margin-top:20px; border-top:1px solid #eee; padding-top:15px;">
                <?php foreach($currentSubjects as $s): ?>
                    <div class="badge">
                        <span onclick='editSub(<?= json_encode($s) ?>)' style="cursor:pointer"><strong><?= $s['sub_key'] ?></strong>: <?= $s['name'] ?> (<?= $s['total_hours'] ?>h)</span>
                        <a href="?del_subject=<?= $s['sub_key'] ?>&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" style="color:red; font-weight:bold; text-decoration:none;">×</a>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="card">
            <h3>Lernsituation bearbeiten</h3>
            <form method="POST" id="ls_form">
                <input type="hidden" name="ls_index" id="ls_index">
                <div style="display:grid; grid-template-columns: 200px 1fr 100px; gap:12px;">
                    <div><label>Fach</label><select name="subject" id="ls_sub"><?php foreach($currentSubjects as $s) echo "<option value='{$s['sub_key']}'>{$s['sub_key']} - {$s['name']}</option>"; ?></select></div>
                    <div><label>Titel / Thema</label><input type="text" name="title" id="ls_title" required></div>
                    <div><label>LS-Nr.</label><input type="text" name="ls_nr" id="ls_nr"></div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr 1fr 2fr 180px 220px; gap:12px; align-items:end;">
                    <div><label>Stunden</label><input type="number" name="hours" id="ls_hours" required></div>
                    <div><label>Start (W)</label><input type="number" name="start" id="ls_start"></div>
                    <div><label>Ende (W)</label><input type="number" name="end" id="ls_end"></div>
                    <div><label>Link (optional)</label><input type="url" name="url" id="ls_url"></div>
                    <div>
    <label>Farbe (Hex)</label>
    <div style="display:flex; gap:4px; align-items: flex-start;">
        <input type="color" name="color" id="ls_color" value="#d1e7dd" 
               style="width: 45px; height: 40px; padding: 0; border: 1px solid #d1d5db; border-radius: 6px; cursor: pointer; flex-shrink: 0; background: none;">
        
        <input type="text" id="ls_color_hex" placeholder="#FFFFFF" 
               style="height: 40px; margin-bottom: 0; flex-grow: 1; text-transform: uppercase; font-family: monospace; font-size: 13px; padding: 10px; box-sizing: border-box;">
    </div>
</div>
                    <div style="display:flex; gap:8px; margin-bottom:10px;">
                        <button type="submit" name="save_ls" class="btn btn-blue" style="width:auto;">LS Speichern</button>
                        <button type="button" onclick="resetLS()" class="btn btn-danger" style="width:auto;">Reset</button>
                    </div>
                </div>
            </form>
            <table class="ls-table">
                <thead><tr><th>Fach</th><th>Nr.</th><th>Thema / Titel</th><th>Dauer</th><th>Wochen</th><th>Aktion</th></tr></thead>
                <tbody>
                <?php foreach($currentSubjects as $s): 
                    // Innerhalb eines Fachs nach LS-Nummer sortieren
                    $lsStmt = $db->prepare("SELECT * FROM planning WHERE subject_id = ? ORDER BY CAST(ls_nr AS UNSIGNED), ls_nr");
                    $lsStmt->execute([$s['id']]);
                    while($ls = $lsStmt->fetch(PDO::FETCH_ASSOC)): ?>
                    <tr>
                        <td><strong><?= $s['sub_key'] ?></strong></td>
                        <td><?= $ls['ls_nr'] ?></td>
                        <td><?= $ls['title'] ?></td>
                        <td><?= $ls['hours'] ?>h</td>
                        <td>W<?= $ls['start'] ?>-<?= $ls['end'] ?></td>
                        <td>
                            <button class="btn btn-blue" style="padding:4px 10px; width:auto; display:inline-block;" onclick='editLS(<?= json_encode($ls) ?>, "<?= $s['sub_key'] ?>")'>✎</button>
                            <a href="?del_ls_sql_id=<?= $ls['id'] ?>&edit_id=<?= $activeId ?>&type=<?= $activeType ?>" class="btn btn-danger" style="padding:4px 10px; width:auto; display:inline-block;">×</a>
                        </td>
                    </tr>
                <?php endwhile; endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
function toggleDropdown(id) {
    document.querySelectorAll('.select-dropdown').forEach(d => { if(d.id !== id) d.classList.remove('show'); });
    document.getElementById(id).classList.toggle('show');
}
function filterList(input, className) {
    let v = input.value.toLowerCase();
    document.querySelectorAll('.'+className).forEach(o => o.style.display = o.textContent.toLowerCase().includes(v) ? '' : 'none');
}
function editSub(s) {
    document.getElementById('old_sub_key').value = s.sub_key;
    document.getElementById('sub_key').value = s.sub_key;
    document.getElementById('sub_name').value = s.name;
    document.getElementById('sub_hours').value = s.total_hours;
    document.getElementById('sub_cat').value = s.category;
}
function editLS(ls, subKey) {
    document.getElementById('ls_index').value = ls.id;
    document.getElementById('ls_sub').value = subKey;
    document.getElementById('ls_title').value = ls.title;
    document.getElementById('ls_nr').value = ls.ls_nr;
    document.getElementById('ls_hours').value = ls.hours;
    document.getElementById('ls_start').value = ls.start;
    document.getElementById('ls_end').value = ls.end;
    document.getElementById('ls_color').value = ls.color;
    document.getElementById('ls_url').value = ls.url || '';
    document.getElementById('ls_color').value = ls.color; 
    document.getElementById('ls_color_hex').value = ls.color.toUpperCase();
}
document.addEventListener('DOMContentLoaded', () => {
    const colorPicker = document.getElementById('ls_color');
    const colorHex = document.getElementById('ls_color_hex');

    // Initialwert setzen
    if(colorPicker && colorHex) {
        colorHex.value = colorPicker.value.toUpperCase();

        // Wenn man im Picker klickt -> Hex-Feld aktualisieren
        colorPicker.addEventListener('input', () => {
            colorHex.value = colorPicker.value.toUpperCase();
        });

        // Wenn man im Hex-Feld tippt -> Picker aktualisieren
        colorHex.addEventListener('input', () => {
            let val = colorHex.value;
            if(!val.startsWith('#')) val = '#' + val;
            if(/^#[0-9A-F]{6}$/i.test(val)) {
                colorPicker.value = val;
            }
        });
    }
});

// WICHTIG: In der vorhandenen editLS-Funktion diese Zeile ergänzen:
// document.getElementById('ls_color_hex').value = ls.color.toUpperCase();
function resetSub() { document.getElementById('old_sub_key').value = ""; document.getElementById('sub_form').reset(); }
function resetLS() { document.getElementById('ls_index').value = ""; document.getElementById('ls_form').reset(); }
window.onclick = e => { if(!e.target.closest('.custom-select-container')) document.querySelectorAll('.select-dropdown').forEach(d => d.classList.remove('show')); }
</script>
</body>
</html>