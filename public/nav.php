<?php
/**
 * Shared top navigation bar — uses same lp-nav style as landing page.
 * Include after session_start() and DB connection.
 * Variables expected: $reportId (int, optional)
 */
$navReportId = isset($reportId) ? (int) $reportId : 0;
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');

$_navAvatar = $_SESSION['avatar'] ?? '';
$_navAvatarUrl = '';
if ($_navAvatar) {
    if (preg_match('/^avatar-(\d+)$/', $_navAvatar, $m) && (int)$m[1] >= 1 && (int)$m[1] <= 10) {
        $_navAvatarUrl = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/Avatar%20' . $m[1] . '.png';
    }
}
$_navLoggedIn = !empty($_SESSION['user_id']);
$_navUsername = htmlspecialchars($_SESSION['username'] ?? '');
$_AVATAR_BASE = 'https://raw.githubusercontent.com/alexharibo/rapportquest/main/Visuel%20guides/';
$_LOGO_URL    = $_AVATAR_BASE . 'ExamQuest%20logo%20med%20futuristisk%20design.png';
?>
<style>
.lp-nav {
    position: fixed; top: 0; left: 0; right: 0; z-index: 200;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 2rem;
    height: 76px;
    background: rgba(10,10,26,.85);
    backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(124,58,237,.18);
    transition: background .3s;
}
.lp-nav-logo img {
    height: 56px; width: auto;
    padding: 10px 0; margin: 10px 0;
}
.lp-nav-links { display: flex; align-items: center; gap: .5rem; }
.lp-nav-link {
    padding: .4rem .85rem; border-radius: 8px;
    color: var(--text-muted); font-size: .875rem; font-weight: 500;
    text-decoration: none; border: 1px solid transparent;
    transition: all .15s;
}
.lp-nav-link:hover,
.lp-nav-link.active { color: #fff; background: rgba(124,58,237,.15); border-color: rgba(124,58,237,.35); }
.lp-nav-icon-link { display: inline-flex; align-items: center; gap: .4rem; }
.lp-nav-icon { width: 18px; height: 18px; object-fit: contain; filter: brightness(.7); transition: filter .15s; }
.lp-nav-icon-link:hover .lp-nav-icon,
.lp-nav-icon-link.active .lp-nav-icon { filter: brightness(1.3); }
.lp-nav-cta {
    padding: .45rem 1.2rem; border-radius: 8px;
    background: var(--primary); color: #fff;
    font-size: .875rem; font-weight: 700;
    text-decoration: none; border: none; cursor: pointer;
    box-shadow: 0 0 16px rgba(124,58,237,.4);
    transition: box-shadow .2s, transform .15s; font-family: inherit;
    display: inline-flex; align-items: center; gap: .35rem;
}
.lp-nav-cta:hover { box-shadow: 0 0 28px rgba(124,58,237,.7); transform: translateY(-1px); }
.lp-nav-avatar {
    width: 30px; height: 30px; border-radius: 50%;
    object-fit: cover; border: 2px solid var(--primary);
}
body { padding-top: 76px; }
</style>

<nav class="lp-nav" aria-label="Hovednavigation">
    <a href="index.php" class="lp-nav-logo">
        <img src="<?= $_LOGO_URL ?>" alt="ExamQuest">
    </a>
    <div class="lp-nav-links">
        <?php
        $_quizHref  = $navReportId > 0 ? "quiz.php?id=$navReportId"      : 'index.php';
        $_clozeHref = $navReportId > 0 ? "cloze.php?id=$navReportId"     : 'index.php';
        $_bossHref  = $navReportId > 0 ? "boss.php?id=$navReportId"      : 'index.php';
        $_dashHref  = $navReportId > 0 ? "dashboard.php?id=$navReportId" : 'dashboard.php';
        ?>
        <a href="<?= $_dashHref ?>" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>home%20ikon.png" class="lp-nav-icon" alt=""> Dashboard
        </a>
        <a href="<?= $_quizHref ?>"  class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'quiz.php'  ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>quiz%20ikon.png" class="lp-nav-icon" alt=""> Quiz
        </a>
        <a href="<?= $_clozeHref ?>" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'cloze.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>cloze%20mode%20ikon.png" class="lp-nav-icon" alt=""> Cloze
        </a>
        <a href="<?= $_bossHref ?>"  class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'boss.php'  ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>boss%20battle%20ikon.png" class="lp-nav-icon" alt=""> Boss
        </a>
        <a href="gamification.php" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'gamification.php' ? 'active' : '' ?>">
            <img src="<?= $_AVATAR_BASE ?>bagdes%20ikon.png" class="lp-nav-icon" alt=""> Badges
        </a>
        <a href="dashboard.php?tab=stats" class="lp-nav-link lp-nav-icon-link">
            <img src="<?= $_AVATAR_BASE ?>Statistik%20ikon.png" class="lp-nav-icon" alt=""> Statistik
        </a>
        <a href="profile.php" class="lp-nav-link lp-nav-icon-link <?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            <?php if ($_navAvatarUrl): ?>
            <img src="<?= $_navAvatarUrl ?>" class="lp-nav-avatar" alt="Avatar">
            <?php else: ?>🧑‍💻<?php endif; ?>
            <?= $_navLoggedIn ? $_navUsername : 'Profil' ?>
        </a>
        <?php if ($_navLoggedIn): ?>
        <a href="logout.php" class="lp-nav-link" style="color:var(--text-muted);">Log ud</a>
        <?php else: ?>
        <button class="lp-nav-link" onclick="openAuthModal('login')" style="cursor:pointer;background:none;border:none;font-family:inherit;font-size:.875rem;">Log ind</button>
        <button class="lp-nav-cta" onclick="openAuthModal('register')" style="cursor:pointer;">Opret konto</button>
        <?php endif; ?>
        <a href="index.php" class="lp-nav-cta">
            <img src="<?= $_AVATAR_BASE ?>upload%20ikon%201.png" style="width:20px;height:20px;object-fit:contain;" alt=""> Upload
        </a>
    </div>
</nav>
<?php require_once __DIR__ . '/auth_modal.php'; ?>

<!-- ══════ SNAKE EASTER EGG (Konami code: ↑↑↓↓←→←→BA) ══════ -->
<div id="snakeOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;align-items:center;justify-content:center;flex-direction:column;">
    <div style="text-align:center;margin-bottom:1rem;">
        <span style="font-size:1.5rem;font-weight:900;color:#7c3aed;letter-spacing:.05em;">🐍 SNAKE</span>
        <span style="display:block;font-size:.8rem;color:#6b7280;margin-top:.25rem;">Piletaster = bevæg · P = pause · ESC = luk</span>
    </div>
    <canvas id="snakeCanvas" width="400" height="400" style="border:2px solid #7c3aed;border-radius:8px;box-shadow:0 0 30px rgba(124,58,237,.5);background:#0a0a1a;display:block;"></canvas>
    <div style="margin-top:1rem;display:flex;gap:1rem;align-items:center;">
        <span id="snakeScore" style="font-size:1.1rem;font-weight:700;color:#fff;">Score: 0</span>
        <button onclick="snakeRestart()" style="padding:.4rem 1rem;background:#7c3aed;color:#fff;border:none;border-radius:6px;cursor:pointer;font-weight:700;font-family:inherit;">Restart</button>
        <button onclick="snakeClose()" style="padding:.4rem 1rem;background:rgba(255,255,255,.1);color:#fff;border:1px solid rgba(255,255,255,.2);border-radius:6px;cursor:pointer;font-family:inherit;">Luk</button>
    </div>
    <div id="snakeMsg" style="margin-top:.75rem;font-size:1rem;font-weight:700;color:#f97316;min-height:1.5rem;"></div>
</div>

<script>
(function(){
    // Konami code detector
    const KONAMI = ['ArrowUp','ArrowUp','ArrowDown','ArrowDown','ArrowLeft','ArrowRight','ArrowLeft','ArrowRight','b','a'];
    let kIdx = 0;
    document.addEventListener('keydown', e => {
        if (e.key === KONAMI[kIdx]) { kIdx++; if (kIdx === KONAMI.length) { kIdx = 0; snakeOpen(); } }
        else kIdx = e.key === KONAMI[0] ? 1 : 0;
    });

    const GRID = 20, COLS = 20, ROWS = 20;
    let snake, dir, nextDir, food, score, paused, dead, loop;
    const overlay  = document.getElementById('snakeOverlay');
    const canvas   = document.getElementById('snakeCanvas');
    const ctx      = canvas.getContext('2d');
    const scoreEl  = document.getElementById('snakeScore');
    const msgEl    = document.getElementById('snakeMsg');

    function rand(n) { return Math.floor(Math.random() * n); }

    function placeFood() {
        let pos;
        do { pos = {x: rand(COLS), y: rand(ROWS)}; }
        while (snake.some(s => s.x === pos.x && s.y === pos.y));
        food = pos;
    }

    function init() {
        snake   = [{x:10,y:10},{x:9,y:10},{x:8,y:10}];
        dir     = {x:1,y:0};
        nextDir = {x:1,y:0};
        score   = 0; paused = false; dead = false;
        msgEl.textContent = '';
        scoreEl.textContent = 'Score: 0';
        placeFood();
        clearInterval(loop);
        loop = setInterval(tick, 120);
    }

    function tick() {
        if (paused || dead) return;
        dir = nextDir;
        const head = {x: snake[0].x + dir.x, y: snake[0].y + dir.y};
        if (head.x < 0 || head.x >= COLS || head.y < 0 || head.y >= ROWS || snake.some(s => s.x === head.x && s.y === head.y)) {
            dead = true; msgEl.textContent = '💀 Game over! Tryk Restart.'; draw(); return;
        }
        snake.unshift(head);
        if (head.x === food.x && head.y === food.y) {
            score += 10; scoreEl.textContent = 'Score: ' + score; placeFood();
        } else { snake.pop(); }
        draw();
    }

    function draw() {
        ctx.fillStyle = '#0a0a1a';
        ctx.fillRect(0, 0, canvas.width, canvas.height);

        // grid dots
        ctx.fillStyle = 'rgba(124,58,237,.08)';
        for (let x = 0; x < COLS; x++) for (let y = 0; y < ROWS; y++) {
            ctx.fillRect(x*GRID+9, y*GRID+9, 2, 2);
        }

        // food
        ctx.fillStyle = '#f97316';
        ctx.shadowColor = '#f97316'; ctx.shadowBlur = 12;
        ctx.beginPath();
        ctx.arc(food.x*GRID+GRID/2, food.y*GRID+GRID/2, GRID/2-2, 0, Math.PI*2);
        ctx.fill();
        ctx.shadowBlur = 0;

        // snake
        snake.forEach((s, i) => {
            const t = i / snake.length;
            ctx.fillStyle = i === 0 ? '#a78bfa' : `hsl(${260 - t*40}, 70%, ${55 - t*15}%)`;
            ctx.shadowColor = i === 0 ? '#7c3aed' : 'transparent';
            ctx.shadowBlur  = i === 0 ? 10 : 0;
            ctx.beginPath();
            ctx.roundRect(s.x*GRID+1, s.y*GRID+1, GRID-2, GRID-2, i===0 ? 6 : 3);
            ctx.fill();
        });
        ctx.shadowBlur = 0;

        if (paused && !dead) {
            ctx.fillStyle = 'rgba(0,0,0,.55)';
            ctx.fillRect(0,0,canvas.width,canvas.height);
            ctx.fillStyle = '#fff'; ctx.font = 'bold 28px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText('PAUSE', canvas.width/2, canvas.height/2);
            ctx.textAlign = 'left';
        }
    }

    document.addEventListener('keydown', e => {
        if (!overlay.style.display || overlay.style.display === 'none') return;
        const map = {ArrowUp:{x:0,y:-1},ArrowDown:{x:0,y:1},ArrowLeft:{x:-1,y:0},ArrowRight:{x:1,y:0}};
        if (map[e.key]) {
            const d = map[e.key];
            if (d.x !== -dir.x || d.y !== -dir.y) nextDir = d;
            e.preventDefault();
        }
        if (e.key === 'p' || e.key === 'P') { paused = !paused; draw(); }
        if (e.key === 'Escape') snakeClose();
    });

    window.snakeOpen = function() {
        overlay.style.display = 'flex';
        init();
    };
    window.snakeClose = function() {
        overlay.style.display = 'none';
        clearInterval(loop);
    };
    window.snakeRestart = function() { init(); };
})();
</script>

