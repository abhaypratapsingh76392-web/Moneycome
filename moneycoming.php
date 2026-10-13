<?php
/* =====================================================================
   MONEY COMING — Single File Game (PHP + HTML + CSS + JS in ONE file)
   ---------------------------------------------------------------------
   ✔ Chalega: sirf is file ko server par daalo (config.php wale server par bhi)
   ✔ Koi alag image / sound file NAHI — saari images inline SVG/CSS se bani
     hain aur sound WebAudio se live banta hai (koi external URL nahi,
     isliye kabhi "image not found" / 404 nahi aayega)
   ✔ Win Rate: 80% LOSS / 20% WIN  (neeche WIN_PERCENT se badal sakte ho)
   ✔ Balance PHP SESSION mein server-side safe rehta hai (hack-proof spin)
   ===================================================================== */

// ---------- CONFIG (yahi se sab control hoga) ----------
define('WIN_PERCENT', 20);          // 20% WIN  => 80% LOSS
define('START_BALANCE', 1000.00);   // naye player ko starting balance
define('GAME_NAME', 'Money Coming');
$ALLOWED_BETS = [1, 5, 10, 50, 100, 500];

if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['mc_balance'])) $_SESSION['mc_balance'] = START_BALANCE;
if (!isset($_SESSION['mc_history'])) $_SESSION['mc_history'] = [];

// ---------- SERVER-SIDE SPIN LOGIC (AJAX) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if ($_POST['action'] === 'state') {
        echo json_encode(['success' => true, 'balance' => round($_SESSION['mc_balance'], 2),
                          'history' => array_slice($_SESSION['mc_history'], 0, 10)]);
        exit;
    }

    if ($_POST['action'] === 'reset') {   // demo balance refill
        $_SESSION['mc_balance'] = START_BALANCE; $_SESSION['mc_history'] = [];
        echo json_encode(['success' => true, 'balance' => START_BALANCE]);
        exit;
    }

    if ($_POST['action'] === 'spin') {
        $bet = floatval($_POST['bet'] ?? 10);
        if (!in_array($bet, $GLOBALS['ALLOWED_BETS'])) $bet = 10;
        $freeSpin = !empty($_POST['free']) && !empty($_SESSION['mc_free']);

        if (!$freeSpin && $_SESSION['mc_balance'] < $bet) {
            echo json_encode(['success' => false, 'message' => 'Insufficient Balance!']);
            exit;
        }
        if ($freeSpin) $_SESSION['mc_free'] = false; else $_SESSION['mc_balance'] -= $bet;

        // ---- 80 / 20 : 20% win chance ----
        $isWin = (mt_rand(1, 100) <= WIN_PERCENT);
        $win = 0; $kind = 'loss'; $multiplier = 0; $wheelIndex = -1; $symbol = '2x';
        $wheelValues = [50, 200, 2000, 500, 100, 300, 150, 1000]; // wheel segments (top se clockwise)

        if ($isWin) {
            $roll = mt_rand(1, 100);
            if ($bet >= 50 && $roll <= 8) {                    // SCATTER bonus
                $kind = 'scatter'; $symbol = 'SCATTER'; $win = $bet * 5;
            } elseif ($bet >= 5 && $roll <= 30) {              // LUCKY WHEEL
                $kind = 'wheel'; $symbol = 'WHEEL';
                $pick = mt_rand(1, 100);                        // chhote prize zyada chance
                $wheelIndex = $pick <= 30 ? 0 : ($pick <= 55 ? 4 : ($pick <= 72 ? 6 : ($pick <= 85 ? 1 : ($pick <= 93 ? 5 : ($pick <= 97 ? 3 : ($pick <= 99 ? 7 : 2))))));
                $win = $wheelValues[$wheelIndex] * ($bet / 10);
            } elseif ($roll <= 38) {                           // RESPIN (free spin)
                $kind = 'respin'; $symbol = 'RESPIN'; $_SESSION['mc_free'] = true; $win = 0;
            } else {                                           // MULTIPLIER win
                $kind = 'multiplier';
                $m = mt_rand(1, 100);
                $multiplier = ($bet >= 10 && $m > 92) ? 10 : ($m > 60 ? 5 : 2);
                $symbol = $multiplier . 'x'; $win = $bet * $multiplier;
            }
        } else {
            $symbol = ['2x', '5x', 'RESPIN', 'WHEEL', '10x'][mt_rand(0, 4)]; // dikhawa symbol, win nahi
        }

        $_SESSION['mc_balance'] += $win;
        // reels ke digits: win par jeeti rakam ke base digits, loss par random
        $base = ($kind === 'multiplier' && $multiplier) ? intval($win / $multiplier) : intval(max($win, $bet));
        $digits = $isWin && $kind !== 'respin'
            ? str_split(str_pad(strval($base % 1000), 3, '0', STR_PAD_LEFT))
            : [strval(mt_rand(0, 9)), strval(mt_rand(0, 9)), strval(mt_rand(0, 9))];

        $row = ['bet' => $bet, 'win' => round($win, 2), 'kind' => $kind, 'time' => date('H:i:s')];
        array_unshift($_SESSION['mc_history'], $row);

        echo json_encode(['success' => true, 'win' => round($win, 2), 'isWin' => $isWin,
            'kind' => $kind, 'multiplier' => $multiplier, 'symbol' => $symbol,
            'wheelIndex' => $wheelIndex, 'digits' => $digits,
            'freeNext' => !empty($_SESSION['mc_free']),
            'balance' => round($_SESSION['mc_balance'], 2)]);
        exit;
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action']); exit;
}
?>
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
<title><?= GAME_NAME ?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;user-select:none}
body{background:#160404;font-family:'Segoe UI',Arial,sans-serif;color:#fff;overflow-x:hidden;
 background-image:radial-gradient(#5a1408 1.2px,transparent 1.3px),radial-gradient(#4a0f06 1.2px,transparent 1.3px);
 background-size:14px 14px;background-position:0 0,7px 7px;background-color:#8f1d05}
#app{max-width:480px;margin:0 auto;min-height:100vh;position:relative;background:linear-gradient(180deg,rgba(120,20,4,.55),rgba(50,5,2,.75));box-shadow:0 0 60px #000}
/* ---------- LOADING SCREEN (screenshot jaisa) ---------- */
#loader{position:fixed;inset:0;z-index:99;background:radial-gradient(circle at 50% 30%,#a8230a,#5c0d03 70%);display:flex;flex-direction:column;align-items:center;padding-top:12vh;transition:opacity .5s}
.logo{font-family:Georgia,serif;font-style:italic;font-weight:900;font-size:46px;letter-spacing:1px;
 background:linear-gradient(180deg,#fff7ae 15%,#ffd83d 35%,#ff9d00 55%,#e02b00 80%);-webkit-background-clip:text;background-clip:text;color:transparent;
 filter:drop-shadow(0 3px 0 #7a0d00) drop-shadow(0 6px 12px rgba(0,0,0,.6));text-align:center;line-height:1.05}
.logo.small{font-size:34px}
.vola{margin-top:16px;font-weight:700;font-size:16px;color:#ffe9c9}.vola .chilli{opacity:.25;font-size:19px}.vola .chilli.on{opacity:1}
.event{margin-top:34px;width:86%;border-radius:10px;padding:22px 10px;text-align:center;background:linear-gradient(135deg,#7b2ff7,#c026d3);box-shadow:0 10px 30px rgba(0,0,0,.5),inset 0 0 30px rgba(255,255,255,.25)}
.event h2{color:#ffe95e;text-shadow:0 2px 0 #a33;font-size:24px}.event p{font-size:13px;margin-top:6px}
.trophy{font-size:56px;filter:drop-shadow(0 6px 10px rgba(0,0,0,.5))}
.loadbar{width:62%;height:12px;background:#2b2b2b;border-radius:8px;margin-top:auto;margin-bottom:12px;overflow:hidden;border:1px solid #000}
.loadbar i{display:block;height:100%;width:0;background:linear-gradient(90deg,#29b6f6,#81d4fa);border-radius:8px;transition:width .15s}
#loadtxt{font-size:15px;margin-bottom:10px}.dont{font-size:15px;margin-bottom:8vh;color:#ffd9c9}
/* ---------- GAME ---------- */
#game{display:none;padding:6px 8px 20px}
.topbar{display:flex;justify-content:space-between;align-items:center;padding:4px 2px}
.bal-pill{background:linear-gradient(180deg,#123a8f,#0a1f52);border:1px solid #4f83ff;border-radius:20px;padding:4px 14px 4px 6px;font-weight:800;display:flex;gap:7px;align-items:center;box-shadow:0 3px 8px rgba(0,0,0,.5)}
.coin-ic{width:24px;height:24px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff2a8,#ffc926 55%,#d98a00);display:inline-flex;align-items:center;justify-content:center;color:#8a5200;font-weight:900;border:2px solid #ffe27a}
.icon-btn{background:rgba(0,0,0,.35);border:1px solid rgba(255,255,255,.25);color:#fff;border-radius:50%;width:34px;height:34px;font-size:16px;cursor:pointer}
/* Wheel frame — 3D gold arch */
.wheel-frame{position:relative;margin-top:4px;border-radius:190px 190px 18px 18px;padding:12px 12px 4px;
 background:linear-gradient(160deg,#ffe97a,#c8860a 30%,#8a4d00 55%,#ffcf3d 80%,#9c5b00);
 box-shadow:0 14px 30px rgba(0,0,0,.65),inset 0 2px 4px #fff8,inset 0 -6px 12px #0007}
.wheel-inner{background:radial-gradient(circle,#14532d 0 58%,#052e16 59%);border-radius:180px 180px 12px 12px;padding:10px;position:relative;overflow:hidden;
 background-image:repeating-conic-gradient(from 45deg,rgba(255,255,255,.045) 0 12deg,transparent 12deg 24deg),radial-gradient(circle,#166534,#052e16)}
#wheelWrap{position:relative;width:300px;height:300px;margin:0 auto;filter:drop-shadow(0 10px 16px rgba(0,0,0,.6))}
#wheel{width:100%;height:100%;transition:transform 5.2s cubic-bezier(.12,.8,.08,1)}
.pointer{position:absolute;top:-4px;left:50%;transform:translateX(-50%);z-index:5;width:44px;height:52px;
 background:linear-gradient(180deg,#4ade80,#15803d);clip-path:polygon(50% 100%,0 0,100% 0);border:none;display:flex;justify-content:center;color:#ffd83d;font-weight:900;font-size:20px;padding-top:3px;filter:drop-shadow(0 3px 3px #000a)}
.hub{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:82px;height:82px;border-radius:50%;z-index:4;
 background:radial-gradient(circle at 35% 30%,#fff2a8,#ffc926 55%,#c47d00);border:4px solid #8a5200;box-shadow:0 4px 14px #000a,inset 0 -6px 10px #0005;
 display:flex;align-items:center;justify-content:center;font-size:44px;font-weight:900;color:#8a5200}
.sparkle{position:absolute;color:#ffe95e;z-index:3;animation:tw 1.1s infinite alternate;font-size:14px;pointer-events:none}
@keyframes tw{from{opacity:.15;transform:scale(.7)}to{opacity:1;transform:scale(1.25)}}
.locked{position:absolute;inset:0;z-index:6;display:none;align-items:center;justify-content:center;flex-direction:column;background:rgba(0,0,0,.18);border-radius:180px 180px 12px 12px;text-align:center}
.locked.show{display:flex}.locked .chain{font-size:44px;transform:rotate(-18deg);letter-spacing:6px}
.locked b{text-shadow:0 2px 6px #000;font-size:19px;line-height:1.5;background:rgba(0,0,0,.35);padding:6px 14px;border-radius:10px}
.wheel-glow{animation:glow .5s infinite alternate}
@keyframes glow{from{filter:drop-shadow(0 0 6px #ffe95e)}to{filter:drop-shadow(0 0 30px #ffe95e)}}
/* unlock bars */
.bars{margin-top:10px}
.bar{margin:6px 2px;padding:8px 10px;border-radius:8px;text-align:center;font-weight:800;font-size:17px;color:#fff;text-shadow:0 1px 2px #000;
 background:linear-gradient(180deg,#8a3b12,#5f2405);border:1px solid #ffb347;box-shadow:inset 0 1px 0 #ffca7a,0 3px 6px #0008}
.bar.gold{background:linear-gradient(180deg,#ffca3a,#e08a00);color:#fff}
.bar .amt{color:#ffe95e}.chip{display:inline-block;padding:1px 9px;border-radius:12px;font-size:12px;vertical-align:middle}
.chip.g{background:#16a34a}.chip.r{background:#dc2626}.chip.p{background:#9333ea}.chip.b{background:#2563eb}
/* reels */
.reels{display:flex;gap:0;margin-top:10px;border-radius:14px;padding:8px 6px;position:relative;
 background:linear-gradient(180deg,#ffdf6b,#b97a06 12%,#7c4a00 88%,#ffcf3d);box-shadow:0 10px 24px rgba(0,0,0,.6),inset 0 2px 3px #fff9}
.reel{flex:1;height:104px;overflow:hidden;border-radius:8px;margin:0 2px;position:relative;
 background:linear-gradient(180deg,#052e16,#16a34a 18%,#22c55e 50%,#16a34a 82%,#052e16);box-shadow:inset 0 10px 18px #000c,inset 0 -10px 18px #000c}
.reel.goldreel{background:linear-gradient(180deg,#8a5200,#f5c542 20%,#ffe97a 50%,#f5c542 80%,#8a5200)}
.strip{position:absolute;width:100%;text-align:center;transition:none}
.cell{height:104px;display:flex;align-items:center;justify-content:center;font-size:64px;font-weight:900;font-family:Georgia,serif;
 background:linear-gradient(180deg,#fffbe6 5%,#ffe97a 30%,#ffc926 52%,#b97a06 72%,#ffe97a 95%);-webkit-background-clip:text;background-clip:text;color:transparent;
 filter:drop-shadow(0 3px 0 rgba(90,45,0,.9)) drop-shadow(0 6px 8px rgba(0,0,0,.55))}
.goldreel .cell{font-size:19px;color:#7c2d12;flex-direction:column;text-shadow:0 1px 0 #fff8}
.goldreel .cell b{font-size:24px}
.blur .cell{filter:blur(2px)}
.winflash{animation:wf .35s 6 alternate}@keyframes wf{from{box-shadow:inset 0 0 0 3px #ffe95e}to{box-shadow:inset 0 0 26px 8px #ffe95e}}
/* bottom control */
.winrow{text-align:center;font-size:20px;font-weight:900;margin:14px 0 4px}.winrow .w{color:#ffd83d;margin-right:18px}.winrow #winAmt{color:#fff}
.winrow.big{animation:pop .4s infinite alternate}@keyframes pop{to{transform:scale(1.12)}}
.controls{display:flex;justify-content:space-between;align-items:flex-end;padding:6px 8px 0}
.ctrl{text-align:center;font-size:12px;color:#ffd9c9}
.round-btn{width:52px;height:52px;border-radius:50%;border:2px solid #ffb347;cursor:pointer;font-size:21px;color:#ffe9c9;
 background:radial-gradient(circle at 35% 30%,#a35a1b,#6b3410 70%);box-shadow:0 5px 10px #000a,inset 0 2px 3px #fff5}
.round-btn:active{transform:scale(.93)}
#spinBtn{width:96px;height:96px;border-radius:50%;border:4px solid #d98a00;cursor:pointer;font-weight:900;font-size:19px;color:#fff;letter-spacing:1px;
 background:radial-gradient(circle at 35% 28%,#a7f3b0,#22c55e 45%,#15803d 80%);box-shadow:0 10px 22px #000b,inset 0 4px 8px #fff8,inset 0 -10px 16px #0006;text-shadow:0 2px 3px #065f46}
#spinBtn::after{content:'';position:absolute;inset:6px 10px auto 10px;height:34px;border-radius:50%;background:linear-gradient(180deg,rgba(255,255,255,.75),rgba(255,255,255,0));pointer-events:none}
#spinBtn{position:relative;overflow:hidden}
.shine{position:relative;overflow:hidden}.shine::before{content:'';position:absolute;top:0;left:-80%;width:50%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.45),transparent);animation:sweep 3.2s infinite;z-index:2}
@keyframes sweep{0%{left:-80%}55%,100%{left:130%}}
.ic{width:30px;height:30px;filter:drop-shadow(0 2px 2px rgba(0,0,0,.6))}
.ic.sm{width:20px;height:20px}
#wheel{border-radius:50%}
.reel::before{content:'';position:absolute;inset:0;z-index:3;pointer-events:none;background:linear-gradient(180deg,rgba(0,0,0,.55),transparent 22%,transparent 78%,rgba(0,0,0,.55)),linear-gradient(90deg,rgba(255,255,255,.14),transparent 18%,transparent 82%,rgba(0,0,0,.25))}
.reel::after{content:'';position:absolute;left:0;right:0;top:50%;height:1px;background:rgba(255,255,220,.18);z-index:3}
.wheel-frame::before{content:'';position:absolute;inset:5px;border-radius:inherit;border:2px solid rgba(255,240,170,.55);pointer-events:none}
.bar,.reels,.wheel-frame{transform:translateZ(0)}
.logo{text-shadow:none}
#spinBtn:active{transform:scale(.94)} #spinBtn:disabled{filter:grayscale(.5);opacity:.7}
#spinBtn.spinning{animation:spinPulse .5s infinite alternate}@keyframes spinPulse{to{transform:scale(1.07)}}
.balrow{display:flex;justify-content:space-between;align-items:center;padding:8px 10px 0;font-size:14px}
.lv{background:#9ca3af;color:#111;border-radius:4px;padding:1px 7px;font-weight:900;font-size:12px}
.betbox{display:flex;align-items:center;gap:8px;justify-content:center;margin-top:8px}
.betbox button{width:38px;height:38px;border-radius:50%;border:2px solid #ffb347;background:#6b3410;color:#fff;font-size:20px;cursor:pointer}
#betVal{font-size:19px;font-weight:900;min-width:86px;text-align:center;background:rgba(0,0,0,.4);padding:6px;border-radius:8px;border:1px solid #ffb34766}
.quick{display:flex;gap:6px;justify-content:center;margin-top:8px}
.quick button{background:rgba(0,0,0,.45);color:#ffe9c9;border:1px solid #ffb34788;border-radius:16px;padding:5px 13px;cursor:pointer;font-weight:700}
.quick button.sel{background:#e08a00;color:#fff}
.toggle.on{background:#22c55e!important;color:#fff!important}
/* modal / toast / coins */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:50;display:none;align-items:center;justify-content:center}
.modal.show{display:flex}
.sheet{background:linear-gradient(180deg,#7c2d12,#451a03);border:2px solid #ffb347;border-radius:16px;padding:20px;width:88%;max-width:400px;max-height:80vh;overflow:auto}
.sheet h3{color:#ffd83d;text-align:center;margin-bottom:12px}
.sheet table{width:100%;border-collapse:collapse;font-size:14px}.sheet td,.sheet th{padding:7px;border-bottom:1px solid #ffffff22;text-align:left}
.closeb{width:100%;margin-top:14px;padding:11px;border:none;border-radius:10px;background:#22c55e;color:#fff;font-weight:900;cursor:pointer}
#toast{position:fixed;top:16%;left:50%;transform:translateX(-50%) scale(.8);background:linear-gradient(180deg,#ffd83d,#e08a00);color:#451a03;font-weight:900;padding:13px 26px;border-radius:30px;z-index:80;opacity:0;transition:.3s;font-size:19px;box-shadow:0 10px 30px #000a;pointer-events:none;text-align:center}
#toast.show{opacity:1;transform:translateX(-50%) scale(1)}
.fallcoin{position:fixed;top:-40px;z-index:70;font-size:26px;animation:fall linear forwards;pointer-events:none}
@keyframes fall{to{transform:translateY(110vh) rotate(560deg)}}
#confetti{position:fixed;inset:0;pointer-events:none;z-index:60}
</style></head>
<body>
<!-- LOADING -->
<div id="splash" style="position:fixed;inset:0;background:#000;z-index:120;display:flex;align-items:center;justify-content:center;flex-direction:column;transition:opacity .4s"><div style="font-family:Georgia;font-weight:900;font-style:italic;font-size:52px;background:linear-gradient(180deg,#fff7ae,#ffd83d 45%,#e07b00);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 3px 2px rgba(120,60,0,.7))">Money</div><div style="width:170px;height:5px;background:#2b2b2b;border-radius:4px;margin-top:10px;overflow:hidden"><i id="splashBar" style="display:block;height:100%;width:8%;background:#ffc926;border-radius:4px;transition:width 1s"></i></div></div>
<div id="loader">
  <div class="logo">Money<br>Coming</div>
  <div class="vola">Volatility : <svg class="ic sm"><use href="#i-chilli"/></svg><svg class="ic sm"><use href="#i-chilli"/></svg><svg class="ic sm" style="opacity:.25"><use href="#i-chilli"/></svg><svg class="ic sm" style="opacity:.25"><use href="#i-chilli"/></svg><svg class="ic sm" style="opacity:.25"><use href="#i-chilli"/></svg></div>
  <div class="event shine"><div class="trophy"><svg class="ic" style="width:74px;height:74px"><use href="#i-trophy"/></svg></div><h2>Join Super Event!</h2><p>Super Tournaments Every<br>Day! Max Rewards and Rebates!</p></div>
  <div class="loadbar"><i id="loadfill"></i></div><div id="loadtxt">Loading... 0%</div>
  <div class="dont">☐ Don't show next time</div>
</div>


<!-- ===== ICON SPRITE : saare icons code se bane hain, koi bahari image nahi ===== -->
<svg width="0" height="0" style="position:absolute">
 <defs>
  <linearGradient id="gGold" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff2a8"/><stop offset=".5" stop-color="#ffc926"/><stop offset="1" stop-color="#c47d00"/></linearGradient>
  <linearGradient id="gGreen" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#bbf7d0"/><stop offset=".5" stop-color="#22c55e"/><stop offset="1" stop-color="#15803d"/></linearGradient>
 </defs>
 <symbol id="i-gear" viewBox="0 0 48 48"><g fill="#ffe1a8"><rect x="21.5" y="3" width="5" height="9" rx="2"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(45 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(90 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(135 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(180 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(225 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(270 24 24)"/><rect x="21.5" y="3" width="5" height="9" rx="2" transform="rotate(315 24 24)"/></g><circle cx="24" cy="24" r="12.5" fill="url(#gGold)" stroke="#8a5200" stroke-width="2"/><circle cx="24" cy="24" r="5.5" fill="#7c3f06"/></symbol>
 <symbol id="i-coins" viewBox="0 0 48 48"><ellipse cx="21" cy="36" rx="13" ry="5" fill="#c47d00"/><ellipse cx="21" cy="31" rx="13" ry="5" fill="url(#gGold)" stroke="#8a5200"/><ellipse cx="21" cy="25" rx="13" ry="5" fill="url(#gGold)" stroke="#8a5200"/><ellipse cx="21" cy="19" rx="13" ry="5" fill="#fff2a8" stroke="#8a5200"/><circle cx="35" cy="12" r="9" fill="url(#gGreen)" stroke="#14532d" stroke-width="2"/><path d="M35 7v10M30 12h10" stroke="#fff" stroke-width="3.4" stroke-linecap="round"/></symbol>
 <symbol id="i-auto" viewBox="0 0 48 48"><path d="M38 24a14 14 0 1 1-4.2-10" fill="none" stroke="#ffe1a8" stroke-width="4.4" stroke-linecap="round"/><path d="M35 3v12H23z" fill="url(#gGold)" stroke="#8a5200" stroke-width="1.4"/><circle cx="24" cy="24" r="3.4" fill="#ffe1a8"/></symbol>
 <symbol id="i-bolt" viewBox="0 0 48 48"><path d="M27 3 9 27h11l-3 18L36 19H24z" fill="url(#gGold)" stroke="#8a5200" stroke-width="2" stroke-linejoin="round"/></symbol>
 <symbol id="i-trophy" viewBox="0 0 48 48"><path d="M13 6h22v7a11 11 0 0 1-22 0z" fill="url(#gGold)" stroke="#8a5200" stroke-width="2"/><path d="M13 10H6a7 7 0 0 0 8 7M35 10h7a7 7 0 0 1-8 7" fill="none" stroke="#c47d00" stroke-width="3.4"/><path d="m24 17 2.2 4.6 5 .6-3.7 3.4 1 4.9L24 28l-4.5 2.5 1-4.9-3.7-3.4 5-.6z" fill="#fff2a8" stroke="#8a5200"/><rect x="21.5" y="27" width="5" height="8" fill="#c47d00"/><rect x="14" y="38" width="20" height="5" rx="2" fill="url(#gGold)" stroke="#8a5200"/></symbol>
 <symbol id="i-coin" viewBox="0 0 48 48"><circle cx="24" cy="24" r="20" fill="url(#gGold)" stroke="#8a5200" stroke-width="3"/><circle cx="24" cy="24" r="14.5" fill="none" stroke="#fff2a8" stroke-width="2"/><text x="24" y="32" text-anchor="middle" font-size="22" font-weight="900" fill="#8a5200" font-family="Georgia">$</text></symbol>
 <symbol id="i-lock" viewBox="0 0 48 48"><rect x="10" y="20" width="28" height="22" rx="5" fill="url(#gGold)" stroke="#8a5200" stroke-width="2.4"/><path d="M16 20v-5a8 8 0 0 1 16 0v5" fill="none" stroke="#d9d9d9" stroke-width="5"/><circle cx="24" cy="29" r="4" fill="#7c3f06"/><rect x="22.4" y="29" width="3.2" height="7" fill="#7c3f06"/></symbol>
 <symbol id="i-chain" viewBox="0 0 48 48"><g fill="none" stroke="url(#gGold)" stroke-width="4"><ellipse cx="15" cy="24" rx="9" ry="5.4" transform="rotate(-32 15 24)"/><ellipse cx="33" cy="24" rx="9" ry="5.4" transform="rotate(-32 33 24)"/></g></symbol>
 <symbol id="i-chilli" viewBox="0 0 48 48"><path d="M8 14c14 22 26 24 34 20C34 40 14 34 6 18z" fill="#dc2626" stroke="#7f1d1d" stroke-width="1.6"/><path d="M8 14c-1-5 2-8 7-9-2 3-2 6 0 8z" fill="#16a34a"/></symbol>
 <symbol id="i-wifi" viewBox="0 0 48 48"><g fill="none" stroke="#4ade80" stroke-linecap="round"><path d="M6 18a27 27 0 0 1 36 0" stroke-width="4.4"/><path d="M13 27a18 18 0 0 1 22 0" stroke-width="4.4"/><path d="M20 35a9 9 0 0 1 8 0" stroke-width="4.4"/></g><circle cx="24" cy="41" r="3" fill="#4ade80"/></symbol>
 <symbol id="i-sound" viewBox="0 0 48 48"><path d="M7 19h8l10-9v28L15 29H7z" fill="#ffe1a8"/><path d="M31 17a9 9 0 0 1 0 14M35 12a16 16 0 0 1 0 24" fill="none" stroke="#ffe1a8" stroke-width="3.4" stroke-linecap="round"/></symbol>
</svg>
<div id="app"><div id="game">
  <div class="topbar">
    <button class="icon-btn" onclick="history.back()">‹</button>
    <div class="bal-pill"><svg class="ic sm" style="width:26px;height:26px"><use href="#i-coin"/></svg> ₹ <span id="balTop">1000.00</span></div>
    <button class="icon-btn" id="soundBtn" title="Sound"><svg class="ic sm"><use href="#i-sound"/></svg></button>
    <button class="icon-btn" onclick="openModal('helpModal')">☰</button>
  </div>

  <!-- WHEEL -->
  <div class="wheel-frame"><div class="wheel-inner">
    <div id="wheelWrap">
      <div class="pointer">$</div>
      <canvas id="wheel" width="640" height="640"></canvas>
      <div class="hub">$</div>
      <div class="locked" id="wheelLock"><svg class="ic" style="width:110px;height:52px;transform:rotate(-14deg)"><use href="#i-chain"/></svg><svg class="ic" style="width:46px;height:46px;margin-top:-8px"><use href="#i-lock"/></svg><b>Bet 5 Up Game<br>To unlock Lucky Wheel</b></div>
    </div>
  </div></div>
  <div class="logo small" style="margin-top:6px">Money Coming</div>

  <!-- UNLOCK BARS -->
  <div class="bars">
    <div class="bar" id="barScatter">Bet <span class="amt">50</span> <span class="chip g">SCATTER</span> ➜ <span class="chip r">SCATTER</span> Bonus 5x</div>
    <div class="bar gold" id="barUnlock">Bet <span class="amt">1</span> unlock <span class="chip g">2x</span> <span class="chip p">5x</span> <span class="chip b">RESPIN</span> &nbsp;|&nbsp; Bet <span class="amt">10</span> unlock <span class="chip p">10x</span></div>
  </div>

  <!-- REELS -->
  <div class="reels" id="reels">
    <div class="reel" id="reel0"><div class="strip"></div></div>
    <div class="reel" id="reel1"><div class="strip"></div></div>
    <div class="reel" id="reel2"><div class="strip"></div></div>
    <div class="reel goldreel" id="reel3"><div class="strip"></div></div>
  </div>

  <div class="winrow" id="winRow"><span class="w">WIN</span> ₹ <span id="winAmt">0.00</span></div>

  <!-- CONTROLS -->
  <div class="controls">
    <div class="ctrl"><button class="round-btn shine" onclick="openModal('helpModal')"><svg class="ic"><use href="#i-gear"/></svg></button><div>Setting</div></div>
    <div class="ctrl"><button class="round-btn shine" id="betMenuBtn"><svg class="ic"><use href="#i-coins"/></svg></button><div>Bet ₹ <span id="betLbl">10</span></div></div>
    <div><button id="spinBtn"><span id="spinLbl" style="font-family:Georgia;font-style:italic;background:linear-gradient(180deg,#fff7ae,#ffd83d 45%,#ff9d00);-webkit-background-clip:text;background-clip:text;color:transparent;filter:drop-shadow(0 2px 1px rgba(90,40,0,.8))">SPIN</span></button></div>
    <div class="ctrl"><button class="round-btn shine toggle" id="autoBtn"><svg class="ic"><use href="#i-auto"/></svg></button><div>Auto</div></div>
    <div class="ctrl"><button class="round-btn shine toggle" id="turboBtn"><svg class="ic"><use href="#i-bolt"/></svg></button><div>Turbo</div></div>
  </div>
  <div class="betbox"><button id="betMinus">−</button><div id="betVal">Bet ₹ 10</div><button id="betPlus">+</button></div>
  <div class="quick" id="quickBets"></div>
  <div class="balrow"><span><span class="lv">LV 0</span> &nbsp;Balance &nbsp;<b style="color:#ffd83d">₹ <span id="balBottom">1000.00</span></b></span>
  <svg class="ic sm"><use href="#i-wifi"/></svg></div>
  <div style="text-align:center;margin-top:10px">
    <button onclick="resetGame()" style="background:rgba(0,0,0,.45);color:#ffe9c9;border:1px solid #ffb34788;border-radius:16px;padding:5px 13px;cursor:pointer;font-weight:700">↺ Refill Demo Balance</button>
    <button onclick="openModal('histModal')" style="background:rgba(0,0,0,.45);color:#ffe9c9;border:1px solid #ffb34788;border-radius:16px;padding:5px 13px;cursor:pointer;font-weight:700">History</button>
  </div>
  <p style="text-align:center;font-size:11px;color:#ffbfa8;margin-top:12px">v_491_292 • Demo game • Win rate 20% • 18+</p>
</div></div>

<!-- MODALS -->
<div class="modal" id="payModal"><div class="sheet"><h3><svg class="ic sm"><use href="#i-trophy"/></svg> PAYTABLE</h3>
<table><tr><th>Result</th><th>Payout</th></tr>
<tr><td>2x Multiplier</td><td>Bet × 2</td></tr><tr><td>5x Multiplier</td><td>Bet × 5</td></tr>
<tr><td>10x Multiplier (Bet ≥10)</td><td>Bet × 10</td></tr>
<tr><td>RESPIN</td><td>1 Free Spin</td></tr>
<tr><td>LUCKY WHEEL (Bet ≥5)</td><td>50 – 2,000 × (Bet/10)</td></tr>
<tr><td>SCATTER (Bet ≥50)</td><td>Bet × 5 Bonus</td></tr></table>
<button class="closeb" onclick="closeModal('payModal')">OK</button></div></div>

<div class="modal" id="histModal"><div class="sheet"><h3>HISTORY</h3><div id="histBody">No spins yet</div>
<button class="closeb" onclick="closeModal('histModal')">CLOSE</button></div></div>

<div class="modal" id="helpModal"><div class="sheet"><h3><svg class="ic sm"><use href="#i-gear"/></svg> SETTINGS / HELP</h3>
<table><tr><td><svg class="ic sm"><use href="#i-sound"/></svg> Sound</td><td><button onclick="toggleSound()" style="padding:5px 14px;border-radius:8px;border:none;background:#22c55e;color:#fff;font-weight:800;cursor:pointer">ON / OFF</button></td></tr>
<tr><td>Lucky Wheel</td><td>Bet ₹5+ par unlock</td></tr>
<tr><td>10x</td><td>Bet ₹10+ par unlock</td></tr>
<tr><td>Scatter</td><td>Bet ₹50+ par unlock</td></tr>
<tr><td>Kaise khelein</td><td>Bet chuno → SPIN dabao. Reels rukne par multiplier / wheel se jeet milti hai.</td></tr></table>
<button class="closeb" onclick="closeModal('helpModal')">OK</button></div></div>

<div id="toast"></div><canvas id="confetti"></canvas>

<script>
/* ================= SOUND (WebAudio — koi file nahi, live banta hai) ================= */
let AC=null, soundOn=true;
function ac(){ if(!AC) AC=new (window.AudioContext||window.webkitAudioContext)(); if(AC.state==='suspended')AC.resume(); return AC; }
function tone(f,t=0.12,type='square',v=0.15,when=0,slide=0){ if(!soundOn)return; try{
  const c=ac(),o=c.createOscillator(),g=c.createGain(),s=c.currentTime+when;
  o.type=type;o.frequency.setValueAtTime(f,s); if(slide)o.frequency.exponentialRampToValueAtTime(slide,s+t);
  g.gain.setValueAtTime(v,s); g.gain.exponentialRampToValueAtTime(0.001,s+t);
  o.connect(g).connect(c.destination); o.start(s); o.stop(s+t);}catch(e){} }
const SFX={
 click:()=>tone(650,.06,'square',.12),
 tick:()=>tone(1200,.03,'square',.07),
 spin:()=>{tone(220,.5,'sawtooth',.08,0,880)},
 reelStop:()=>{tone(180,.1,'square',.18);tone(90,.14,'square',.15,.02)},
 win:()=>[523,659,784,1047].forEach((f,i)=>tone(f,.16,'triangle',.2,i*.09)),
 bigwin:()=>[523,659,784,1047,784,1047,1319,1568].forEach((f,i)=>tone(f,.22,'triangle',.22,i*.11)),
 coin:()=>{tone(1568,.08,'square',.12);tone(2093,.25,'square',.12,.07)},
 lose:()=>tone(320,.3,'sawtooth',.07,0,110),
 wheelTick:()=>tone(950,.03,'square',.09)
};
function toggleSound(){ soundOn=!soundOn; document.getElementById('soundBtn').style.opacity=soundOn?'1':'.45'; if(soundOn)SFX.click(); }
document.getElementById('soundBtn').onclick=toggleSound;

/* ================= WHEEL (HIGH-RES CANVAS 640px — ekdum clear, 3D shading) ================= */
const WHEEL=[{v:'50',c:'#22c55e',c2:'#14532d',t:'#ffffff'},{v:'200',c:'#fde047',c2:'#eab308',t:'#14532d'},{v:'2,000',c:'#ef4444',c2:'#991b1b',t:'#ffffff'},{v:'500',c:'#fde047',c2:'#eab308',t:'#14532d'},{v:'100',c:'#22c55e',c2:'#14532d',t:'#ffffff'},{v:'300',c:'#fde047',c2:'#eab308',t:'#14532d'},{v:'150',c:'#22c55e',c2:'#14532d',t:'#ffffff'},{v:'1,000',c:'#fde047',c2:'#eab308',t:'#14532d'}];
function drawWheel(){
  const cv=document.getElementById('wheel'); if(!cv||!cv.getContext)return; const x=cv.getContext('2d'),C=320,R=306;
  x.clearRect(0,0,640,640);
  // bahari sunehra ghaira + chamak
  let rim=x.createRadialGradient(C-70,C-90,60,C,C,R); rim.addColorStop(0,'#fff2a8');rim.addColorStop(.55,'#f5c542');rim.addColorStop(1,'#8a5200');
  x.beginPath();x.arc(C,C,R,0,7);x.fillStyle=rim;x.fill();
  x.beginPath();x.arc(C,C,R-16,0,7);x.fillStyle='#7f1d1d';x.fill();
  // rim par safed batti (screenshot wali dots)
  for(let i=0;i<24;i++){const a=i/24*Math.PI*2;x.beginPath();x.arc(C+(R-25)*Math.cos(a),C+(R-25)*Math.sin(a),5.5,0,7);x.fillStyle=i%2?'#fff7cc':'#ffd83d';x.shadowColor='#ffe95e';x.shadowBlur=10;x.fill();x.shadowBlur=0;}
  const bet=BETS[betIdx], f=bet>=10?bet/10:1;
  WHEEL.forEach((sg,i)=>{
    const a0=(i*45-90)*Math.PI/180, a1=((i+1)*45-90)*Math.PI/180;
    const g=x.createRadialGradient(C,C,40,C,C,R-42); g.addColorStop(0,'#ffffff'); g.addColorStop(.28,sg.c); g.addColorStop(1,sg.c2);
    x.beginPath();x.moveTo(C,C);x.arc(C,C,R-42,a0,a1);x.closePath();x.fillStyle=g;x.fill();
    x.strokeStyle='#f5c542';x.lineWidth=4;x.stroke();
    // har hisse par upar ki chamak (3D gloss)
    x.save();x.beginPath();x.moveTo(C,C);x.arc(C,C,R-42,a0,a1);x.closePath();x.clip();
    const gl=x.createLinearGradient(C,C-R,C,C+R);gl.addColorStop(0,'rgba(255,255,255,.38)');gl.addColorStop(.45,'rgba(255,255,255,0)');gl.addColorStop(1,'rgba(0,0,0,.20)');x.fillStyle=gl;x.fillRect(0,0,640,640);x.restore();
    // number (bet ke hisaab se bada hota hai)
    const am=(a0+a1)/2, tx=C+205*Math.cos(am), ty=C+205*Math.sin(am);
    const label=f>1? String(Math.round(parseInt(sg.v.replace(/,/g,''))*f)).replace(/\B(?=(\d{3})+(?!\d))/g,',') : sg.v;
    x.save();x.translate(tx,ty);x.rotate(am+Math.PI/2);x.font='900 37px Georgia';x.textAlign='center';x.textBaseline='middle';
    x.lineWidth=6;x.strokeStyle='rgba(90,30,0,.45)';x.strokeText(label,0,0);x.fillStyle=sg.t;x.fillText(label,0,0);x.restore();
  });
  // beech ki roshni
  const glow=x.createRadialGradient(C,C,10,C,C,190);glow.addColorStop(0,'rgba(255,255,230,.85)');glow.addColorStop(1,'rgba(255,255,230,0)');
  x.beginPath();x.arc(C,C,190,0,7);x.fillStyle=glow;x.fill();
  const wrap=document.getElementById('wheelWrap');
  if(wrap && !wrap.dataset.spark){ wrap.dataset.spark=1;
    for(let i=0;i<10;i++){const sp=document.createElement('span');sp.className='sparkle';sp.textContent='✦';
      sp.style.left=(Math.random()*92)+'%';sp.style.top=(Math.random()*92)+'%';sp.style.animationDelay=(Math.random())+'s';wrap.appendChild(sp);} }
}
drawWheel();
/* ================= REELS ================= */
const DIGITS=['0','1','2','3','4','5','6','7','8','9'];
const SYMS=[{t:'2x',s:'WIN'},{t:'5x',s:'WIN'},{t:'10x',s:'WIN'},{t:'RESPIN',s:''},{t:'WHEEL',s:'ALL'},{t:'SCATTER',s:''}];
function fillStrip(el,items,finalIdx,cellFn){
  const strip=el.querySelector('.strip'); let h='';
  const seq=[]; for(let i=0;i<24;i++) seq.push(items[Math.floor(Math.random()*items.length)]);
  seq.push(items[finalIdx]);
  seq.forEach(it=>h+=cellFn(it)); strip.innerHTML=h; strip.dataset.h=104; return seq.length;
}
function cellDigit(d){return `<div class="cell">${d}</div>`}
function cellSym(s){return `<div class="cell"><b>${s.t}</b><small>${s.s}</small></div>`}
function setReel(i,val,isSym){
  const el=document.getElementById('reel'+i);
  if(isSym){const idx=Math.max(0,SYMS.findIndex(s=>s.t===val));fillStrip(el,SYMS,idx,cellSym);el.querySelector('.strip').style.transform='translateY(0px)';}
  else{fillStrip(el,DIGITS,DIGITS.indexOf(val),cellDigit);el.querySelector('.strip').style.transform='translateY(0px)';}
}
setReel(0,'1');setReel(1,'0');setReel(2,'0');setReel(3,'WHEEL',true);

function spinReel(i,finalVal,isSym,dur){
  return new Promise(res=>{
    const el=document.getElementById('reel'+i),strip=el.querySelector('.strip');
    const items=isSym?SYMS:DIGITS, fn=isSym?cellSym:cellDigit;
    const fIdx=isSym?Math.max(0,SYMS.findIndex(s=>s.t===finalVal)):DIGITS.indexOf(finalVal);
    const n=fillStrip(el,items,fIdx,fn); el.classList.add('blur');
    strip.style.transition='none'; strip.style.transform='translateY(0px)'; void strip.offsetWidth;
    strip.style.transition=`transform ${dur}ms cubic-bezier(.15,.9,.25,1)`;
    strip.style.transform=`translateY(-${(n-1)*104}px)`;
    let ticks=0; const ti=setInterval(()=>{SFX.tick(); if(++ticks>dur/70)clearInterval(ti)},70);
    setTimeout(()=>{el.classList.remove('blur');SFX.reelStop();res()},dur);
  });
}

/* ================= GAME STATE ================= */
const BETS=[1,5,10,50,100,500]; let betIdx=2, balance=1000, spinning=false, auto=false, turbo=false, freeNext=false;
const $=id=>document.getElementById(id);
function money(n){return Number(n).toLocaleString('en-IN',{minimumFractionDigits:2,maximumFractionDigits:2})}
function setBalance(b){balance=b;$('balTop').textContent=money(b);$('balBottom').textContent=money(b)}
function toast(msg,ms=2200){const t=$('toast');t.textContent=msg;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),ms)}
function updateBetUI(){
  if(typeof drawWheel==='function') drawWheel();
  $('betVal').textContent='Bet ₹ '+BETS[betIdx]; const bl=$('betLbl'); if(bl)bl.textContent=BETS[betIdx];
  $('wheelLock').classList.toggle('show',BETS[betIdx]<5);
  document.querySelectorAll('#quickBets button').forEach((b,i)=>b.classList.toggle('sel',i===betIdx));
}
BETS.forEach((b,i)=>{const btn=document.createElement('button');btn.textContent=b.toLocaleString('en-IN');
  btn.onclick=()=>{betIdx=i;SFX.click();updateBetUI()};$('quickBets').appendChild(btn)});
const bm=$('betMenuBtn'); if(bm) bm.onclick=()=>{betIdx=(betIdx+1)%BETS.length;SFX.click();updateBetUI()};
$('betMinus').onclick=()=>{if(betIdx>0)betIdx--;SFX.click();updateBetUI()};
$('betPlus').onclick =()=>{if(betIdx<BETS.length-1)betIdx++;SFX.click();updateBetUI()};
$('autoBtn').onclick=e=>{auto=!auto;e.currentTarget.classList.toggle('on',auto);SFX.click();if(auto&&!spinning)doSpin()};
$('turboBtn').onclick=e=>{turbo=!turbo;e.currentTarget.classList.toggle('on',turbo);SFX.click()};
function openModal(id){$(id).classList.add('show');SFX.click()} function closeModal(id){$(id).classList.remove('show')}
document.querySelectorAll('.modal').forEach(m=>m.addEventListener('click',e=>{if(e.target===m)m.classList.remove('show')}));

/* coin rain + confetti */
function coinRain(n=26){for(let i=0;i<n;i++){const c=document.createElement('div');c.className='fallcoin';c.textContent='🪙';
  c.style.left=Math.random()*96+'vw';c.style.animationDuration=(1+Math.random()*1.4)+'s';c.style.animationDelay=(Math.random()*.6)+'s';
  document.body.appendChild(c);setTimeout(()=>c.remove(),3200)}}
function countUp(el,to,dur=900){const st=performance.now(),from=0;
  (function f(t){const p=Math.min(1,(t-st)/dur);el.textContent=money(from+(to-from)*(1-Math.pow(1-p,3)));if(p<1)requestAnimationFrame(f)})(st)}

/* wheel spin -> returns promise */
let wheelRot=0;
function spinWheelTo(idx){
  return new Promise(res=>{
    const wheel=$('wheel'); wheel.parentElement.classList.add('wheel-glow');
    const segCenter=idx*45+22.5, target=360*6+(360-segCenter);
    wheelRot+=target-(wheelRot%360); // hamesha aage ghumega
    wheel.style.transform=`rotate(${wheelRot}deg)`;
    let last=0; const ti=setInterval(()=>{SFX.wheelTick()},110);
    setTimeout(()=>{clearInterval(ti);wheel.parentElement.classList.remove('wheel-glow');res()},turbo?2600:5250);
  });
}

/* ================= SPIN ================= */
/* Browser-preview demo fallback — PHP hosting par server logic (upar PHP me) hi chalegi.
   Dono jagah SAME rule hai: 20% WIN / 80% LOSS */
const WHEELVALS=[50,200,2000,500,100,300,150,1000];
function localSpin(bet){
  if(!freeNext) balance-=bet;
  const isWin=Math.floor(Math.random()*100)<20;
  let win=0,kind='loss',multiplier=0,wheelIndex=-1,symbol=['2x','5x','RESPIN','WHEEL','10x'][Math.floor(Math.random()*5)],free=false;
  if(isWin){ const roll=Math.random()*100+1;
    if(bet>=50&&roll<=8){kind='scatter';symbol='SCATTER';win=bet*5}
    else if(bet>=5&&roll<=30){kind='wheel';symbol='WHEEL';const p=Math.random()*100+1;
      wheelIndex=p<=30?0:p<=55?4:p<=72?6:p<=85?1:p<=93?5:p<=97?3:p<=99?7:2; win=WHEELVALS[wheelIndex]*(bet/10)}
    else if(roll<=38){kind='respin';symbol='RESPIN';free=true}
    else{kind='multiplier';const m=Math.random()*100+1;multiplier=(bet>=10&&m>92)?10:(m>60?5:2);symbol=multiplier+'x';win=bet*multiplier}
  }
  balance+=win;
  const base=(kind==='multiplier')?win/multiplier:Math.max(win,bet);
  const digits=(isWin&&kind!=='respin')?String(Math.floor(base)%1000).padStart(3,'0').split(''):[0,0,0].map(()=>String(Math.floor(Math.random()*10)));
  return {success:true,win,isWin,kind,multiplier,symbol,wheelIndex,digits,freeNext:free,balance};
}
async function doSpin(){
  if(spinning)return;
  const bet=BETS[betIdx];
  if(!freeNext && balance<bet){toast('❌ Insufficient Balance! Refill karo');SFX.lose();auto=false;$('autoBtn').classList.remove('on');return}
  spinning=true;$('spinBtn').disabled=true;$('spinBtn').classList.add('spinning');$('spinLbl').textContent=freeNext?'FREE':'•••';
  $('winAmt').textContent='0.00';$('winRow').classList.remove('big');SFX.spin();
  let data;
  try{
    const r=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
      body:new URLSearchParams({action:'spin',bet:bet,free:freeNext?1:0})});
    data=await r.json();
  }catch(e){ toast('Server error — PHP hosting par file chalao'); spinning=false;$('spinBtn').disabled=false; return; }
  if(!data.success){toast('❌ '+data.message);spinning=false;$('spinBtn').disabled=false;$('spinBtn').classList.remove('spinning');return}
  setBalance(data.balance + (data.win||0)); // visual: pehle bet kata hua dikhega, jeet baad me judegi
  setBalance(data.balance - (data.win||0) + (freeNext?bet:0)*0); // server balance hi final hai neeche set hoga
  const d=turbo?650:1250;
  await Promise.all([
    spinReel(0,data.digits[0],false,d),
    spinReel(1,data.digits[1],false,d+250),
    spinReel(2,data.digits[2],false,d+500),
    spinReel(3,data.symbol,true,d+750)
  ]);
  if(data.kind==='wheel' && data.wheelIndex>=0){ toast('🎡 LUCKY WHEEL!'); await spinWheelTo(data.wheelIndex); }
  freeNext=!!data.freeNext;
  if(data.win>0){
    SFX.win(); if(data.win>=bet*5){SFX.bigwin();coinRain(40)} else coinRain(18); SFX.coin();
    $('reels').classList.add('winflash'); setTimeout(()=>$('reels').classList.remove('winflash'),2200);
    $('winRow').classList.add('big'); countUp($('winAmt'),data.win);
    toast((data.kind==='wheel'?'🎡 WHEEL WIN! +₹':data.kind==='scatter'?'⭐ SCATTER WIN! +₹':'🎉 WIN! +₹')+money(data.win));
  } else if(data.kind==='respin'){ SFX.win(); toast('🔁 RESPIN! Ek FREE spin mila'); }
  else { SFX.lose(); }
  setBalance(data.balance);
  loadHistory();
  $('spinLbl').textContent=freeNext?'FREE':'SPIN';
  spinning=false;$('spinBtn').disabled=false;$('spinBtn').classList.remove('spinning');
  if(auto) setTimeout(doSpin, turbo?300:900);
}
$('spinBtn').onclick=()=>{ac();SFX.click();doSpin()};
document.addEventListener('keydown',e=>{if(e.code==='Space'){e.preventDefault();doSpin()}});

/* long-press spin = auto (screenshot: "Hold for setting" jaisa behaviour) */
let holdT=null;
$('spinBtn').addEventListener('pointerdown',()=>{holdT=setTimeout(()=>{auto=true;$('autoBtn').classList.add('on');toast('🔁 AUTO SPIN ON')},700)});
['pointerup','pointerleave'].forEach(ev=>$('spinBtn').addEventListener(ev,()=>clearTimeout(holdT)));

async function loadHistory(){
  try{const r=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'state'})});
  const d=await r.json(); if(d.success){ setBalance(d.balance);
    $('histBody').innerHTML=d.history.length?('<table>'+d.history.map(h=>`<tr><td>${h.time}</td><td>Bet ₹${h.bet}</td><td>${h.kind}</td><td style="color:${h.win>0?'#4ade80':'#f87171'}">${h.win>0?'+₹'+money(h.win):'—'}</td></tr>`).join('')+'</table>'):'No spins yet'; }}catch(e){}
}
async function resetGame(){ try{const r=await fetch(location.href,{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({action:'reset'})});
  const d=await r.json(); if(d.success){setBalance(d.balance);toast('✅ Balance refill: ₹'+money(d.balance))} }catch(e){ setBalance(1000); toast('Demo balance: ₹1,000'); } }

/* ================= LOADER ================= */
setTimeout(()=>{const sb=document.getElementById('splashBar'); if(sb)sb.style.width='100%'},50);
setTimeout(()=>{const sp=document.getElementById('splash'); if(sp){sp.style.opacity=0; setTimeout(()=>sp.style.display='none',420)}},1150);
(function(){
  let p=0; const iv=setInterval(()=>{ p=Math.min(100,p+Math.random()*11);
    $('loadfill').style.width=p+'%'; $('loadtxt').textContent='Loading... '+Math.floor(p)+'%';
    if(p>=100){clearInterval(iv); setTimeout(()=>{ $('loader').style.opacity=0;
      setTimeout(()=>{$('loader').style.display='none';$('game').style.display='block';updateBetUI();loadHistory()},480); },250);} },130);
})();
updateBetUI();
</script>
</body></html>
