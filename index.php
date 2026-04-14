<?php
$jsonFile = 'didakt_data.json';
$data = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : ['classes' => []];

$selId = $_GET['c'] ?? null;
$class = ($selId && isset($data['classes'][$selId])) ? $data['classes'][$selId] : null;
$schuljahr = "2025/2026"; 

$dropdownClasses = $data['classes'] ?? [];
uasort($dropdownClasses, function($a, $b) {
    return strnatcasecmp($a['name'], $b['name']);
});

function getSorted($subjects) {
    $b = []; $u = []; if(!$subjects) return [];
    foreach ($subjects as $k => $s) { 
        $s['key'] = $k; 
        if (($s['category']??'')==='bezogen' || strpos($k,'LF')===0) $b[]=$s; 
        else $u[]=$s; 
    }
    usort($b, function($x,$y){return strnatcasecmp($x['key'],$y['key']);});
    usort($u, function($x,$y){return strcasecmp($x['name'],$y['name']);});
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
                if (!($ls['end'] < $existing['start'] || $ls['start'] > $existing['end'])) {
                    $overlap = true; break;
                }
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
    <title>Jahresplanung - MMBbS</title>
    <style>
        :root {
            --primary-blue: #14508c;
            --border-dark: #d1d5db;
            --border-light: #e5e7eb;
        }

        body { 
            font-family: 'Segoe UI', system-ui, sans-serif; 
            background: #f0f2f5; 
            margin: 0; 
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .header-banner { 
            background: #fff; padding: 10px 40px; display: grid;
            grid-template-columns: 1fr auto 1fr; align-items: center; 
            border-bottom: 1px solid var(--border-dark); gap: 20px;
        }

        .logo-area img { height: 50px; display: block; }
        .header-title h1 { margin: 0; font-size: 1.2rem; color: var(--primary-blue); text-align: center; white-space: nowrap; }

        .nav-area { display: flex; justify-content: flex-end; gap: 15px; align-items: center; }

        /* DROPDOWN */
        .custom-select-container { position: relative; width: 250px; user-select: none; }
        .select-trigger { 
            padding: 8px 12px; background: white; border: 1px solid var(--border-dark); 
            border-radius: 4px; cursor: pointer; display: flex; justify-content: space-between; align-items: center;
            font-size: 14px;
        }
        .select-dropdown { 
            position: absolute; top: 100%; left: 0; right: 0; background: white; 
            border: 1px solid var(--border-dark); border-radius: 0 0 4px 4px; 
            display: none; z-index: 9999; max-height: 350px; overflow-y: auto; box-shadow: 0 10px 15px rgba(0,0,0,0.1);
        }
        .select-dropdown.show { display: block; }
        .search-box { position: sticky; top: 0; background: #f8f9fa; padding: 8px; border-bottom: 1px solid var(--border-dark); z-index: 10; }
        .search-box input { width: 100%; padding: 6px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; }
        .option-item { padding: 10px 12px; cursor: pointer; font-size: 14px; border-bottom: 1px solid #f0f0f0; }
        .option-item:hover { background: #e9ecef; }
        .option-item.hidden { display: none; }

        .btn-admin { 
            text-decoration: none; background: #34495e; color: white; padding: 8px 16px; 
            border-radius: 4px; font-size: 13px; white-space: nowrap;
        }

        /* GRID */
        .container { padding: 30px 40px; flex: 1; }
        .grid { display: grid; grid-template-columns: 280px repeat(13, 1fr); background: #fff; border: 1px solid var(--border-dark); border-radius: 4px; overflow: hidden; }
        .cell { padding: 10px; min-height: 50px; position: relative; border-top: 1px solid var(--border-light); border-left: 1px solid var(--border-light); box-sizing: border-box; }
        .header-cell { background: var(--primary-blue); color: white; text-align: center; font-weight: 600; font-size: 13px; border-top: none; }
        .cat-row { grid-column: span 14; background: #eceef1; padding: 10px 15px; font-weight: bold; font-size: 12px; text-transform: uppercase; color: #4b5563; border-top: 2px solid var(--border-dark); }
        
        /* LS-BAR STYLES */
        .ls-bar { 
            position: absolute; top: 8px; height: 34px; border-radius: 4px; 
            font-size: 11px; padding: 6px 10px; box-sizing: border-box; z-index: 10; 
            border: 1px solid rgba(0,0,0,0.1); overflow: hidden; text-overflow: ellipsis; 
            white-space: nowrap; transition: filter 0.2s, box-shadow 0.2s;
        }

        /* Hover-Effekt NUR wenn Link vorhanden ist (has-url Klasse) */
        .ls-bar.has-url:hover {
            filter: brightness(0.9);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }

        .welcome-msg { text-align: center; margin-top: 100px; color: #9ba3af; }
        footer { background: #fff; border-top: 1px solid var(--border-dark); padding: 20px 40px; margin-top: 40px; }
        .footer-content { display: flex; justify-content: space-between; color: #6b7280; font-size: 13px; }
        .footer-link { color: var(--primary-blue); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<header class="header-banner">
    <div class="logo-area">
        <img src="logo.png" alt="Logo" onerror="this.style.visibility='hidden'">
    </div>

    <div class="header-title">
        <h1>Didaktische Jahresplanung <span style="color:#666">SJ <?= $schuljahr ?></span></h1>
    </div>

    <div class="nav-area">
        <div class="custom-select-container" id="customSelect">
            <div class="select-trigger" onclick="toggleDropdown(event)">
                <span><?= $class ? htmlspecialchars($class['name']) : 'Klasse wählen...' ?></span>
                <small>▼</small>
            </div>
            <div class="select-dropdown" id="dropdownMenu">
                <div class="search-box">
                    <input type="text" id="classSearch" placeholder="Suchen..." onkeyup="filterClasses()" onclick="event.stopPropagation()">
                </div>
                <?php foreach($dropdownClasses as $id => $c): ?>
                    <div class="option-item" data-name="<?= strtolower(htmlspecialchars($c['name'])) ?>" onclick="selectClass('<?= $id ?>')">
                        <?= htmlspecialchars($c['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <a href="admin.php" class="btn-admin">Admin-Bereich</a>
    </div>
</header>

<div class="container">
    <?php if($class): $sorted = getSorted($class['subjects']); ?>
        <h1 style="margin-top:0;"><?= htmlspecialchars($class['name']) ?></h1>
        <div class="grid">
            <div class="cell header-cell">Fächer / Lernfelder</div>
            <?php for($i=1; $i<=13; $i++) echo "<div class='cell header-cell'>Woche $i</div>"; ?>
            
            <?php foreach($sorted as $catName => $subs): if(empty($subs)) continue; ?>
                <div class="cat-row"><?= $catName ?></div>
                <?php foreach($subs as $s): 
                    $stackedRows = stackPlanning($s['planning'] ?? []);
                    $rowCount = max(1, count($stackedRows)); 
                ?>
                    <div class="cell" style="grid-row: span <?= $rowCount ?>; background:#f9fafb;">
                        <strong><?= htmlspecialchars($s['key']) ?></strong><br><small><?= htmlspecialchars($s['name']) ?></small>
                    </div>
                    <?php for($r=0; $r < $rowCount; $r++): ?>
                        <?php for($w=1; $w<=13; $w++): ?>
                            <div class="cell">
                                <?php if(isset($stackedRows[$r])): foreach($stackedRows[$r] as $p): if($p['start'] == $w): 
                                    $numWeeks = ($p['end'] - $p['start'] + 1);
                                    $barWidth = "calc(" . ($numWeeks * 100) . "% + " . ($numWeeks - 1) . "px - 14px)";
                                    
                                    // LINK LOGIK
                                    $url = !empty($p['url']) ? $p['url'] : null;
                                    $hasUrlClass = $url ? 'has-url' : '';
                                ?>
                                    <?php if($url): ?>
                                        <a href="<?= htmlspecialchars($url) ?>" target="_blank" style="text-decoration:none; color:inherit;">
                                    <?php endif; ?>

                                    <div class="ls-bar <?= $hasUrlClass ?>" 
                                         style="background:<?= $p['color'] ?>; width:<?= $barWidth ?>; left: 7px; cursor: <?= $url ? 'pointer' : 'default' ?>;" 
                                         title="<?= htmlspecialchars($p['title']) . ($url ? ' (Klicken zum Öffnen)' : '') ?>">
                                        <b><?= htmlspecialchars($p['ls_nr']) ?>:</b> <?= htmlspecialchars($p['title']) ?>
                                    </div>

                                    <?php if($url): ?>
                                        </a>
                                    <?php endif; ?>

                                <?php endif; endforeach; endif; ?>
                            </div>
                        <?php endfor; endfor; ?>
                <?php endforeach; endforeach; ?>
        </div>
    <?php else: ?>
        <div class="welcome-msg">
            <div style="font-size: 4rem; margin-bottom: 20px;">🗓️</div>
            <h2>Willkommen</h2>
            <p>Bitte wählen Sie oben eine Klasse aus.</p>
        </div>
    <?php endif; ?>
</div>

<footer>
    <div class="footer-content">
        <div>
            <h4>Lizenz</h4>
            © <?= date('Y') ?> MMBbS Hannover | NM | <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" class="footer-link">GNU AGPL v3</a>
        </div>
        <div style="text-align: right;">
            <h4>Kontakt</h4>
            <a href="mailto:info@mmbbs.de" class="footer-link">info@mmbbs.de</a><br>
            <a href="https://www.mmbbs.de" target="_blank" class="footer-link">www.mmbbs.de</a>
        </div>
    </div>
</footer>

<script>
function toggleDropdown(e) {
    if(e) e.stopPropagation();
    const menu = document.getElementById('dropdownMenu');
    menu.classList.toggle('show');
    if(menu.classList.contains('show')) {
        document.getElementById('classSearch').focus();
    }
}

function filterClasses() {
    const input = document.getElementById('classSearch');
    const filter = input.value.toLowerCase();
    const items = document.getElementsByClassName('option-item');
    for (let i = 0; i < items.length; i++) {
        const txtValue = items[i].getAttribute('data-name');
        items[i].classList.toggle('hidden', txtValue.indexOf(filter) === -1);
    }
}

function selectClass(id) {
    window.location.href = 'index.php?c=' + id;
}

window.onclick = function(event) {
    if (!event.target.closest('#customSelect')) {
        document.getElementById('dropdownMenu').classList.remove('show');
    }
}
</script>
</body>
</html>