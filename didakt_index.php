<?php
// --- DATENBANK VERBINDUNG ---
try {
    $db = new PDO('sqlite:didakt_data.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (Exception $e) {
    die("Datenbankfehler. Bitte didakt_admin.php aufrufen.");
}

$selId = $_GET['c'] ?? null;
$class = null;
if ($selId) {
    $stmt = $db->prepare("SELECT * FROM entities WHERE id = ? AND type='classes'");
    $stmt->execute([$selId]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
}

$schuljahr = "2025/2026"; 
$dropdownClasses = $db->query("SELECT * FROM entities WHERE type='classes' ORDER BY name COLLATE NOCASE")->fetchAll(PDO::FETCH_ASSOC);

function getSortedData($db, $entityId) {
    $stmt = $db->prepare("SELECT * FROM subjects WHERE entity_id = ? ORDER BY 
        CASE WHEN sub_key LIKE 'LF%' THEN 0 ELSE 1 END, 
        CAST(SUBSTR(sub_key, 3) AS INTEGER), sub_key");
    $stmt->execute([$entityId]);
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $b = []; $u = [];
    foreach ($subs as $s) {
        $lsStmt = $db->prepare("SELECT * FROM planning WHERE subject_id = ? ORDER BY ls_nr");
        $lsStmt->execute([$s['id']]);
        $s['planning'] = $lsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (($s['category']??'')==='bezogen' || strpos($s['sub_key'],'LF')===0) $b[]=$s; 
        else $u[]=$s; 
    }
    return ['Berufsbezogener Lernbereich' => $b, 'Berufsübergreifend' => $u];
}

function stackPlanning($planning) {
    if (!$planning) return [];
    usort($planning, function($a, $b) { return $a['start'] <=> $b['start']; });
    $rows = [];
    foreach ($planning as $ls) {
        $placed = false;
        foreach ($rows as &$row) {
            $overlap = false;
            foreach ($row as $existing) {
                if (!($ls['end'] < $existing['start'] || $ls['start'] > $existing['end'])) { $overlap = true; break; }
            }
            if (!$overlap) { $row[] = $ls; $placed = true; break; }
        }
        if (!$placed) { $rows[] = [$ls]; }
    }
    return $rows;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Didaktischer Jahresplan - MMBbS</title>
    <style>
        :root { --primary-blue: #14508c; --border-dark: #d1d5db; --border-light: #e5e7eb; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: #f0f2f5; margin: 0; display: flex; flex-direction: column; min-height: 100vh; }
        
        header { background: #fff; padding: 10px 40px; display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; border-bottom: 1px solid var(--border-dark); gap: 20px; }
        .header-title h1 { margin: 0; font-size: 1.25rem; color: var(--primary-blue); }
        
        /* Dropdown & Suche */
        .custom-select-container { position: relative; width: 280px; }
        .select-trigger { padding: 10px 15px; background: white; border: 2px solid var(--primary-blue); border-radius: 8px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--primary-blue); transition: all 0.2s; }
        .select-dropdown { position: absolute; top: calc(100% + 5px); left: 0; right: 0; background: white; border: 1px solid var(--border-dark); border-radius: 8px; display: none; z-index: 1000; box-shadow: 0 10px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .select-dropdown.show { display: block; }
        .search-box { padding: 10px; background: #f8fafc; border-bottom: 1px solid var(--border-light); }
        .search-box input { width: 100%; padding: 8px 12px; border: 1px solid var(--border-dark); border-radius: 6px; box-sizing: border-box; outline: none; }
        .option-item { padding: 12px 15px; cursor: pointer; transition: background 0.2s; border-bottom: 1px solid #f1f5f9; font-size: 0.95rem; }
        .option-item:hover { background: #eff6ff; color: var(--primary-blue); }
        .option-item.hidden { display: none; }

        /* Grid & Gantt */
        .container { padding: 30px 40px; flex: 1; }
        .grid { display: grid; grid-template-columns: 280px repeat(13, 1fr); background: #fff; border: 1px solid var(--border-dark); border-radius: 4px; }
        .cell { padding: 10px; min-height: 54px; position: relative; border-top: 1px solid var(--border-light); border-left: 1px solid var(--border-light); background: #fff; }
        .header-cell { background: var(--primary-blue); color: white; text-align: center; font-weight: 600; padding: 12px; border: none; border-left: 1px solid rgba(255,255,255,0.1); }
        .cat-row { grid-column: span 14; background: #f1f5f9; padding: 12px 20px; font-weight: bold; color: #475569; border-top: 1px solid var(--border-dark); }
        
        /* Die Balken */
        .ls-bar { 
            position: absolute; top: 8px; height: 38px; border-radius: 6px; 
            font-size: 11px; padding: 6px 12px; box-sizing: border-box; 
            z-index: 100; /* Sehr hoch, damit sie über Gitterlinien liegen */
            border: 1px solid rgba(0,0,0,0.08); overflow: hidden; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.1s;
        }
        .ls-bar:hover { transform: translateY(-1px); filter: brightness(0.95); }

        /* Footer */
        footer { background-color: #14508c; color: white; padding: 40px 0; margin-top: 60px; }
        .footer-content { max-width: 1320px; margin: 0 auto; padding: 0 40px; display: flex; justify-content: space-between; align-items: center; }
        .footer-link { color: #89b1d8; text-decoration: none; transition: color 0.2s; }
        .footer-link:hover { color: white; }
    </style>
</head>
<body>

<header>
    <div><img src="logo.png" style="height:55px;" onerror="this.style.visibility='hidden'"></div>
    <div class="header-title"><h1>Didaktische Jahresplanung <span style="font-weight:300; color:#64748b;">SJ <?= $schuljahr ?></span></h1></div>
    <div style="display:flex; justify-content:flex-end; gap:15px; align-items:center;">
        <div class="custom-select-container" id="customSelect">
            <div class="select-trigger" onclick="toggleMenu(event)">
                <span><?= $class ? htmlspecialchars($class['name']) : 'Klasse wählen...' ?></span>
                <small>▼</small>
            </div>
            <div class="select-dropdown" id="dropdownMenu">
                <div class="search-box">
                    <input type="text" id="classSearch" placeholder="Suchen..." onkeyup="filterClasses()" onclick="event.stopPropagation()">
                </div>
                <?php foreach($dropdownClasses as $c): ?>
                    <div class="option-item" data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>" onclick="location.href='?c=<?= $c['id'] ?>'">
                        <?= htmlspecialchars($c['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="didakt_admin.php" style="text-decoration:none; background:#34495e; color:white; padding:10px 20px; border-radius:8px; font-size:14px; font-weight:600;">Admin</a>
    </div>
</header>

<div class="container">
    <?php if($class): $sorted = getSortedData($db, $class['id']); ?>
        <h1 style="margin-top:0; margin-bottom:25px; color:#1e293b;"><?= htmlspecialchars($class['name']) ?></h1>
        <div class="grid">
            <div class="cell header-cell" style="border-left:none;">Fächer / Lernfelder</div>
            <?php for($i=1; $i<=13; $i++) echo "<div class='cell header-cell'>Woche $i</div>"; ?>
            
            <?php foreach($sorted as $catName => $subs): if(empty($subs)) continue; ?>
                <div class="cat-row"><?= $catName ?></div>
                <?php foreach($subs as $s): 
                    $stackedRows = stackPlanning($s['planning']);
                    $rowCount = max(1, count($stackedRows)); 
                ?>
                    <div class="cell" style="grid-row: span <?= $rowCount ?>; background:#f8fafc; font-weight:600; border-left:none;">
                        <span style="color:var(--primary-blue)"><?= htmlspecialchars($s['sub_key']) ?></span><br>
                        <small style="color:#64748b; font-weight:400;"><?= htmlspecialchars($s['name']) ?></small>
                    </div>
                    <?php for($r=0; $r < $rowCount; $r++): ?>
                        <?php for($w=1; $w<=13; $w++): 
                            $hasStart = false;
                            if(isset($stackedRows[$r])) {
                                foreach($stackedRows[$r] as $p) if($p['start'] == $w) $hasStart = true;
                            }
                        ?>
                            <div class="cell" style="<?= $hasStart ? 'z-index: 10;' : '' ?>">
                                <?php if(isset($stackedRows[$r])): foreach($stackedRows[$r] as $p): if($p['start'] == $w): 
                                    $numWeeks = ($p['end'] - $p['start'] + 1);
                                    $barWidth = "calc(" . ($numWeeks * 100) . "% + " . ($numWeeks - 1) . "px - 14px)";
                                    $url = !empty($p['url']) ? $p['url'] : null;
                                ?>
                                    <div class="ls-bar" 
                                         style="background:<?= $p['color'] ?>; width:<?= $barWidth ?>; left: 7px; <?= $url ? 'cursor:pointer' : '' ?>"
                                         onclick="<?= $url ? "window.open('$url','_blank')" : "" ?>">
                                        <strong><?= htmlspecialchars($p['ls_nr'] ?? '') ?></strong> <?= htmlspecialchars($p['title']) ?>
                                    </div>
                                <?php endif; endforeach; endif; ?>
                            </div>
                        <?php endfor; endfor; ?>
                <?php endforeach; endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align:center; padding:100px 0; color:#64748b;">
            <p style="font-size:4rem; margin:0;">🗓️</p>
            <h3>Bitte wählen Sie eine Klasse aus dem Menü oben rechts.</h3>
        </div>
    <?php endif; ?>
</div>

<footer>
    <div class="footer-content">
        <div>
            <h4 style="margin:0 0 10px 0;">Lizenz</h4>
            © <?= date('Y') ?> MMBbS Hannover | <a href="https://github.com/herr-nm/MMBbS_Didaktischer_Jahresplan" target="_blank" class="footer-link">Neumann</a> | <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="footer-link">GNU AGPL v3</a>
        </div>
        <div style="text-align: right;">
            <h4 style="margin:0 0 10px 0;">Kontakt</h4>
            <a href="mailto:info@mmbbs.de" class="footer-link">info@mmbbs.de</a><br>
            <a href="https://www.mmbbs.de" target="_blank" class="footer-link">www.mmbbs.de</a>
        </div>
    </div>
</footer>

<script>
function toggleMenu(e) {
    e.stopPropagation();
    document.getElementById('dropdownMenu').classList.toggle('show');
    if(document.getElementById('dropdownMenu').classList.contains('show')) {
        document.getElementById('classSearch').focus();
    }
}
function filterClasses() {
    const filter = document.getElementById('classSearch').value.toLowerCase();
    const items = document.getElementsByClassName('option-item');
    for (let i = 0; i < items.length; i++) {
        const txtValue = items[i].getAttribute('data-name');
        items[i].classList.toggle('hidden', txtValue.indexOf(filter) === -1);
    }
}
window.onclick = function(e) { if (!e.target.closest('#customSelect')) document.getElementById('dropdownMenu').classList.remove('show'); }
</script>
</body>
</html>